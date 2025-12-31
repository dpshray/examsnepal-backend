{{-- Hello,  {{ $user }},
Your token for password reset is {{ $token }}(this token expires at {{ $expiration_period }})--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset Verification</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, Helvetica, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8; padding:20px;">
    <tr>
        <td align="center">

            <!-- Container -->
            <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">

                <!-- Header -->
                <tr>
                    <td style="background-color:#1d4ed8; padding:20px; text-align:center; color:#ffffff;">
                        <h1 style="margin:0; font-size:22px;">ExamsNepal</h1>
                        <p style="margin:5px 0 0; font-size:13px;">Security Notice</p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:30px; color:#333333; font-size:14px; line-height:1.6;">
                        <p>Hello <strong>{{ $user }}</strong>,</p>

                        <p>
                            You recently requested to reset the password for your
                            <strong>ExamsNepal</strong> account.
                            Use the one-time verification code below to securely complete your password reset.
                        </p>

                        <!-- Code Box -->
                        <div style="margin:25px 0; text-align:center;">
                            <div style="font-size:28px; letter-spacing:4px; font-weight:bold; background:#f1f5f9; padding:15px; border-radius:6px; display:inline-block; color:#1d4ed8;">
                                {{ $token }}
                            </div>
                            <p style="margin-top:10px; font-size:12px; color:#6b7280;">
                                One-time use · Expires on <strong>{{ $expiration_period }}</strong>
                            </p>
                        </div>

                        <p>
                            Enter this code on the password reset page.
                            <strong>Do not share this code</strong> with anyone or use it on untrusted websites.
                        </p>

                        <p style="margin-top:20px;">
                            If you did not request a password reset, you can safely ignore this email.
                            Your account will remain secure and your current password will continue to work.
                        </p>

                        <p style="margin-top:30px;">
                            Regards,<br>
                            <strong>ExamsNepal Team</strong>
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f9fafb; padding:15px; text-align:center; font-size:12px; color:#6b7280;">
                        This message was sent by <strong>ExamsNepal</strong>.<br>
                        For support or more information, visit
                        <a href="https://examsnepal.com" style="color:#1d4ed8; text-decoration:none;">
                            examsnepal.com
                        </a>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
