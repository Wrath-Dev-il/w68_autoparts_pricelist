<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>W68 Print Center</title>
    <style>
        :root {
            --green: #0a351f;
            --green-2: #0f4b2f;
            --maroon: #8b0015;
            --gold: #f0ba35;
            --cream: #fffbea;
            --paper: #ffffff;
            --line: #171717;
            --soft: #e2d7c5;
            --muted: #716a5d;
        }

        * { box-sizing: border-box; }

        html, body {
            min-height: 100%;
            margin: 0;
            background: #f0eadb;
            color: #151515;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            padding:
                max(8px, env(safe-area-inset-top))
                max(8px, env(safe-area-inset-right))
                max(8px, env(safe-area-inset-bottom))
                max(8px, env(safe-area-inset-left));
        }

        .print-center {
            width: min(680px, 100%);
            min-height: calc(100dvh - 16px);
            margin: 0 auto;
            padding: 12px;
            border: 3px solid #5c1b69;
            border-radius: 25px;
            background: var(--cream);
            box-shadow: inset 0 0 0 2px #101010;
        }

        .print-center-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 4px 8px 10px;
        }

        .brand strong {
            display: block;
            color: var(--green);
            font-size: 16px;
            letter-spacing: .6px;
        }

        .brand span {
            display: block;
            margin-top: 2px;
            color: var(--maroon);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .8px;
        }

        .mode-switch {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .mode-switch a {
            min-height: 34px;
            padding: 0 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--green);
            border-radius: 999px;
            background: #fff;
            color: var(--green);
            font-size: 9px;
            font-weight: 900;
            text-decoration: none;
        }

        .mode-switch a.active {
            background: var(--green);
            color: var(--gold);
        }

        .mode-switch a.disabled {
            opacity: .42;
            pointer-events: none;
        }

        .preview-shell {
            position: relative;
            min-height: 340px;
            height: min(49dvh, 520px);
            margin: 0 20px 10px;
            padding: 18px 18px 18px 18px;
            overflow: auto;
            border: 1px solid #d8ccb9;
            background: #d8cdbc;
            -webkit-overflow-scrolling: touch;
        }

        .preview-scrollbar {
            position: absolute;
            top: 10px;
            right: 7px;
            bottom: 10px;
            width: 15px;
            border-radius: 999px;
            background: var(--green);
            pointer-events: none;
        }

        .preview-scrollbar::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 28px;
            border-radius: 999px;
            background: var(--gold);
        }

        .print-document {
            width: 100%;
            min-width: 560px;
            padding: 18px;
            background: var(--paper);
            color: #000;
        }

        .doc-brand {
            margin-bottom: 15px;
            text-align: center;
        }

        .doc-brand strong {
            display: block;
            font-size: 19px;
        }

        .doc-brand span {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .7px;
        }

        .doc-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8px;
        }

        .doc-table th,
        .doc-table td {
            padding: 4px 4px;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        .doc-table th {
            border-bottom: 1px solid #000;
            font-size: 7px;
            text-align: center;
            white-space: nowrap;
        }

        .doc-table .num {
            text-align: right;
            white-space: nowrap;
        }

        .doc-total {
            margin-top: 12px;
            padding: 7px 0;
            display: grid;
            grid-template-columns: 1fr 130px;
            gap: 10px;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            font-size: 10px;
            font-weight: 900;
        }

        .doc-total span:first-child { text-align: right; }
        .doc-total span:last-child { text-align: right; }

        .control-stack {
            padding: 0 20px 12px;
        }

        .setting-row,
        .setting-pair {
            margin-top: 7px;
        }

        .setting-row {
            min-height: 36px;
            display: grid;
            grid-template-columns: 1fr 1.7fr 1fr;
            gap: 8px;
            align-items: center;
        }

        .setting-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .setting-pair > div {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 7px;
            align-items: center;
        }

        .setting-label {
            color: #161616;
            font-size: 9px;
            font-weight: 900;
            white-space: nowrap;
        }

        .pill {
            min-height: 30px;
            padding: 4px 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #111;
            border-radius: 999px;
            background: #ddd1bd;
            color: #111;
            font-size: 9px;
            font-weight: 900;
            text-align: center;
        }

        .pill-wide {
            grid-column: 2 / 4;
        }

        input[type="number"] {
            width: 100%;
            min-height: 30px;
            border: 2px solid #111;
            border-radius: 999px;
            background: #ddd1bd;
            text-align: center;
            font: 900 10px Arial, sans-serif;
            outline: none;
        }

        .toggle-row {
            margin: 12px 4px 10px;
            display: flex;
            justify-content: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .toggle-row label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 9px;
            font-weight: 900;
        }

        .toggle-row input {
            width: 16px;
            height: 16px;
            accent-color: var(--green);
        }

        .action {
            width: 100%;
            min-height: 42px;
            margin-top: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #111;
            border-radius: 0;
            font-size: 10px;
            font-weight: 1000;
            text-decoration: none;
            touch-action: manipulation;
            cursor: pointer;
        }

        .action-print {
            background: var(--green);
            color: var(--gold);
        }

        .action-fallback {
            background: #fff;
            color: var(--maroon);
            border-color: var(--maroon);
        }

        .action-back {
            background: #a90008;
            color: #ffd33d;
        }

        .device-note {
            margin: 7px 4px 0;
            color: var(--muted);
            font-size: 8px;
            font-weight: 700;
            line-height: 1.4;
            text-align: center;
        }

        #browser-print-output {
            display: none;
        }

        @media (max-width: 620px) {
            body { padding: 4px; }

            .print-center {
                min-height: calc(100dvh - 8px);
                padding: 8px;
                border-radius: 18px;
            }

            .print-center-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .mode-switch {
                width: 100%;
                justify-content: stretch;
            }

            .mode-switch a {
                flex: 1 1 0;
            }

            .preview-shell {
                height: 42dvh;
                min-height: 270px;
                margin-inline: 10px;
                padding: 12px 22px 12px 12px;
            }

            .control-stack { padding-inline: 10px; }

            .setting-row {
                grid-template-columns: 74px 1fr;
            }

            .setting-row .setting-label:first-child {
                grid-column: 1;
            }

            .setting-row .pill-wide {
                grid-column: 2;
            }

            .setting-pair {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .setting-pair > div {
                grid-template-columns: 1fr;
                gap: 4px;
            }
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            .print-center {
                display: none !important;
            }

            #browser-print-output {
                display: block !important;
            }

            #browser-print-output .print-document {
                min-width: 0 !important;
                width: 100% !important;
                padding: 0 !important;
                break-after: page;
                page-break-after: always;
            }

            #browser-print-output .print-document:last-child {
                break-after: auto;
                page-break-after: auto;
            }

            .doc-table {
                font-size: 9px;
            }

            .doc-table th {
                font-size: 8px;
            }
        }
    </style>
