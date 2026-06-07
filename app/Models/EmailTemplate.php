<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;

class EmailTemplate extends Model
{
    protected $fillable = [
        'template_type',
        'subject',
        'content_blocks',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'content_blocks' => 'array',
    ];

    public static function getActiveTemplate(string $templateType): ?self
    {
        return self::where('template_type', $templateType)
            ->where('is_active', true)
            ->first();
    }

    public static function requiredTemplateTypes(): array
    {
        return [
            'first_time_password_setup',
            'password_reset',
        ];
    }

    public static function isRequiredTemplate(string $templateType): bool
    {
        return in_array($templateType, self::requiredTemplateTypes(), true);
    }

    public static function canDisable(string $templateType): bool
    {
        return ! self::isRequiredTemplate($templateType);
    }

    public static function viewNameFor(string $templateType): string
    {
        return match ($templateType) {
            'first_time_password_setup',
            'password_reset' => 'emails.password_action',

            'post_status_submitted',
            'post_status_reverted_to_pending_author',
            'post_status_approved_published',
            'post_status_rejected' => 'emails.post_status_notification',

            default => throw new InvalidArgumentException("Unsupported email template type: {$templateType}"),
        };
    }

    public static function defaultDescriptionFor(string $templateType): string
    {
        return match ($templateType) {
            'first_time_password_setup' => 'Email gửi cho người dùng khi cần thiết lập mật khẩu lần đầu sau khi đăng nhập SSO.',
            'password_reset' => 'Email gửi cho người dùng khi yêu cầu đặt lại mật khẩu.',
            'post_status_submitted' => 'Email thông báo khi có bài viết mới cần duyệt.',
            'post_status_reverted_to_pending' => 'Email nhắc duyệt lại khi bài viết được chuyển về trạng thái chờ duyệt.',
            'post_status_reverted_to_pending_author' => 'Email gửi cho tác giả khi bài viết bị gỡ và cần duyệt lại.',
            'post_status_approved_published' => 'Email thông báo khi bài viết được duyệt và xuất bản.',
            'post_status_approved_scheduled' => 'Email thông báo khi bài viết được duyệt nhưng đang lên lịch đăng.',
            'post_status_rejected' => 'Email thông báo khi bài viết bị từ chối.',
            'post_status_notification' => 'Email thông báo cho người dùng về trạng thái bài viết.',
            default => throw new InvalidArgumentException("Unsupported email template type: {$templateType}"),
        };
    }

    public static function defaultSubjectFor(string $templateType): string
    {
        return match ($templateType) {
            'first_time_password_setup' => 'Thiết lập mật khẩu tài khoản FITA VNUA',
            'password_reset' => 'Đặt lại mật khẩu tài khoản FITA VNUA',
            'post_status_submitted' => 'Bài viết chờ duyệt: {{ $tieu_de_bai_viet }}',
            'post_status_reverted_to_pending' => 'Nhắc duyệt lại bài viết: {{ $tieu_de_bai_viet }}',
            'post_status_reverted_to_pending_author' => 'Bài viết đã bị gỡ và chờ duyệt lại: {{ $tieu_de_bai_viet }}',
            'post_status_approved_published' => 'Bài viết đã được duyệt: {{ $tieu_de_bai_viet }}',
            'post_status_approved_scheduled' => 'Bài viết đã được duyệt và lên lịch: {{ $tieu_de_bai_viet }}',
            'post_status_rejected' => 'Bài viết bị từ chối: {{ $tieu_de_bai_viet }}',
            'post_status_notification' => '{{ $ten_hanh_dong }}: {{ $tieu_de_bai_viet }}',
            default => throw new InvalidArgumentException("Unsupported email template type: {$templateType}"),
        };
    }

