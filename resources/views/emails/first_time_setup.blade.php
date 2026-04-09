<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiết lập mật khẩu - FITA VNUA</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap');
        body { margin: 0; padding: 0; background-color: #f0f4f8; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-spacing: 0; border-collapse: collapse; }
        td { padding: 0; }
        img { border: 0; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f0f4f8; padding-top: 40px; padding-bottom: 40px; }
        .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 700px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .button:hover { background-color: #f59e0b !important; }
    </style>
</head>
<body>
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
                            <h1 style="color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">
                                Học viện Nông nghiệp Việt Nam
                            </h1>
                            <p style="color: #ffffff; margin: 5px 0 0 0; font-size: 18px; font-weight: 700; letter-spacing: 1px;">
                                Khoa Công nghệ thông tin
                            </p>
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
                <h2 style="color: #1e293b; font-size: 20px; margin: 0 0 20px 0;">Xin chào {{ $user->name }},</h2>

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">
                    @if(!empty($isReset))
                        Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn trên cổng thông tin Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.
                    @else
                        Chào mừng bạn đến với cổng thông tin Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.
                    @endif
                </p>

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                    @if(!empty($isReset))
                        Vui lòng nhấn vào nút bên dưới để đặt lại mật khẩu:
                    @else
                        Bạn đã đăng nhập thành công qua Hệ thống ST Single Sign-On. Tuy nhiên, để đảm bảo tính bảo mật và cấp quyền truy cập, bạn cần thực hiện <strong>thiết lập mật khẩu lần đầu</strong>. Vui lòng nhấn vào nút bên dưới để tiến hành:
                    @endif
                </p>

                <table width="100%">
                    <tr>
                        <td align="center" style="padding: 10px 0 30px 0;">
                            <a href="{{ $setupUrl }}" class="button" style="background-color: #F6A309; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block; transition: background-color 0.3s ease;">
                                {{ !empty($isReset) ? 'ĐẶT LẠI MẬT KHẨU' : 'THIẾT LẬP MẬT KHẨU NGAY' }}
                            </a>
                        </td>
                    </tr>
                </table>

                <div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 15px 20px; border-radius: 4px;">
                    <p style="color: #334155; font-size: 16px; line-height: 1.5; margin: 0;"><strong style="color: #1d4ed8;">Lưu ý bảo mật:</strong></p>
                    <ul style="color: #334155; font-size: 16px; line-height: 1.5; margin: 10px 0 0 20px; padding: 0;">
                        <li>Liên kết này chỉ có hiệu lực trong vòng <strong>{{ $expiresInHuman }}</strong>.</li>
                        <li>Không chia sẻ liên kết này với bất kỳ ai khác để tránh rủi ro bảo mật.</li>
                        <li>{{ !empty($isReset) ? 'Nếu bạn không yêu cầu đổi mật khẩu, vui lòng bỏ qua email.' : 'Nếu bạn không yêu cầu tài khoản này, vui lòng bỏ qua email.' }}</li>
                        <li>Nếu bạn cần hỗ trợ, vui lòng liên hệ <a href="mailto:{{ $systemEmail }}" style="color: #0961AA;">{{ $systemEmail }}</a></li>
                    </ul>

                </div>

                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 25px 0 0 0;">
                    Trân trọng,<br>
                    <strong style="color: #0961AA;">Ban Quản trị Website FITA VNUA</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f1f5f9; padding: 25px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="color: #64748b; font-size: 16px; line-height: 1.5; margin: 0 0 10px 0;">
                    © {{ date('Y') }} Khoa Công nghệ thông tin - Học viện Nông nghiệp Việt Nam.
                </p>

                <p style="color: #94a3b8; font-size: 14px; line-height: 1.5; margin: 0;">
                    Nếu nút bấm không hoạt động, hãy copy và dán đường dẫn sau vào trình duyệt:<br>
                    <a href="{{ $setupUrl }}" style="color: #3b82f6; text-decoration: underline; word-break: break-all;">
                        {{ $setupUrl }}
                    </a>
                </p>
            </td>
        </tr>
    </table>
</center>
</body>
</html>
