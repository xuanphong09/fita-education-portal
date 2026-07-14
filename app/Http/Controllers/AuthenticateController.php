<?php

namespace App\Http\Controllers;

use App\Jobs\SendFirstSsoPasswordSetupEmail;
use App\Jobs\SyncStudentGradesJob;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use App\Services\VnuaTrainingService;
use Exception;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AuthenticateController extends Controller
{
    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('client.home');
    }

    public function redirectToSSO()
    {
        $ssoUrl = rtrim((string) config('auth.sso.url'), '/');
        $clientId = (string) config('auth.sso.client_id');

        if ($ssoUrl === '' || $clientId === '' || $clientId === 'client_id') {
            return redirect()->route('login')->withErrors(['msg' => 'SSO chưa được cấu hình đầy đủ.']);
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => route('sso.callback'),
            'response_type' => 'code',
            'scope' => '',
        ]);

        // Chuyển hướng ngoài domain ứng dụng.
        return redirect()->away($ssoUrl.'/oauth/authorize?'.$query);
    }

    public function handleSSOCallback(Request $request)
    {
        try {
            $code = (string) $request->input('code', '');
            if ($code === '') {
                return redirect()->route('login')->withErrors(['msg' => 'Đăng nhập thất bại: Thiếu mã xác thực từ SSO.']);
            }

            $data = $this->getAccessToken($code);
            if (!isset($data['access_token'])) {
                return redirect()->route('login')->withErrors(['msg' => 'Đăng nhập thất bại: Không nhận được access token.']);
            }

            $userData = $this->getUserData($data['access_token']);
            $user = $this->findOrCreateUser($userData, $data['access_token']);

            if (!$user) {
                return redirect()->route('login')->withErrors(['msg' => 'Đăng nhập thất bại: Không thể đồng bộ tài khoản.']);
            }

            if (!$user->is_active) {
                return redirect()->route('login')->withErrors(['msg' => __('auth.inactive')]);
            }

            $this->sendPasswordSetupLinkIfNeeded($user);

            Auth::login($user, true);

            $showSyncPasswordWarning = false;

            if ($user->user_type === 'student') {
                $user->loadMissing('student');

                $student = $user->student;

                if ($student && filled($student->vnua_password)) {
                    SyncStudentGradesJob::dispatch($student->id, false);
                }
                else{
                    $showSyncPasswordWarning = true;
                }
            }
            $request->session()->regenerate();

            if ($showSyncPasswordWarning) {
                return redirect()
                    ->route('client.home')
                    ->with(
                        'sync_password_warning',
                        __('Please go to Username → Account to start syncing immediately.')
                    );
            }

            return redirect()->route('client.home');
        } catch (QueryException $e) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
            $rawMessage = Str::lower($e->getMessage());

            Log::error('SSO sync query exception', [
                'sql_state' => $sqlState,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            if ($sqlState === '23000' && str_contains($rawMessage, 'duplicate entry')) {
                return redirect()->route('login')->withErrors(['msg' => 'Đăng nhập thất bại: Mã người dùng từ SSO đã tồn tại trong hệ thống.']);
            }

            return redirect()->route('login')->withErrors(['msg' => 'Đăng nhập thất bại: Không thể đồng bộ dữ liệu người dùng.']);
        } catch (Exception $e) {
            return redirect()->route('login')->withErrors(['msg' => 'Đăng nhập thất bại: '.$e->getMessage()]);
        }
    }

    public function getAccessToken(string $code)
    {
        // server: url -> ip
//        $response = Http::asForm()->post(config('auth.sso.url').'/oauth/token', [
        $response = Http::asForm()->post(config('auth.sso.ip').'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('auth.sso.client_id'),
            'client_secret' => config('auth.sso.client_secret'),
            'redirect_uri' => route('sso.callback'),
            'code' => $code,
        ]);

        return $response->json();
    }

    public function getUserData(string $accessToken)
    {
        // server: url -> ip
//        $response = Http::withToken($accessToken)->get(config('auth.sso.url').'/api/user');
        $response = Http::withToken($accessToken)->get(config('auth.sso.ip').'/api/user');
        return $response->json();
    }
    public function findOrCreateUser(array $userData, string $accessToken)
    {
        $userData = array_merge($userData, ['access_token' => $accessToken]);

        if (!isset($userData['id'], $userData['email'], $userData['full_name'])) {
            throw new Exception('Dữ liệu người dùng từ SSO không hợp lệ.');
        }

        $email = $userData['email'];
//        $code = trim((string) ($userData['code'] ?? ''));

        // --- THÊM BƯỚC KIỂM TRA ĐUÔI EMAIL ---
        $isLecturerEmail = Str::endsWith($email, '@vnua.edu.vn');
        $isStudentEmail = Str::endsWith($email, '@sv.vnua.edu.vn');

        if (!$isLecturerEmail && !$isStudentEmail) {
            throw new Exception('Truy cập bị từ chối. Vui lòng sử dụng email do nhà trường cấp (@vnua.edu.vn hoặc @sv.vnua.edu.vn).');
        }
//        if ($code === '') {
//            throw new Exception('Đăng nhập bị từ chối: Tài khoản của bạn bị thiếu thông tin Mã sinh viên / Mã giảng viên từ hệ thống.');
//        }
        $userType = $this->determineUserType($email);

        $user = User::where('sso_id', $userData['id'])->first();

        if ($user) {
            $user->update([
                'access_token' => $userData['access_token'],
                'name' => $userData['full_name'],
                'email' => $userData['email'],
                'last_login_at'=> now(),
//                'user_type' => $user->user_type ?: $userType,
            ]);

//            $this->syncProfileByType($user, $userData);

            return $user;
        }

        $user = User::where('email', $userData['email'])->first();
        if ($user) {
            $conflict = User::where('sso_id', $userData['id'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($conflict) {
                throw new Exception('Tài khoản SSO này đã liên kết với người dùng khác.');
            }

            if (!empty($user->sso_id) && (string) $user->sso_id !== (string) $userData['id']) {
                throw new Exception('Email này đã liên kết với một tài khoản SSO khác. Vui lòng đăng nhập bằng đúng tài khoản SSO đã liên kết.');
            }

            if (empty($user->sso_id)) {
                $user->update([
                    'sso_id' => $userData['id'],
                    'access_token' => $userData['access_token'],
                    'name' => $userData['full_name'],
                    'email' => $userData['email'],
                    'last_login_at'=> now(),
                    'user_type' => $user->user_type ?: $userType,
                ]);
            }

            $this->syncProfileByType($user, $userData);

            return $user;
        }

        $user = User::create([
            'name' => $userData['full_name'],
            'email' => $userData['email'],
            'sso_id' => $userData['id'],
            'last_login_at' => now(),
            'access_token' => $userData['access_token'],
            'user_type' => $userType,
            'is_active' => true,
        ]);

        $this->syncProfileByType($user, $userData);
        $this->assignDefaultRole($user);

        return $user;
    }

    private function syncProfileByType(User $user, array $userData): void
    {
        $code = trim((string) ($userData['code'] ?? ''));

        // Nếu SSO không trả mã, bỏ qua đồng bộ bảng hồ sơ để tránh ghi sai/nhầm người.
        if ($code === '') {
            return;
        }

        if ($user->user_type === 'student') {
            // students.student_code: NOT NULL, unique, max 10
            $studentCode = Str::upper(Str::limit($code, 10, ''));

            Student::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'student_code' => $studentCode,
                    'phone' => $userData['phone'] ?? null,
                ]
            );

            return;
        }

        if ($user->user_type === 'lecturer') {
            // lecturers.staff_code: NOT NULL, unique, max 20
            $staffCode = Str::upper(Str::limit($code, 20, ''));

            $suffix = '-'.Str::lower($staffCode);

            Lecturer::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'staff_code' => $staffCode ,
                    'slug' => Str::slug($userData['full_name']).$suffix,
                    'phone' => $userData['phone'] ?? null,
                ]
            );
        }
    }

