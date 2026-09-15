<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reset your RushPi password</title>
</head>

<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="background:#f1f5f9;"
    >
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="max-width:600px;overflow:hidden;border-radius:22px;background:#ffffff;box-shadow:0 15px 40px rgba(15,23,42,.10);"
                >
                    <tr>
                        <td
                            align="center"
                            style="background:#0754d8;padding:25px 30px;"
                        >
                            <img
                                src="https://rushpi.asyncafrica.com/rushpii-01.png"
                                alt="RushPi"
                                width="180"
                                style="display:block;width:180px;max-width:100%;height:auto;border:0;"
                            >
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:36px 34px;">
                            <p style="margin:0 0 10px;color:#0754d8;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;">
                                Account recovery
                            </p>

                            <h1 style="margin:0 0 22px;color:#0f172a;font-size:28px;line-height:1.25;">
                                Reset your password
                            </h1>

                            <p style="margin:0 0 16px;color:#475569;font-size:15px;line-height:1.7;">
                                Hello {{ $name }},
                            </p>

                            <p style="margin:0 0 16px;color:#475569;font-size:15px;line-height:1.7;">
                                We received a request to reset the password for your RushPi account.
                            </p>

                            <p style="margin:0 0 26px;color:#475569;font-size:15px;line-height:1.7;">
                                Click the button below to create a new password.
                            </p>

                            <table
                                role="presentation"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                style="margin:0 0 27px;"
                            >
                                <tr>
                                    <td
                                        align="center"
                                        style="border-radius:999px;background:#0754d8;"
                                    >
                                        <a
                                            href="{{ $resetUrl }}"
                                            style="display:inline-block;border-radius:999px;padding:15px 28px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;"
                                        >
                                            Reset my password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin:0 0 22px;border-radius:14px;background:#eff6ff;padding:15px 17px;color:#1e40af;font-size:13px;line-height:1.6;">
                                For your security, do not share this link with anyone.
                            </div>

                            <p style="margin:0 0 10px;color:#64748b;font-size:13px;line-height:1.6;">
                                If you did not request a password reset, you can safely ignore this email.
                            </p>

                            <p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">
                                If the button does not work, copy and paste this address into your browser:
                            </p>

                            <p style="margin:8px 0 0;word-break:break-all;color:#0754d8;font-size:12px;line-height:1.6;">
                                <a
                                    href="{{ $resetUrl }}"
                                    style="color:#0754d8;"
                                >
                                    {{ $resetUrl }}
                                </a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td
                            align="center"
                            style="border-top:1px solid #e2e8f0;padding:22px 30px;color:#64748b;font-size:12px;line-height:1.6;"
                        >
                            <p style="margin:0;">
                                © {{ date('Y') }} RushPi. All rights reserved.
                            </p>

                            @if ($supportEmail)
                                <p style="margin:5px 0 0;">
                                    Support:
                                    <a
                                        href="mailto:{{ $supportEmail }}"
                                        style="color:#0754d8;text-decoration:none;"
                                    >
                                        {{ $supportEmail }}
                                    </a>
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