    public static function defaultContentBlocks(string $templateType): array
    {
        return match ($templateType) {
            'first_time_password_setup' => [
                'greeting' => 'Xin chào {{ $nguoi_dung }},',
                'intro' => 'Chào mừng bạn đến với cổng thông tin Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.',
                'main_message' => 'Bạn đã đăng nhập thành công qua Hệ thống ST Single Sign-On. Tuy nhiên, để đảm bảo tính bảo mật và cấp quyền truy cập, bạn cần thực hiện thiết lập mật khẩu lần đầu. Vui lòng nhấn vào nút bên dưới để tiến hành:',
                'button_text' => 'THIẾT LẬP MẬT KHẨU NGAY',
                'security_heading' => 'Lưu ý bảo mật:',
                'security_note' => "Liên kết thiết lập mật khẩu chỉ có hiệu lực trong vòng {{ \$expiresInHuman ?? '24 giờ' }}.\nKhông chia sẻ liên kết này với bất kỳ ai khác để tránh rủi ro bảo mật.\nNếu bạn không yêu cầu tài khoản này, vui lòng bỏ qua email.\nNếu bạn cần hỗ trợ, vui lòng liên hệ {{ \$systemEmail ?? config('mail.from.address') }}",
                'signature' => 'Ban Quản trị Website FITA VNUA',
                'footer_hint' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:',
                'footer_support' => 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.',
            ],

            'password_reset' => [
                'greeting' => 'Xin chào {{ $nguoi_dung }},',
                'intro' => 'Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn trên cổng thông tin Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.',
                'main_message' => 'Nếu bạn là người thực hiện yêu cầu này, vui lòng nhấn vào nút bên dưới để đặt lại mật khẩu mới:',
                'button_text' => 'ĐẶT LẠI MẬT KHẨU',
                'security_heading' => 'Lưu ý bảo mật:',
                'security_note' => "Liên kết đặt lại mật khẩu chỉ có hiệu lực trong vòng {{ \$expiresInHuman ?? '60 phút' }}.\nKhông chia sẻ liên kết này với bất kỳ ai khác để tránh rủi ro bảo mật.\nNếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.\nNếu bạn cần hỗ trợ, vui lòng liên hệ {{ \$systemEmail ?? config('mail.from.address') }}",
                'signature' => 'Ban Quản trị Website FITA VNUA',
                'footer_hint' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:',
                'footer_support' => 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.',
            ],

            'post_status_submitted' => self::postStatusContentBlocks(
                'Có một bài viết mới đang chờ duyệt trong hệ thống.',
                'Vui lòng kiểm tra và xử lý bài viết trong hệ thống.',
                'Duyệt bài ngay',
                'Nếu bạn không phải người duyệt bài, vui lòng bỏ qua email này.',
                'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.'
            ),

            'post_status_reverted_to_pending' => self::postStatusContentBlocks(
                'Bài viết đã được gỡ xuống và quay lại trạng thái chờ duyệt. Vui lòng kiểm tra và duyệt lại.',
                'Đây là email nhắc duyệt lại do bài viết đã bị gỡ về trạng thái chờ duyệt.',
                'Duyệt lại bài viết',
                'Nếu bạn không phải người duyệt bài, vui lòng bỏ qua email này.',
                'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.'
            ),

            'post_status_reverted_to_pending_author' => self::postStatusContentBlocks(
                'Bài viết của bạn đã bị gỡ khỏi trạng thái đã đăng và chuyển về chờ duyệt lại.',
                'Bạn vui lòng mở bài viết trong trang quản trị để kiểm tra nội dung và gửi duyệt lại.',
                'Xem bài trong quản trị',
                'Bạn cần chỉnh sửa và gửi duyệt lại sau khi kiểm tra.',
                'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.'
            ),

            'post_status_approved_published' => self::postStatusContentBlocks(
                'Bài viết của bạn đã được duyệt thành công.',
                'Bạn có thể xem bài viết đã xuất bản bằng nút bên trên.',
                'Xem bài viết',
                'Nếu bạn cần chỉnh sửa, vui lòng liên hệ quản trị viên.',
                'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.'
            ),

            'post_status_approved_scheduled' => self::postStatusContentBlocks(
                'Bài viết của bạn đã được duyệt và đang lên lịch đăng.',
                'Bài viết đã được duyệt và đang lên lịch đăng, hiện chưa hiển thị ngay ngoài trang người dùng.',
                'Xem lịch đăng',
                'Nếu bạn cần thay đổi lịch đăng, vui lòng liên hệ quản trị viên.',
                'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.'
            ),

            'post_status_rejected' => self::postStatusContentBlocks(
                'Bài viết của bạn đã bị từ chối.',
                'Vui lòng cập nhật lại nội dung và gửi duyệt lại sau khi chỉnh sửa.',
                'Chỉnh sửa bài viết',
                'Nếu bạn có thắc mắc về nội dung từ chối, vui lòng liên hệ quản trị viên.',
                'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.'
            ),

            'post_status_notification' => [
                'greeting' => 'Xin chào {{ $nguoi_dung }},',
                'intro' => 'Thông báo trạng thái bài viết của bạn.',
                'title_label' => 'Tiêu đề:',
                'category_label' => 'Danh mục:',
                'actor_label' => 'Người thực hiện:',
                'reason_label' => 'Lý do từ chối:',
                'schedule_label' => 'Lịch đăng:',
                'description' => 'Vui lòng xem thông tin bên dưới.',
                'button_text' => 'Xem bài viết',
                'footer_text' => 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn trực tiếp vào trình duyệt.',
                'signature' => 'Ban Quản trị Website FITA VNUA',
            ],

            default => throw new InvalidArgumentException("Unsupported email template type: {$templateType}"),
        };
    }

