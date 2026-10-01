<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>W68 Printer Bridge | {{ $invoiceNo ?: 'Invoice' }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; font-family: Arial, Helvetica, sans-serif; }
        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #fff;
            background: radial-gradient(circle at center, #0b6b50 0%, #064e3b 57%, #043f31 100%);
        }
        .card { width: min(560px, 94vw); display: grid; justify-items: center; gap: 12px; text-align: center; }
        .radar {
            position: relative;
            width: min(330px, 75vw);
            aspect-ratio: 1;
            overflow: hidden;
            border: 1px solid rgba(255,227,110,.45);
            border-radius: 50%;
            background:
                radial-gradient(circle, transparent 0 24%, rgba(255,227,110,.20) 24.5% 25%, transparent 25.5% 49%, rgba(255,227,110,.22) 49.5% 50%, transparent 50.5% 74%, rgba(255,227,110,.24) 74.5% 75%, transparent 75.5%);
            box-shadow: inset 0 0 70px rgba(255,227,110,.06), 0 0 42px rgba(0,0,0,.14);
        }
        .axis { position: absolute; left: 50%; top: 50%; background: rgba(255,227,110,.22); transform: translate(-50%, -50%); }
        .axis.x { width: 100%; height: 1px; }
        .axis.y { width: 1px; height: 100%; }
        .sweep {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 46%;
            height: 3px;
            transform-origin: 0 50%;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(255,227,110,.08), rgba(255,227,110,.95));
            box-shadow: 0 0 14px rgba(255,227,110,.55);
            animation: scan 1.1s linear infinite;
            will-change: transform;
        }
        .sweep::after {
            content: "";
            position: absolute;
            right: -4px;
            top: 50%;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #ffe36e;
            transform: translateY(-50%);
            box-shadow: 0 0 12px rgba(255,227,110,.85);
        }
        .printer {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 66px;
            height: 66px;
            display: grid;
            place-items: center;
            border: 3px solid #ffe36e;
            border-radius: 50%;
            background: #064e3b;
            color: #ffe36e;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 0 8px rgba(255,227,110,.10), 0 0 28px rgba(255,227,110,.22);
            animation: pulse .95s ease-in-out infinite alternate;
        }
        .printer svg { width: 34px; height: 34px; fill: currentColor; }
        h1 { margin: 2px 0 0; font-size: 21px; letter-spacing: .8px; }
        .subtitle { color: #ffe36e; font-size: 12px; font-weight: 900; letter-spacing: .45px; }
        p { max-width: 480px; margin: 0; color: #e5f4ef; font-size: 11px; line-height: 1.5; }
        .actions { width: min(440px, 100%); display: grid; gap: 9px; margin-top: 6px; }
        .button {
            min-height: 48px;
            display: grid;
            place-items: center;
            border: 2px solid #ffe36e;
            border-radius: 11px;
            background: #ffe36e;
            color: #064e3b;
            text-decoration: none;
            font-size: 11px;
            font-weight: 1000;
            letter-spacing: .6px;
            cursor: pointer;
        }
        .button.secondary { background: transparent; color: #ffe36e; }
        .expiry { color: #b9d9cd; font-size: 9px; }
        @keyframes scan {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes pulse {
            from { transform: translate(-50%, -50%) scale(.94); }
            to { transform: translate(-50%, -50%) scale(1.05); }
        }
        @media (prefers-reduced-motion: reduce) {
            .sweep { animation-duration: 2.4s; }
            .printer { animation-duration: 1.8s; }
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="radar" aria-hidden="true">
            <span class="axis x"></span>
            <span class="axis y"></span>
            <span class="sweep"></span>
            <span class="printer">
                <svg viewBox="0 0 24 24"><path d="M7 7V3h10v4M7 17v4h10v-4M6 9h12a3 3 0 0 1 3 3v4h-4v-3H7v3H3v-4a3 3 0 0 1 3-3Zm2 6h8v4H8z"/></svg>
            </span>
        </div>
        <h1>OPENING W68 PRINTER BRIDGE</h1>
        <div class="subtitle">INVOICE {{ $invoiceNo ?: 'W68' }}</div>
        <p>The installed W68 Printer Bridge discovers real installed and Wi-Fi IPP/AirPrint printers. Safari itself cannot scan your local printer network. Printer/network credentials stay on this device and are never sent to W68.</p>
        <p id="ios-note" hidden style="color:#ffe36e;font-weight:800;">On iPad/iPhone, install the updated W68 Printer Bridge first, then tap OPEN W68 PRINTER BRIDGE. The first scan must be allowed to access your Local Network.</p>
        <div class="actions">
            <a class="button" id="open-bridge" href="{{ $deepLink }}">OPEN W68 PRINTER BRIDGE</a>
            <a class="button secondary" href="{{ $fallbackUrl }}">USE BROWSER PRINT</a>
        </div>
        <div class="expiry">Secure print job expires {{ $expiresAt->format('Y-m-d H:i:s') }}.</div>
    </main>
    <script>
        (function () {
            var bridge = document.getElementById('open-bridge');
            if (!bridge) return;

            var isIOS =
                /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

            var note = document.getElementById('ios-note');

            if (isIOS) {
                if (note) note.hidden = false;

                // Do not automatically navigate Safari to a custom scheme.
                // If the native app is missing or an old build did not
                // register w68print://, Safari reports "address is invalid".
                return;
            }

            window.setTimeout(function () {
                try { window.location.href = bridge.href; } catch (error) {}
            }, 180);
        })();
    </script>
</body>
</html>
