<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Post;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $this->command->warn('No users found. Please create a user first.');
            return;
        }

        $categories = Category::where('is_active', true)->get()->values();
        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please create categories first.');
            return;
        }

        $pickCategoryId = function (int $offset = 0) use ($categories): int {
            return $categories[$offset % $categories->count()]->id;
        };

        $posts = [
            [
                'title' => ['vi' => 'Khoa CNTT tổ chức hội thảo khoa học năm 2026', 'en' => 'Faculty of IT organizes scientific conference 2026'],
                'excerpt' => ['vi' => 'Hội thảo khoa học về AI, IoT, Blockchain trong nông nghiệp thông minh.', 'en' => 'A scientific conference about AI, IoT, and Blockchain in smart agriculture.'],
                'content' => ['vi' => '<p>Hội thảo khoa học cấp khoa với nhiều báo cáo chất lượng.</p>', 'en' => '<p>Faculty-level scientific conference with many quality papers.</p>'],
                'status' => 'published',
                'is_featured' => true,
                'published_at' => now()->subDays(1),
                'category_id' => $pickCategoryId(0),
            ],
            [
                'title' => ['vi' => 'Sinh viên CNTT đạt giải Nhất cuộc thi lập trình toàn quốc', 'en' => 'IT students win First Prize in national programming contest'],
                'excerpt' => ['vi' => 'Đội tuyển Khoa CNTT vượt qua hơn 100 đội dự thi.', 'en' => 'The IT student team outperformed more than 100 competitors.'],
                'content' => ['vi' => '<p>Đây là thành tích nổi bật của sinh viên Khoa CNTT.</p>', 'en' => '<p>This is an outstanding achievement for IT students.</p>'],
                'status' => 'published',
                'is_featured' => true,
                'published_at' => now()->subDays(2),
                'category_id' => $pickCategoryId(1),
            ],
            [
                'title' => ['vi' => 'Khai giảng khóa đào tạo AI và Data Science', 'en' => 'Opening AI and Data Science short course'],
                'excerpt' => ['vi' => 'Khóa học 3 tháng cho sinh viên và người đi làm.', 'en' => 'A 3-month program for students and professionals.'],
                'content' => ['vi' => '<p>Chương trình học kết hợp online và offline.</p>', 'en' => '<p>The training combines online and offline sessions.</p>'],
                'status' => 'published',
                'is_featured' => true,
                'published_at' => now()->subDays(3),
                'category_id' => $pickCategoryId(2),
            ],
            [
                'title' => ['vi' => 'Lịch bảo vệ đồ án tốt nghiệp học kỳ II', 'en' => 'Graduation thesis defense schedule for semester II'],
                'excerpt' => ['vi' => 'Cập nhật lịch bảo vệ và danh sách hội đồng.', 'en' => 'Updated defense schedule and committee list.'],
                'content' => ['vi' => '<p>Nhà trường thông báo lịch bảo vệ đồ án từ ngày 20 đến 25.</p>', 'en' => '<p>The university announces the defense timeline from 20th to 25th.</p>'],
                'status' => 'published',
                'is_featured' => true,
                'published_at' => now()->subDays(5),
                'category_id' => $pickCategoryId(0),
            ],
            [
                'title' => ['vi' => 'Thông báo tuyển cộng tác viên CLB lập trình', 'en' => 'Programming club recruitment announcement'],
                'excerpt' => ['vi' => 'Mở đơn tuyển thành viên mới cho CLB lập trình.', 'en' => 'Open recruitment for new programming club members.'],
                'content' => ['vi' => '<p>Ưu tiên sinh viên năm nhất và năm hai đam mê lập trình.</p>', 'en' => '<p>Priority for first- and second-year students passionate about coding.</p>'],
                'status' => 'published',
                'is_featured' => true,
                'published_at' => now()->subDays(6),
                'category_id' => $pickCategoryId(1),
            ],
            [
                'title' => ['vi' => 'Hướng dẫn đăng ký môn học trực tuyến học kỳ mới', 'en' => 'How to register courses online for the new semester'],
                'excerpt' => ['vi' => 'Quy trình đăng ký học phần qua cổng thông tin sinh viên.', 'en' => 'Course registration workflow via student portal.'],
                'content' => ['vi' => '<p>Sinh viên thực hiện đăng ký theo đúng mốc thời gian.</p>', 'en' => '<p>Students should register within the announced timeline.</p>'],
                'status' => 'published',
                'is_featured' => false,
                'published_at' => now()->subDays(7),
                'category_id' => $pickCategoryId(2),
            ],
            [
                'title' => ['vi' => 'Thông tin học bổng doanh nghiệp năm 2026', 'en' => 'Enterprise scholarship information 2026'],
                'excerpt' => ['vi' => 'Nhiều suất học bổng cho sinh viên có thành tích tốt.', 'en' => 'Many scholarships for high-performing students.'],
                'content' => ['vi' => '<p>Học bổng từ các doanh nghiệp công nghệ đối tác của khoa.</p>', 'en' => '<p>Scholarships from technology partners of the faculty.</p>'],
                'status' => 'published',
                'is_featured' => false,
                'published_at' => now()->subDays(8),
                'category_id' => $pickCategoryId(0),
            ],
            [
                'title' => ['vi' => 'Seminar an toàn thông tin cho sinh viên năm cuối', 'en' => 'Cybersecurity seminar for final-year students'],
                'excerpt' => ['vi' => 'Chuyên gia chia sẻ kỹ năng thực chiến an toàn thông tin.', 'en' => 'Experts share practical cybersecurity skills.'],
                'content' => ['vi' => '<p>Seminar tập trung vào pentest cơ bản và phòng thủ hệ thống.</p>', 'en' => '<p>The seminar focuses on basic pentesting and system defense.</p>'],
                'status' => 'published',
                'is_featured' => false,
                'published_at' => now()->subDays(9),
                'category_id' => $pickCategoryId(1),
            ],
            [
                'title' => ['vi' => 'Thông báo nghỉ lễ giỗ tổ Hùng Vương', 'en' => 'Hung Kings Commemoration Day holiday notice'],
                'excerpt' => ['vi' => 'Lịch nghỉ và lịch học bù dành cho sinh viên toàn khoa.', 'en' => 'Holiday and make-up class schedule for all students.'],
                'content' => ['vi' => '<p>Sinh viên theo dõi lịch học bù trên cổng thông tin.</p>', 'en' => ''],
                'status' => 'published',
                'is_featured' => false,
                'published_at' => now()->subDays(10),
                'category_id' => $pickCategoryId(2),
            ],
            [
                'title' => ['vi' => 'Cập nhật kế hoạch thực tập doanh nghiệp', 'en' => 'Updated internship plan with partner companies'],
                'excerpt' => ['vi' => 'Sinh viên năm cuối chuẩn bị hồ sơ thực tập.', 'en' => 'Final-year students prepare internship applications.'],
                'content' => ['vi' => '<p>Kế hoạch thực tập được điều chỉnh theo lịch doanh nghiệp.</p>', 'en' => ''],
                'status' => 'published',
                'is_featured' => false,
                'published_at' => now()->subDays(11),
                'category_id' => $pickCategoryId(0),
            ],
            [
                'title' => ['vi' => 'Dự thảo chương trình đào tạo mới ngành CNTT', 'en' => 'Draft of the new IT curriculum'],
                'excerpt' => ['vi' => 'Lấy ý kiến đóng góp từ giảng viên và sinh viên.', 'en' => 'Collecting feedback from lecturers and students.'],
                'content' => ['vi' => '<p>Nhà trường công bố bản dự thảo để lấy ý kiến trong 2 tuần.</p>', 'en' => '<p>The draft is published for a 2-week feedback period.</p>'],
                'status' => 'draft',
                'is_featured' => false,
                'published_at' => null,
                'category_id' => $pickCategoryId(1),
            ],
            [
                'title' => ['vi' => 'Thông báo cũ: lịch thi học kỳ I năm 2024', 'en' => 'Old notice: semester I exam schedule 2024'],
                'excerpt' => ['vi' => 'Nội dung lưu trữ để tra cứu.', 'en' => 'Archived content for reference.'],
                'content' => ['vi' => '<p>Bài viết đã lưu trữ.</p>', 'en' => '<p>This post is archived.</p>'],
                'status' => 'archived',
                'is_featured' => false,
                'published_at' => now()->subYear(),
                'category_id' => $pickCategoryId(2),
            ],
        ];

        foreach ($posts as $postData) {
            $titleVi = $postData['title']['vi'] ?? '';
            $titleEn = $postData['title']['en'] ?? '';

            $baseSlug = Str::slug($titleVi !== '' ? $titleVi : $titleEn);
            $slug = $baseSlug !== '' ? $baseSlug : Str::random(12);

            Post::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $postData['title'],
                    'content' => $postData['content'],
                    'excerpt' => $postData['excerpt'],
                    'slug_translations' => [
                        'vi' => Str::slug($titleVi) ?: $slug,
                        'en' => Str::slug($titleEn),
                    ],
                    'category_id' => $postData['category_id'],
                    'user_id' => $user->id,
                    'status' => $postData['status'],
                    'is_featured' => $postData['is_featured'],
                    'published_at' => $postData['published_at'],
                    'views' => rand(60, 3500),
                ]
            );
        }

        $this->command->info('Posts seeded successfully!');
    }
}