    public static function fieldDefinitionsFor(string $templateType): array
    {
        return match ($templateType) {
            'first_time_password_setup' => [
                [
                    'title' => 'Nội dung email thiết lập mật khẩu lần đầu',
                    'fields' => [
                        ['key' => 'greeting', 'label' => 'Lời chào', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'intro', 'label' => 'Đoạn giới thiệu', 'type' => 'textarea', 'rows' => 3],
                        ['key' => 'main_message', 'label' => 'Nội dung chính', 'type' => 'textarea', 'rows' => 4],
                        ['key' => 'button_text', 'label' => 'Chữ trên nút', 'type' => 'text'],
                        ['key' => 'security_heading', 'label' => 'Tiêu đề khối bảo mật', 'type' => 'text'],
                        ['key' => 'security_note', 'label' => 'Nội dung lưu ý bảo mật', 'type' => 'textarea', 'rows' => 6],
                        ['key' => 'signature', 'label' => 'Chữ ký', 'type' => 'text'],
                        ['key' => 'footer_hint', 'label' => 'Dòng hướng dẫn', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'footer_support', 'label' => 'Dòng chân thư', 'type' => 'textarea', 'rows' => 2],
                    ],
                ],
            ],

            'password_reset' => [
                [
                    'title' => 'Nội dung email đặt lại mật khẩu',
                    'fields' => [
                        ['key' => 'greeting', 'label' => 'Lời chào', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'intro', 'label' => 'Đoạn giới thiệu', 'type' => 'textarea', 'rows' => 3],
                        ['key' => 'main_message', 'label' => 'Nội dung chính', 'type' => 'textarea', 'rows' => 4],
                        ['key' => 'button_text', 'label' => 'Chữ trên nút', 'type' => 'text'],
                        ['key' => 'security_heading', 'label' => 'Tiêu đề khối bảo mật', 'type' => 'text'],
                        ['key' => 'security_note', 'label' => 'Nội dung lưu ý bảo mật', 'type' => 'textarea', 'rows' => 6],
                        ['key' => 'signature', 'label' => 'Chữ ký', 'type' => 'text'],
                        ['key' => 'footer_hint', 'label' => 'Dòng hướng dẫn', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'footer_support', 'label' => 'Dòng chân thư', 'type' => 'textarea', 'rows' => 2],
                    ],
                ],
            ],

            'post_status_submitted' => [
                [
                    'title' => self::defaultDescriptionFor($templateType),
                    'fields' => [
                        ['key' => 'greeting', 'label' => 'Lời chào', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'intro', 'label' => 'Đoạn giới thiệu', 'type' => 'textarea', 'rows' => 3],
                        ['key' => 'title_label', 'label' => 'Nhãn tiêu đề', 'type' => 'text'],
                        ['key' => 'category_label', 'label' => 'Nhãn danh mục', 'type' => 'text'],
                        ['key' => 'actor_label', 'label' => 'Nhãn người thực hiện', 'type' => 'text'],
                        ['key' => 'description', 'label' => 'Mô tả', 'type' => 'textarea', 'rows' => 3],
                        ['key' => 'button_text', 'label' => 'Chữ trên nút', 'type' => 'text'],
                        ['key' => 'signature', 'label' => 'Chữ ký', 'type' => 'text'],
                        ['key' => 'footer_text', 'label' => 'Dòng hướng dẫn', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'footer_support', 'label' => 'Dòng chân thư', 'type' => 'textarea', 'rows' => 2],
                    ],
                ],
            ],
            'post_status_approved_published' =>[
                [
                    'title' => self::defaultDescriptionFor($templateType),
                    'fields' => [
                        ['key' => 'greeting', 'label' => 'Lời chào', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'intro', 'label' => 'Đoạn giới thiệu', 'type' => 'textarea', 'rows' => 3],
                        ['key' => 'title_label', 'label' => 'Nhãn tiêu đề', 'type' => 'text'],
                        ['key' => 'category_label', 'label' => 'Nhãn danh mục', 'type' => 'text'],
                        ['key' => 'actor_label', 'label' => 'Nhãn người thực hiện', 'type' => 'text'],
                        ['key' => 'schedule_label', 'label' => 'Nhãn lịch đăng', 'type' => 'text'],
                        ['key' => 'description', 'label' => 'Mô tả', 'type' => 'textarea', 'rows' => 3],
                        ['key' => 'button_text', 'label' => 'Chữ trên nút', 'type' => 'text'],
                        ['key' => 'signature', 'label' => 'Chữ ký', 'type' => 'text'],
                        ['key' => 'footer_text', 'label' => 'Dòng hướng dẫn', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'footer_support', 'label' => 'Dòng chân thư', 'type' => 'textarea', 'rows' => 2],
                    ],
                ],
            ],
            'post_status_reverted_to_pending',
            'post_status_reverted_to_pending_author',
            'post_status_approved_scheduled',
            'post_status_rejected' => self::postStatusFieldDefinitions($templateType),

            'post_status_notification' => [
                [
                    'title' => 'Nội dung chung',
                    'fields' => [
                        ['key' => 'greeting', 'label' => 'Lời chào', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'intro', 'label' => 'Đoạn giới thiệu', 'type' => 'textarea', 'rows' => 3],
                        ['key' => 'description', 'label' => 'Mô tả', 'type' => 'textarea', 'rows' => 3],
                        ['key' => 'button_text', 'label' => 'Chữ trên nút', 'type' => 'text'],
                        ['key' => 'footer_text', 'label' => 'Dòng hướng dẫn', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'footer_support', 'label' => 'Dòng chân thư', 'type' => 'textarea', 'rows' => 2],
                        ['key' => 'signature', 'label' => 'Chữ ký', 'type' => 'text'],
                    ],
                ],
            ],

            default => throw new InvalidArgumentException("Unsupported email template type: {$templateType}"),
        };
    }

