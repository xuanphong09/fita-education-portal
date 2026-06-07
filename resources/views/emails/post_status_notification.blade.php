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
    $content = $content ?? [];

    $actionUrl = $lien_ket_hanh_dong
        ?? $actionUrl
        ?? $postUrl
        ?? $editUrl
        ?? $reviewUrl
        ?? '#';

    $primaryCtaUrl = $actionUrl;

    $title = $tieu_de_bai_viet
        ?? $postTitle
        ?? 'Bài viết';

    $title = trim((string) $title) !== '' ? $title : 'Bài viết';

    $rawCategoryNames = $categoryNames ?? null;

    if (isset($danh_muc_bai_viet) && trim((string) $danh_muc_bai_viet) !== '') {
        $categoryLabel = $danh_muc_bai_viet;
    } elseif (is_array($rawCategoryNames)) {
        $categoryLabel = implode(' - ', $rawCategoryNames);
    } elseif (is_string($rawCategoryNames) && trim($rawCategoryNames) !== '') {
        $categoryLabel = $rawCategoryNames;
    } else {
        $categoryLabel = '—';
    }

    $actorDisplay = $nguoi_thuc_hien
        ?? $actorName
        ?? 'Hệ thống';

    $noteDisplay = $ghi_chu
        ?? $note
        ?? '';

    $scheduledRaw = $scheduledPublishAt
        ?? $lich_dang
        ?? null;

    $scheduledAt = null;

    if ($scheduledRaw) {
        try {
            $scheduledAt = \Carbon\Carbon::parse($scheduledRaw);
        } catch (\Throwable) {
            $scheduledAt = null;
        }
    }
    $baseUrl = rtrim(config('app.url123') ?: 'https://st-dse.vnua.edu.vn', '/');

    $academyLogoUrl = $baseUrl . '/assets/images/LogoVnua.png';
    $facultyLogoUrl = $baseUrl . '/assets/images/LogoKhoaCNTT.png';

        $renderInfoLine = function (?string $line, string $fallbackLabel, string $fallbackValue = '') {
        $line = trim((string) ($line ?? ''));

        if ($line === '') {
            $line = trim($fallbackLabel . ': ' . $fallbackValue);
        }

        $position = mb_strpos($line, ':');

        if ($position === false) {
            return [
                'label' => $line,
                'value' => '',
            ];
        }

        $label = trim(mb_substr($line, 0, $position));
        $value = trim(mb_substr($line, $position + 1));

        return [
            'label' => $label . ':',
            'value' => $value,
        ];
    };

    $titleLine = $renderInfoLine(
        data_get($content, 'title_label'),
        'Tiêu đề',
        $title
    );

    $categoryLine = $renderInfoLine(
        data_get($content, 'category_label'),
        'Danh mục',
        $categoryLabel
    );

    $actorLine = $renderInfoLine(
        data_get($content, 'actor_label'),
        'Người thực hiện',
        $actorDisplay
    );

    $reasonLine = $renderInfoLine(
        data_get($content, 'reason_label'),
        'Lý do từ chối',
        $noteDisplay
    );

    $scheduleLine = $renderInfoLine(
        data_get($content, 'schedule_label'),
        'Lịch đăng',
        $scheduledAt ? $scheduledAt->format('H:i d/m/Y') : ''
    );
@endphp
<center class="wrapper">
    <table class="main" width="100%">
        <tr>
            <td style="background-color: #0961AA; padding: 25px 20px;">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="right" width="20%" style="vertical-align: middle; padding-right: 15px;">
                            <img src="{{ $academyLogoUrl }}" alt="Logo Học viện" style="width: 50px; height: 50px; display: block; object-fit: contain;">
                        </td>
                        <td align="center" width="60%" style="vertical-align: middle;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; text-transform: uppercase;">
                                Khoa Công nghệ thông tin
                            </h1>
                            <p style="color: #ffffff; margin: 5px 0 0 0; font-size: 16px; font-weight: 600;">
                                Học viện Nông nghiệp Việt Nam
                            </p>
                        </td>
                        <td align="left" width="20%" style="vertical-align: middle; padding-left: 15px;">
                            <img src="{{ $facultyLogoUrl }}" alt="FITA logo" style="width: 55px; height: 55px; display: block; object-fit: contain;">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 30px;">
                <h2 style="color: #1e293b; font-size: 20px; margin: 0 0 20px 0;">{{ data_get($content, 'greeting', 'Xin chào,') }}</h2>

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">
                    {!! nl2br(e(data_get($content, 'intro', ''))) !!}
                </p>

                <div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 15px 20px; border-radius: 4px; margin-bottom: 20px;">
                    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 8px 0;">
                        <strong style="color: #1d4ed8;">{{ $titleLine['label'] }}</strong>
                        <span style="color: #334155;">{{ $titleLine['value'] }}</span>
                    </p>

                    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 8px 0;">
                        <strong style="color: #1d4ed8;">{{ $categoryLine['label'] }}</strong>
                        <span style="color: #334155;">{{ $categoryLine['value'] }}</span>
                    </p>

                    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0;">
                        <strong style="color: #1d4ed8;">{{ $actorLine['label'] }}</strong>
                        <span style="color: #334155;">{{ $actorLine['value'] }}</span>
                    </p>
                </div>

                @if(trim((string) $noteDisplay) !== '')
                    <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px 20px; border-radius: 4px; margin-bottom: 20px;">
                        <p style="color: #991b1b; font-size: 16px; line-height: 1.6; margin: 0;">
                            <strong style="color: #991b1b;">{{ $reasonLine['label'] }}</strong>
                            <span>{!! nl2br(e($reasonLine['value'])) !!}</span>
                        </p>
                    </div>
                @endif

                @if($scheduledAt)
                    <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 15px 20px; border-radius: 4px; margin-bottom: 20px;">
                        <p style="color: #1d4ed8; font-size: 16px; line-height: 1.6; margin: 0;">
                            <strong style="color: #1d4ed8;">{{ $scheduleLine['label'] }}</strong>
                            <span style="color: #334155;">{{ $scheduleLine['value'] }}</span>
                        </p>
                    </div>
                @endif

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">{!! nl2br(e(data_get($content, 'description', ''))) !!}</p>

                @if(data_get($content, 'button_text'))
                    <table width="100%">
                        <tr>
                            <td align="center" style="padding: 10px 0 0 0;">
                                <a href="{{ $actionUrl }}" class="button">{{ data_get($content, 'button_text') }}</a>
                            </td>
                        </tr>
                    </table>
                @endif

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 25px 0 0 0;">
                    Trân trọng,<br>
                    <strong style="color: #0961AA;">{{ data_get($content, 'signature', 'Ban Quản trị Website FITA VNUA') }}</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f1f5f9; padding: 25px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="color: #64748b; font-size: 16px; line-height: 1.5; margin: 0 0 10px 0;">© {{ date('Y') }} Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.</p>
                <p style="color: #94a3b8; font-size: 14px; line-height: 1.5; margin: 0;">
                    {{ data_get($content, 'footer_text', 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn trực tiếp vào trình duyệt.') }}<br>
                    <a href="{{ $actionUrl }}" style="color: #3b82f6; text-decoration: underline; word-break: break-all;">
                        {{ $actionUrl }}
                    </a>
                </p>
                <p style="color: #94a3b8; font-size: 14px; line-height: 1.5; margin: 8px 0 0 0;">
                    {{ data_get($content, 'footer_support', 'Email này được gửi tự động từ hệ thống. Vui lòng không trả lời email này.') }}
                </p>
            </td>
        </tr>
    </table>
</center>
</body>
</html>