</head>
<body>
    <main class="print-center" data-print-center
          data-mode="{{ $mode }}"
          data-bridge-url="{{ $bridgeUrl }}"
          data-home-url="{{ $homeUrl }}">
        <header class="print-center-head">
            <div class="brand">
                <strong>W68 PRINT CENTER</strong>
                <span>ERW • SPECIAL STORE</span>
            </div>
            <nav class="mode-switch" aria-label="Cart print mode">
                <a href="{{ $allUrl }}" class="{{ $mode === 'all' ? 'active' : '' }}">
                    ALL ITEMS ({{ $allCount }})
                </a>
                <a href="{{ $selectedUrl }}"
                   class="{{ $mode === 'selected' ? 'active' : '' }} {{ $selectedCount === 0 ? 'disabled' : '' }}"
                   aria-disabled="{{ $selectedCount === 0 ? 'true' : 'false' }}">
                    SELECTED ONLY ({{ $selectedCount }})
                </a>
            </nav>
        </header>

        <section class="preview-shell" aria-label="Print preview">
            <div class="preview-scrollbar" aria-hidden="true"></div>
            <article class="print-document" data-print-document>
                <div class="doc-brand">
                    <strong>ERW</strong>
                    <span>SPECIAL STORE</span>
                </div>

                <table class="doc-table">
                    <thead>
                        <tr>
                            <th>PRODUCT CODE</th>
                            <th>PART NO.</th>
                            <th>DESCRIPTION</th>
                            <th>QTY</th>
                            <th>UNIT PRICE</th>
                            @if ($showLess)
                                <th>LESS</th>
                            @endif
                            <th>SUB TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['product_code'] !== '' ? $item['product_code'] : '—' }}</td>
                                <td>{{ $item['part_number'] !== '' ? $item['part_number'] : '—' }}</td>
                                <td>{{ $item['description'] !== '' ? $item['description'] : '—' }}</td>
                                <td class="num">{{ $item['qty'] }}</td>
                                <td class="num">{{ number_format($item['unit_price'], 2) }}</td>
                                @if ($showLess)
                                    <td class="num">
                                        {{ $item['discount_percent'] > 0 ? rtrim(rtrim(number_format($item['discount_percent'], 2, '.', ''), '0'), '.') . '%' : '' }}
                                    </td>
                                @endif
                                <td class="num">{{ number_format($item['subtotal'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="doc-total">
                    <span>TOTAL</span>
                    <span>{{ number_format($total, 2) }}</span>
                </div>
            </article>
        </section>

        <section class="control-stack">
            <div class="setting-row">
                <span class="setting-label">DESTINATION</span>
                <span class="pill pill-wide" data-destination>AUTO DETECT</span>
            </div>

            <div class="setting-pair">
                <div>
                    <span class="setting-label">PAGES</span>
                    <span class="pill">ALL</span>
                </div>
                <div>
                    <span class="setting-label">COPIES</span>
                    <input type="number" min="1" max="20" value="1" inputmode="numeric" data-copies aria-label="Copies">
                </div>
            </div>

            <div class="setting-row">
                <span class="setting-label">PAPER SIZE</span>
                <span class="pill pill-wide">A4</span>
            </div>

            <div class="setting-pair">
                <div>
                    <span class="setting-label">MARGINS</span>
                    <span class="pill">10 MM</span>
                </div>
                <div>
                    <span class="setting-label">SCALE</span>
                    <span class="pill">100%</span>
                </div>
            </div>

            <div class="toggle-row">
                <label>
                    <input type="checkbox" data-headers-footers>
                    HEADERS &amp; FOOTERS
                </label>
                <label>
                    <input type="checkbox" data-background-graphics>
                    BACKGROUND GRAPHICS
                </label>
            </div>

            <button type="button" class="action action-print" data-print>PRINT</button>
            <button type="button" class="action action-fallback" data-system-print hidden>USE SYSTEM PRINT / AIRPRINT</button>
            <a href="{{ $homeUrl }}" class="action action-back">GO BACK TO HOME</a>

            <p class="device-note" data-device-note>
                W68 is checking the print method for this device.
            </p>
        </section>
    </main>

    <section id="browser-print-output" aria-hidden="true"></section>

    <script>
        (function () {
            'use strict';

            var center = document.querySelector('[data-print-center]');
            var source = document.querySelector('[data-print-document]');
            var output = document.getElementById('browser-print-output');
            var printButton = document.querySelector('[data-print]');
            var systemPrintButton = document.querySelector('[data-system-print]');
            var copiesInput = document.querySelector('[data-copies]');
            var destination = document.querySelector('[data-destination]');
            var note = document.querySelector('[data-device-note]');

            if (!center || !source || !output || !printButton) return;

            // W68_PRINT_CENTER_BRIDGE_ONLY_20261015
            // The primary PRINT action must never call window.print().
            var ua = navigator.userAgent || '';
            var platform = navigator.userAgentData && navigator.userAgentData.platform
                ? String(navigator.userAgentData.platform)
                : '';
            var touchPoints = Number(navigator.maxTouchPoints || 0);
            var appleTouch = /iPad|iPhone|iPod/i.test(ua)
                || (/Macintosh/i.test(ua) && touchPoints > 1);
            var androidLike = !appleTouch && (
                /Android|Xiaomi|MIUI/i.test(ua)
                || /Android/i.test(platform)
                || (/Linux/i.test(ua) && touchPoints > 1)
            );
            var windowsLike = /Windows NT/i.test(ua) || /Windows/i.test(platform);

            destination.textContent = 'W68 PRINTER BRIDGE';

            if (appleTouch) {
                note.textContent =
                    'W68 Bridge is the primary print method. AirPrint is available only from the separate fallback button below.';
                if (systemPrintButton) {
                    systemPrintButton.hidden = false;
                    systemPrintButton.textContent = 'AIRPRINT FALLBACK';
                }
            } else if (windowsLike) {
                note.textContent =
                    'PRINT uses W68 Printer Bridge. Windows system print will not open automatically.';
                if (systemPrintButton) {
                    systemPrintButton.hidden = false;
                    systemPrintButton.textContent = 'WINDOWS PRINT FALLBACK';
                }
            } else if (androidLike) {
                note.textContent =
                    'PRINT opens W68 Printer Bridge and sends directly to the selected reachable printer.';
            } else {
                note.textContent =
                    'PRINT uses W68 Printer Bridge. Browser/system printing is available only as a fallback.';
                if (systemPrintButton) systemPrintButton.hidden = false;
            }

            function safeCopies() {
                var copies = Math.round(Number(copiesInput && copiesInput.value ? copiesInput.value : 1));
                if (!isFinite(copies)) copies = 1;
                copies = Math.max(1, Math.min(20, copies));
                if (copiesInput) copiesInput.value = String(copies);
                return copies;
            }

            function buildBrowserPrintOutput() {
                output.innerHTML = '';
                var copies = safeCopies();

                for (var i = 0; i < copies; i += 1) {
                    var clone = source.cloneNode(true);
                    clone.removeAttribute('data-print-document');
                    output.appendChild(clone);
                }
            }

            function systemPrintFallback() {
                buildBrowserPrintOutput();
                try {
                    window.focus();
                    window.print();
                } catch (error) {
                    alert('Unable to open the system printer on this device.');
                }
            }

            function openBridge() {
                if (!center.dataset.bridgeUrl) {
                    alert('W68 Printer Bridge is not configured for this device.');
                    return;
                }

                var target = new URL(center.dataset.bridgeUrl, window.location.href);
                target.searchParams.set('mode', center.dataset.mode || 'all');
                target.searchParams.set('copies', String(safeCopies()));
                window.location.href = target.toString();
            }

            printButton.addEventListener('click', function (event) {
                event.preventDefault();
                openBridge();
            }, false);

            if (systemPrintButton) {
                systemPrintButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    systemPrintFallback();
                }, false);
            }

            window.addEventListener('afterprint', function () {
                output.innerHTML = '';
            });

            copiesInput && copiesInput.addEventListener('change', safeCopies);
        })();
    </script>
</body>
</html>
