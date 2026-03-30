<?php

namespace Database\Seeders;

use App\Models\GroupSubject;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $groups = GroupSubject::query()->get()->keyBy('sort_order');

        $subjects = [
            // --- NHÓM 1: ĐẠI CƯƠNG & KỸ NĂNG (G=1) ---
            ['code' => 'ML01020', 'vi' => 'Triết học Mác - Lênin', 'en' => 'Philosophy of Marxism and Leninism', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 1],
            ['code' => 'TH01009', 'vi' => 'Tin học đại cương', 'en' => 'Basics of informatics', 'tc' => 2, 'lt' => 1.5, 'th' => 0.5, 'g' => 1],
            ['code' => 'TH01002', 'vi' => 'Vật lý đại cương A', 'en' => 'Physics for informatics', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 1],
            ['code' => 'TH01029', 'vi' => 'Cơ sở Vật lý cho tin học', 'en' => 'The physical foundations for computer science.', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 1],
            ['code' => 'TH01006', 'vi' => 'Đại số tuyến tính', 'en' => 'Linear algebra', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 1],
            ['code' => 'TH01024', 'vi' => 'Toán giải tích', 'en' => 'Calculus', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 1],
            ['code' => 'ML01009', 'vi' => 'Pháp luật đại cương', 'en' => 'Introduction to laws', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'SN00010', 'vi' => 'Tiếng Anh bổ trợ', 'en' => 'An Introduction to Cefr - Based Tests', 'tc' => 1, 'lt' => 1, 'th' => 0, 'g' => 1],
            ['code' => 'GT01016', 'vi' => 'Giáo dục thể chất đại cương', 'en' => 'General physical education', 'tc' => 1, 'lt' => 0.5, 'th' => 0.5, 'g' => 1],
            ['code' => 'QS01011,QS01012', 'vi' => 'Giáo dục quốc phòng 1, 2', 'en' => 'National defense education 1, 2', 'tc' => 5, 'lt' => 5, 'th' => 0, 'g' => 1],
            ['code' => 'QS01013,QS01014', 'vi' => 'Giáo dục quốc phòng 3, 4', 'en' => 'National defense education 3, 4', 'tc' => 6, 'lt' => 6, 'th' => 0, 'g' => 1],
            ['code' => 'SN00011', 'vi' => 'Tiếng Anh 0', 'en' => 'English 0', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'ML01021', 'vi' => 'Kinh tế chính trị Mác - Lênin', 'en' => 'Political economy of Marxism and Leninism', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'SN01032', 'vi' => 'Tiếng Anh 1', 'en' => 'English 1', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 1],
            ['code' => 'SN01033', 'vi' => 'Tiếng Anh 2', 'en' => 'English 2', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 1],
            ['code' => 'ML01022', 'vi' => 'Chủ nghĩa xã hội khoa học', 'en' => 'Socialism', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'ML01005', 'vi' => 'Tư tưởng Hồ Chí Minh', 'en' => 'Ho Chi Minh Idcology', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'ML01023', 'vi' => 'Lịch sử Đảng Cộng sản Việt Nam', 'en' => 'Vietnamese Communist Party History', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'MT01001', 'vi' => 'Hóa học đại cương', 'en' => 'General chemistry', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'SN01016', 'vi' => 'Tâm lý học đại cương', 'en' => 'Introduction to Psychology', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'TH01025', 'vi' => 'Phương pháp tính', 'en' => 'Numerical method', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'TH02032', 'vi' => 'Phân tích số liệu', 'en' => 'Data Analysis', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'KT01020', 'vi' => 'Nguyên lý và kỹ năng khởi nghiệp', 'en' => 'Principles and skills ofenterpreneurship', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'Chọn 2/9 học phần GDTC: GT01017, GT01018,GT01019, GT01020,GT01021, GT01022,GT01023, GT01014,GT01015', 'vi' => 'Giáo dục thể chất (Chọn 02 trong 09 HP: Điền kinh, Thể dục Aerobic, Bóng đá, Bóng ền, Bóng rổ. Cầu lông, Cờ vua, Khiêu vũ thể thao, Bơi)', 'en' => 'Athletics, Thletics Aerobic, Gymnastics, Football, Volleyball, Basketball, Badminton, Chess, Dance Sport, Swimming', 'tc' => 1, 'lt' => 0, 'th' => 1, 'g' => 1],
            ['code' => 'KN01001/ KN01002/ KN01003/ KN01004/ KN01005/ KN01006/ KN01007/ KN01008/ KN01009/ KN01010/', 'vi' => 'Kỹ năng mềm: 90 tiết (Chọn 3 trong 10 học phần, mỗi học phần 30 tiết: Kỹ năng giao tiếp, Kỹ năng lãnh đạo, Kỹ năng quản lý bản thân, Kỹ năng tìm kiếm việc làm, Kỹ năng làm việc nhóm, Kỹnăng hội nhập quốc tế, Kỹnăng khởi nghiệp, Kỹ năng bán hàng, Kỹ năng thuyết trình, Kỹ năng làm việc với các bên liên quan)', 'en' => 'Athletics, Thletics Aerobic, Gymnastics, Football, Volleyball, Basketball, Badminton, Chess, Dance Sport, Swimming', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],

            // --- NHÓM 2: CƠ SỞ NGÀNH (G=2) ---
            ['code' => 'TH02036', 'vi' => 'Nhập môn Công nghệ phần mềm', 'en' => 'Introduction to Software Engineering', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 2],
            ['code' => 'TH01007', 'vi' => 'Xác suất thống kê', 'en' => 'Probability and Statistics', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 2],
            ['code' => 'TH01023', 'vi' => 'Toán rời rạc', 'en' => 'Discrete mathematics', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 2],
            ['code' => 'TH02044', 'vi' => 'Kiến trúc máy tính', 'en' => 'Computer architectures', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 2],
            ['code' => 'TH02001', 'vi' => 'Cơ sở dữ liệu', 'en' => 'Databases', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 2],
            ['code' => 'TH02045', 'vi' => 'Kỹ thuật lập trình', 'en' => 'Programming techniques', 'tc' => 4, 'lt' => 3, 'th' => 1, 'g' => 2],
            ['code' => 'TH02034', 'vi' => 'Kỹ thuật lập trình', 'en' => 'Programming techniques', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 2],
            ['code' => 'TH02037', 'vi' => 'Phân tích và thiết kế hệ thống', 'en' => 'System analysis and design', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 2],
            ['code' => 'TH02046', 'vi' => 'Cấu trúc dữ liệu và giải thuật', 'en' => 'Data structures and Algorithms', 'tc' => 4, 'lt' => 3, 'th' => 1, 'g' => 2],
            ['code' => 'TH02016', 'vi' => 'Cấu trúc dữ liệu và giải thuật', 'en' => 'Data structures and Algorithms', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 2],
            ['code' => 'TH02035', 'vi' => 'Thực hành cấu trúc dữ liệu và giải thuật', 'en' => 'Practice data structures and Algorithms', 'tc' => 1, 'lt' => 0, 'th' => 1, 'g' => 2],
            ['code' => 'TH03005', 'vi' => 'Hệ quản trị cơ sở dữ liệu', 'en' => 'Database management systems', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 2],
            ['code' => 'TH03107', 'vi' => 'Hệ quản trị cơ sở dữ liệu', 'en' => 'Database management systems', 'tc' => 2, 'lt' => 1, 'th' => 1, 'g' => 2],
            ['code' => 'TH02038', 'vi' => 'Mạng máy tính', 'en' => 'Computer networking', 'tc' => 3, 'lt' => 2.5, 'th' => 0.5, 'g' => 2],
            ['code' => 'TH02015', 'vi' => 'Nguyên lý hệ điều hành', 'en' => 'Principles of operating systems', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 2],
            ['code' => 'TH03106', 'vi' => 'Lập trình hướng đối tượng', 'en' => 'Object-oriented programming', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 2],

            // --- NHÓM 3: CHUYÊN NGÀNH & TỰ CHỌN (G=3) ---
            ['code' => 'TH03206', 'vi' => 'Trí tuệ nhân tạo', 'en' => 'Artificial intelligence', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 3],
            ['code' => 'TH03134', 'vi' => 'Phát triển phần mềm ứng dụng', 'en' => 'Application software development', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03111', 'vi' => 'Lập trình Java', 'en' => 'Java programming', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03133', 'vi' => 'Phát triển ứng dụng web', 'en' => 'Web-based application development', 'tc' => 4, 'lt' => 3, 'th' => 1, 'g' => 3],
            ['code' => 'TH03109', 'vi' => 'Phát triển ứng dụng web', 'en' => 'Web-based application development', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03115', 'vi' => 'Phát triển ứng dụng GIS', 'en' => 'GIS application development', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03324', 'vi' => 'An toàn hệ thống thông tin', 'en' => 'Information systems security', 'tc' => 3, 'lt' => 2.5, 'th' => 0.5, 'g' => 3],
            ['code' => 'TH03224', 'vi' => 'An ninh mạng và hệ điều hành', 'en' => 'Cybersecurity and operating systems', 'tc' => 3, 'lt' => 2.5, 'th' => 0.5, 'g' => 3],
            ['code' => 'TH03101', 'vi' => 'Quản lý dự án phần mềm', 'en' => 'Software Project Management', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 3],
            ['code' => 'TH03102', 'vi' => 'Phân tích yêu cầu phần mềm', 'en' => 'Software Requirements Engineering', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 3],
            ['code' => 'TH03103', 'vi' => 'Kiến trúc và thiết kế phần mềm', 'en' => 'Software Architecture and Design', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 3],
            ['code' => 'TH03137', 'vi' => 'Kiểm thử và đảm bảo chất lượng phần mềm', 'en' => 'Software Testing and Quality Assurance', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03105', 'vi' => 'Kiểm thử và đảm bảo chất lượng phần mềm', 'en' => 'Software Testing and Quality Assurance', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 3],
            ['code' => 'TH03112', 'vi' => 'Phát triển ứng dụng di động', 'en' => 'Mobile application development', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03136', 'vi' => 'Thiết kế giao diện người dùng', 'en' => 'User Interface Design', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 3],
            ['code' => 'TH03216', 'vi' => 'Quản trị mạng (Windows Server)', 'en' => 'Network administration (Windows server)', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03207', 'vi' => 'Học máy', 'en' => 'Machine learning', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 3],
            ['code' => 'TH03316', 'vi' => 'Khai phá dữ liệu trên Python và ứng dụng trong nôngn ghiệp', 'en' => 'Data mining with Python and applications in agriculture', 'tc' => 2, 'lt' => 1.5, 'th' => 0.5, 'g' => 3],
            ['code' => 'TH03507', 'vi' => 'Quản trị mạng 2 (Linux)', 'en' => 'Network administration 2 (Linux)', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03110', 'vi' => 'Phát triển ứng dụng web 2', 'en' => 'Web development 2-based application', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03327', 'vi' => 'Các vấn đề hiện đại của công nghệ thông tin', 'en' => 'Emerging Issues & Trends of Information Technology', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 3],
            ['code' => 'TH03116', 'vi' => 'Thương mại điện tử', 'en' => 'e-commerce', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03325', 'vi' => 'Quản lý dự án CNTT', 'en' => 'IT project management', 'tc' => 2, 'lt' => 1.5, 'th' => 0.5, 'g' => 3],
            ['code' => 'TH03303', 'vi' => 'Thiết kế và quần lý dự án CNTT', 'en' => 'IT Project Design and Management', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 3],
            ['code' => 'TH03328', 'vi' => 'Hệ thống thông tin quản lý', 'en' => 'Management Information Systems', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 3],
            ['code' => 'TH03326', 'vi' => 'Phát triển ứng dụng quản trị doanh nghiệp', 'en' => 'Developing applications for enterprise management', 'tc' => 3, 'lt' => 2.5, 'th' => 0.5, 'g' => 3],
            ['code' => 'TH03117', 'vi' => 'Hệ thống hoạch định nguồn lực nghiệp doanh nghiệp', 'en' => 'Enterprise resource planning system', 'tc' => 3, 'lt' => 2, 'th' => 1, 'g' => 3],
            ['code' => 'TH03201', 'vi' => 'Phân tích và thiết kế hệ thống hướng đối tượng', 'en' => 'Object-oriented analysis and design', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 3],
            ['code' => 'KQ01211', 'vi' => 'Quản trị học', 'en' => 'Principles of Management', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 1],
            ['code' => 'SN03039', 'vi' => 'Tiếng Anh chuyên ngành CNTT & IT', 'en' => 'English for ICT studies', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'MT01008', 'vi' => 'Sinh thái môi trường', 'en' => 'Environmental Ecology', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'PTH03222', 'vi' => 'Ứng dụng CNTT và truyền thông trong quản lý và sản xuất nông nghiệp', 'en' => 'Applications of ICT in agricultural production and management', 'tc' => 2, 'lt' => 1.5, 'th' => 0.5, 'g' => 1],
            ['code' => 'KQ03331', 'vi' => 'Nguyên lý thương mại điện tử', 'en' => 'Principles of e-commerce', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'CD02148', 'vi' => 'Đồ họa kỹ thuật trên máy tính', 'en' => 'Principles of e-commerce', 'tc' => 2, 'lt' => 2, 'th' => 0, 'g' => 1],
            ['code' => 'CD03913', 'vi' => 'Kỹ thuật Robot', 'en' => 'Robotic Engineering', 'tc' => 3, 'lt' => 2.5, 'th' => 0.5, 'g' => 1],
            ['code' => 'KQ02209', 'vi' => 'Quản trị doanh nghiệp', 'en' => 'Corporation Management', 'tc' => 3, 'lt' => 2.5, 'th' => 0.5, 'g' => 1],
            ['code' => 'KT02003', 'vi' => 'Nguyên lý kinh tế', 'en' => 'Priciples of Economics', 'tc' => 3, 'lt' => 3, 'th' => 0, 'g' => 1],

            // --- NHÓM 4: THỰC TẬP & TỐT NGHIỆP (G=4) ---
            ['code' => 'TH03996', 'vi' => 'Thực tập chuyên ngành', 'en' => 'Internship', 'tc' => 10, 'lt' => 0, 'th' => 10, 'g' => 4],
            ['code' => 'TH04996', 'vi' => 'Khóa luận tốt nghiệp', 'en' => 'Graduation thesis', 'tc' => 10, 'lt' => 0, 'th' => 10, 'g' => 4],
        ];

        foreach ($subjects as $s) {
            $group = $groups->get($s['g']);

            Subject::query()->updateOrCreate(
                ['code' => $s['code']],
                [
                    'name' => ['vi' => $s['vi'], 'en' => $s['en']],
                    'credits' => $s['tc'],
                    'credits_theory' => $s['lt'],
                    'credits_practice' => $s['th'],
                    'group_subject_id' => $group?->id,
                    'is_active' => true,
                ]
            );
        }
    }
}
