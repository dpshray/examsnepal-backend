<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $resubscribed ? 'Subscribed again' : 'Unsubscribed' }} · ExamsNepal</title>
    <style>
        :root { --bg: #f4f5f2; --card: #ffffff; --ink: #1f2521; --muted: #5f665f; --brand: #16803c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #151816; --card: #1f2421; --ink: #eef1ee; --muted: #a9b0aa; --brand: #3fb468; } }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        main { max-width: 440px; margin: 12vh auto 0; padding: 0 16px; }
        .card { background: var(--card); border-radius: 12px; padding: 28px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { color: var(--muted); line-height: 1.55; margin: 0 0 16px; }
        button { background: none; border: 1px solid var(--brand); color: var(--brand); border-radius: 8px; padding: 10px 16px; font-size: 14px; cursor: pointer; }
        a { color: var(--brand); }
    </style>
</head>
<body>
<main>
    <div class="card">
        @if($resubscribed)
            <h1>You're subscribed again</h1>
            <p>You'll get study reminders and progress reports from ExamsNepal again.</p>
        @else
            <h1>You've been unsubscribed</h1>
            <p>You won't get any more reminder or offer emails from ExamsNepal. Emails you ask for, like password resets and payment receipts, will still arrive.</p>
            <form method="post" action="{{ URL::signedRoute('marketing.resubscribe', ['send' => $send->id]) }}">
                <button type="submit">Unsubscribed by mistake? Subscribe again</button>
            </form>
        @endif
        <p style="margin:20px 0 0"><a href="{{ config('marketing.site_url') }}">Go to ExamsNepal</a></p>
    </div>
</main>
</body>
</html>
