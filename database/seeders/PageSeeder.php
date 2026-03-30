<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'chan-trang',
                'layout' => 'footer_page',
                'content_data' => [
                    'en' => [
                        'contact' => [
                            'email' => 'cntt@vnua.edu.vn',
                            'phone' => '(024) 62617701 – Fax: (024) 38276554',
                            'address' => 'P316, 3rd Floor, Administration Building, Vietnam Academy of Agriculture'
                        ],
                        'socials' => [
                            ['id' => 'C4st4Go2', 'url' => 'https://www.facebook.com/FITA.VNUA', 'icon' => 'facebook', 'name' => 'Fanpage'],
                            ['id' => 'WqaiI8hM', 'url' => 'https://www.instagram.com/fita-vnua/', 'icon' => 'instagram', 'name' => 'Instagram'],
                            ['id' => 'ELhcSRnc', 'url' => 'https://www.youtube.com/channel/UC_O9ofPYoZ_zYvWuE8ITMeg', 'icon' => 'youtube', 'name' => 'YouTube']
                        ],
                        'quick_links' => [
                            ['id' => '8mBsdFPc', 'url' => 'http://127.0.0.1:8000/bai-viet?danh-muc=tin-tuc', 'name' => 'News'],
                            ['id' => 'Mtj2rIcz', 'url' => 'http://127.0.0.1:8000/gioi-thieu', 'name' => 'Introduction'],
                            ['id' => 'pILkQva0', 'url' => 'http://127.0.0.1:8000/giang-vien', 'name' => 'Lecturers - Staff']
                        ]
                    ],
                    'vi' => [
                        'contact' => [
                            'email' => 'cntt@vnua.edu.vn',
                            'phone' => '(024) 62617701 – Fax: (024) 38276554',
                            'address' => 'P316, Tầng 3 Nhà Hành chính, Học viện Nông nghiệp Việt Nam'
                        ],
                        'socials' => [
                            ['id' => 'C4st4Go2', 'url' => 'https://www.facebook.com/FITA.VNUA', 'icon' => 'facebook', 'name' => 'Fanpage'],
                            ['id' => 'WqaiI8hM', 'url' => 'https://www.instagram.com/fita-vnua/', 'icon' => 'instagram', 'name' => 'Instagram'],
                            ['id' => 'ELhcSRnc', 'url' => 'https://www.youtube.com/channel/UC_O9ofPYoZ_zYvWuE8ITMeg', 'icon' => 'youtube', 'name' => 'Youtube']
                        ],
                        'quick_links' => [
                            ['id' => '8mBsdFPc', 'url' => 'http://127.0.0.1:8000/bai-viet?danh-muc=tin-tuc', 'name' => 'Tin tức'],
                            ['id' => 'Mtj2rIcz', 'url' => 'http://127.0.0.1:8000/gioi-thieu', 'name' => 'Giới thiệu chung'],
                            ['id' => 'pILkQva0', 'url' => 'http://127.0.0.1:8000/giang-vien', 'name' => 'Giảng viên - Cán bộ']
                        ]
                    ]
                ],
            ],
            [
                'slug' => 'gioi-thieu',
                'layout' => 'introduction_page',
                'content_data' => [
                    'en' => [
                        'dynamicBlocks' => [
                            [
                                'id' => 'cp4vXg1e',
                                'type' => 'generalIntroduction',
                                'data' => [
                                    'photo' => '/storage/uploads/pages/k1vdfR6ZoSwe0aBLGDjgqDWAG82WrFGZ0GgWwHV5.jpg',
                                    'description' => "The Faculty of Information Technology was newly established on October 10, 2005, according to Decision No. 839/QD-NNI of the Rector, comprising 5 departments with 70 staff members and nearly 600 students. However, the undergraduate program in Informatics had already been implemented since January 25, 2002, according to Decision No. 439/QD/BGD&ĐT-ĐH of the Minister of Education and Training, to meet the demand for IT human resources for the industrialization and modernization of the country in general and for the modernization of agriculture in particular.\n\nThe Faculty currently comprises 5 Departments (Software Technology, Computer Science, Mathematics, Applied Mathematics and Informatics, Physics) and 1 Office Unit. Some departments have a long tradition, such as the Mathematics and Physics departments, established since the founding of the university, and the Software Technology and Computer Science departments, which developed from the Information Technology Center established in the early 1980s."
                                ]
                            ],
                            [
                                'id' => '2nH0NTsp',
                                'type' => 'block3Columns',
                                'data' => [
                                    ['title' => 'VISION', 'content' => 'To become a highly reputable training institution in the country and the region for training high-quality human resources, conducting scientific research, applying knowledge, and developing technology in the fields of computer science, information technology, artificial intelligence, communication, and big data to serve the development of agriculture, farmers, and rural areas, contributing to the industrialization and modernization of the country.'],
                                    ['title' => 'MISSION', 'content' => 'Training and providing high-quality human resources, conducting scientific research, developing technology, transferring knowledge and new products in computer science, information technology, artificial intelligence, communications, and big data. Simultaneously, providing high-quality human resources capable of applying information technology, artificial intelligence, communications, and big data in agriculture and rural development, making a significant and effective contribution to the development of agriculture, farmers, rural areas, and the country\'s increasingly deep international integration.'],
                                    ['title' => 'PHILOSOPHY OF EDUCATION', 'content' => '“Professionalism – Creativity – Integration – Responsibility” aims to train human resources with strong professional skills, dynamism, and creativity in their work, meeting practical requirements and international integration, and being responsible to themselves, their families, and society. The educational goals of research-oriented universities are not only to access advanced knowledge and technology but also to enhance the capacity for creating new knowledge and technology, orienting the application of technology for the benefit of humanity and sustainable development, contributing to the formation of a new generation of citizens with the capacity and responsibility to serve society.']
                                ]
                            ],
                            [
                                'id' => 'znSuMO7X',
                                'type' => 'blockSingle',
                                'data' => [
                                    'title' => 'STRATEGIC OBJECTIVES',
                                    'description' => "Strategic Goals for 2030 and Vision for 2050:\n\n– Flexible training programs combining research-oriented and career-oriented training to serve societal needs, establishing a reputation as a highly reputable training institution in applied information technology in agriculture and rural development in Vietnam.\n\n– A dedicated and highly skilled staff with strong research capabilities and modern facilities, striving to become a center for research, technology transfer, and information technology services in agriculture and rural development by 2030.\n\n– An ideal working and learning environment for staff, lecturers, and students.\n\n– Domestic and international cooperation, strengthening communication and promotion, and establishing the brand.\n\n– Prioritizing research and development of intelligent systems and high-tech information technology applications in agriculture and rural development."
                                ]
                            ],
                            [
                                'id' => 'OQNmYnFW',
                                'type' => 'blockSingle',
                                'data' => [
                                    'title' => 'CORE VALUES',
                                    'description' => "The Faculty of Information Technology constantly strives to create \"difference and uniqueness\":\n\n– Unity: \"Strong unity, continuous effort for ever-improving progress.\"\n\n– Responsibility: Responsibility, dedication, and selfless commitment are the noble values ​​of generations of staff at the Faculty of Information Technology, Vietnam Academy of Agriculture.\n\n– Integration: International integration to access regional and global higher education standards, cooperation between the Academy, Faculty, and businesses to meet practical requirements.\n\n– Innovation: Innovation based on absorbing the best of human knowledge, inheriting achievements, and promoting good traditional values ​​to achieve high quality in training and scientific research.\n\n– Quality: High quality is the goal, the driving force, and the core element that makes up the brand of the Faculty of Information Technology – Vietnam Academy of Agriculture."
                                ]
                            ]
                        ]
                    ],
                    'vi' => [
                        'dynamicBlocks' => [
                            [
                                'id' => 'cp4vXg1e',
                                'type' => 'generalIntroduction',
                                'data' => [
                                    'photo' => '/storage/uploads/pages/k1vdfR6ZoSwe0aBLGDjgqDWAG82WrFGZ0GgWwHV5.jpg',
                                    'description' => "Khoa Công nghệ thông tin mới được thành lập từ 10/10/2005 theo QĐ số 839/QĐ – NNI của Hiệu trưởng, gồm 5 Bộ môn với 70 CBCNV và gần 600 sinh viên. Tuy vậy, Chương trình đào tạo đại học ngành Tin học đã được triển khai từ 25/1/2002 theo QĐ số 439/ QĐ/BGD&ĐT-ĐH của Bộ trưởng Bộ giáo dục và Đào tạo nhằm đáp ứng yêu cầu đào tạo nhân lực CNTT cho công cuộc Công nghiệp hóa, hiện đại hóa đất nước nói chung và cho lĩnh vực Hiện đại hóa Nông nghiệp nói riêng.\nKhoa hiện nay bao gồm 05 Bộ môn (Công nghệ phần mềm, Khoa học máy tính, Toán, Toán-Tin ứng dụng, Vật lý) và 01 Tổ Văn phòng, trong đó có một số bộ môn của Khoa đã có bề dày truyền thống như các Bộ môn Toán và Vật lý được thành lập từ ngày thành lập trường, và bộ môn CNPM và KHMT được phát triển từ Trung tâm Tin học thành lập từ đầu những năm 1980.\nNhững thành tựu của khoa Công nghệ Thông tin trong 20 năm qua là minh chứng rõ nét cho tinh thần đoàn kết, sáng tạo và nỗ lực không ngừng của toàn thể cán bộ, giảng viên và sinh viên. Chúng ta tự hào về quá khứ và tin tưởng vào tương lai, tiếp tục góp phần xây dựng nền nông nghiệp số, hiện đại, bền vững và hội nhập quốc tế."
                                ]
                            ],
                            [
                                'id' => '2nH0NTsp',
                                'type' => 'block3Columns',
                                'data' => [
                                    ['title' => 'TẦM NHÌN', 'content' => 'Trở thành một cơ sở đào tạo có uy tín cao trong nước và khu vực về đào tạo nguồn nhân lực có chất lượng cao, NCKH, ứng dụng tri thức và phát triển công nghệ trong lĩnh vực khoa học máy tính, CNTT, trí tuệ nhân tạo, truyền thông và dữ liệu lớn phục vụ công cuộc phát triển nông nghiệp, nông dân, nông thôn góp phần vào sự nghiệp Công nghiệp hóa – Hiện đại hóa đất nước.'],
                                    ['title' => 'SỨ MẠNG', 'content' => 'Đào tạo và cung cấp nguồn nhân lực chất lượng cao, NCKH, phát triển công nghệ, chuyển giao tri thức, sản phẩm mới về khoa học máy tính, CNTT, trí tuệ nhân tạo, truyền thông và dữ liệu lớn. Đồng thời, cung cấp nguồn nhân lực chất lượng cao để có thể ứng dụng CNTT, trí tuệ nhân tạo, truyền thông và dữ liệu lớn trong nông nghiệp & phát triển nông thôn, đóng góp đắc lực và hiệu quả vào sự nghiệp phát triển nông nghiệp, nông dân, nông thôn và hội nhập quốc tế ngày càng sâu rộng của đất nước'],
                                    ['title' => 'TRIẾT LÝ GIÁO DỤC', 'content' => '“Chuyên nghiệp – Sáng tạo – Hội nhập – Trách nhiệm” hướng đến mục tiêu đào tạo nguồn nhân lực có năng lực chuyên môn tốt và chuyên nghiệp, năng động và sáng tạo trong công việc, đáp ứng yêu cầu thực tiễn và hội nhập quốc tế, có trách nhiệm với bản thân, gia đình và xã hội. Mục tiêu giáo dục của đại học định hướng nghiên cứu không chỉ là tiếp cận tri thức và công nghệ tiên tiến mà còn nâng cao năng lực sáng tạo tri thức và công nghệ mới, định hướng áp dụng công nghệ vị nhân sinh và phát triển bền vững, góp phần hình thành thế hệ công dân mới có năng lực và trách nhiệm phụng sự xã hội.']
                                ]
                            ],
                            [
                                'id' => 'znSuMO7X',
                                'type' => 'blockSingle',
                                'data' => [
                                    'title' => 'MỤC TIÊU CHIẾN LƯỢC',
                                    'description' => "Mục tiêu chiến lược đến năm 2030 và tầm nhìn đến năm 2050:\n\n– Chương trình đào tạo linh hoạt giữa đào tạo theo định hướng nghiên cứu và định hướng nghề nghiệp phục vụ nhu cầu xã hội, tạo danh tiếng của cơ sở đào tạo có uy tín cao về công nghệ thông tin ứng dụng trong nông nghiệp và phát triển nông thôn của Việt Nam.\n\n– Đội ngũ cán bộ tâm huyết, giỏi chuyên môn, nghiệp vụ, năng lực nghiên cứu, cơ sở vật chất hiện đại, phấn đấu trở thành trung tâm nghiên cứu chuyển giao tiến bộ khoa học kỹ thuật, dịch vụ công nghệ thông tin trong nông nghiệp và phát triển nông thôn vào năm 2030.\n\n– Môi trường làm việc, học tập lý tưởng cho cán bộ, giảng viên và sinh viên.\n\n– Hợp tác trong nước và quốc tế, đẩy mạnh truyền thông, quảng bá, khẳng định thương hiệu.\n\n– Ưu tiên nghiên cứu phát triển các hệ thống thông minh và các ứng dụng công nghệ thông tin công nghệ cao trong nông nghiệp và phát triển nông thôn."
                                ]
                            ],
                            [
                                'id' => 'OQNmYnFW',
                                'type' => 'blockSingle',
                                'data' => [
                                    'title' => 'GIÁ TRỊ CỐT LÕI',
                                    'description' => "Khoa Công nghệ thông tin không ngừng phấn đấu để kiến tạo nên “sự khác biệt, đặc trưng”:\n\n– Đoàn kết: “Đoàn kết chặt chẽ, cố gắng không ngừng để tiến bộ mãi”.\n\n– Trách nhiệm: Trách nhiệm, tận tâm và cống hiến hết mình là giá trị cao quý của các thế hệ cán bộ Khoa Công nghệ thông tin, Học viện Nông nghiệp Việt Nam.\n\n– Hội nhập: Hội nhập quốc tế để tiếp cận chuẩn mực giáo dục đại học khu vực và thế giới, hợp tác Học viện – Khoa – Doanh nghiệp để đáp ứng yêu cầu thực tiễn.\n\n– Sáng tạo: Đổi mới sáng tạo dựa trên tiếp thu những tinh hoa tri thức của nhân loại, kế thừa những thành quả đã đạt được và phát huy những giá trị truyền thống tốt đẹp nhằm đạt được chất lượng cao trong đào tạo và nghiên cứu khoa học.\n\n– Chất lượng: Chất lượng cao là mục tiêu, là động lực phấn đấu, là yếu tố cốt lõi làm nên thương hiệu Khoa Công nghệ thông tin – Học viện Nông nghiệp Việt Nam."
                                ]
                            ]
                        ]
                    ]
                ],
            ],
        ];

        foreach ($pages as $page) {
            \App\Models\Page::query()->updateOrCreate(
                ['slug' => $page['slug']],
                ['layout' => $page['layout'], 'content_data' => $page['content_data']],
            );
        }
    }
}
