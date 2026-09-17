<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>W68 Customer Authorization Required</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f7f7f5;
            color: #202722;
            font-family: Arial, Helvetica, sans-serif;
        }
        .card {
            width: min(520px, 100%);
            background: #fff;
            border: 1px solid #e8e8e4;
            border-radius: 24px;
            padding: 36px;
            box-shadow: 0 18px 60px rgba(34, 44, 37, .10);
            text-align: center;
        }
        .mark {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            border-radius: 20px;
            display: grid;
            place-items: center;
            background: #6f1725;
            color: #fff;
            font-weight: 900;
            font-size: 22px;
            letter-spacing: 1px;
        }
        h1 { margin: 0 0 12px; font-size: 24px; }
        p { margin: 0; color: #69726c; font-size: 14px; line-height: 1.65; }
        .notice {
            margin-top: 22px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #faf3f4;
            color: #7b2634;
            font-weight: 700;
            font-size: 13px;
            line-height: 1.55;
        }
        .help { margin-top: 20px; font-size: 12px; color: #89908b; }
    </style>
</head>
<body>
    <main class="card">
        <div class="mark">W68</div>
        <h1>Customer Authorization Required</h1>
        <p>This customer portal is available only through an active authorization link or QR code generated from W68 Customer Master.</p>
        <div class="notice">{{ $authorizationMessage ?? 'Please request an authorization link or QR code from W68.' }}</div>
        <p class="help">If your link expired or was deleted, W68 can generate a new authorization or change its validity.</p>
    </main>
</body>
</html>
