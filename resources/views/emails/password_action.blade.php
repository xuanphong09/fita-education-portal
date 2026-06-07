<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FITA VNUA - Email tài khoản</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f0f4f8;
            font-family: Arial, Helvetica, Tahoma, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse;
            font-family: Arial, Helvetica, Tahoma, sans-serif;
        }

        td, p, h1, h2, a, li, strong {
            font-family: Arial, Helvetica, Tahoma, sans-serif;
        }

        td { padding: 0; }
        img { border: 0; }

        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f0f4f8;
            padding-top: 40px;
            padding-bottom: 40px;
        }

        .main {
            background-color: #ffffff;
            margin: 0 auto;
            width: 100%;
            max-width: 700px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .button:hover {
            background-color: #f59e0b !important;
        }
    </style>
</head>
<body>
@php
    $content = $content ?? [];
    $actionUrl = $actionUrl ?? $setupUrl ?? $resetUrl ?? '#';
    $signature = data_get($content, 'signature', 'Ban Quản trị Website FITA VNUA');
    $baseUrl = rtrim(config('app.url123') ?: 'https://st-dse.vnua.edu.vn', '/');

    $academyLogoUrl = $baseUrl . '/assets/images/LogoVnua.png';
    $facultyLogoUrl = $baseUrl . '/assets/images/LogoKhoaCNTT.png';
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
                    {{ data_get($content, 'intro', '') }}
                </p>

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                    {{ data_get($content, 'main_message', '') }}
                </p>

                <table width="100%">
                    <tr>
                        <td align="center" style="padding: 0 0 30px 0;">
                            <a href="{{ $actionUrl }}" class="button" style="background-color: #F6A309; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block; transition: background-color 0.3s ease;">
                                {{ data_get($content, 'button_text', 'Xác nhận ngay') }}
                            </a>
                        </td>
                    </tr>
                </table>

                <div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 15px 20px; border-radius: 4px;">
                    <p style="color: #334155; font-size: 16px; line-height: 1.5; margin: 0 0 10px 0;"><strong style="color: #1d4ed8;">{{ data_get($content, 'security_heading', 'Lưu ý bảo mật:') }}</strong></p>
                    <ul  style="color:#334155;font-size:16px;line-height:1.5;margin:10px 0 0 30px;padding:0">
{{--                        {!! nl2br(e(data_get($content, 'security_note', ''))) !!}--}}
                        @foreach(explode("\n", data_get($content, 'security_note', '') ?? 'Chưa có nội dung.') as $p)
                            @if(trim($p))
                                <li>{{ $p }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 25px 0 0 0;">
                    Trân trọng,<br>
                    <strong style="color: #0961AA;">{{ $signature }}</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f1f5f9; padding: 25px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="color: #64748b; font-size: 16px; line-height: 1.5; margin: 0 0 10px 0;">
                    © {{ date('Y') }} Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.
                </p>

                <p style="color: #94a3b8; font-size: 14px; line-height: 1.5; margin: 0;">
                    {{ data_get($content, 'footer_hint', 'Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:') }}<br>
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


