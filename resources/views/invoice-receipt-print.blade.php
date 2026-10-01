@php
    $receiptDate = trim((string) ($invoiceReceipt['date'] ?? ''));
    if ($receiptDate !== '') {
        try {
            $receiptDate = \Carbon\Carbon::parse($receiptDate)->format('d-m-Y');
        } catch (\Throwable $error) {
            // Keep the raw W68 value.
        }
    }

    $showDiscountColumn = $items->contains(
        fn (array $item): bool => abs((float) ($item['discountPercent'] ?? 0)) > 0.000001
    );

    $formatQty = static function ($value): string {
        $number = (float) $value;
        return floor($number) == $number
            ? number_format($number, 0, '.', '')
            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    };

    $normalizeText = static function ($value): string {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    };

    $alreadyIncluded = static function ($existing, $candidate) use ($normalizeText): bool {
        $existingNormalized = $normalizeText($existing);
        $candidateNormalized = $normalizeText($candidate);
        if ($candidateNormalized === '') return true;
        if ($existingNormalized === '') return false;
        if (str_contains($existingNormalized, $candidateNormalized)) return true;

        $existingTokens = array_values(array_unique(array_filter(explode(' ', $existingNormalized))));
        $candidateTokens = array_values(array_unique(array_filter(explode(' ', $candidateNormalized))));
        if ($candidateTokens === []) return true;

        $matchedTokens = count(array_intersect($candidateTokens, $existingTokens));
        $requiredMatches = count($candidateTokens) <= 2
            ? count($candidateTokens)
            : (int) ceil(count($candidateTokens) * 0.70);

        return $matchedTokens >= $requiredMatches;
    };
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Invoice {{ $invoiceReceipt['invoice_no'] ?? '' }} | Print</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: Arial, sans-serif;
            background: #07588a;
            color: #000;
        }

        .receipt {
            position: absolute;
            left: -100000px;
            top: 0;
            width: 100%;
            background: #fff;
            font-size: 12px;
            line-height: 1.3;
        }

        .radar-screen {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #fff;
            background:
                radial-gradient(circle at center, #1680bd 0%, #0e6ca5 44%, #07517f 74%, #04395f 100%);
        }

        .radar-card {
            width: min(430px, 92vw);
            display: grid;
            justify-items: center;
            gap: 9px;
            text-align: center;
        }

        .radar {
            position: relative;
            width: min(330px, 76vw);
            aspect-ratio: 1;
            overflow: hidden;
            border: 1px solid rgba(215,241,255,.48);
            border-radius: 50%;
            background:
                radial-gradient(circle, transparent 0 24%, rgba(210,240,255,.22) 24.4% 24.8%, transparent 25.2% 49%, rgba(210,240,255,.26) 49.4% 49.8%, transparent 50.2% 74%, rgba(210,240,255,.30) 74.4% 74.8%, transparent 75.2%);
            box-shadow: inset 0 0 65px rgba(187,235,255,.08);
        }

        .axis-x, .axis-y {
            position: absolute;
            left: 50%;
            top: 50%;
            background: rgba(218,244,255,.25);
            transform: translate(-50%, -50%);
        }
        .axis-x { width: 100%; height: 1px; }
        .axis-y { width: 1px; height: 100%; }

        .sweep {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 46%;
            height: 4px;
            border-radius: 999px;
            transform-origin: 0 50%;
            background: linear-gradient(90deg, rgba(255,255,255,.10), rgba(255,255,255,.95));
            box-shadow: 0 0 14px rgba(218,245,255,.80);
            animation: sweep 3.4s linear infinite;
            will-change: transform;
        }
        .sweep::after {
            content: "";
            position: absolute;
            right: -5px;
            top: 50%;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #fff;
            transform: translateY(-50%);
            box-shadow: 0 0 16px rgba(255,255,255,.95);
        }

        .scan-ring {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 72px;
            height: 72px;
            border: 2px solid rgba(255,255,255,.55);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: scan-ring 1.35s ease-out infinite;
        }
        .scan-ring.r2 { animation-delay: .45s; }
        .scan-ring.r3 { animation-delay: .90s; }

        .printer-dot {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 66px;
            height: 66px;
            display: grid;
            place-items: center;
            border: 3px solid #fff;
            border-radius: 50%;
            background: #fff;
            color: #08608f;
            box-shadow: 0 0 0 8px rgba(212,243,255,.14), 0 0 30px rgba(218,245,255,.45);
            transform: translate(-50%, -50%);
            animation: pulse 1s ease-in-out infinite alternate;
        }
        .printer-dot svg { width: 34px; height: 34px; fill: currentColor; }

        .radar-printer {
            position: absolute;
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border: 2px solid rgba(255,255,255,.88);
            border-radius: 50%;
            background: rgba(255,255,255,.12);
            color: #fff;
            opacity: 0;
            transform: translate(-50%, -50%) scale(.65);
            animation: printer-found 3.6s ease-in-out infinite;
        }

        .radar-printer svg {
            width: 19px;
            height: 19px;
            fill: currentColor;
        }

        .radar-printer.p1 { left: 27%; top: 31%; animation-delay: .55s; }
        .radar-printer.p2 { left: 75%; top: 27%; animation-delay: 1.15s; }
        .radar-printer.p3 { left: 78%; top: 70%; animation-delay: 1.75s; }
        .radar-printer.p4 { left: 28%; top: 74%; animation-delay: 2.35s; }

        .radar-card strong { font-size: 18px; letter-spacing: 1.2px; }
        .radar-card > span { font-size: 12px; font-weight: 800; color: #dcf4ff; }
        .radar-card small { max-width: 370px; font-size: 10px; line-height: 1.45; color: rgba(235,248,255,.84); }

        .radar-result-note {
            width: min(400px, 100%);
            margin-top: 5px;
            color: rgba(235,248,255,.90);
            font-size: 10px;
            line-height: 1.45;
            text-align: center;
        }

        .print-modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: grid;
            place-items: center;
            padding: 14px;
            background: rgba(2, 30, 46, .82);
        }

        .print-modal[hidden] { display: none !important; }

        .print-modal-card {
            width: min(1080px, 97vw);
            max-height: 96vh;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 16px;
            background: #f4f7f5;
            color: #111;
            box-shadow: 0 22px 70px rgba(0,0,0,.36);
        }

        .print-modal-header {
            padding: 15px 17px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #064e3b;
            color: #fff;
        }

        .print-modal-header h2 {
            margin: 0;
            color: #ffe36e;
            font-size: 17px;
        }

        .print-modal-header p {
            margin: 3px 0 0;
            color: #d8eee6;
            font-size: 9px;
        }

        .print-modal-close {
            width: 38px;
            height: 38px;
            border: 1px solid rgba(255,227,110,.55);
            border-radius: 9px;
            background: transparent;
            color: #ffe36e;
            font-size: 22px;
            cursor: pointer;
        }

        .print-modal-body {
            min-height: 0;
            padding: 14px;
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
            gap: 14px;
            overflow: hidden;
        }

        .print-control-panel {
            align-self: start;
            display: grid;
            gap: 10px;
        }

        .print-control-box {
            padding: 12px;
            display: grid;
            gap: 5px;
            border: 1px solid #d8e2dd;
            border-radius: 11px;
            background: #fff;
        }

        .print-control-box span {
            color: #607068;
            font-size: 8px;
            font-weight: 900;
            letter-spacing: .7px;
        }

        .print-control-box strong {
            color: #064e3b;
            font-size: 11px;
        }

        .copies-input-row {
            display: grid;
            grid-template-columns: 38px 1fr 38px;
            gap: 6px;
            align-items: center;
        }

        .copies-input-row button {
            height: 38px;
            border: 0;
            border-radius: 8px;
            background: #064e3b;
            color: #ffe36e;
            font-size: 18px;
            font-weight: 1000;
            cursor: pointer;
        }

        .copies-input-row input {
            width: 100%;
            height: 38px;
            border: 1px solid #cbd9d2;
            border-radius: 8px;
            text-align: center;
            color: #064e3b;
            font: 900 14px Arial, sans-serif;
        }

        .preview-scroll {
            min-width: 0;
            min-height: 0;
            overflow: auto;
            padding: 16px;
            border: 1px solid #d8e2dd;
            border-radius: 11px;
            background:
                linear-gradient(45deg,#e8eeeb 25%,transparent 25%) 0 0/20px 20px,
                linear-gradient(45deg,transparent 75%,#e8eeeb 75%) 0 0/20px 20px,
                linear-gradient(45deg,transparent 75%,#e8eeeb 75%) 10px -10px/20px 20px,
                linear-gradient(45deg,#e8eeeb 25%,#eef3f0 25%) 10px -10px/20px 20px;
        }

        .preview-pages {
            display: grid;
            gap: 18px;
            justify-content: center;
        }

        .preview-page-shell {
            width: 190mm;
            min-height: 277mm;
            padding: 0;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 6px 24px rgba(0,0,0,.20);
        }

        .preview-page-shell .receipt.preview-receipt {
            position: static !important;
            left: auto !important;
            top: auto !important;
            width: 190mm !important;
            min-height: 277mm !important;
            padding: 0 !important;
            margin: 0 !important;
            background: #fff !important;
        }

        .print-modal-footer {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border-top: 1px solid #d8e2dd;
            background: #fff;
        }

        .print-modal-footer-note {
            color: #66766e;
            font-size: 9px;
            line-height: 1.35;
        }

        .print-modal-print {
            min-width: 150px;
            min-height: 44px;
            border: 0;
            border-radius: 10px;
            background: #064e3b;
            color: #ffe36e;
            font: 1000 11px Arial, sans-serif;
            letter-spacing: .6px;
            cursor: pointer;
        }

        @media (max-width: 760px) {
            .print-modal-body {
                grid-template-columns: 1fr;
                overflow: auto;
            }

            .preview-scroll {
                min-height: 58vh;
            }

            .print-control-panel {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .print-control-box.copies-box {
                grid-column: 1 / -1;
            }
        }

        .open-printer {
            min-width: 180px;
            min-height: 46px;
            margin-top: 8px;
            padding: 0 22px;
            border: 2px solid #fff;
            border-radius: 999px;
            background: #fff;
            color: #07588a;
            font: 900 11px Arial, sans-serif;
            letter-spacing: .7px;
            cursor: pointer;
        }

        .open-printer:disabled {
            opacity: .55;
            cursor: wait;
        }

        .back-link {
            color: #fff;
            font-size: 10px;
            font-weight: 800;
        }

        @keyframes sweep {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes scan-ring {
            0% { opacity: .75; transform: translate(-50%, -50%) scale(.55); }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(2.7); }
        }
        @keyframes pulse {
            from { transform: translate(-50%, -50%) scale(.94); }
            to { transform: translate(-50%, -50%) scale(1.04); }
        }

        @keyframes printer-found {
            0%, 15% { opacity: 0; transform: translate(-50%, -50%) scale(.65); }
            28%, 72% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            88%, 100% { opacity: .28; transform: translate(-50%, -50%) scale(.88); }
        }

        .sales-order-topline,
        .sales-order-customer-info > table,
        .items-table,
        .summary,
        .sales-order-footer,
        .sales-order-meta {
            width: 100%;
            border-collapse: collapse;
        }

        .sales-order-topline { table-layout: fixed; }
        .sales-order-topline td { padding: 0; vertical-align: middle; }
        .top-left { width: 30%; }
        .erw-heading { width: 40%; text-align: center; font-size: 15px; font-weight: 700; }
        .invoice-heading { width: 30%; text-align: left; color: #d00000; font-weight: 700; }
        .invoice-align-row { display: flex; align-items: baseline; width: max-content; margin-left: 80px; }
        .invoice-label { font-size: 12px; }
        .invoice-value { font-size: 14px; font-variant-numeric: tabular-nums; }
        .sep-dash { border-top: 1px dashed #000; margin: 5px 0; }
        .sales-order-header-separator { margin: 5px 0 6px; }

        .sales-order-customer-info { margin-bottom: 15px; }
        .sales-order-customer-info td { padding: 0; vertical-align: top; font-size: 12px; line-height: 1.35; }
        .customer-side { width: 70%; }
        .meta-side { width: 30%; }
        .label { font-size: 12px; }
        .header-value { font-size: 11px; }
        .customer-value { font-size: 11.5px; font-weight: 700; }
        .sales-order-meta { width: max-content; table-layout: auto; }
        .sales-order-meta .meta-label { width: 80px; min-width: 80px; padding-right: 3px; text-align: left; font-size: 12px; white-space: nowrap; }
        .sales-order-meta .meta-value { padding: 0; text-align: left; font-size: 11px; white-space: nowrap; }
        .sales-order-meta .terms-value { font-size: 11.5px; }

        .items-table { table-layout: fixed; margin-bottom: 8px; }
        .items-table th, .items-table td { padding: 2px 4px; border: 1px solid transparent; font-size: 12px; font-weight: 400; }
        .items-table thead th { padding: 1px 2px 3px; text-align: center; vertical-align: middle; white-space: nowrap; }
        .items-table thead .sales-order-heading-bottom th { height: 0; padding: 0; line-height: 0; font-size: 0; border-top: 1px dashed #000; }
        .items-table .c { text-align: center; }
        .items-table .r { text-align: right; }
        .qty-col { width: 7%; }
        .unit-col { width: 9%; }
        .code-col { width: 18%; word-break: break-word; }
        .desc-col { width: 34%; word-break: break-word; }
        .price-col { width: 12%; }
        .disc-col { width: 8%; }
        .total-col { width: 12%; }
        .items-table tbody .code-col,
        .items-table tbody .price-col,
        .items-table tbody .total-col { text-align: center; }

        .items-table.no-discount .qty-col { width: 8.333333%; }
        .items-table.no-discount .unit-col { width: 10.333333%; }
        .items-table.no-discount .code-col { width: 19.333333%; }
        .items-table.no-discount .desc-col { width: 35.333333%; }
        .items-table.no-discount .price-col { width: 13.333333%; }
        .items-table.no-discount .total-col { width: 13.333333%; }

        .summary { table-layout: fixed; margin-top: 3px; }
        .summary td { padding: 1px 4px; font-size: 12px; }
        .summary .lb { width: 65%; }
        .summary .vl { width: 35%; text-align: right; }

        .rush-text {
            margin: 5px 0;
            text-align: center;
            font-family: 'Arial Black', Arial, sans-serif;
            font-size: 25px;
            font-weight: 400;
        }

        .sales-order-footer { margin-top: 3px; table-layout: fixed; }
        .sales-order-footer td { padding: 1px 4px; vertical-align: top; font-size: 12px; line-height: 1.3; }
        .signoff-line { display: grid; grid-template-columns: 100px minmax(0, 1fr); align-items: baseline; }
        .signoff-label, .signoff-value { font-size: 12px; white-space: nowrap; }
        .signoff-value { padding-left: 5px; }
        .financial-label { text-align: left; white-space: nowrap; }
        .financial-value { text-align: right; white-space: nowrap; }

        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            html, body {
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .radar-screen,
            .print-modal { display: none !important; }
            .receipt {
                position: static !important;
                left: auto !important;
                top: auto !important;
                width: 100% !important;
                max-width: 190mm !important;
                padding: 0 !important;
                margin: 0 auto !important;
                display: block !important;
            }
            .receipt.print-copy {
                break-before: page;
                page-break-before: always;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .sweep { animation-duration: 2.2s; }
            .printer-dot { animation-duration: 1.8s; }
            .scan-ring { animation-duration: 2.4s; }
        }
    </style>
</head>
<body>
    <section class="radar-screen" id="printer-radar">
        <div class="radar-card">
            <div class="radar" aria-hidden="true">
                <span class="axis-x"></span>
                <span class="axis-y"></span>
                <span class="scan-ring r1"></span>
                <span class="scan-ring r2"></span>
                <span class="scan-ring r3"></span>
                <span class="sweep"></span>
                <span class="radar-printer p1"><svg viewBox="0 0 24 24"><path d="M7 7V3h10v4M7 17v4h10v-4M6 9h12a3 3 0 0 1 3 3v4h-4v-3H7v3H3v-4a3 3 0 0 1 3-3Zm2 6h8v4H8z"/></svg></span>
                <span class="radar-printer p2"><svg viewBox="0 0 24 24"><path d="M7 7V3h10v4M7 17v4h10v-4M6 9h12a3 3 0 0 1 3 3v4h-4v-3H7v3H3v-4a3 3 0 0 1 3-3Zm2 6h8v4H8z"/></svg></span>
                <span class="radar-printer p3"><svg viewBox="0 0 24 24"><path d="M7 7V3h10v4M7 17v4h10v-4M6 9h12a3 3 0 0 1 3 3v4h-4v-3H7v3H3v-4a3 3 0 0 1 3-3Zm2 6h8v4H8z"/></svg></span>
                <span class="radar-printer p4"><svg viewBox="0 0 24 24"><path d="M7 7V3h10v4M7 17v4h10v-4M6 9h12a3 3 0 0 1 3 3v4h-4v-3H7v3H3v-4a3 3 0 0 1 3-3Zm2 6h8v4H8z"/></svg></span>
                <span class="printer-dot">
                    <svg viewBox="0 0 24 24"><path d="M7 7V3h10v4M7 17v4h10v-4M6 9h12a3 3 0 0 1 3 3v4h-4v-3H7v3H3v-4a3 3 0 0 1 3-3Zm2 6h8v4H8z"/></svg>
                </span>
            </div>
            <strong id="radar-title">SCANNING FOR PRINTERS</strong>
            <span id="printer-status">Scanning your device print environment…</span>
            <div class="radar-result-note">
                Printer signals are shown visually around the radar. Real printer names and the physical printer selection are controlled by the device print service and are not exposed to Safari/Chrome.
            </div>

            <button type="button" class="open-printer" id="open-printer" disabled>PRINT RECEIPT</button>
            <a class="back-link" href="{{ $ordersUrl }}">BACK TO INVOICED</a>
        </div>
    </section>

    <section class="print-modal" id="print-modal" hidden aria-hidden="true">
        <div class="print-modal-card" role="dialog" aria-modal="true" aria-labelledby="print-modal-title">
            <header class="print-modal-header">
                <div>
                    <h2 id="print-modal-title">PRINT RECEIPT</h2>
                    <p>W68 Sales Order receipt preview</p>
                </div>
                <button type="button" class="print-modal-close" id="print-modal-close" aria-label="Close">&times;</button>
            </header>

            <div class="print-modal-body">
                <aside class="print-control-panel">
                    <div class="print-control-box">
                        <span>PAPER SIZE</span>
                        <strong>A4 PORTRAIT</strong>
                    </div>

                    <div class="print-control-box">
                        <span>DOCUMENT MARGINS</span>
                        <strong>10 MM W68 LAYOUT</strong>
                    </div>

                    <div class="print-control-box copies-box">
                        <span>COPIES</span>
                        <div class="copies-input-row">
                            <button type="button" id="copies-minus" aria-label="Decrease copies">−</button>
                            <input type="number" id="copies-input" min="1" max="20" step="1" value="1" inputmode="numeric">
                            <button type="button" id="copies-plus" aria-label="Increase copies">+</button>
                        </div>
                    </div>
                </aside>

                <div class="preview-scroll">
                    <div class="preview-pages" id="preview-pages"></div>
                </div>
            </div>

            <footer class="print-modal-footer">
                <div class="print-modal-footer-note">
                    A4 portrait / 10 mm W68 document margins. Final physical printer selection is handled by the device.
                </div>
                <button type="button" class="print-modal-print" id="print-modal-print">PRINT</button>
            </footer>
        </div>
    </section>

    <article class="receipt" id="w68-receipt">
        <table class="sales-order-topline">
            <tr>
                <td class="top-left"></td>
                <td class="erw-heading">ERW</td>
                <td class="invoice-heading">
                    <div class="invoice-align-row">
                        <span class="invoice-label">NO.</span>
                        <span class="invoice-value">{{ $invoiceReceipt['invoice_no'] ?? '' }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <div class="sep-dash sales-order-header-separator"></div>

        <div class="sales-order-customer-info">
            <table>
                <tr>
                    <td class="customer-side">
                        <span class="label">CUSTOMER:</span>
                        <span class="header-value customer-value">{{ $invoiceReceipt['customer_name'] ?? '' }}</span><br>
                        <span class="label">ADDRESS:</span>
                        <span class="header-value">{{ $invoiceReceipt['customer_address'] ?? '' }}</span>
                    </td>
                    <td class="meta-side">
                        <table class="sales-order-meta">
                            <tr><td class="meta-label">SN NO.:</td><td class="meta-value">{{ $invoiceReceipt['sales_number'] ?? '' }}</td></tr>
                            <tr><td class="meta-label">DATE:</td><td class="meta-value">{{ $receiptDate }}</td></tr>
                            <tr><td class="meta-label">TIN:</td><td class="meta-value">{{ $invoiceReceipt['customer_tin'] ?? '' }}</td></tr>
                            <tr><td class="meta-label">TERMS:</td><td class="meta-value terms-value">{{ $invoiceReceipt['terms'] ?? '' }}</td></tr>
                            <tr><td class="meta-label">SALESMAN:</td><td class="meta-value">{{ $invoiceReceipt['salesman'] ?? '' }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="sep-dash sales-order-header-separator"></div>

        <table class="items-table {{ $showDiscountColumn ? '' : 'no-discount' }}">
            <thead>
                <tr>
                    <th class="qty-col">QTY</th>
                    <th class="unit-col">UNIT</th>
                    <th class="code-col">PRODUCT CODE</th>
                    <th class="desc-col">ITEM</th>
                    <th class="price-col">UNIT PRICE</th>
                    @if ($showDiscountColumn)<th class="disc-col">LESS</th>@endif
                    <th class="total-col">TOTAL</th>
                </tr>
                <tr class="sales-order-heading-bottom">
                    <th colspan="{{ $showDiscountColumn ? 7 : 6 }}"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    @php
                        $details = [];
                        $description = trim((string) ($item['description'] ?? ''));
                        if ($description !== '') $details[] = $description;
                        foreach (['application', 'position'] as $detailKey) {
                            $detail = trim((string) ($item[$detailKey] ?? ''));
                            if ($detail !== '' && !$alreadyIncluded(implode(' ', $details), $detail)) {
                                $details[] = $detail;
                            }
                        }
                        $qty = (float) ($item['qty'] ?? 0);
                        $additionalQty = (float) ($item['additionalQty'] ?? 0);
                        $qtyLabel = $formatQty($qty) . ($additionalQty > 0 ? '+(' . $formatQty($additionalQty) . ')' : '');
                        $code = trim((string) ($item['priceCode'] ?? '')) ?: trim((string) ($item['productCode'] ?? ''));
                    @endphp
                    <tr>
                        <td class="qty-col c">{{ $qtyLabel }}</td>
                        <td class="unit-col c">{{ $item['oum'] ?? '' }}</td>
                        <td class="code-col">{{ $code }}</td>
                        <td class="desc-col">{{ implode(' ', $details) }}</td>
                        <td class="price-col r">{{ number_format((float) ($item['price'] ?? 0), 2) }}</td>
                        @if ($showDiscountColumn)
                            <td class="disc-col c">
                                @if ((float) ($item['discountPercent'] ?? 0) > 0)
                                    {{ $formatQty($item['discountPercent']) }}%
                                @endif
                            </td>
                        @endif
                        <td class="total-col r">{{ number_format((float) ($item['printSubtotal'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="sep-dash"></div>
        <table class="summary">
            <tr><td class="lb">TOTAL(QTY: {{ $formatQty($totalItems) }})</td><td class="vl">{{ number_format((float) ($invoiceReceipt['invoice_amount'] ?? 0), 2) }}</td></tr>
        </table>
        <div class="sep-dash"></div>

        @if (!empty($invoiceReceipt['rush_text']))
            <div class="rush-text">{{ $invoiceReceipt['rush_text'] }}</div>
        @endif

        <table class="sales-order-footer">
            <colgroup>
                @if ($showDiscountColumn)
                    <col style="width:7%"><col style="width:9%"><col style="width:18%"><col style="width:34%"><col style="width:12%"><col style="width:8%"><col style="width:12%">
                @else
                    <col style="width:8.333333%"><col style="width:10.333333%"><col style="width:19.333333%"><col style="width:35.333333%"><col style="width:13.333333%"><col style="width:13.333333%">
                @endif
            </colgroup>
            <tr>
                <td colspan="4"><span class="signoff-line"><span class="signoff-label">PREPARED BY:</span><span class="signoff-value">{{ $invoiceReceipt['prepared_by'] ?? '' }}</span></span></td>
                <td class="financial-label">INVOICE AMOUNT:</td>
                @if ($showDiscountColumn)<td></td>@endif
                <td class="financial-value">{{ number_format((float) ($invoiceReceipt['invoice_amount'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"><span class="signoff-line"><span class="signoff-label">PACKED BY:</span><span class="signoff-value">{{ $invoiceReceipt['packed_by'] ?? '' }}</span></span></td>
                <td class="financial-label">ADDITIONAL LESS:</td>
                @if ($showDiscountColumn)<td></td>@endif
                <td class="financial-value">{{ number_format((float) ($invoiceReceipt['additional_less'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"><span class="signoff-line"><span class="signoff-label">CHECKED BY:</span><span class="signoff-value">{{ $invoiceReceipt['checked_by'] ?? '' }}</span></span></td>
                <td class="financial-label">NET AMOUNT:</td>
                @if ($showDiscountColumn)<td></td>@endif
                <td class="financial-value">{{ number_format((float) ($invoiceReceipt['net_amount'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"><span class="signoff-line"><span class="signoff-label">RECEIVED BY:</span><span class="signoff-value">&nbsp;</span></span></td>
                <td></td>
                @if ($showDiscountColumn)<td></td>@endif
                <td></td>
            </tr>
        </table>
    </article>

    <script>
        (function () {
            var openButton = document.getElementById('open-printer');
            var status = document.getElementById('printer-status');
            var title = document.getElementById('radar-title');
            var modal = document.getElementById('print-modal');
            var modalClose = document.getElementById('print-modal-close');
            var finalPrint = document.getElementById('print-modal-print');
            var copiesMinus = document.getElementById('copies-minus');
            var copiesPlus = document.getElementById('copies-plus');
            var copiesInput = document.getElementById('copies-input');
            var previewPages = document.getElementById('preview-pages');
            var receipt = document.getElementById('w68-receipt');
            var copies = 1;
            var maxCopies = 20;

            function normalizedCopies(value) {
                return Math.max(1, Math.min(maxCopies, Number(value) || 1));
            }

            function setCopies(value) {
                copies = normalizedCopies(value);
                if (copiesInput) copiesInput.value = String(copies);
                buildPreview();
            }

            function removeGeneratedPrintCopies() {
                document.querySelectorAll('.receipt.print-copy').forEach(function (copy) {
                    copy.remove();
                });
            }

            function buildPrintCopies() {
                removeGeneratedPrintCopies();
                if (!receipt || copies <= 1) return;

                var anchor = receipt;
                for (var index = 2; index <= copies; index += 1) {
                    var clone = receipt.cloneNode(true);
                    clone.removeAttribute('id');
                    clone.classList.add('print-copy');
                    clone.setAttribute('data-copy-number', String(index));
                    clone.setAttribute('aria-hidden', 'true');
                    anchor.insertAdjacentElement('afterend', clone);
                    anchor = clone;
                }
            }

            function buildPreview() {
                if (!previewPages || !receipt) return;
                previewPages.innerHTML = '';

                for (var index = 1; index <= copies; index += 1) {
                    var shell = document.createElement('div');
                    shell.className = 'preview-page-shell';

                    var clone = receipt.cloneNode(true);
                    clone.removeAttribute('id');
                    clone.classList.add('preview-receipt');
                    clone.setAttribute('data-preview-copy', String(index));

                    shell.appendChild(clone);
                    previewPages.appendChild(shell);
                }
            }

            function openModal() {
                if (!modal) return;
                buildPreview();
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                if (!modal) return;
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            function markReady() {
                if (title) title.textContent = 'PRINTER SCAN READY';
                if (status) status.textContent = 'Receipt setup is ready.';
                if (openButton) {
                    openButton.disabled = false;
                    openButton.removeAttribute('aria-disabled');
                }
            }

            function printReceipt() {
                buildPrintCopies();

                try {
                    // A website cannot bypass the browser/OS printer picker or
                    // enumerate/select physical printers. The W68 document
                    // itself is fixed to A4 portrait with 10 mm margins.
                    window.print();
                } catch (error) {
                    removeGeneratedPrintCopies();
                    console.error('Unable to open print dialog:', error);
                }
            }

            if (openButton) {
                openButton.setAttribute('aria-disabled', 'true');
                openButton.addEventListener('click', openModal, false);
            }

            if (modalClose) {
                modalClose.addEventListener('click', closeModal, false);
            }

            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) closeModal();
                }, false);
            }

            if (copiesMinus) {
                copiesMinus.addEventListener('click', function () {
                    setCopies(copies - 1);
                }, false);
            }

            if (copiesPlus) {
                copiesPlus.addEventListener('click', function () {
                    setCopies(copies + 1);
                }, false);
            }

            if (copiesInput) {
                copiesInput.addEventListener('change', function () {
                    setCopies(copiesInput.value);
                }, false);
                copiesInput.addEventListener('input', function () {
                    copies = normalizedCopies(copiesInput.value);
                }, false);
            }

            if (finalPrint) {
                finalPrint.addEventListener('click', printReceipt, false);
            }

            window.addEventListener('afterprint', function () {
                removeGeneratedPrintCopies();
            }, false);

            window.addEventListener('load', function () {
                setCopies(1);
                window.setTimeout(markReady, 2600);
            }, false);
        })();
    </script>
</body>
</html>

{{-- W68_PRINT_PREVIEW_MODAL_V124_20261001 --}}
