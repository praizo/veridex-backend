<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Veridex verification code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6fb; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f6fb; padding: 40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="width: 520px; max-width: calc(100% - 32px); background-color: #ffffff; border-radius: 10px; border: 1px solid #dbe3f1; box-shadow: 0 12px 30px rgba(10, 29, 67, 0.10); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #0a1d43; padding: 28px 40px; text-align: left;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="width: 32px; vertical-align: middle;">
                                        <svg width="28" height="28" viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path d="M3 5.5L13 2L23 5.5V13C23 18.5 18.5 22.5 13 24C7.5 22.5 3 18.5 3 13V5.5Z" stroke="#ffffff" stroke-width="1.6" stroke-linejoin="round"/>
                                            <path d="M8 12.5L11.5 16L18 9.5" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </td>
                                    <td style="vertical-align: middle; padding-left: 10px;">
                                        <div style="color: #ffffff; font-size: 25px; font-weight: 800; margin: 0; line-height: 1;">Veridex<span style="font-size: 11px; font-weight: 500; opacity: 0.7;">&trade;</span></div>
                                        <div style="color: #c8d6f0; font-size: 12px; font-weight: 700; letter-spacing: 1.8px; margin-top: 8px; text-transform: uppercase;">Trusted e-invoicing</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="color: #0a1d43; font-size: 22px; line-height: 1.3; margin: 0 0 12px;">
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
                            <div style="background-color: #f0f4fb; border: 1px solid #c8d6f0; border-radius: 10px; padding: 26px 20px; text-align: center; margin: 28px 0;">
                                <div style="color: #0a1d43; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; margin-bottom: 10px; text-transform: uppercase;">Verification code</div>
                                <span style="font-size: 36px; font-weight: 800; letter-spacing: 10px; color: #0a1d43; font-family: 'Courier New', monospace;">{{ $code }}</span>
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
                        <td style="background-color: #f8fafc; padding: 20px 40px; border-top: 1px solid #dbe3f1; text-align: center;">
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
