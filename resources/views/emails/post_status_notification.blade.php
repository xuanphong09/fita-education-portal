<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo bài viết - FITA VNUA</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f0f4f8; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-spacing: 0; border-collapse: collapse; }
        td { padding: 0; }
        img { border: 0; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f0f4f8; padding-top: 40px; padding-bottom: 40px; }
        .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 700px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .button { display: inline-block; background-color: #F6A309; color: #ffffff !important; text-decoration: none; padding: 14px 30px; border-radius: 6px; font-weight: bold; font-size: 16px; }
    </style>
</head>
<body>
@php
    $isSubmitted = $action === 'submitted';
    $isApproved = $action === 'approved';
    $isRejected = $action === 'rejected';
    $isRevertedToPending = $action === 'reverted_to_pending';
    $isRevertedToPendingAuthor = $action === 'reverted_to_pending_author';
    $title = trim((string) $postTitle) !== '' ? $postTitle : 'Bài viết';
    $categoryLabel = !empty($categoryNames) ? implode(' - ', $categoryNames) : '—';
    $scheduledAt = $scheduledPublishAt ? \Carbon\Carbon::parse($scheduledPublishAt) : null;
    $isScheduledInFuture = $scheduledAt?->isFuture() ?? false;

    $primaryCtaLabel = null;
    $primaryCtaUrl = null;
    $description = '';

    if ($isSubmitted) {
        $primaryCtaLabel = 'Duyệt bài ngay';
        $primaryCtaUrl = $reviewUrl;
        $description = 'Vui lòng kiểm tra và xử lý bài viết trong hệ thống.';
    } elseif ($isRevertedToPending) {
        $primaryCtaLabel = 'Duyệt lại bài viết';
        $primaryCtaUrl = $reviewUrl;
        $description = 'Đây là email nhắc duyệt lại do bài viết đã bị gỡ về trạng thái chờ duyệt.';
    } elseif ($isRevertedToPendingAuthor) {
        $primaryCtaLabel = 'Xem bài trong quản trị';
        $primaryCtaUrl = $editUrl;
        $description = 'Bạn vui lòng mở bài viết trong trang quản trị để kiểm tra nội dung và gửi duyệt lại.';
    } elseif ($isApproved) {
        if ($isScheduledInFuture) {
            $primaryCtaLabel = 'Xem lịch đăng';
            $primaryCtaUrl = $editUrl;
            $description = 'Bài viết đã được duyệt và đang lên lịch đăng, hiện chưa hiển thị ngay ngoài trang người dùng.';
        } else {
            $primaryCtaLabel = 'Xem bài viết';
            $primaryCtaUrl = $postUrl;
            $description = 'Bạn có thể xem bài viết đã xuất bản bằng nút bên trên.';
        }
    } elseif ($isRejected) {
        $primaryCtaLabel = 'Chỉnh sửa bài viết';
        $primaryCtaUrl = $editUrl;
        $description = 'Vui lòng cập nhật lại nội dung và gửi duyệt lại sau khi chỉnh sửa.';
    }
@endphp
<center class="wrapper">
    <table class="main" width="100%">
        <tr>
            <td style="background-color: #0961AA; padding: 25px 20px;">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="right" width="20%" style="vertical-align: middle; padding-right: 15px;">
                            <img src="{{ $message->embed(public_path('assets/images/Logo Học viện.png')) }}" alt="Logo Học viện" style="width: 50px; height: 50px; display: block; object-fit: contain;">
                        </td>
                        <td align="center" width="60%" style="vertical-align: middle;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Học viện Nông nghiệp Việt Nam</h1>
                            <p style="color: #ffffff; margin: 5px 0 0 0; font-size: 18px; font-weight: 700; letter-spacing: 1px;">Khoa Công nghệ thông tin</p>
                        </td>
                        <td align="left" width="20%" style="vertical-align: middle; padding-left: 15px;">
                            <img src="{{ $message->embed(public_path('assets/images/LogoKhoaCNTT.png')) }}" alt="FITA logo" style="width: 55px; height: 55px; display: block; object-fit: contain;">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 30px;">
                <h2 style="color: #1e293b; font-size: 20px; margin: 0 0 20px 0;">Xin chào {{ $recipientName }},</h2>

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">
                    @if($isSubmitted)
                        Có một bài viết mới đang chờ duyệt trong hệ thống.
                    @elseif($isRevertedToPending)
                        Bài viết đã được gỡ xuống và quay lại trạng thái chờ duyệt. Vui lòng kiểm tra và duyệt lại.
                    @elseif($isRevertedToPendingAuthor)
                        Bài viết của bạn đã bị gỡ khỏi trạng thái đã đăng và chuyển về chờ duyệt lại.
                    @elseif($isApproved)
                        Bài viết của bạn đã được duyệt thành công.
                    @elseif($isRejected)
                        Bài viết của bạn đã bị từ chối.
                    @endif
                </p>

                <div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 15px 20px; border-radius: 4px; margin-bottom: 20px;">
                    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 8px 0;"><strong style="color: #1d4ed8;">Tiêu đề:</strong> {{ $title }}</p>
                    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 8px 0;"><strong style="color: #1d4ed8;">Danh mục:</strong> {{ $categoryLabel }}</p>
                    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0;"><strong style="color: #1d4ed8;">Người thực hiện:</strong> {{ $actorName }}</p>
                </div>

                @if($isRejected && $note)
                    <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px 20px; border-radius: 4px; margin-bottom: 20px;">
                        <p style="color: #991b1b; font-size: 16px; line-height: 1.6; margin: 0;"><strong>Lý do từ chối:</strong> {{ $note }}</p>
                    </div>
                @endif

                @if($scheduledPublishAt)
                    <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 15px 20px; border-radius: 4px; margin-bottom: 20px;">
                        <p style="color: #1d4ed8; font-size: 16px; line-height: 1.6; margin: 0;"><strong>Lên lịch đăng:</strong> {{ \Carbon\Carbon::parse($scheduledPublishAt)->format('H:i d/m/Y') }}</p>
                    </div>
                @endif

                @if($primaryCtaLabel && $primaryCtaUrl)
                    <table width="100%">
                        <tr>
                            <td align="center" style="padding: 10px 0 30px 0;">
                                <a href="{{ $primaryCtaUrl }}" class="button">{{ $primaryCtaLabel }}</a>
                            </td>
                        </tr>
                    </table>
                @endif

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">{{ $description }}</p>

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 25px 0 0 0;">
                    Trân trọng,<br>
                    <strong style="color: #0961AA;">Ban Quản trị Website FITA VNUA</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f1f5f9; padding: 25px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="color: #64748b; font-size: 16px; line-height: 1.5; margin: 0 0 10px 0;">© {{ date('Y') }} Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.</p>
                <p style="color: #94a3b8; font-size: 14px; line-height: 1.5; margin: 0;">
                    {{ $isSubmitted ? 'Nếu bạn không phải người duyệt bài, vui lòng bỏ qua email này.' : 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn trực tiếp vào trình duyệt.' }}
                </p>
                <p style="color: #94a3b8; font-size: 14px; line-height: 1.5; margin: 0;">
                    {{ $primaryCtaUrl }}
                </p>
            </td>
        </tr>
    </table>
</center>
</body>
</html>