    public function mergedContentBlocks(): array
    {
        return array_replace_recursive(
            self::defaultContentBlocks($this->template_type),
            $this->content_blocks ?? []
        );
    }

    public function render(array $data = [], array $overrides = []): array
    {
        $blocks = array_replace_recursive($this->mergedContentBlocks(), $overrides);

        $payload = array_merge($data, $blocks, [
            'actionUrl' => $data['actionUrl']
                ?? $data['setupUrl']
                    ?? $data['resetUrl']
                    ?? $data['passwordUrl']
                    ?? $data['postUrl']
                    ?? $data['editUrl']
                    ?? $data['reviewUrl']
                    ?? '#',

            'actionLabel' => $data['actionLabel'] ?? $this->defaultActionLabel($data['action'] ?? null),
        ]);

        $payload = $this->withVietnameseTemplateVariables($payload);

        $renderedBlocks = $this->renderContentBlocks($blocks, $payload);

        $viewData = array_merge($payload, $renderedBlocks, [
            'content' => $renderedBlocks,
            'templateType' => $this->template_type,
            'actionUrl' => $payload['lien_ket_hanh_dong'] ?? $payload['actionUrl'] ?? '#',
            'primaryCtaUrl' => $payload['lien_ket_hanh_dong'] ?? $payload['actionUrl'] ?? '#',
        ]);

        return [
            'subject' => Blade::render(
                $this->subject ?: self::defaultSubjectFor($this->template_type),
                array_merge($payload, $renderedBlocks)
            ),

            'body' => view(
                self::viewNameFor($this->template_type),
                $viewData
            )->render(),
        ];
    }

