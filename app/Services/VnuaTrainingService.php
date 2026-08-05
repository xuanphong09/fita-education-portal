<?php

namespace App\Services;

use App\Exceptions\VnuaInvalidCredentialsException;
use App\Exceptions\VnuaSyncException;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class VnuaTrainingService
{
    private string $baseUrl = 'https://daotao.vnua.edu.vn';

    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36';

    public function syncStudentGrades(Student $student, bool $force = false): array
    {
        $student->refresh();

        if (blank($student->student_code)) {
            throw new VnuaSyncException('Sinh viên chưa có mã sinh viên.');
        }

        if (blank($student->vnua_password)) {
            throw new VnuaInvalidCredentialsException('Sinh viên chưa lưu mật khẩu trang đào tạo.');
        }

        if (
            !$force
            && $student->grade_sync_status === 'success'
            && $student->grade_sync_success_at
            && $student->grade_sync_success_at->gt(now()->subHours(6))
        ) {
            return [
                'skipped' => true,
                'message' => 'Dữ liệu đã được đồng bộ gần đây.',
                'student' => $student,
            ];
        }

        if (!$force && $student->grade_sync_status === 'invalid_password') {
            return [
                'skipped' => true,
                'message' => 'Mật khẩu trang đào tạo không đúng. Cần cập nhật lại mật khẩu.',
                'student' => $student,
            ];
        }

        $lock = Cache::lock('sync-student-grades:' . $student->id, 180);

        if (!$lock->get()) {
            return [
                'skipped' => true,
                'message' => 'Sinh viên này đang được đồng bộ điểm.',
                'student' => $student,
            ];
        }

        try {
            $student->forceFill([
                'grade_sync_status' => 'syncing',
                'grade_sync_message' => null,
                'grade_sync_started_at' => now(),
            ])->save();

            $result = $this->syncGrades(
                studentCode: $student->student_code,
                password: $student->vnua_password
            );

            $rows = $result['rows'] ?? [];
            $semesters = $result['semesters'] ?? [];

            if (empty($rows) && empty($semesters)) {
                $student->forceFill([
                    'grade_sync_status' => 'no_data',
                    'grade_sync_message' => 'Đăng nhập thành công nhưng chưa lấy được dữ liệu điểm.',
                    'grade_sync_failed_at' => now(),
                ])->save();

                return [
                    'skipped' => false,
                    'rows_count' => 0,
                    'semesters_count' => 0,
                    'message' => 'Không có dữ liệu điểm.',
                    'student' => $student->fresh(),
                ];
            }

            if (!empty($rows)) {
                $this->saveGradesToDatabase($student->id, $rows);
            }

            if (!empty($semesters)) {
                $this->updateStudentStats($student->id, $semesters);
            }

            $student->refresh();

            $student->forceFill([
                'grade_sync_status' => 'success',
                'grade_sync_message' => 'Đồng bộ thành công.',
                'grade_sync_failed_count' => 0,
                'grade_sync_success_at' => now(),
                'grade_sync_failed_at' => null,
            ])->save();

            return [
                'skipped' => false,
                'rows_count' => count($rows),
                'semesters_count' => count($semesters),
                'gpa_4' => $student->gpa_4,
                'gpa_10' => $student->gpa_10,
                'total_credits_earned' => $student->total_credits_earned,
                'updated_at' => $student->last_academic_stats_updated_at,
                'student' => $student->fresh(),
            ];
        } catch (VnuaInvalidCredentialsException $e) {
            $student->forceFill([
                'grade_sync_status' => 'invalid_password',
                'grade_sync_message' => 'Sai mật khẩu trang đào tạo.',
                'grade_sync_failed_count' => (int) $student->grade_sync_failed_count + 1,
                'grade_sync_failed_at' => now(),
            ])->save();

            throw $e;
        } catch (Throwable $e) {
            Log::error('Đồng bộ điểm thất bại', [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $student->forceFill([
                'grade_sync_status' => 'failed',
                'grade_sync_message' => 'Không thể đồng bộ dữ liệu lúc này. Vui lòng thử lại sau.',
                'grade_sync_failed_count' => (int) $student->grade_sync_failed_count + 1,
                'grade_sync_failed_at' => now(),
            ])->save();

            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    public function syncGrades(string $studentCode, string $password): array
    {
        $cookieJar = new CookieJar();

        $currentUser = $this->login($studentCode, $password, $cookieJar);

        $semesters = $this->getGrades(
            accessToken: $currentUser['access_token'],
            cookieJar: $cookieJar
        );

        return [
            'current_user' => $currentUser,
            'semesters' => $semesters,
            'rows' => $this->flattenGrades($semesters),
        ];
    }

    private function login(string $studentCode, string $password, CookieJar $cookieJar): array
    {
        Http::withoutVerifying()
            ->timeout(20)
            ->withOptions([
                'cookies' => $cookieJar,
            ])
            ->withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept-Language' => 'vi',
                'Referer' => $this->baseUrl . '/',
            ])
            ->get($this->baseUrl . '/');

        $loginPayload = [
            'username' => $studentCode,
            'password' => $password,
            'uri' => $this->baseUrl . '/#/',
        ];

        $code = base64_encode(json_encode(
            $loginPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));

        $response = Http::withoutVerifying()
            ->timeout(20)
            ->withOptions([
                'cookies' => $cookieJar,
                'allow_redirects' => false,
            ])
            ->withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'vi',
                'Referer' => $this->baseUrl . '/',
            ])
            ->get($this->baseUrl . '/api/pn-signin', [
                'code' => $code,
                'gopage' => '',
                'mgr' => 1,
            ]);

        if (!in_array($response->status(), [301, 302], true)) {
            Log::warning('Trang đào tạo không redirect sau đăng nhập', [
                'student_code' => $studentCode,
                'http_status' => $response->status(),
                'body_preview' => $this->bodyPreview($response->body()),
            ]);

            throw new VnuaInvalidCredentialsException(
                'Sai mã sinh viên hoặc mật khẩu trang đào tạo.'
            );
        }

        $location = $response->header('Location');

        if (!$location) {
            Log::warning('Không tìm thấy Location redirect sau đăng nhập', [
                'student_code' => $studentCode,
                'http_status' => $response->status(),
            ]);

            throw new VnuaSyncException('Không tìm thấy Location redirect sau đăng nhập.');
        }

        $currUserEncoded = $this->extractCurrUserFromLocation($location);

        if (!$currUserEncoded) {
            Log::warning('Không tìm thấy CurrUser trong Location redirect', [
                'student_code' => $studentCode,
                'location_preview' => $this->maskSensitiveLocation($location),
            ]);

            throw new VnuaSyncException('Không tìm thấy CurrUser trong Location redirect.');
        }

        $currentUser = $this->decodeBase64Json($currUserEncoded);

        if (!($currentUser['result'] ?? false)) {
            throw new VnuaInvalidCredentialsException(
                $currentUser['message'] ?? 'Sai mã sinh viên hoặc mật khẩu trang đào tạo.'
            );
        }

        if (empty($currentUser['access_token'])) {
            throw new VnuaInvalidCredentialsException(
                'Đăng nhập thành công nhưng không có access_token.'
            );
        }

        return $currentUser;
    }

    private function getGrades(string $accessToken, CookieJar $cookieJar): array
    {
        $paths = [
            '/api/srm/w-locdsdiemsinhvien?hien_thi_mon_theo_hkdk=false',
            '/manage/api/srm/w-locdsdiemsinhvien?hien_thi_mon_theo_hkdk=false',
        ];

        $lastError = null;

        foreach ($paths as $path) {
            $url = $this->baseUrl . $path;

            $referer = str_starts_with($path, '/manage/')
                ? $this->baseUrl . '/manage/'
                : $this->baseUrl . '/#/';

            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withOptions([
                    'cookies' => $cookieJar,
                    'allow_redirects' => false,
                ])
                ->withHeaders([
                    'Accept' => 'application/json, text/plain, */*',
                    'Content-Type' => 'text/plain',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Origin' => $this->baseUrl,
                    'Referer' => $referer,
                    'idpc' => '0',
                    'ua' => $this->encodeUa($url),
                    'User-Agent' => $this->userAgent,
                ])
                ->withBody('', 'text/plain')
                ->post($url);

            $json = $response->json();

            if ($response->status() === 404) {
                Log::warning('Endpoint điểm bị 404, thử endpoint khác', [
                    'url' => $url,
                    'http_status' => $response->status(),
                    'body_preview' => $this->bodyPreview($response->body()),
                ]);

                $lastError = 'Endpoint bị 404: ' . $url;
                continue;
            }

            if (!is_array($json)) {
                Log::warning('API điểm không trả về JSON hợp lệ', [
                    'url' => $url,
                    'http_status' => $response->status(),
                    'content_type' => $response->header('Content-Type'),
                    'body_preview' => $this->bodyPreview($response->body()),
                ]);

                $lastError = 'API không trả về JSON hợp lệ tại: ' . $url;
                continue;
            }

            if (!$response->successful() || !($json['result'] ?? false)) {
                Log::warning('Không lấy được điểm từ trang đào tạo', [
                    'url' => $url,
                    'http_status' => $response->status(),
                    'message' => $json['message'] ?? null,
                    'keys' => array_keys($json),
                ]);

                $lastError = 'Không lấy được điểm. HTTP ' . $response->status()
                    . ' - ' . ($json['message'] ?? 'Trang đào tạo không trả về dữ liệu hợp lệ.');

                continue;
            }

            $semesters = data_get($json, 'data.ds_diem_hocky', []);

            if (!is_array($semesters)) {
                Log::warning('Không tìm thấy data.ds_diem_hocky trong response điểm', [
                    'url' => $url,
                    'data_keys' => is_array($json['data'] ?? null) ? array_keys($json['data']) : null,
                ]);

                $lastError = 'Không tìm thấy danh sách điểm học kỳ.';
                continue;
            }

            return $semesters;
        }

        throw new VnuaSyncException(
            $lastError ?: 'Không lấy được điểm từ trang đào tạo.'
        );
    }

    private function flattenGrades(array $semesters): array
    {
        $rows = [];

        foreach ($semesters as $semester) {
            foreach (($semester['ds_diem_mon_hoc'] ?? []) as $subject) {
                $rows[] = [
                    'hoc_ky' => $semester['hoc_ky'] ?? null,
                    'ten_hoc_ky' => $semester['ten_hoc_ky'] ?? null,
                    'dtb_hk_he10' => $semester['dtb_hk_he10'] ?? null,
                    'dtb_hk_he4' => $semester['dtb_hk_he4'] ?? null,
                    'dtb_tich_luy_he_10' => $semester['dtb_tich_luy_he_10'] ?? null,
                    'dtb_tich_luy_he_4' => $semester['dtb_tich_luy_he_4'] ?? null,
                    'so_tin_chi_dat_tich_luy' => $semester['so_tin_chi_dat_tich_luy'] ?? null,

                    'ma_mon' => $subject['ma_mon'] ?? null,
                    'ten_mon' => $subject['ten_mon'] ?? null,
                    'ten_mon_eg' => $subject['ten_mon_eg'] ?? null,
                    'nhom_to' => $subject['nhom_to'] ?? null,
                    'so_tin_chi' => $subject['so_tin_chi'] ?? null,
                    'diem_thi' => $subject['diem_thi'] ?? null,
                    'diem_giua_ky' => $subject['diem_giua_ky'] ?? null,
                    'diem_tk_10' => $subject['diem_tk'] ?? null,
                    'diem_tk_4' => $subject['diem_tk_so'] ?? null,
                    'diem_chu' => $subject['diem_tk_chu'] ?? null,
                    'ket_qua' => $subject['ket_qua'] ?? null,
                    'khong_tinh_diem_tbtl' => $subject['khong_tinh_diem_tbtl'] ?? null,
                    'ly_do_khong_tinh_diem_tbtl' => $subject['ly_do_khong_tinh_diem_tbtl'] ?? null,
                    'ds_diem_thanh_phan' => $subject['ds_diem_thanh_phan'] ?? [],
                ];
            }
        }

        return $rows;
    }

    public function saveGradesToDatabase(int $studentId, array $rows): void
    {
        DB::transaction(function () use ($studentId, $rows) {
            $codes = collect($rows)
                ->pluck('ma_mon')
                ->filter()
                ->map(fn ($code) => $this->normalizeSubjectCode($code))
                ->unique()
                ->values();

            $subjectsByCode = Subject::query()
                ->whereIn('code', $codes)
                ->get()
                ->keyBy(fn (Subject $subject) => $this->normalizeSubjectCode($subject->code));

            foreach ($rows as $row) {
                $maMon = $this->normalizeSubjectCode($row['ma_mon'] ?? '');

                if ($maMon === '') {
                    continue;
                }

                $subject = $subjectsByCode->get($maMon);

                if (!$subject) {
                    Log::warning('Đồng bộ điểm: Không tìm thấy môn trong bảng subjects', [
                        'student_id' => $studentId,
                        'ma_mon' => $maMon,
                        'ten_mon' => $row['ten_mon'] ?? null,
                    ]);

                    continue;
                }

                $academicSemester = $row['hoc_ky'] ?? null;

                if (blank($academicSemester)) {
                    Log::warning('Đồng bộ điểm: Thiếu học kỳ', [
                        'student_id' => $studentId,
                        'ma_mon' => $maMon,
                        'ten_mon' => $row['ten_mon'] ?? null,
                    ]);

                    continue;
                }

                StudentGrade::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'subject_id' => $subject->id,
                        'academic_semester' => $academicSemester,
                    ],
                    [
                        'final_score' => $this->numericOrNull($row['diem_thi'] ?? null),
                        'score_10' => $this->numericOrNull($row['diem_tk_10'] ?? null),
                        'score_4' => $this->numericOrNull($row['diem_tk_4'] ?? null),
                        'letter_grade' => filled($row['diem_chu'] ?? null) ? trim((string) $row['diem_chu']) : null,
                        'is_passed' => $this->resolvePassedStatus($row),
                        'is_studying' => $this->resolvePassedStatus($row) === -1,
                    ]
                );
            }
        });
    }

    public function updateStudentStats(int $studentId, array $semesters): void
    {
        $validSemester = collect($semesters)
            ->filter(fn ($semester) => filled($semester['dtb_tich_luy_he_10'] ?? null))
            ->sortByDesc(fn ($semester) => (int) ($semester['hoc_ky'] ?? 0))
            ->first();

        if (!$validSemester) {
            return;
        }

        Student::where('id', $studentId)->update([
            'gpa_4' => $this->numericOrNull($validSemester['dtb_tich_luy_he_4'] ?? null),
            'gpa_10' => $this->numericOrNull($validSemester['dtb_tich_luy_he_10'] ?? null),
            'total_credits_earned' => (int) ($this->numericOrNull($validSemester['so_tin_chi_dat_tich_luy'] ?? null) ?? 0),
            'last_academic_stats_updated_at' => now(),
        ]);
    }

    private function extractCurrUserFromLocation(string $location): ?string
    {
        $query = parse_url($location, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $params);

            if (!empty($params['CurrUser'])) {
                return $params['CurrUser'];
            }
        }

        $fragment = parse_url($location, PHP_URL_FRAGMENT);

        if ($fragment) {
            $questionPos = strpos($fragment, '?');

            if ($questionPos !== false) {
                $fragmentQuery = substr($fragment, $questionPos + 1);

                parse_str($fragmentQuery, $params);

                if (!empty($params['CurrUser'])) {
                    return $params['CurrUser'];
                }
            }

            $fragment = ltrim($fragment, '/?');

            parse_str($fragment, $params);

            if (!empty($params['CurrUser'])) {
                return $params['CurrUser'];
            }
        }

        return null;
    }

    private function decodeBase64Json(string $value): array
    {
        $value = trim($value);
        $value = str_replace(' ', '+', $value);
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);

        $json = base64_decode($value, true);

        if ($json === false) {
            throw new VnuaInvalidCredentialsException(
                'Sai mã sinh viên hoặc mật khẩu trang đào tạo.'
            );
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning('Không decode được CurrUser từ trang đào tạo', [
                'message' => $e->getMessage(),
            ]);

            throw new VnuaInvalidCredentialsException(
                'Sai mã sinh viên hoặc mật khẩu trang đào tạo.'
            );
        }

        if (!is_array($data)) {
            throw new VnuaInvalidCredentialsException(
                'Sai mã sinh viên hoặc mật khẩu trang đào tạo.'
            );
        }

        return $data;
    }

    private function encodeUa(string $endpoint): string
    {
        $api = $this->extractApiPart($endpoint);

        if ($api === '') {
            return '';
        }

        if (strlen($api) > 22) {
            $api = substr($api, 0, 22);
        }

        return $this->gc($api);
    }

    private function extractApiPart(string $endpoint): string
    {
        $value = strtolower($endpoint);

        if (str_starts_with($value, 'api/')) {
            $value = '/' . $value;
        }

        $pos = strpos($value, '/api/');

        if ($pos === false) {
            return '';
        }

        return strtoupper(substr($value, $pos + strlen('/api/')));
    }

    private function gc(string $value): string
    {
        $be = random_int(1, 31);

        $text = random_int(10, 99)
            . (int) floor(microtime(true) * 1000)
            . random_int(10, 99)
            . $value;

        $chars = array_merge([$be + 32], $this->ec($text, $be));

        $utf8 = '';

        foreach ($chars as $charCode) {
            $utf8 .= mb_chr($charCode, 'UTF-8');
        }

        return base64_encode($utf8);
    }

    private function ec(string $text, int $seed): array
    {
        $key = array_reverse($this->rk($seed));
        $textCodes = array_map('ord', str_split($text));

        $fullKey = [];

        while (count($fullKey) < count($textCodes)) {
            $fullKey = array_merge($fullKey, $key);
        }

        $result = [];

        foreach ($textCodes as $index => $code) {
            $result[] = $code ^ $fullKey[$index];
        }

        return $result;
    }

    private function rk(int $seed): array
    {
        $source = $this->sc();
        $step = ($seed % 3) + 1;
        $result = [];

        for ($i = 0; $i < 10; $i++) {
            $result[] = $source[($seed + $i * $step) % count($source)];
        }

        return $result;
    }

    private function sc(): array
    {
        return [
            4, 165, 110, 3, 44, 202, 186, 28,
            118, 177, 32, 94, 219, 6, 199, 27,
            101, 191, 66, 115, 234, 120, 10, 236,
            104, 108, 74, 247, 68, 198, 62, 203,
        ];
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeSubjectCode(mixed $code): string
    {
        return mb_strtoupper(trim((string) $code));
    }

    private function resolvePassedStatus(array $row): int
    {
        $raw = $row['ket_qua'] ?? null;
        $score10 = $this->numericOrNull($row['diem_tk_10'] ?? null);
        $finalScore = $this->numericOrNull($row['diem_thi'] ?? null);

        if (blank($raw) && $score10 === null && $finalScore === null) {
            return -1;
        }

        if (is_bool($raw)) {
            return $raw ? 1 : 0;
        }

        if (is_numeric($raw)) {
            $numericResult = (int) $raw;

            if ($numericResult === 1) {
                return 1;
            }

            if ($numericResult === -1) {
                return -1;
            }

            return 0;
        }

        $normalized = mb_strtolower(trim((string) $raw));

        if (in_array($normalized, ['đạt', 'dat', 'pass', 'passed', 'true'], true)) {
            return 1;
        }

        if (in_array($normalized, ['không đạt', 'khong dat', 'chưa đạt', 'chua dat', 'fail', 'failed', 'false'], true)) {
            return 0;
        }

        if ($score10 !== null) {
            return $score10 >= 4.0 ? 1 : 0;
        }

        return 0;
    }

    private function bodyPreview(string $body): string
    {
        return mb_substr($body, 0, 500);
    }

    private function maskSensitiveLocation(string $location): string
    {
        return preg_replace('/CurrUser=([^&]+)/', 'CurrUser=[MASKED]', $location) ?? $location;
    }
}
