import 'dart:typed_data';

import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

import '../models/print_job.dart';

class CartListPdf {
  const CartListPdf._();

  static Future<Uint8List> build(
    W68PrintJob job,
    PdfPageFormat format, {
    int copies = 1,
  }) async {
    final document = pw.Document();
    final showLess =
        job.items.any((item) => item.discountPercent.abs() > 0.000001);
    final base = pw.TextStyle(fontSize: 8.5);
    final header = pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold);
    final totalStyle =
        pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold);
    final safeCopies = copies.clamp(1, 20);

    for (var copyIndex = 0; copyIndex < safeCopies; copyIndex++) {
      document.addPage(
        pw.MultiPage(
          pageFormat: format,
          margin: pw.EdgeInsets.all(10 * PdfPageFormat.mm),
          build: (context) => [
            pw.Center(
              child: pw.Column(
                children: [
                  pw.Text(
                    'ERW',
                    style: pw.TextStyle(
                      fontSize: 17,
                      fontWeight: pw.FontWeight.bold,
                    ),
                  ),
                  pw.SizedBox(height: 2),
                  pw.Text(
                    'SPECIAL STORE',
                    style: pw.TextStyle(
                      fontSize: 11,
                      fontWeight: pw.FontWeight.bold,
                      letterSpacing: .6,
                    ),
                  ),
                ],
              ),
            ),
            pw.SizedBox(height: 14),
            pw.Table(
              columnWidths: {
                0: const pw.FlexColumnWidth(14),
                1: const pw.FlexColumnWidth(14),
                2: const pw.FlexColumnWidth(28),
                3: const pw.FlexColumnWidth(7),
                4: const pw.FlexColumnWidth(13),
                if (showLess) 5: const pw.FlexColumnWidth(10),
                if (showLess) 6: const pw.FlexColumnWidth(14),
                if (!showLess) 5: const pw.FlexColumnWidth(14),
              },
              children: [
                pw.TableRow(
                  children: [
                    _cell('PRODUCT CODE', header, center: true),
                    _cell('PART NO.', header, center: true),
                    _cell('DESCRIPTION', header, center: true),
                    _cell('QTY', header, center: true),
                    _cell('UNIT PRICE', header, center: true),
                    if (showLess) _cell('LESS', header, center: true),
                    _cell('SUB TOTAL', header, center: true),
                  ],
                ),
                ...job.items.map(
                  (item) => pw.TableRow(
                    children: [
                      _cell(item.productCode, base),
                      _cell(item.partNumber, base),
                      _cell(_description(item), base),
                      _cell(_qty(item.qty), base, right: true),
                      _cell(_money(item.unitPrice), base, right: true),
                      if (showLess)
                        _cell(
                          item.discountPercent > 0
                              ? '${_qty(item.discountPercent)}%'
                              : '',
                          base,
                          right: true,
                          dashWhenEmpty: false,
                        ),
                      _cell(_money(item.printSubtotal), base, right: true),
                    ],
                  ),
                ),
              ],
            ),
            pw.SizedBox(height: 10),
            _rule(),
            pw.Padding(
              padding: const pw.EdgeInsets.symmetric(vertical: 7),
              child: pw.Row(
                children: [
                  pw.Expanded(
                    child: pw.Align(
                      alignment: pw.Alignment.centerRight,
                      child: pw.Text('TOTAL', style: totalStyle),
                    ),
                  ),
                  pw.SizedBox(width: 20),
                  pw.SizedBox(
                    width: 95,
                    child: pw.Align(
                      alignment: pw.Alignment.centerRight,
                      child: pw.Text(
                        _money(job.receipt.netAmount),
                        style: totalStyle,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            _rule(),
          ],
        ),
      );
    }

    return document.save();
  }

  static pw.Widget _rule() => pw.Container(
        height: .6,
        color: PdfColors.black,
      );

  static pw.Widget _cell(
    String value,
    pw.TextStyle style, {
    bool center = false,
    bool right = false,
    bool dashWhenEmpty = true,
  }) =>
      pw.Padding(
        padding: const pw.EdgeInsets.symmetric(horizontal: 3, vertical: 4),
        child: pw.Align(
          alignment: right
              ? pw.Alignment.centerRight
              : center
                  ? pw.Alignment.center
                  : pw.Alignment.centerLeft,
          child: pw.Text(
            value.trim().isEmpty && dashWhenEmpty ? '—' : value,
            style: style,
          ),
        ),
      );

  static String _description(W68ReceiptItem item) {
    final parts = <String>[];

    for (final value in [item.description, item.application, item.brand]) {
      final text = value.trim();
      if (text.isEmpty) continue;
      if (parts.any((existing) => existing.toLowerCase() == text.toLowerCase())) {
        continue;
      }
      parts.add(text);
    }

    return parts.isEmpty ? '—' : parts.join(' + ');
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
