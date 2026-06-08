<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Veridex verification code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f7f5; padding: 40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="width: 520px; max-width: calc(100% - 32px); background-color: #ffffff; border-radius: 10px; border: 1px solid #dbe7df; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #052e1a; padding: 28px 40px; text-align: left;">
                            <div style="color: #ffffff; font-size: 25px; font-weight: 800; margin: 0; line-height: 1;">Veridex</div>
                            <div style="color: #9ee6bd; font-size: 12px; font-weight: 700; letter-spacing: 1.8px; margin-top: 10px; text-transform: uppercase;">Trusted e-invoicing</div>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="color: #10231a; font-size: 22px; line-height: 1.3; margin: 0 0 12px;">
                                @if($type === 'registration')
                                    Confirm your Veridex account
                                @else
                                    Complete your Veridex sign in
                                @endif
                            </h1>

                            <p style="color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 8px;">
                                @if($type === 'registration')
                                    Use this one-time code to verify your email and finish creating your account.
                                @elseif($type === 'password_reset')
                                    Use this one-time code to reset your Veridex password.
                                @else
                                    Use this one-time code to securely access your dashboard.
                                @endif
                            </p>

                            <!-- OTP Code -->
                            <div style="background-color: #ecfdf3; border: 1px solid #b7e4c7; border-radius: 10px; padding: 26px 20px; text-align: center; margin: 28px 0;">
                                <div style="color: #166534; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; margin-bottom: 10px; text-transform: uppercase;">Verification code</div>
                                <span style="font-size: 36px; font-weight: 800; letter-spacing: 10px; color: #052e1a; font-family: 'Courier New', monospace;">{{ $code }}</span>
                            </div>

                            <p style="color: #64748b; font-size: 14px; line-height: 1.5; margin: 0 0 6px;">
                                This code expires in <strong>5 minutes</strong>.
                            </p>
                            <p style="color: #64748b; font-size: 13px; line-height: 1.5; margin: 0;">
                                If you didn't request this code, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 40px; border-top: 1px solid #dbe7df; text-align: center;">
                            <p style="color: #64748b; font-size: 12px; margin: 0;">
                                &copy; {{ date('Y') }} Veridex. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
