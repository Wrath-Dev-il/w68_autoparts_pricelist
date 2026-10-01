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

        .card {
            width: min(560px, 94vw);
            display: grid;
            justify-items: center;
            gap: 12px;
            text-align: center;
        }

        .radar {
            position: relative;
            width: min(320px, 72vw);
            aspect-ratio: 1;
            overflow: hidden;
            border: 1px solid rgba(255,227,110,.45);
            border-radius: 50%;
            background:
                radial-gradient(circle, transparent 0 24%, rgba(255,227,110,.20) 24.5% 25%, transparent 25.5% 49%, rgba(255,227,110,.22) 49.5% 50%, transparent 50.5% 74%, rgba(255,227,110,.24) 74.5% 75%, transparent 75.5%);
            box-shadow: inset 0 0 70px rgba(255,227,110,.06), 0 0 42px rgba(0,0,0,.14);
        }

        .axis {
            position: absolute;
            left: 50%;
            top: 50%;
            background: rgba(255,227,110,.22);
            transform: translate(-50%, -50%);
        }

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
            animation: scan 3.4s linear infinite;
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
        }

        .printer svg { width: 34px; height: 34px; fill: currentColor; }

        h1 { margin: 2px 0 0; font-size: 21px; letter-spacing: .8px; }
        .subtitle { color: #ffe36e; font-size: 12px; font-weight: 900; letter-spacing: .45px; }
        p { max-width: 480px; margin: 0; color: #e5f4ef; font-size: 11px; line-height: 1.5; }

        .actions {
            width: min(440px, 100%);
            display: grid;
            gap: 9px;
            margin-top: 6px;
        }

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

        .button.secondary {
            background: transparent;
            color: #ffe36e;
        }

        .button[aria-disabled="true"] {
            opacity: .5;
            cursor: not-allowed;
        }

        .expiry { color: #b9d9cd; font-size: 9px; }

        .install-modal {
            position: fixed;
            inset: 0;
            z-index: 20;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(2,30,23,.82);
        }

        .install-modal[hidden] { display: none !important; }

        .install-card {
            width: min(460px, 94vw);
            padding: 22px;
            display: grid;
            gap: 13px;
            border: 1px solid rgba(255,227,110,.48);
            border-radius: 16px;
            background: #064e3b;
            box-shadow: 0 22px 65px rgba(0,0,0,.34);
            text-align: center;
        }

        .install-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: rgba(255,227,110,.12);
            color: #ffe36e;
        }

        .install-icon svg { width: 34px; height: 34px; fill: currentColor; }

        .install-card h2 {
            margin: 0;
            color: #ffe36e;
            font-size: 18px;
        }

        .install-card p {
            margin: 0 auto;
            color: #e5f4ef;
        }

        .install-error {
            color: #ffd1d1;
            font-size: 10px;
            font-weight: 800;
        }

        @keyframes scan {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    @php
        $iosInstall = $installUrls['ios'] ?? '';
        $androidInstall = $installUrls['android'] ?? '';
        $windowsInstall = $installUrls['windows'] ?? '';
    @endphp

    <main class="card">
        <div class="radar" aria-hidden="true">
            <span class="axis x"></span>
            <span class="axis y"></span>
            <span class="sweep"></span>
            <span class="printer">
                <svg viewBox="0 0 24 24"><path d="M7 7V3h10v4M7 17v4h10v-4M6 9h12a3 3 0 0 1 3 3v4h-4v-3H7v3H3v-4a3 3 0 0 1 3-3Zm2 6h8v4H8z"/></svg>
            </span>
        </div>

        <h1>W68 PRINTER BRIDGE</h1>
        <div class="subtitle">INVOICE {{ $invoiceNo ?: 'W68' }}</div>
        <p>
            W68 Printer Bridge is required for printer discovery and direct printing.
            Browser printing has been disabled for W68 receipts.
        </p>

        <div class="actions">
            <a class="button" id="open-bridge" href="{{ $deepLink }}">OPEN W68 PRINTER BRIDGE</a>
            <button class="button secondary" type="button" id="show-install">INSTALL PRINTER BRIDGE</button>
        </div>

        <div class="expiry">Secure print job expires {{ $expiresAt->format('Y-m-d H:i:s') }}.</div>
    </main>

    <section class="install-modal" id="install-modal" hidden aria-hidden="true">
        <div class="install-card" role="dialog" aria-modal="true" aria-labelledby="install-title">
            <span class="install-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3v10m0 0 4-4m-4 4-4-4M5 16v4h14v-4"/></svg>
            </span>

            <h2 id="install-title">W68 PRINTER BRIDGE REQUIRED</h2>
            <p>
                To use PRINT RECEIPT, install W68 Printer Bridge once on this device.
                After installation, W68 can scan available printers and print directly without the browser print window.
            </p>

            <a
                class="button"
                id="install-bridge"
                href="#"
                data-ios="{{ $iosInstall }}"
                data-android="{{ $androidInstall }}"
                data-windows="{{ $windowsInstall }}"
            >INSTALL</a>

            <button class="button secondary" type="button" id="already-installed">I ALREADY INSTALLED IT</button>
            <div class="install-error" id="install-error" hidden></div>
        </div>
    </section>

    <script>
        (function () {
            var openBridge = document.getElementById('open-bridge');
            var showInstall = document.getElementById('show-install');
            var modal = document.getElementById('install-modal');
            var install = document.getElementById('install-bridge');
            var alreadyInstalled = document.getElementById('already-installed');
            var installError = document.getElementById('install-error');

            function platform() {
                var ua = navigator.userAgent || '';
                var isiPad =
                    /iPad|iPhone|iPod/.test(ua) ||
                    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

                if (isiPad) return 'ios';
                if (/Android/i.test(ua)) return 'android';
                if (/Windows/i.test(ua)) return 'windows';
                return 'windows';
            }

            function installerUrl() {
                if (!install) return '';
                return (install.getAttribute('data-' + platform()) || '').trim();
            }

            function showInstallModal() {
                if (!modal) return;

                var url = installerUrl();

                if (install) {
                    install.href = url || '#';
                    install.setAttribute('aria-disabled', url ? 'false' : 'true');
                }

                if (installError) {
                    installError.hidden = !!url;
                    installError.textContent = url
                        ? ''
                        : 'The installer for this device has not been published by W68 yet.';
                }

                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
            }

            function hideInstallModal() {
                if (!modal) return;
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
            }

            if (showInstall) {
                showInstall.addEventListener('click', showInstallModal, false);
            }

            if (install) {
                install.addEventListener('click', function (event) {
                    var url = installerUrl();

                    if (!url) {
                        event.preventDefault();
                        showInstallModal();
                        return;
                    }

                    try {
                        localStorage.setItem('w68PrinterBridgeInstallStarted', '1');
                    } catch (error) {}
                }, false);
            }

            if (alreadyInstalled) {
                alreadyInstalled.addEventListener('click', function () {
                    try {
                        localStorage.setItem('w68PrinterBridgeInstalled', '1');
                    } catch (error) {}

                    hideInstallModal();
                }, false);
            }

            if (openBridge) {
                openBridge.addEventListener('click', function () {
                    try {
                        localStorage.setItem('w68PrinterBridgeInstalled', '1');
                    } catch (error) {}
                }, false);
            }

            var remembered = false;

            try {
                remembered = localStorage.getItem('w68PrinterBridgeInstalled') === '1';
            } catch (error) {}

            if (!remembered) {
                window.setTimeout(showInstallModal, 250);
            }
        })();
    </script>
</body>
</html>

{{-- W68_PRINTER_BRIDGE_REQUIRED_V125_20261001 --}}
