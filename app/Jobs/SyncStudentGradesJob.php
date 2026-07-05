<?php

namespace App\Jobs;

use App\Exceptions\VnuaInvalidCredentialsException;
use App\Models\Student;
use App\Services\VnuaTrainingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncStudentGradesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $studentId,
        public bool $force = false
    ) {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(VnuaTrainingService $service): void
    {
        Log::info('Bắt đầu job đồng bộ điểm', [
            'student_id' => $this->studentId,
            'force' => $this->force,
        ]);

        $student = Student::find($this->studentId);

        if (!$student) {
            Log::warning('Không tìm thấy sinh viên để đồng bộ điểm', [
                'student_id' => $this->studentId,
            ]);

            return;
        }

        if (blank($student->vnua_password)) {
            Log::info('Bỏ qua đồng bộ vì sinh viên chưa lưu mật khẩu trang đào tạo', [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
            ]);

            return;
        }

        try {
            $result = $service->syncStudentGrades($student, $this->force);

            Log::info('Kết thúc job đồng bộ điểm', [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'skipped' => $result['skipped'] ?? false,
                'message' => $result['message'] ?? null,
                'rows_count' => $result['rows_count'] ?? null,
                'semesters_count' => $result['semesters_count'] ?? null,
            ]);
        } catch (VnuaInvalidCredentialsException $e) {
            Log::warning('Sai mật khẩu trang đào tạo khi đồng bộ điểm', [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'message' => $e->getMessage(),
            ]);

            return;
        } catch (Throwable $e) {
            Log::error('Đồng bộ điểm thất bại', [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncStudentGradesJob failed sau khi retry', [
            'student_id' => $this->studentId,
            'message' => $e->getMessage(),
        ]);
    }
}
