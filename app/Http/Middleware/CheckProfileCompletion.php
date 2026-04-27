<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
class CheckProfileCompletion
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->user_type === 'student') {
            $student = $user->student;

            // Định nghĩa các trường bắt buộc phải có
            $isMissingInfo = !$student ||
//                empty($student->major_id) ||
                empty($student->intake_id) ||
                empty($student->class_name) ||
                empty($student->program_major_id);

            if ($isMissingInfo) {
                // 1. Cho phép nếu đang truy cập chính trang profile
                if ($request->routeIs('client.account')) {
                    return $next($request);
                }
                if ($request->routeIs('handleLogout')) {
                    return $next($request);
                }

                // 2. Cực kỳ quan trọng: Cho phép các request ngầm của Livewire để không bị load trang
                if ($request->hasHeader('X-Livewire')) {
                    return $next($request);
                }

                // 3. Nếu đang ở các trang khác, đá về trang profile kèm cờ setup
                return redirect()->route('client.account', ['setup' => 1])
                    ->with('warning', 'Vui lòng hoàn thiện thông tin ngành, khóa, lớp để tiếp tục.');
            }
        }

        return $next($request);
    }
}
