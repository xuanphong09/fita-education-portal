<?php

namespace App\Services;

use App\Exceptions\VnuaInvalidCredentialsException;
use App\Exceptions\VnuaSyncException;
use App\Models\Student;
use Exception;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use App\Models\Subject;
use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\Cache;
class VnuaTrainingService
{
    private string $baseUrl = 'https://daotao.vnua.edu.vn';

    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36';

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
        // Mở trang trước để tạo session/cookie ban đầu
        Http::withoutVerifying()
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
                'mgr' => 0,
            ]);

        if (! in_array($response->status(), [301, 302], true)) {
            throw new Exception('Đăng nhập thất bại. HTTP ' . $response->status());
        }

        $location = $response->header('Location');

        if (! $location) {
            throw new Exception('Không tìm thấy Location redirect sau đăng nhập.');
        }

        $currUserEncoded = $this->extractCurrUserFromLocation($location);

        if (! $currUserEncoded) {
            throw new Exception('Không tìm thấy CurrUser trong Location redirect.');
        }

        $currentUser = $this->decodeBase64Json($currUserEncoded);

        if (! ($currentUser['result'] ?? false)) {
            throw new VnuaInvalidCredentialsException(
                $currentUser['message'] ?? 'Sai mật khẩu trang đào tạo.'
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
        $url = $this->baseUrl . '/manage/api/srm/w-locdsdiemsinhvien?hien_thi_mon_theo_hkdk=false';

        $response = Http::withoutVerifying()
            ->timeout(20)
            ->withOptions([
                'cookies' => $cookieJar,
            ])
            ->withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'Content-Type' => 'text/plain',
                'Authorization' => 'Bearer ' . $accessToken,
                'Origin' => $this->baseUrl,
                'Referer' => $this->baseUrl . '/manage/',
                'idpc' => '0',
                'ua' => $this->encodeUa($url),
                'User-Agent' => $this->userAgent,
            ])
            ->withBody('', 'text/plain')
            ->post($url);

        $json = $response->json();

        if (! $response->successful() || ! ($json['result'] ?? false)) {
            throw new VnuaSyncException(
                'Không lấy được điểm. HTTP ' . $response->status()
                . ' - ' . ($json['message'] ?? 'Trang đào tạo không trả về dữ liệu hợp lệ.')
            );
        }

        return data_get($json, 'data.ds_diem_hocky', []);
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
                    'ket_qua' => $subject['ket_qua'] ?? 0,
                    'khong_tinh_diem_tbtl' => $subject['khong_tinh_diem_tbtl'] ?? null,
                    'ly_do_khong_tinh_diem_tbtl' => $subject['ly_do_khong_tinh_diem_tbtl'] ?? null,
                    'ds_diem_thanh_phan' => $subject['ds_diem_thanh_phan'] ?? [],
                ];
            }
        }

        return $rows;
    }

    private function extractCurrUserFromLocation(string $location): ?string
    {
        $fragment = parse_url($location, PHP_URL_FRAGMENT);

        if ($fragment) {
            $fragment = ltrim($fragment, '/?');
            parse_str($fragment, $params);

            if (! empty($params['CurrUser'])) {
                return $params['CurrUser'];
            }
        }

        $query = parse_url($location, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $params);

            if (! empty($params['CurrUser'])) {
                return $params['CurrUser'];
            }
        }

        return null;
    }

    private function decodeBase64Json(string $value): array
    {
        $value = urldecode($value);
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);

        $json = base64_decode($value, true);

        if ($json === false) {
            throw new Exception('Không decode được Base64.');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
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

    public function saveGradesToDatabase(int $studentId, array $rows): void
    {
        DB::transaction(function () use ($studentId, $rows) {
            foreach ($rows as $row) {
                // Lấy mã môn, ví dụ: 'TH03111'
                $maMon = $row['ma_mon'] ?? null;
                if (!$maMon) continue;

                // 1. Tìm Subject trong DB của hệ thống thông qua mã môn
                $subject = Subject::where('code', $maMon)->first();

                if (!$subject) {
                    // Nếu môn này hệ thống chưa có, ghi log lại để admin bổ sung sau
                    Log::warning("Đồng bộ điểm SV ID {$studentId}: Không tìm thấy môn [{$maMon}] - {$row['ten_mon']} trong bảng subjects.");
                    continue;
                }

                // 2. Chuyển đổi trạng thái "Đạt/Chưa đạt"
//                $isPassed = 0;
//
//                if (isset($row['ket_qua'])) {
//                    if ($row['ket_qua'] === 'Đạt') {
//                        $isPassed = 1;
//                    }
//                }
//
//                if (empty($row['diem_tk_10']) && empty($row['diem_thi'])) {
//                    $isPassed = -1;
//                }

                // 3. Lưu hoặc cập nhật (UpdateOrCreate giúp không bị duplicate điểm nếu đồng bộ nhiều lần)
                StudentGrade::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'subject_id' => $subject->id,
                        'academic_semester' => $row['hoc_ky'],
                    ],
                    [
                        // Xử lý cẩn thận kiểu dữ liệu phòng trường hợp chuỗi rỗng
                        'final_score' => is_numeric($row['diem_thi']) ? (float) $row['diem_thi'] : null,
                        'score_10' => is_numeric($row['diem_tk_10']) ? (float) $row['diem_tk_10'] : null,
                        'score_4' => is_numeric($row['diem_tk_4']) ? (float) $row['diem_tk_4'] : null,
                        'letter_grade' => $row['diem_chu'] ?: null,

                        'is_passed' => $row['ket_qua'],
                        'is_studying' => false,
                    ]
                );
            }
        });
    }

    public function updateStudentStats(int $studentId, array $semesters): void
    {
        $semesterCollection = collect($semesters);
        $firstValidSemester = $semesterCollection
            ->filter(function ($semester) {
                return !empty($semester['dtb_tich_luy_he_10']);
            })
            ->first();
        if ($firstValidSemester) {
            Student::where('id', $studentId)->update([
                'gpa_4' => $firstValidSemester['dtb_tich_luy_he_4'] ?? null,
                'gpa_10' => $firstValidSemester['dtb_tich_luy_he_10'] ?? null,
                'total_credits_earned' => $firstValidSemester['so_tin_chi_dat_tich_luy'] ?? 0,
                'last_academic_stats_updated_at' => now(),
             ]);
        }
    }

    public function syncStudentGrades(Student $student, bool $force = false): array
    {
        $student->refresh();

        if (blank($student->student_code)) {
            throw new VnuaSyncException('Sinh viên chưa có mã sinh viên.');
        }

        if (blank($student->vnua_password)) {
            throw new VnuaInvalidCredentialsException('Sinh viên chưa lưu mật khẩu trang đào tạo.');
        }

        // Không cào lại quá thường xuyên, trừ khi bấm đồng bộ thủ công.
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

        // Nếu đã biết sai mật khẩu thì không tự động cào lại mỗi lần đăng nhập.
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
                'grade_sync_failed_count' => $student->grade_sync_failed_count + 1,
                'grade_sync_failed_at' => now(),
            ])->save();

            throw $e;
        } catch (Throwable $e) {
            $student->forceFill([
                'grade_sync_status' => 'failed',
                'grade_sync_message' => 'Sai mật khẩu trang đào tạo.',
                'grade_sync_failed_count' => $student->grade_sync_failed_count + 1,
                'grade_sync_failed_at' => now(),
            ])->save();

            throw $e;
        } finally {
            optional($lock)->release();
        }
    }
}
