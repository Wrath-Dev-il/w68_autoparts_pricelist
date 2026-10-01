import 'dart:typed_data';

import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

import '../models/print_job.dart';

class ReceiptPdf {
  const ReceiptPdf._();

  static Future<Uint8List> build(
    W68PrintJob job,
    PdfPageFormat format,
  ) async {
    final document = pw.Document();
    final receipt = job.receipt;
    final showDiscount =
        job.items.any((item) => item.discountPercent.abs() > 0.000001);
    final base = pw.TextStyle(fontSize: 9);
    final bold = pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold);

    document.addPage(
      pw.MultiPage(
        pageFormat: format,
        margin: const pw.EdgeInsets.fromLTRB(28, 28, 28, 28),
        build: (context) => [
          pw.Table(
            columnWidths: const {
              0: pw.FlexColumnWidth(3),
              1: pw.FlexColumnWidth(4),
              2: pw.FlexColumnWidth(3),
            },
            children: [
              pw.TableRow(
                children: [
                  pw.SizedBox(),
                  pw.Center(
                    child: pw.Text(
                      'ERW',
                      style: pw.TextStyle(
                        fontSize: 12,
                        fontWeight: pw.FontWeight.bold,
                      ),
                    ),
                  ),
                  pw.Align(
                    alignment: pw.Alignment.centerRight,
                    child: pw.Text(
                      'NO. ${receipt.invoiceNo}',
                      style: pw.TextStyle(
                        color: PdfColors.red,
                        fontSize: 10,
                        fontWeight: pw.FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
          _rule(),
          pw.Row(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Expanded(
                flex: 7,
                child: pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    _line('CUSTOMER:', receipt.customerName, bold, base),
                    _line('ADDRESS:', receipt.customerAddress, base, base),
                  ],
                ),
              ),
              pw.Expanded(
                flex: 3,
                child: pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    _line('SN NO.:', receipt.salesNumber, base, base),
                    _line('DATE:', receipt.date, base, base),
                    _line('TIN:', receipt.customerTin, base, base),
                    _line('TERMS:', receipt.terms, base, base),
                    _line('SALESMAN:', receipt.salesman, base, base),
                  ],
                ),
              ),
            ],
          ),
          pw.SizedBox(height: 5),
          _rule(),
          pw.Table(
            columnWidths: {
              0: const pw.FlexColumnWidth(7),
              1: const pw.FlexColumnWidth(9),
              2: const pw.FlexColumnWidth(18),
              3: const pw.FlexColumnWidth(34),
              4: const pw.FlexColumnWidth(12),
              if (showDiscount) 5: const pw.FlexColumnWidth(8),
              if (showDiscount) 6: const pw.FlexColumnWidth(12),
              if (!showDiscount) 5: const pw.FlexColumnWidth(12),
            },
            children: [
              pw.TableRow(
                decoration: const pw.BoxDecoration(
                  border: pw.Border(
                    bottom: pw.BorderSide(width: .6),
                  ),
                ),
                children: [
                  _cell('QTY', bold, center: true),
                  _cell('UNIT', bold, center: true),
                  _cell('PRODUCT CODE', bold, center: true),
                  _cell('ITEM', bold, center: true),
                  _cell('UNIT PRICE', bold, center: true),
                  if (showDiscount) _cell('LESS', bold, center: true),
                  _cell('TOTAL', bold, center: true),
                ],
              ),
              ...job.items.map(
                (item) => pw.TableRow(
                  children: [
                    _cell(_qtyLabel(item), base, center: true),
                    _cell(item.oum, base, center: true),
                    _cell(item.receiptCode, base),
                    _cell(_itemText(item), base),
                    _cell(_money(item.unitPrice), base, right: true),
                    if (showDiscount)
                      _cell(
                        item.discountPercent > 0
                            ? '${_qty(item.discountPercent)}%'
                            : '',
                        base,
                        center: true,
                      ),
                    _cell(_money(item.printSubtotal), base, right: true),
                  ],
                ),
              ),
            ],
          ),
          _rule(),
          pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
            children: [
              pw.Text('TOTAL(QTY: ${_qty(receipt.totalQty)})', style: base),
              pw.Text(_money(receipt.invoiceAmount), style: base),
            ],
          ),
          _rule(),
          if (receipt.rushText.trim().isNotEmpty)
            pw.Center(
              child: pw.Padding(
                padding: const pw.EdgeInsets.symmetric(vertical: 3),
                child: pw.Text(
                  receipt.rushText,
                  style: pw.TextStyle(
                    fontSize: 19,
                    fontWeight: pw.FontWeight.bold,
                  ),
                ),
              ),
            ),
          pw.Row(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Expanded(
                flex: 6,
                child: pw.Column(
                  children: [
                    _line('PREPARED BY:', receipt.preparedBy, base, base),
                    _line('PACKED BY:', receipt.packedBy, base, base),
                    _line('CHECKED BY:', receipt.checkedBy, base, base),
                    _line('RECEIVED BY:', '', base, base),
                  ],
                ),
              ),
              pw.SizedBox(width: 18),
              pw.Expanded(
                flex: 4,
                child: pw.Column(
                  children: [
                    _moneyLine('INVOICE AMOUNT:', receipt.invoiceAmount, base),
                    _moneyLine('ADDITIONAL LESS:', receipt.additionalLess, base),
                    _moneyLine('NET AMOUNT:', receipt.netAmount, base),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );

    return document.save();
  }

  static pw.Widget _rule() => pw.Container(
        margin: const pw.EdgeInsets.symmetric(vertical: 4),
        decoration: const pw.BoxDecoration(
          border: pw.Border(top: pw.BorderSide(width: .6)),
        ),
      );

  static pw.Widget _line(
    String label,
    String value,
    pw.TextStyle labelStyle,
    pw.TextStyle valueStyle,
  ) =>
      pw.Padding(
        padding: const pw.EdgeInsets.only(bottom: 2),
        child: pw.RichText(
          text: pw.TextSpan(
            style: valueStyle,
            children: [
              pw.TextSpan(text: '$label ', style: labelStyle),
              pw.TextSpan(text: value),
            ],
          ),
        ),
      );

  static pw.Widget _moneyLine(
    String label,
    double value,
    pw.TextStyle style,
  ) =>
      pw.Padding(
        padding: const pw.EdgeInsets.only(bottom: 2),
        child: pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text(label, style: style),
            pw.Text(_money(value), style: style),
          ],
        ),
      );

  static pw.Widget _cell(
    String value,
    pw.TextStyle style, {
    bool center = false,
    bool right = false,
  }) =>
      pw.Padding(
        padding: const pw.EdgeInsets.symmetric(horizontal: 2, vertical: 2),
        child: pw.Align(
          alignment: right
              ? pw.Alignment.centerRight
              : center
                  ? pw.Alignment.center
                  : pw.Alignment.centerLeft,
          child: pw.Text(value, style: style),
        ),
      );

  static String _itemText(W68ReceiptItem item) {
    final parts = <String>[];
    for (final value in [item.description, item.application, item.position]) {
      final text = value.trim();
      if (text.isEmpty) continue;
      if (parts.any(
        (existing) => existing.toLowerCase().contains(text.toLowerCase()),
      )) {
        continue;
      }
      parts.add(text);
    }
    return parts.join(' ');
  }

  static String _qtyLabel(W68ReceiptItem item) {
    final base = _qty(item.qty);
    if (item.additionalQty <= 0) return base;
    return '$base+(${_qty(item.additionalQty)})';
  }

  static String _qty(double value) {
    if (value == value.roundToDouble()) return value.toStringAsFixed(0);
    return value
        .toStringAsFixed(2)
        .replaceFirst(RegExp(r'0+$'), '')
        .replaceFirst(RegExp(r'\.$'), '');
  }

  static String _money(double value) => value.toStringAsFixed(2);
}
