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
                'title' => ['vi' => 'Học viện Nông nghiệp Việt Nam lần đầu góp mặt trong bảng xếp hạng QS theo ngành 2026.', 'en' => 'Vietnam National University of Agriculture appears in the QS World University Rankings by Subject 2026 for the first time.'],
                'excerpt' => ['vi' => 'Lần đầu tiên, Học viện Nông nghiệp Việt Nam(VNUA) chính thức góp mặt trong bảng xếp hạng QS World University Rankings by Subject 2026 – một trong những bảng xếp hạng đại học uy tín hàng đầu thế giới.', 'en' => 'For the first time, Vietnam National University of Agriculture (VNUA) has officially been included in the QS World University Rankings by Subject 2026 — one of the world’s most prestigious university ranking systems.'],
                'content' => ['vi' => '<p style=\"text-align: justify;\">Lần đầu ti&ecirc;n,&nbsp;Học viện N&ocirc;ng nghiệp Việt Nam(VNUA) ch&iacute;nh thức g&oacute;p mặt trong bảng xếp hạng&nbsp;QS World University Rankings by Subject 2026&nbsp;&ndash;&nbsp;một trong những bảng xếp hạng đại học uy t&iacute;n h&agrave;ng đầu thế giới.&nbsp;Sự kiện n&agrave;y đ&aacute;nh dấu bước tiến quan trọng trong qu&aacute; tr&igrave;nh hội nhập quốc tế của Học viện, đồng thời khẳng định chất lượng đ&agrave;o tạo v&agrave; nghi&ecirc;n cứu trong lĩnh vực n&ocirc;ng nghiệp &ndash;&nbsp;thế mạnh cốt l&otilde;i của&nbsp;Học viện.</p>\n<p style=\"text-align: justify;\">Theo kết quả c&ocirc;ng bố ng&agrave;y 25/3, Việt Nam c&oacute; 13 cơ sở gi&aacute;o dục đại học được xếp hạng theo ng&agrave;nh, trong đ&oacute;,&nbsp;Học viện N&ocirc;ng nghiệp Việt Nam l&agrave; một trong 4 đại diện lần đầu g&oacute;p mặt.</p>\n<table id=\"undefined\" class=\"imgEditor\" style=\"width: 99.0064%;\" border=\"0\">\n<tbody>\n<tr>\n<td style=\"width: 100%;\"><img class=\"imgtelerik aligncenter\" style=\"display: block; margin-left: auto; margin-right: auto;\" src=\"https://file.vnua.edu.vn/data/0/images/2026/03/26/host/img-0906.jpeg?w=680\" alt=\"\"></td>\n</tr>\n<tr class=\"alt_imgEditor\">\n<td style=\"width: 100%;\"><em>(Nguồn:&nbsp;Vnexpress)&nbsp;</em></td>\n</tr>\n</tbody>\n</table>\n<p style=\"text-align: justify;\">Đ&aacute;ng ch&uacute; &yacute;,&nbsp;Học viện N&ocirc;ng nghiệp Việt Nam&nbsp;được xếp hạng trong lĩnh vực N&ocirc;ng, L&acirc;m nghiệp (Agriculture &amp; Forestry) ở&nbsp;nh&oacute;m 301-350 thế giới,&nbsp;một kết quả rất t&iacute;ch cực đối với lần đầu tham gia bảng xếp hạng uy t&iacute;n n&agrave;y.</p>\n<p style=\"text-align: justify;\">Bảng xếp hạng của QS được x&acirc;y dựng dựa tr&ecirc;n nhiều ti&ecirc;u ch&iacute; như uy t&iacute;n học thuật, đ&aacute;nh gi&aacute; của nh&agrave; tuyển dụng, năng suất nghi&ecirc;n cứu v&agrave; mức độ quốc tế h&oacute;a.&nbsp;Việc lọt v&agrave;o bảng xếp hạng QS theo ng&agrave;nh kh&ocirc;ng chỉ l&agrave; sự ghi nhận về chất lượng đ&agrave;o tạo, m&agrave; c&ograve;n phản &aacute;nh năng lực nghi&ecirc;n cứu v&agrave; đ&oacute;ng g&oacute;p của Học viện trong lĩnh vực n&ocirc;ng nghiệp &ndash;&nbsp;một ng&agrave;nh trụ cột của Việt Nam.&nbsp;Đ&acirc;y l&agrave; th&agrave;nh t&iacute;ch đ&aacute;ng ghi nhận, đặc biệt trong bối cảnh Học viện lần đầu tham gia bảng xếp hạng. Th&agrave;nh tựu n&agrave;y cho thấy năng lực nghi&ecirc;n cứu, giảng dạy v&agrave; mức độ ảnh hưởng học thuật của Học viện đang từng bước tiệm cận chuẩn mực quốc tế.</p>\n<p style=\"text-align: justify;\">Trong nhiều năm qua, Học viện N&ocirc;ng nghiệp Việt Nam đ&atilde; kh&ocirc;ng ngừng n&acirc;ng cao chất lượng giảng dạy, đẩy mạnh c&ocirc;ng bố quốc tế, tăng cường hợp t&aacute;c to&agrave;n cầu v&agrave; ứng dụng khoa học c&ocirc;ng nghệ v&agrave;o thực tiễn sản xuất n&ocirc;ng nghiệp. Đ&acirc;y ch&iacute;nh l&agrave; nền tảng quan trọng gi&uacute;p Học viện đạt được bước tiến mang t&iacute;nh đột ph&aacute; n&agrave;y.</p>\n<p style=\"text-align: justify;\">Việc được&nbsp;g&oacute;p mặt&nbsp;trong bảng xếp hạng QS&nbsp;mang lại nhiều cơ hội quan trọng cho Học viện như n&acirc;ng cao uy t&iacute;n học thuật tr&ecirc;n trường quốc tế;&nbsp;thu h&uacute;t sinh vi&ecirc;n v&agrave; giảng vi&ecirc;n chất lượng cao;&nbsp;mở rộng hợp t&aacute;c nghi&ecirc;n cứu v&agrave; chuyển giao c&ocirc;ng nghệ.</p>\n<p style=\"text-align: justify;\">Việc lần đầu ti&ecirc;n được vinh danh trong bảng xếp hạng QS theo ng&agrave;nh năm 2026 kh&ocirc;ng chỉ l&agrave; th&agrave;nh tựu đ&aacute;ng tự h&agrave;o, m&agrave; c&ograve;n l&agrave; động lực để Học viện N&ocirc;ng nghiệp Việt Nam tiếp tục đổi mới, n&acirc;ng cao chất lượng đ&agrave;o tạo v&agrave; nghi&ecirc;n cứu, hướng tới mục ti&ecirc;u trở th&agrave;nh trường đại học h&agrave;ng đầu khu vực trong lĩnh vực n&ocirc;ng nghiệp v&agrave; ph&aacute;t triển bền vững.</p>\n<table id=\"undefined\" class=\"imgEditor\" style=\"width: 99.5435%;\" border=\"0\">\n<tbody>\n<tr>\n<td style=\"width: 100%;\"><img class=\"imgtelerik aligncenter\" style=\"display: block; margin-left: auto; margin-right: auto;\" src=\"https://file.vnua.edu.vn/data/0/images/2026/03/26/host/img-0903.jpeg?w=680\" alt=\"\"></td>\n</tr>\n<tr class=\"alt_imgEditor\">\n<td style=\"width: 100%;\">&nbsp;</td>\n</tr>\n</tbody>\n</table>\n<p style=\"text-align: right;\"><strong>&nbsp;Đ&agrave;o Hương &ndash; Nh&agrave; xuất bản HVNN</strong></p>', 'en' => '<p style=\"text-align: justify;\" data-start=\"0\" data-end=\"413\">For the first time, Vietnam National University of Agriculture (VNUA) has officially been included in the QS World University Rankings by Subject 2026 &mdash; one of the world&rsquo;s most prestigious university ranking systems. This milestone marks an important step in the Academy&rsquo;s international integration process, while also affirming the quality of its education and research in agriculture &mdash; its core strength.</p>\n<p style=\"text-align: justify;\" data-start=\"415\" data-end=\"645\" data-is-last-node=\"\" data-is-only-node=\"\">According to the results announced on March 25, Vietnam has 13 higher education institutions ranked by subject, with Vietnam National University of Agriculture being one of four representatives appearing</p>\n<table id=\"undefined\" class=\"imgEditor\" style=\"width: 99.0064%;\" border=\"0\">\n<tbody>\n<tr>\n<td style=\"width: 100%;\"><img class=\"imgtelerik aligncenter\" style=\"display: block; margin-left: auto; margin-right: auto;\" src=\"https://file.vnua.edu.vn/data/0/images/2026/03/26/host/img-0906.jpeg?w=680\" alt=\"\"></td>\n</tr>\n<tr class=\"alt_imgEditor\">\n<td style=\"width: 100%;\"><em>(Nguồn:&nbsp;Vnexpress)&nbsp;</em></td>\n</tr>\n</tbody>\n</table>\n<div class=\"flex flex-col text-sm pb-25\" style=\"text-align: justify;\">\n<section class=\"text-token-text-primary w-full focus:outline-none [--shadow-height:45px] has-data-writing-block:pointer-events-none has-data-writing-block:-mt-(--shadow-height) has-data-writing-block:pt-(--shadow-height) [&amp;:has([data-writing-block])&gt;*]:pointer-events-auto scroll-mt-[calc(var(--header-height)+min(200px,max(70px,20svh)))]\" dir=\"auto\" data-turn-id=\"request-WEB:a306a2a3-5f61-4e96-b6d4-651af60dfc1d-5\" data-testid=\"conversation-turn-12\" data-scroll-anchor=\"true\" data-turn=\"assistant\">\n<div class=\"text-base my-auto mx-auto pb-10 [--thread-content-margin:var(--thread-content-margin-xs,calc(var(--spacing)*4))] @w-sm/main:[--thread-content-margin:var(--thread-content-margin-sm,calc(var(--spacing)*6))] @w-lg/main:[--thread-content-margin:var(--thread-content-margin-lg,calc(var(--spacing)*16))] px-(--thread-content-margin)\">\n<div class=\"[--thread-content-max-width:40rem] @w-lg/main:[--thread-content-max-width:48rem] mx-auto max-w-(--thread-content-max-width) flex-1 group/turn-messages focus-visible:outline-hidden relative flex w-full min-w-0 flex-col agent-turn\">\n<div class=\"flex max-w-full flex-col gap-4 grow\">\n<div class=\"min-h-8 text-message relative flex w-full flex-col items-end gap-2 text-start break-words whitespace-normal outline-none keyboard-focused:focus-ring [.text-message+&amp;]:mt-1\" dir=\"auto\" tabindex=\"0\" data-message-author-role=\"assistant\" data-message-id=\"feaaf0bb-319c-4447-9baf-b8307780d67b\" data-message-model-slug=\"gpt-5-3\" data-turn-start-message=\"true\">\n<div class=\"flex w-full flex-col gap-1 empty:hidden\">\n<div class=\"markdown prose dark:prose-invert w-full wrap-break-word dark markdown-new-styling\">\n<p data-start=\"0\" data-end=\"232\">Notably, Vietnam National University of Agriculture has been ranked in the field of Agriculture &amp; Forestry, placed in the 301&ndash;350 band globally &mdash; a very positive result for its first participation in this prestigious ranking.</p>\n<p data-start=\"234\" data-end=\"877\">The QS rankings are built on multiple criteria, including academic reputation, employer reputation, research productivity, and internationalization. Being included in the QS World University Rankings by Subject is not only a recognition of educational quality but also reflects the Academy&rsquo;s research capacity and contributions to agriculture &mdash; a key sector of Vietnam&rsquo;s economy. This is a remarkable achievement, especially given that this is the Academy&rsquo;s first time participating in the rankings. It demonstrates that its research capability, teaching quality, and academic influence are steadily approaching international standards.</p>\n<p data-start=\"879\" data-end=\"1204\">Over the years, Vietnam National University of Agriculture has continuously improved its teaching quality, promoted international publications, strengthened global partnerships, and applied science and technology to agricultural production. These efforts have laid a solid foundation for this breakthrough achievement.</p>\n<p data-start=\"1206\" data-end=\"1462\">Being listed in the QS rankings brings many important opportunities for the Academy, such as enhancing its international academic reputation, attracting high-quality students and faculty, and expanding research collaboration and technology transfer.</p>\n<p data-start=\"1464\" data-end=\"1831\" data-is-last-node=\"\" data-is-only-node=\"\">This first-time recognition in the QS World University Rankings by Subject 2026 is not only a source of pride but also a strong motivation for Vietnam National University of Agriculture to continue innovating, improving the quality of education and research, and striving to become a leading university in the region in agriculture and sustainable development.</p>\n</div>\n</div>\n</div>\n</div>\n<div class=\"mt-3 w-full empty:hidden\">&nbsp;</div>\n</div>\n</div>\n</section>\n</div>\n<table id=\"undefined\" class=\"imgEditor\" style=\"width: 99.5435%;\" border=\"0\">\n<tbody>\n<tr>\n<td style=\"width: 100%;\"><img class=\"imgtelerik aligncenter\" style=\"display: block; margin-left: auto; margin-right: auto;\" src=\"https://file.vnua.edu.vn/data/0/images/2026/03/26/host/img-0903.jpeg?w=680\" alt=\"\"></td>\n</tr>\n<tr class=\"alt_imgEditor\">\n<td style=\"width: 100%;\">&nbsp;</td>\n</tr>\n</tbody>\n</table>\n<p style=\"text-align: right;\"><strong>&nbsp;Đ&agrave;o Hương &ndash; Nh&agrave; xuất bản HVNN</strong></p>'],
                'status' => 'published',
                'thumbnail' => 'uploads/posts/hXlix4qXqpbCB5337qFXxFXIIgEs1dIH229xI5OZ.jpg',
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

