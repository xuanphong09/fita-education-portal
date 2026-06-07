<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Template cho email thiết lập mật khẩu lần đầu
        EmailTemplate::updateOrCreate(
            ['template_type' => 'first_time_password_setup'],
            [
                'subject' => 'Thiết lập mật khẩu tài khoản FITA VNUA',
                'content_blocks' => [
                    'greeting' => 'Xin chào {{ $nguoi_dung }},',
                    'intro' => 'Chào mừng bạn đến với cổng thông tin Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.',
                    'main_message' => 'Bạn đã đăng nhập thành công qua Hệ thống ST Single Sign-On. Tuy nhiên, để đảm bảo tính bảo mật và cấp quyền truy cập, bạn cần thực hiện thiết lập mật khẩu lần đầu. Vui lòng nhấn vào nút bên dưới để tiến hành:',
                    'button_text' => 'THIẾT LẬP MẬT KHẨU NGAY',
                    'security_heading' => 'Lưu ý bảo mật:',
                    'security_note' => "Liên kết thiết lập mật khẩu chỉ có hiệu lực trong vòng {{ \$thoi_gian_hieu_luc }}.\nKhông chia sẻ liên kết này với bất kỳ ai khác để tránh rủi ro bảo mật.\nNếu bạn không yêu cầu tài khoản này, vui lòng bỏ qua email.\nNếu bạn cần hỗ trợ, vui lòng liên hệ: {{ \$email_he_thong }}",
                    'signature' => 'Ban Quản trị Website FITA VNUA',
                    'footer_hint' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:',
                    'footer_support' => 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.',
                ],
                'is_active' => true,
                'description' => 'Email gửi cho người dùng mới đăng nhập qua SSO để thiết lập mật khẩu lần đầu.',
            ]
        );

        // Template cho email đặt lại mật khẩu
        EmailTemplate::updateOrCreate(
            ['template_type' => 'password_reset'],
            [
                'subject' => 'Đặt lại mật khẩu tài khoản FITA VNUA',
                'content_blocks' => [
                    'greeting' => 'Xin chào {{ $nguoi_dung }},',
                    'intro' => 'Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn trên cổng thông tin Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.',
                    'main_message' => 'Nếu bạn là người thực hiện yêu cầu này, vui lòng nhấn vào nút bên dưới để đặt lại mật khẩu mới:',
                    'button_text' => 'ĐẶT LẠI MẬT KHẨU',
                    'security_heading' => 'Lưu ý bảo mật:',
                    'security_note' => "Liên kết đặt lại mật khẩu chỉ có hiệu lực trong vòng {{ \$thoi_gian_hieu_luc }}.\nKhông chia sẻ liên kết này với bất kỳ ai khác để tránh rủi ro bảo mật.\nNếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.\nNếu bạn cần hỗ trợ, vui lòng liên hệ: {{ \$email_he_thong }}",
                    'signature' => 'Ban Quản trị Website FITA VNUA',
                    'footer_hint' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:',
                    'footer_support' => 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.',
                ],
                'is_active' => true,
                'description' => 'Email gửi cho người dùng xác nhận để đặt lại mật khẩu. ',
            ]
        );

        EmailTemplate::updateOrCreate(
            ['template_type' => 'post_status_submitted'],
            [
                'subject' => 'Bài viết chờ duyệt: {{ $tieu_de_bai_viet }}',
                'content_blocks' => [
                    'greeting' => 'Xin chào {{ $nguoi_dung }},',
                    'intro' => 'Có một bài viết mới đang chờ duyệt trong hệ thống.',
                    'title_label' => 'Tiêu đề: {{ $tieu_de_bai_viet }}',
                    'category_label' => 'Danh mục: {{ $danh_muc_bai_viet }}',
                    'actor_label' => 'Người thực hiện: {{ $nguoi_thuc_hien }}',
                    'description' => 'Vui lòng kiểm tra và xử lý bài viết trong hệ thống.',
                    'button_text' => 'XEM BÀI VIẾT',
                    'signature' => 'Ban Quản trị Website FITA VNUA',
                    'footer_text' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:',
                    'footer_support' => 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.',
                ],
                'is_active' => true,
                'description' => 'Email thông báo khi có bài viết mới cần duyệt.',
            ]
        );

        EmailTemplate::updateOrCreate(
            ['template_type' => 'post_status_reverted_to_pending_author'],
            [
                'subject' => 'Bài viết đã đăng được thu hồi về trạng thái chờ duyệt: {{ $tieu_de_bai_viet }} ',
                'content_blocks' => [
                    'greeting' => 'Xin chào {{ $nguoi_dung }},',
                    'intro' => 'Bài viết của bạn đã bị gỡ khỏi trạng thái đã đăng và chuyển về chờ duyệt lại.',
                    'title_label' => 'Tiêu đề: {{ $tieu_de_bai_viet }}',
                    'category_label' => 'Danh mục: {{ $danh_muc_bai_viet }}',
                    'actor_label' => 'Người thực hiện: {{ $nguoi_thuc_hien }}',
                    'button_text' => 'XEM BÀI TRONG QUẢN TRỊ',
                    'description' => 'Bạn vui lòng mở bài viết trong trang quản trị để kiểm tra nội dung và gửi duyệt lại.',
                    'signature' => 'Ban Quản trị Website FITA VNUA',
                    'footer_text' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:',
                    'footer_support' => 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.',
                ],
                'is_active' => true,
                'description' => 'Email gửi cho tác giả khi bài viết bị gỡ và cần duyệt lại.',
            ]
        );

        EmailTemplate::updateOrCreate(
            ['template_type' => 'post_status_approved_published'],
            [
                'subject' => 'Bài viết đã được duyệt: {{ $tieu_de_bai_viet }} ',
                'content_blocks' => [
                    'greeting' => 'Xin chào {{ $nguoi_dung }},',
                    'intro' => 'Bài viết của bạn đã được duyệt thành công.',
                    'title_label' => 'Tiêu đề: {{ $tieu_de_bai_viet }}',
                    'category_label' => 'Danh mục: {{ $danh_muc_bai_viet }}',
                    'actor_label' => 'Người thực hiện: {{ $nguoi_thuc_hien }}',
                    'schedule_label' => 'Lịch đăng: {{ $lich_dang }}',
                    'description' => 'Bạn có thể xem bài viết đã xuất bản bằng nút bên dưới.',
                    'button_text' => 'XEM BÀI VIẾT',
                    'signature' => 'Ban Quản trị Website FITA VNUA',
                    'footer_text' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:',
                    'footer_support' => 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.',
                ],
                'is_active' => true,
                'description' => 'Email thông báo khi bài viết được duyệt và xuất bản.',
            ]
        );

        EmailTemplate::updateOrCreate(
            ['template_type' => 'post_status_rejected'],
            [
                'subject' => 'Bài viết bị từ chối: {{ $tieu_de_bai_viet }} ',
                'content_blocks' => [
                    'greeting' => 'Xin chào {{ $nguoi_dung }},',
                    'intro' => 'Bài viết của bạn đã bị từ chối.',
                    'title_label' => 'Tiêu đề: {{ $tieu_de_bai_viet }}',
                    'category_label' => 'Danh mục: {{ $danh_muc_bai_viet }}',
                    'actor_label' => 'Người thực hiện: {{ $nguoi_thuc_hien }}',
                    'reason_label' => 'Lý do từ chối: {{ $ghi_chu }}',
                    'description' => 'Vui lòng cập nhật lại nội dung và gửi duyệt lại sau khi chỉnh sửa.',
                    'button_text' => 'CHỈNH SỬA BÀI VIẾT',
                    'signature' => 'Ban Quản trị Website FITA VNUA',
                    'footer_text' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:',
                    'footer_support' => 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.',
                ],
                'is_active' => true,
                'description' => 'Email thông báo khi bài viết bị từ chối.',
            ]
        );

//        EmailTemplate::updateOrCreate(
//            ['template_type' => 'post_status_notification'],
//            [
//                'subject' => '{{ $ten_hanh_dong }}: {{ $tieu_de_bai_viet }}',
//                'content_blocks' => [
//                    'greeting' => 'Xin chào {{ $nguoi_dung }},',
//                    'intro' => 'Thông báo trạng thái bài viết của bạn.',
//                    'title_label' => 'Tiêu đề:',
//                    'category_label' => 'Danh mục:',
//                    'actor_label' => 'Người thực hiện:',
//                    'reason_label' => 'Lý do từ chối:',
//                    'schedule_label' => 'Lên lịch đăng:',
//                    'description' => 'Vui lòng xem thông tin bên dưới.',
//                    'button_text' => 'Xem bài viết',
//                    'footer_text' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn trực tiếp vào trình duyệt.',
//                    'signature' => 'Ban Quản trị Website FITA VNUA',
//                ],
//                'is_active' => false,
//                'description' => 'Template cũ trước khi tách theo action. Đã được thay thế bằng các template con.',
//            ]
//        );
    }
}