//    private function assignDefaultRole(User $user): void
//    {
//        $roleName = match ($user->user_type) {
//            'student' => 'sinh_vien',
//            'lecturer' => 'giang_vien',
//            default => null,
//        };
//
//        if (!$roleName) {
//            return;
//        }
//
//        // Spatie roles cần guard_name, nếu thiếu sẽ lỗi NOT NULL (hay gặp ở user SSO mới).
//        $role = Role::firstOrCreate([
//            'name' => $roleName,
//            'guard_name' => 'web',
//        ]);
//
//        if (!$user->hasRole($roleName)) {
//            $user->assignRole($role->name);
//        }
//    }

    private function assignDefaultRole(User $user): void
    {
        if ($user->user_type !== 'student') {
            return;
        }

        $roleName = 'sinh_vien';

        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        if (!$user->hasRole($roleName)) {
            $user->assignRole($role->name);
        }
    }

    private function determineUserType(string $email): string
    {
        $email = Str::lower(trim($email));

        if (Str::endsWith($email, '@sv.vnua.edu.vn')) {
            return 'student';
        }

        if (Str::endsWith($email, '@vnua.edu.vn')) {
            return 'admin';
        }
        throw new Exception('Truy cập bị từ chối. Vui lòng sử dụng email do nhà trường cấp (@vnua.edu.vn hoặc @sv.vnua.edu.vn).');
//        return match ($role) {
//            'superAdmin', 'officer', 'teacher',  => 'lecturer',
//            default => 'student',
//        };
    }

    private function sendPasswordSetupLinkIfNeeded(User $user): void
    {
        if (!empty($user->password)) {
            return;
        }

        $cooldownMinutes = max(1, (int) config('auth.sso.password_setup_resend_cooldown', 60));
        $nextAllowedAt = now()->subMinutes($cooldownMinutes);

        // Fallback an toàn khi migration chưa chạy trên môi trường hiện tại.
        if (!Schema::hasColumn('users', 'sso_password_setup_sent_at')) {
            SendFirstSsoPasswordSetupEmail::dispatch($user->id);
            return;
        }

        // Atomic guard: cho phép gửi lần đầu hoặc gửi lại sau khoảng cooldown.
        $shouldQueue = User::whereKey($user->id)
            ->whereNull('password')
            ->where(function ($query) use ($nextAllowedAt) {
                $query->whereNull('sso_password_setup_sent_at')
                    ->orWhere('sso_password_setup_sent_at', '<=', $nextAllowedAt);
            })
            ->update(['sso_password_setup_sent_at' => now()]);

        if ($shouldQueue) {
            SendFirstSsoPasswordSetupEmail::dispatch($user->id);
        }
    }
}
