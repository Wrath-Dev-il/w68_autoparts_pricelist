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
            animation: sweep 1.05s linear infinite;
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

        .radar-card strong { font-size: 18px; letter-spacing: 1.2px; }
        .radar-card > span { font-size: 12px; font-weight: 800; color: #dcf4ff; }
        .radar-card small { max-width: 370px; font-size: 10px; line-height: 1.45; color: rgba(235,248,255,.84); }

        .print-settings-card {
            width: min(410px, 100%);
            margin-top: 8px;
            padding: 12px;
            display: none;
            gap: 8px;
            border: 1px solid rgba(255,255,255,.30);
            border-radius: 13px;
            background: rgba(255,255,255,.10);
            backdrop-filter: blur(4px);
            text-align: left;
        }

        .print-settings-card.is-ready {
            display: grid;
        }

        .print-setting-row {
            min-height: 48px;
            padding: 9px 11px;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 8px;
            border-radius: 10px;
            background: #064e3b;
            border: 1px solid rgba(255,227,110,.38);
        }

        .print-setting-copy {
            min-width: 0;
            display: grid;
            gap: 2px;
        }

        .print-setting-copy span {
            color: #bfe4d8;
            font-size: 8px;
            font-weight: 900;
            letter-spacing: .75px;
        }

        .print-setting-copy strong {
            color: #ffe36e;
            font-size: 11px;
            font-weight: 1000;
            letter-spacing: .25px;
        }

        .print-setting-badge {
            color: #ffe36e;
            font-size: 8px;
            font-weight: 1000;
            letter-spacing: .5px;
            white-space: nowrap;
        }

        .copies-control {
            display: inline-grid;
            grid-template-columns: 34px 42px 34px;
            align-items: center;
            overflow: hidden;
            border: 1px solid rgba(255,227,110,.58);
            border-radius: 9px;
            background: rgba(255,255,255,.06);
        }

        .copies-control button {
            width: 34px;
            height: 34px;
            border: 0;
            background: transparent;
            color: #ffe36e;
            font: 1000 18px Arial, sans-serif;
            cursor: pointer;
        }

        .copies-control output {
            display: grid;
            place-items: center;
            height: 34px;
            border-left: 1px solid rgba(255,227,110,.26);
            border-right: 1px solid rgba(255,227,110,.26);
            color: #fff;
            font: 1000 13px Arial, sans-serif;
        }

        .print-format-note {
            color: rgba(235,248,255,.86);
            font-size: 9px;
            line-height: 1.45;
            text-align: center;
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
            .radar-screen { display: none !important; }
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
                <span class="printer-dot">
                    <svg viewBox="0 0 24 24"><path d="M7 7V3h10v4M7 17v4h10v-4M6 9h12a3 3 0 0 1 3 3v4h-4v-3H7v3H3v-4a3 3 0 0 1 3-3Zm2 6h8v4H8z"/></svg>
                </span>
            </div>
            <strong id="radar-title">PREPARING A4 RECEIPT</strong>
            <span id="printer-status">Preparing W68 print settings…</span>
            <small>The Sales Order receipt is formatted by W68 for A4 portrait with fixed 10 mm document margins.</small>

            <div class="print-settings-card" id="print-settings-card" aria-live="polite">
                <div class="print-setting-row">
                    <span class="print-setting-copy">
                        <span>PAPER SIZE</span>
                        <strong>A4 PORTRAIT</strong>
                    </span>
                    <span class="print-setting-badge">W68 FORMAT</span>
                </div>

                <div class="print-setting-row">
                    <span class="print-setting-copy">
                        <span>DOCUMENT MARGINS</span>
                        <strong>10 MM FIXED LAYOUT</strong>
                    </span>
                    <span class="print-setting-badge">W68 FORMAT</span>
                </div>

                <div class="print-setting-row">
                    <span class="print-setting-copy">
                        <span>COPIES</span>
                        <strong>Choose how many receipt copies to produce</strong>
                    </span>
                    <span class="copies-control" aria-label="Number of copies">
                        <button type="button" id="copies-minus" aria-label="Decrease copies">−</button>
                        <output id="copies-count">1</output>
                        <button type="button" id="copies-plus" aria-label="Increase copies">+</button>
                    </span>
                </div>
            </div>

            <div class="print-format-note" id="print-format-note">
                After this screen, your device opens its printer picker so you can choose the physical printer.
            </div>

            <button type="button" class="open-printer" id="open-printer" disabled>SELECT PRINTER &amp; PRINT 1 COPY</button>
            <a class="back-link" href="{{ $ordersUrl }}">BACK TO INVOICED</a>
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
            var button = document.getElementById('open-printer');
            var status = document.getElementById('printer-status');
            var title = document.getElementById('radar-title');
            var settings = document.getElementById('print-settings-card');
            var copiesMinus = document.getElementById('copies-minus');
            var copiesPlus = document.getElementById('copies-plus');
            var copiesCount = document.getElementById('copies-count');
            var receipt = document.getElementById('w68-receipt');
            var copies = 1;
            var maxCopies = 20;

            function updateCopies(next) {
                copies = Math.max(1, Math.min(maxCopies, Number(next) || 1));

                if (copiesCount) {
                    copiesCount.textContent = String(copies);
                }

                if (button) {
                    button.textContent =
                        'SELECT PRINTER & PRINT ' +
                        copies +
                        (copies === 1 ? ' COPY' : ' COPIES');
                }
            }

            function removeGeneratedCopies() {
                document.querySelectorAll('.receipt.print-copy').forEach(function (copy) {
                    copy.remove();
                });
            }

            function buildGeneratedCopies() {
                removeGeneratedCopies();
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

            function markReady() {
                if (title) {
                    title.textContent = 'A4 RECEIPT READY';
                }

                if (status) {
                    status.textContent =
                        'Choose the number of copies, then select your printer.';
                }

                if (settings) {
                    settings.classList.add('is-ready');
                }

                if (button) {
                    button.disabled = false;
                    button.removeAttribute('aria-disabled');
                }

                updateCopies(copies);
            }

            function openPrinter(event) {
                if (event) event.preventDefault();

                buildGeneratedCopies();

                if (status) {
                    status.textContent =
                        copies +
                        (copies === 1 ? ' A4 copy is' : ' A4 copies are') +
                        ' prepared. Choose the physical printer in your device print window.';
                }

                try {
                    // Browser security requires the real printer selection to be
                    // handled by the operating system. This call must remain
                    // inside the direct customer tap/click.
                    window.print();
                } catch (error) {
                    removeGeneratedCopies();

                    if (status) {
                        status.textContent =
                            'Unable to open the printer window. Tap the print button again.';
                    }

                    console.error('Unable to open native print dialog:', error);
                }
            }

            if (copiesMinus) {
                copiesMinus.addEventListener('click', function () {
                    updateCopies(copies - 1);
                }, false);
            }

            if (copiesPlus) {
                copiesPlus.addEventListener('click', function () {
                    updateCopies(copies + 1);
                }, false);
            }

            if (button) {
                button.setAttribute('aria-disabled', 'true');
                button.addEventListener('click', openPrinter, false);
            }

            window.addEventListener('afterprint', function () {
                removeGeneratedCopies();

                if (status) {
                    status.textContent =
                        'Printer window closed. Change copies or print again if needed.';
                }
            }, false);

            window.addEventListener('load', function () {
                window.setTimeout(markReady, 1200);
            }, false);
        })();
    </script>
</body>
</html>

{{-- W68_A4_COPIES_PRINT_V123_20261001 --}}
