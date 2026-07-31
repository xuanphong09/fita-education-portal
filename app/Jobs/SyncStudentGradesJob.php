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

    /**
     * Nếu worker vượt quá timeout, Laravel đánh dấu job thất bại
     * và gọi phương thức failed().
     */
    public bool $failOnTimeout = true;

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
            'attempt' => $this->attempts(),
        ]);

        $student = Student::find($this->studentId);

        if (!$student) {
            Log::warning('Không tìm thấy sinh viên để đồng bộ điểm', [
                'student_id' => $this->studentId,
            ]);

            return;
        }

        if (blank($student->vnua_password)) {
            $student->forceFill([
                'grade_sync_status' => 'failed',
                'grade_sync_message' => 'Sinh viên chưa lưu mật khẩu trang đào tạo.',
                'grade_sync_failed_at' => now(),
            ])->save();

            Log::info('Bỏ qua đồng bộ vì sinh viên chưa lưu mật khẩu trang đào tạo', [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
            ]);

            return;
        }

        // Chuyển trạng thái ngay khi worker thực sự nhận job.
        // Giao diện Livewire sẽ đọc trạng thái này trong lần poll kế tiếp.
        $student->forceFill([
            'grade_sync_status' => 'syncing',
            'grade_sync_message' => 'Hệ thống đang lấy dữ liệu điểm từ trang đào tạo.',
            'grade_sync_started_at' => now(),
            'grade_sync_failed_at' => null,
        ])->save();

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
            // VnuaTrainingService đã cập nhật trạng thái invalid_password.
            // Không throw lại vì sai mật khẩu không nên retry tự động.
            Log::warning('Sai mật khẩu trang đào tạo khi đồng bộ điểm', [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Đồng bộ điểm thất bại', [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            // Service đã đặt failed. Nếu vẫn còn lượt retry, đổi lại queued
            // để giao diện phản ánh đúng rằng job đang chờ thử lại.
            if ($this->attempts() < $this->tries) {
                $student->forceFill([
                    'grade_sync_status' => 'queued',
                    'grade_sync_message' => sprintf(
                        'Đồng bộ gặp lỗi, hệ thống sẽ thử lại lần %d.',
                        $this->attempts() + 1
                    ),
                    'grade_sync_started_at' => null,
                ])->save();
            }

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Student::query()
            ->whereKey($this->studentId)
            ->update([
                'grade_sync_status' => 'failed',
                'grade_sync_message' => 'Đồng bộ thất bại sau khi đã thử lại.',
                'grade_sync_failed_at' => now(),
                'updated_at' => now(),
            ]);

        Log::error('SyncStudentGradesJob failed sau khi retry', [
            'student_id' => $this->studentId,
            'message' => $e->getMessage(),
        ]);
    }
}
