<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Invitation</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f3f4f6;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 20px 0;">
        <tr>
            <td align="center">

                <!-- Main Container -->
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td
                            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; text-align: center;">
                            <div
                                style="background: rgba(255,255,255,0.15); display: inline-block; padding: 10px 20px; border-radius: 25px; margin-bottom: 15px;">
                                <span style="color: #ffffff; font-size: 13px; font-weight: 600; letter-spacing: 1px;">📧
                                    EXAM INVITATION</span>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                You're Invited!
                            </h1>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 40px 30px;">

                            <!-- Greeting -->
                            <p style="font-size: 16px; color: #1f2937; margin: 0 0 20px 0;">
                                Hello <strong style="color: #667eea;">{{ $participantName }}</strong>,
                            </p>

                            <p style="font-size: 15px; color: #4b5563; margin: 0 0 25px 0; line-height: 1.6;">
                                <strong>{{ $corporateName }}</strong> has invited you to participate in an important
                                examination. Please review the details below:
                            </p>

                            <!-- Exam Card -->
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background: linear-gradient(135deg, #f0f4ff 0%, #e9d5ff 100%); border-left: 4px solid #667eea; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <h2
                                            style="color: #5b21b6; margin: 0 0 10px 0; font-size: 22px; font-weight: 700;">
                                            📝 {{ $examTitle }}
                                        </h2>

                                        @if ($examDescription)
                                            <p
                                                style="color: #6b7280; margin: 10px 0 0 0; font-size: 14px; line-height: 1.6;">
                                                {{ $examDescription }}
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <!-- Exam Schedule -->
                            @if ($startDate && $endDate)
                                <table width="100%" cellpadding="0" cellspacing="0"
                                    style="background: #fef3c7; border: 2px solid #fbbf24; border-radius: 8px; margin: 25px 0;">
                                    <tr>
                                        <td style="padding: 20px;">
                                            <p style="margin: 0 0 12px 0; font-size: 16px;">
                                                <span style="font-size: 20px;">⏰</span>
                                                <strong style="color: #92400e;">Exam Schedule</strong>
                                            </p>
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td
                                                        style="padding: 8px 0; color: #78350f; font-weight: 600; width: 80px;">
                                                        Start:</td>
                                                    <td style="padding: 8px 0; color: #92400e;">
                                                        {{ \Carbon\Carbon::parse($startDate)->format('M d, Y • h:i A') }}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 0; color: #78350f; font-weight: 600;">End:
                                                    </td>
                                                    <td style="padding: 8px 0; color: #92400e;">
                                                        {{ \Carbon\Carbon::parse($endDate)->format('M d, Y • h:i A') }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <!-- Login Credentials -->
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px; margin: 30px 0;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <p style="margin: 0 0 18px 0; font-size: 18px;">
                                            <span style="font-size: 24px;">🔐</span>
                                            <strong style="color: #111827;">Your Login Credentials</strong>
                                        </p>

                                        <table width="100%" cellpadding="0" cellspacing="0"
                                            style="background: #ffffff; border-radius: 8px; margin-bottom: 15px;">
                                            <tr>
                                                <td style="padding: 18px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td
                                                                style="padding: 10px 0; color: #6b7280; font-size: 14px; width: 100px;">
                                                                📧 Email:</td>
                                                            <td
                                                                style="padding: 10px 0; color: #111827; font-weight: 600; font-size: 14px;">
                                                                {{ $loginEmail }}</td>
                                                        </tr>
                                                        @if ($loginPhone)
                                                            <tr>
                                                                <td
                                                                    style="padding: 10px 0; color: #6b7280; font-size: 14px;">
                                                                    📱 Phone:</td>
                                                                <td
                                                                    style="padding: 10px 0; color: #111827; font-weight: 600; font-size: 14px;">
                                                                    {{ $loginPhone }}</td>
                                                            </tr>
                                                        @endif
                                                        <tr>
                                                            <td
                                                                style="padding: 10px 0; color: #6b7280; font-size: 14px;">
                                                                🔑 Password:</td>
                                                            <td style="padding: 10px 0;">
                                                                <span
                                                                    style="background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 4px; font-size: 14px; font-weight: 600;">{{ $loginPassword }}</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <table width="100%" cellpadding="0" cellspacing="0"
                                            style="background: #fef2f2; border-left: 3px solid #ef4444; border-radius: 6px;">
                                            <tr>
                                                <td style="padding: 12px 15px;">
                                                    <p
                                                        style="margin: 0; font-size: 13px; color: #991b1b; line-height: 1.5;">
                                                        <strong>⚠️ Important:</strong> Please keep these credentials
                                                        confidential and do not share them with anyone.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 35px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $examUrl }}"
                                            style="display: inline-block; background: #667eea; color: #ffffff; text-decoration: none; padding: 15px 40px; border-radius: 8px; font-weight: 600; font-size: 16px;">
                                            🚀 Start Your Exam Now
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Important Reminders -->
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 8px; margin: 30px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0; font-size: 14px; color: #1e40af; line-height: 1.8;">
                                            <strong>📌 Important Reminders:</strong><br>
                                            • Complete the exam before the deadline<br>
                                            • Ensure a stable internet connection<br>
                                            • Use a desktop or laptop for best experience<br>
                                            • Contact support if you face technical issues
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Footer Message -->
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #e5e7eb;">
                                <tr>
                                    <td>
                                        <p style="font-size: 14px; color: #6b7280; margin: 0 0 10px 0;">
                                            If you have any questions or need assistance, please don't hesitate to reach
                                            out to your exam administrator.
                                        </p>

                                        <p style="font-size: 15px; color: #1f2937; margin: 20px 0 0 0;">
                                            Best regards,<br>
                                            <strong style="color: #667eea;">{{ config('app.name') }} Team</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="background: #f9fafb; padding: 25px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.6;">
                                This is an automated message. Please do not reply to this email.<br>
                                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
