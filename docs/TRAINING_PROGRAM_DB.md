# Training Program Database

Module nay quan ly chuong trinh dao tao theo:
- bo mon (`departments`)
- nganh (`majors`, tuy chon)
- khoa (`intakes`)
- nhom mon hoc (`group_subjects`)
- hoc ky (`program_semesters`)
- danh sach mon hoc tung hoc ky (`program_semester_subjects`)

## Bang moi

### 1) `group_subjects`
- `code` (unique): ma nhom mon hoc
- `name` (json): ten nhom song ngu
- `description` (json, nullable)
- `sort_order`
- `is_active`
- `deleted_at`

### 2) `subjects`
- `code` (unique): ma mon hoc, vi du `IT202`
- `name` (json): ten song ngu
- `description` (json, nullable)
- `credits`
- `tuition_credits`: so tin chi tinh hoc phi
- `group_subject_id` -> `group_subjects.id` (nullable)
- `is_active`

### 3) `training_programs`
- `code` (unique): ma chuong trinh
- `name` (json): ten CTDT song ngu
- `department_id` -> `departments.id`
- `major_id` -> `majors.id` (nullable)
- `intake_id` -> `intakes.id`
- `school_year_start`, `school_year_end`: nien khoa (vi du 2026-2030)
- `version` (mac dinh 1)
- `total_credits`
- `thumbnail`: anh dai dien CTDT
- `status`: `draft|published|archived`
- `published_at`, `notes`
- `deleted_at`

Rang buoc: unique scope `department_id + major_id + intake_id + version`.

### 4) `program_semesters`
- `training_program_id` -> `training_programs.id`
- `semester_no` (hoc ky so)
- `title` (json, nullable)
- `total_credits`, `sort_order`

Rang buoc: unique `training_program_id + semester_no`.

### 5) `program_semester_subjects`
- `program_semester_id` -> `program_semesters.id`
- `subject_id` -> `subjects.id`
- `is_required`, `type` (`required|elective`)
- `hours_total`, `hours_theory`, `hours_practice`, `notes`, `order`

Rang buoc: unique `program_semester_id + subject_id`.

### 6) `subject_prerequisites`
- `subject_id`: mon hoc hien tai (mon A)
- `prerequisite_subject_id`: mon hoc tien quyet (mon B)

Nghia la: de hoc A thi phai hoc B truoc.
Rang buoc: unique cap `subject_id + prerequisite_subject_id` de tranh lap lai.

## Models moi
- `App\Models\GroupSubject`
- `App\Models\Subject`
- `App\Models\TrainingProgram`
- `App\Models\ProgramSemester`

Da bo sung quan he `trainingPrograms()` vao:
- `App\Models\Department`
- `App\Models\Major`
- `App\Models\Intake`

## Seeder mau
- `Database\Seeders\GroupSubjectSeeder`
  - Tao 3 nhom mon hoc mau
- `Database\Seeders\SubjectSeeder`
  - Tao 8 mon hoc song ngu
  - Gan mon vao nhom mon hoc
  - Tao du lieu mon tien quyet
- `Database\Seeders\TrainingProgramSeeder`
  - Tao 1 CTDT mau theo khoa/bo mon hien co
  - Tao 4 hoc ky mau
  - Gan 8 mon vao hoc ky voi thong tin tiet hoc

Da duoc dang ky trong `DatabaseSeeder`.

## Run nhanh

```bash
php artisan migrate
php artisan db:seed --class=TrainingProgramSeeder
```

Hoac reset toan bo:

```bash
php artisan migrate:fresh --seed
```

## Truy van mon tien quyet khi xem chi tiet mon

```php
$subject = Subject::with('prerequisites')->findOrFail($id);

// $subject->prerequisites la danh sach mon B cua mon A
```

## Giai thich nhanh theo cot giao dien

Neu giao dien bang mon hoc cua ban co cac cot nhu anh (`Ma MH`, `Ten mon hoc`, `Chuyen nganh`, `So tin chi`, `So tin chi hoc phi`, `Mon bat buoc`, `Da hoc`, `Tong tiet`, `Ly thuyet`, `Thuc hanh`) thi map nhu sau:

- `Stt`: so thu tu hien thi (tu `program_semester_subjects.order`)
- `Ma MH`: `subjects.code`
- `Ten mon hoc`: `subjects.name`
- `Chuyen nganh`: `training_programs.major_id` -> `majors.name`
- `So tin chi`: `subjects.credits`
- `So tin chi hoc phi`: `subjects.tuition_credits`
- `Mon bat buoc`: `program_semester_subjects.is_required` (hoac `type = required`)
- `Da hoc`: cot nay nen de trong bang ket qua hoc tap sinh vien (khong nen de trong CTDT goc)
- `Tong tiet`: `program_semester_subjects.hours_total`
- `Ly thuyet`: `program_semester_subjects.hours_theory`
- `Thuc hanh`: `program_semester_subjects.hours_practice`

> Goi y: `Da hoc` la du lieu theo tung sinh vien, ban nen tao them bang hoc tap/rut gon nhu `student_subject_results` cho dung nghiep vu.


