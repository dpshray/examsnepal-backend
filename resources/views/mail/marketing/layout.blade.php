<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>ExamsNepal</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f2;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2521">
    @if($preheader)
        {{-- Inbox preview text; hidden in the body. --}}
        <div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
    @endif
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f2">
        <tr>
            <td align="center" style="padding:24px 12px">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px">
                    <tr>
                        <td style="padding:0 4px 16px;font-size:18px;font-weight:700;color:#16803c">ExamsNepal</td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border-radius:12px;padding:28px 28px 20px;font-size:15px;line-height:1.6">
                            {!! $body !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 8px;font-size:12px;line-height:1.5;color:#6b716c;text-align:center">
                            You're receiving this because you have an ExamsNepal account.<br>
                            {{ $postalAddress }}
                            @if($unsubscribeUrl)
                                <br><a href="{{ $unsubscribeUrl }}" style="color:#6b716c">Unsubscribe from these emails</a>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if($openPixel)
        <img src="{{ $openPixel }}" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px">
    @endif
</body>
</html>