    public function renderSubject(array $data = []): string
    {
        $payload = array_merge($data, $this->mergedContentBlocks(), [
            'actionUrl' => $data['actionUrl']
                ?? $data['setupUrl']
                    ?? $data['resetUrl']
                    ?? $data['passwordUrl']
                    ?? $data['postUrl']
                    ?? '#',

            'actionLabel' => $data['actionLabel'] ?? $this->defaultActionLabel($data['action'] ?? null),
        ]);

        $payload = $this->withVietnameseTemplateVariables($payload);

        return Blade::render(
            $this->subject ?: self::defaultSubjectFor($this->template_type),
            $payload
        );
    }

    public function renderBody(array $data = []): string
    {
        return $this->render($data)['body'];
    }

    public static function postStatusTemplateTypeForAction(string $action, ?string $scheduledPublishAt = null): string
    {
        return match ($action) {
            'submitted' => 'post_status_submitted',
            'reverted_to_pending',
            'reverted_to_pending_author' => 'post_status_reverted_to_pending_author',
            'approved' => 'post_status_approved_published',
            'rejected' => 'post_status_rejected',
            default => throw new InvalidArgumentException("Unsupported post status action: {$action}"),
        };
    }

    private function renderContentBlocks(array $blocks, array $payload): array
    {
        $rendered = [];

        foreach ($blocks as $key => $value) {
            $rendered[$key] = is_string($value)
                ? Blade::render($value, $payload)
                : $value;
        }

        return $rendered;
    }

    private function defaultActionLabel(?string $action): string
    {
        return match ($action) {
            'submitted' => 'Bài viết chờ duyệt',
            'approved' => 'Bài viết đã được duyệt',
            'rejected' => 'Bài viết bị từ chối',
            'reverted_to_pending' => 'Nhắc duyệt lại bài viết',
            'reverted_to_pending_author' => 'Bài viết đã bị gỡ và chờ duyệt lại',
            default => 'Thông báo bài viết',
        };
    }

    private static function postStatusContentBlocks(string $intro, string $description, string $buttonText, string $footerText, string $footerSupport): array
    {
        return [
            'greeting' => 'Xin chào {{ $nguoi_dung }},',
            'intro' => $intro,
            'title_label' => 'Tiêu đề:',
            'category_label' => 'Danh mục:',
            'actor_label' => 'Người thực hiện:',
            'reason_label' => 'Lý do từ chối:',
            'schedule_label' => 'Lịch đăng:',
            'description' => $description,
            'button_text' => $buttonText,
            'footer_text' => $footerText,
            'footer_support' => $footerSupport,
            'signature' => 'Ban Quản trị Website FITA VNUA',
        ];
    }

    private static function postStatusFieldDefinitions(string $templateType): array
    {
        return [
            [
                'title' => self::defaultDescriptionFor($templateType),
                'fields' => [
                    ['key' => 'greeting', 'label' => 'Lời chào', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'intro', 'label' => 'Đoạn giới thiệu', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'title_label', 'label' => 'Nhãn tiêu đề', 'type' => 'text'],
                    ['key' => 'category_label', 'label' => 'Nhãn danh mục', 'type' => 'text'],
                    ['key' => 'actor_label', 'label' => 'Nhãn người thực hiện', 'type' => 'text'],
                    ['key' => 'reason_label', 'label' => 'Nhãn lý do từ chối', 'type' => 'text'],
                    ['key' => 'schedule_label', 'label' => 'Nhãn lịch đăng', 'type' => 'text'],
                    ['key' => 'button_text', 'label' => 'Chữ trên nút', 'type' => 'text'],
                    ['key' => 'description', 'label' => 'Mô tả', 'type' => 'textarea', 'rows' => 3],
                    ['key' => 'signature', 'label' => 'Chữ ký', 'type' => 'text'],
                    ['key' => 'footer_text', 'label' => 'Dòng hướng dẫn', 'type' => 'textarea', 'rows' => 2],
                    ['key' => 'footer_support', 'label' => 'Dòng chân thư', 'type' => 'textarea', 'rows' => 2],
                ],
            ],
        ];
    }

    private static function isScheduledInFuture(?string $scheduledPublishAt): bool
    {
        if (! $scheduledPublishAt) {
            return false;
        }

        try {
            return \Carbon\Carbon::parse($scheduledPublishAt)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    private function withVietnameseTemplateVariables(array $payload): array
    {
        $user = $payload['user'] ?? null;

        $nguoiDung = data_get($user, 'name')
            ?? ($payload['userName'] ?? null)
            ?? ($payload['recipientName'] ?? null)
            ?? 'người dùng';

        $categoryNames = $payload['categoryNames'] ?? '';

        if (is_array($categoryNames)) {
            $categoryNames = implode(', ', $categoryNames);
        }

        return array_merge($payload, [
            'nguoi_dung' => $nguoiDung,
            'lien_ket_hanh_dong' => $payload['actionUrl']
                ?? $payload['setupUrl']
                    ?? $payload['resetUrl']
                    ?? $payload['postUrl']
                    ?? '#',
            'ten_hanh_dong' => $payload['actionLabel'] ?? 'Xem chi tiết',
            'email_he_thong' => $payload['systemEmail'] ?? config('mail.from.address'),
            'thoi_gian_hieu_luc' => $payload['expiresInHuman'] ?? '60 phút',

            'lien_ket_thiet_lap_mat_khau' => $payload['setupUrl'] ?? $payload['actionUrl'] ?? '#',
            'lien_ket_dat_lai_mat_khau' => $payload['resetUrl'] ?? $payload['actionUrl'] ?? '#',

            'tieu_de_bai_viet' => $payload['postTitle'] ?? 'Bài viết',
            'danh_muc_bai_viet' => $categoryNames,
            'nguoi_thuc_hien' => $payload['actorName'] ?? 'Hệ thống',
            'ghi_chu' => $payload['note'] ?? '',
            'lich_dang' => $payload['scheduledPublishAt'] ?? '',
            'lien_ket_bai_viet' => $payload['postUrl'] ?? '#',
            'lien_ket_chinh_sua' => $payload['editUrl'] ?? '#',
            'lien_ket_duyet_bai' => $payload['reviewUrl'] ?? '#',
        ]);
    }
}
