import 'dart:async';
import 'dart:math' as math;

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:printing/printing.dart';

import 'models/print_job.dart';
import 'services/job_client.dart';
import 'services/printer_discovery_service.dart';
import 'services/receipt_pdf.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const W68PrinterBridgeApp());
}

class W68PrinterBridgeApp extends StatelessWidget {
  const W68PrinterBridgeApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'W68 Printer Bridge',
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF064E3B),
          primary: const Color(0xFF064E3B),
          secondary: const Color(0xFFD8AD16),
        ),
        scaffoldBackgroundColor: const Color(0xFFF4F7F5),
      ),
      home: const PrinterBridgeHome(),
    );
  }
}

class PrinterBridgeHome extends StatefulWidget {
  const PrinterBridgeHome({super.key});

  @override
  State<PrinterBridgeHome> createState() => _PrinterBridgeHomeState();
}

class _PrinterBridgeHomeState extends State<PrinterBridgeHome>
    with SingleTickerProviderStateMixin {
  static const green = Color(0xFF064E3B);
  static const greenDark = Color(0xFF043F31);
  static const gold = Color(0xFFFFE36E);
  static const maroon = Color(0xFF76051D);

  final jobClient = const PrinterJobClient();
  final discovery = PrinterDiscoveryService();
  final appLinks = AppLinks();

  late final AnimationController radarController;
  StreamSubscription<Uri>? linkSubscription;

  W68PrintJob? job;
  List<BridgePrinter> printers = const [];
  String? selectedPrinterKey;
  String? error;
  String status = 'Waiting for a W68 invoice…';
  bool loadingJob = false;
  bool scanning = false;
  bool printing = false;

  @override
  void initState() {
    super.initState();
    radarController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1150),
    )..repeat();

    _initializeLinks();
    WidgetsBinding.instance.addPostFrameCallback((_) => _scanPrinters());
  }

  Future<void> _initializeLinks() async {
    try {
      final initial = await appLinks.getInitialLink();
      if (initial != null) await _handleLink(initial);
    } catch (e) {
      _setError('Unable to read initial print link: $e');
    }

    linkSubscription = appLinks.uriLinkStream.listen(
      _handleLink,
      onError: (Object e) => _setError('Unable to read W68 print link: $e'),
    );
  }

  Future<void> _handleLink(Uri uri) async {
    if (uri.scheme.toLowerCase() != 'w68print' ||
        uri.host.toLowerCase() != 'job') {
      return;
    }

    final rawUrl = uri.queryParameters['url'];
    final jobUri = rawUrl == null ? null : Uri.tryParse(rawUrl);
    if (jobUri == null) {
      _setError('The W68 print link is missing a valid job URL.');
      return;
    }

    setState(() {
      loadingJob = true;
      error = null;
      status = 'Loading secured invoice…';
    });

    try {
      final loaded = await jobClient.fetch(jobUri);
      if (!mounted) return;
      setState(() {
        job = loaded;
        loadingJob = false;
        status = 'Scanning for connected printers…';
      });
      await _scanPrinters();
    } catch (e) {
      if (!mounted) return;
      setState(() => loadingJob = false);
      _setError(e.toString());
    }
  }

  Future<void> _scanPrinters() async {
    if (scanning) return;
    setState(() {
      scanning = true;
      error = null;
      status = 'Scanning system + Wi-Fi printers…';
    });

    try {
      final found = await discovery.discover();
      if (!mounted) return;
      setState(() {
        printers = found;
        scanning = false;
        status = found.isEmpty
            ? 'No printers found yet. Try the native printer picker.'
            : '${found.length} printer${found.length == 1 ? '' : 's'} found';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => scanning = false);
      _setError('Printer scan failed: $e');
    }
  }

  Future<void> _printTo(BridgePrinter target) async {
    final currentJob = job;
    if (currentJob == null || printing) return;

    setState(() {
      printing = true;
      selectedPrinterKey = target.key;
      error = null;
      status = 'Sending invoice ${currentJob.invoiceNo} to ${target.displayName}…';
    });

    try {
      final bytes = await ReceiptPdf.build(currentJob, PdfPageFormat.a4);
      var sent = false;

      try {
        sent = await Printing.directPrintPdf(
          printer: target.printer,
          name: 'W68 Invoice ${currentJob.invoiceNo}',
          format: PdfPageFormat.a4,
          usePrinterSettings: true,
          onLayout: (_) async => bytes,
        );
      } catch (_) {
        sent = false;
      }

      if (!sent) {
        sent = await Printing.layoutPdf(
          name: 'W68 Invoice ${currentJob.invoiceNo}',
          format: PdfPageFormat.a4,
          usePrinterSettings: true,
          onLayout: (_) async => bytes,
        );
      }

      if (!mounted) return;
      setState(() {
        printing = false;
        status = sent ? 'Print job sent.' : 'Print was cancelled.';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => printing = false);
      _setError('Unable to print: $e');
    }
  }

  Future<void> _nativePicker() async {
    final currentJob = job;
    if (currentJob == null || printing) return;

    try {
      final picked = await Printing.pickPrinter(
        context: context,
        title: 'Choose W68 Printer',
      );
      if (picked == null || !mounted) return;

      await _printTo(
        BridgePrinter(
          printer: picked,
          source: 'NATIVE PICKER',
          reachable: picked.isAvailable,
        ),
      );
    } catch (e) {
      _setError('Native printer picker is unavailable: $e');
    }
  }

  void _setError(String message) {
    if (!mounted) return;
    setState(() {
      error = message;
      status = 'Printer bridge needs attention.';
    });
  }

  @override
  void dispose() {
    linkSubscription?.cancel();
    radarController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final currentJob = job;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: green,
        foregroundColor: Colors.white,
        titleSpacing: 14,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'W68 PRINTER BRIDGE',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
            ),
            Text(
              'REAL PRINTER DISCOVERY',
              style: TextStyle(
                color: gold,
                fontSize: 9,
                fontWeight: FontWeight.w900,
                letterSpacing: 1,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: scanning ? null : _scanPrinters,
            icon: const Icon(Icons.radar),
            tooltip: 'Scan again',
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _scanPrinters,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
            children: [
              _jobCard(currentJob),
              const SizedBox(height: 14),
              Center(
                child: SizedBox(
                  width: 270,
                  height: 270,
                  child: AnimatedBuilder(
                    animation: radarController,
                    builder: (context, child) => CustomPaint(
                      painter: RadarPainter(
                        progress: radarController.value,
                        connectedCount:
                            printers.where((p) => p.connected).length,
                        scanning: scanning,
                      ),
                      child: Center(
                        child: Container(
                          width: 66,
                          height: 66,
                          decoration: BoxDecoration(
                            color: green,
                            shape: BoxShape.circle,
                            border: Border.all(color: gold, width: 3),
                            boxShadow: const [
                              BoxShadow(
                                color: Color(0x33D8AD16),
                                blurRadius: 22,
                                spreadRadius: 4,
                              ),
                            ],
                          ),
                          child: const Icon(
                            Icons.print,
                            color: gold,
                            size: 32,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              Text(
                status,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: green,
                  fontSize: 11,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (error != null) ...[
                const SizedBox(height: 12),
                _errorBox(error!),
              ],
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: scanning ? null : _scanPrinters,
                      icon: const Icon(Icons.radar),
                      label: const Text('SCAN AGAIN'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed:
                          currentJob == null || printing ? null : _nativePicker,
                      style: FilledButton.styleFrom(
                        backgroundColor: green,
                        foregroundColor: gold,
                      ),
                      icon: const Icon(Icons.print),
                      label: const Text('NATIVE PICKER'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              const Text(
                'PRINTERS',
                style: TextStyle(
                  color: green,
                  fontSize: 11,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 1.1,
                ),
              ),
              const SizedBox(height: 8),
              if (printers.isEmpty) _emptyPrinters(),
              ...printers.map(_printerCard),
            ],
          ),
        ),
      ),
    );
  }

  Widget _jobCard(W68PrintJob? currentJob) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFDCE6E1)),
        ),
        child: currentJob == null
            ? Row(
                children: [
                  const Icon(Icons.receipt_long, color: green),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      loadingJob
                          ? 'Loading W68 invoice…'
                          : 'Open an invoice in W68 and press PRINT.',
                      style: const TextStyle(
                        color: green,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              )
            : Row(
                children: [
                  const Icon(Icons.receipt_long, color: maroon),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'INVOICE ${currentJob.invoiceNo}',
                          style: const TextStyle(
                            color: green,
                            fontSize: 14,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${currentJob.receipt.customerName} • ${currentJob.orderCode}',
                          style: const TextStyle(
                            color: Color(0xFF6E7D75),
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    currentJob.receipt.netAmount.toStringAsFixed(2),
                    style: const TextStyle(
                      color: maroon,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
      );

  Widget _errorBox(String message) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFFEEEE),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: const Color(0xFFE6B8B8)),
        ),
        child: Text(
          message,
          style: const TextStyle(
            color: maroon,
            fontSize: 10,
            fontWeight: FontWeight.w700,
          ),
        ),
      );

  Widget _emptyPrinters() => Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFDCE6E1)),
        ),
        child: const Text(
          'No printers found yet. Put this device and printer on the same Wi-Fi and scan again. Vendor-only printers can still appear in the native picker after their driver or print service is installed.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: Color(0xFF6E7D75),
            fontSize: 10,
            height: 1.45,
            fontWeight: FontWeight.w700,
          ),
        ),
      );

  Widget _printerCard(BridgePrinter p) {
    final connected = p.connected;
    final selected = selectedPrinterKey == p.key;
    final background = connected ? green : Colors.white;
    final foreground = connected ? Colors.white : green;
    final secondary = connected ? gold : const Color(0xFF6E7D75);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 220),
      margin: const EdgeInsets.only(bottom: 9),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(
          color: selected
              ? gold
              : connected
                  ? greenDark
                  : const Color(0xFFDCE6E1),
          width: selected ? 3 : 1,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(13),
        onTap: job == null || printing ? null : () => _printTo(p),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color:
                      connected ? const Color(0x1FFFE36E) : const Color(0xFFEAF6F1),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(
                  Icons.print,
                  color: connected ? gold : green,
                  size: 27,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      p.displayName,
                      style: TextStyle(
                        color: foreground,
                        fontSize: 12,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${p.source} • ${p.printer.location ?? p.printer.model ?? p.printer.url}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: secondary,
                        fontSize: 9,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    connected ? 'CONNECTED' : 'OFFLINE',
                    style: TextStyle(
                      color: connected ? gold : const Color(0xFF9A3A47),
                      fontSize: 8,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  if (p.isDefault)
                    Text(
                      'DEFAULT',
                      style: TextStyle(
                        color: connected ? gold : green,
                        fontSize: 8,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class RadarPainter extends CustomPainter {
  const RadarPainter({
    required this.progress,
    required this.connectedCount,
    required this.scanning,
  });

  final double progress;
  final int connectedCount;
  final bool scanning;

  @override
  void paint(Canvas canvas, Size size) {
    const green = Color(0xFF064E3B);
    const gold = Color(0xFFD8AD16);
    final center = size.center(Offset.zero);
    final radius = size.shortestSide / 2;

    canvas.drawCircle(
      center,
      radius,
      Paint()..color = const Color(0xFFEAF6F1),
    );

    final line = Paint()
      ..color = const Color(0x33064E3B)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;

    for (final factor in [1.0, .72, .46]) {
      canvas.drawCircle(center, radius * factor, line);
    }

    canvas.drawLine(
      Offset(center.dx - radius, center.dy),
      Offset(center.dx + radius, center.dy),
      line,
    );
    canvas.drawLine(
      Offset(center.dx, center.dy - radius),
      Offset(center.dx, center.dy + radius),
      line,
    );

    final sweep = Paint()
      ..shader = SweepGradient(
        startAngle: 0,
        endAngle: scanning ? 1.35 : .85,
        colors: scanning
            ? const [
                Color(0x00D8AD16),
                Color(0x66D8AD16),
                Color(0xFFFFE36E),
              ]
            : const [
                Color(0x00D8AD16),
                Color(0x33D8AD16),
                Color(0x99D8AD16),
              ],
      ).createShader(Rect.fromCircle(center: center, radius: radius));

    canvas.save();
    canvas.translate(center.dx, center.dy);
    canvas.rotate(progress * math.pi * 2);
    canvas.translate(-center.dx, -center.dy);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -.05,
      1.12,
      true,
      sweep,
    );
    canvas.restore();

    // Bright rotating beam so the scan remains visibly animated on iPad.
    final beamAngle = progress * math.pi * 2;
    final beamEnd = Offset(
      center.dx + radius * .92 * math.cos(beamAngle),
      center.dy + radius * .92 * math.sin(beamAngle),
    );
    canvas.drawLine(
      center,
      beamEnd,
      Paint()
        ..color = scanning ? gold : gold.withValues(alpha: .65)
        ..strokeWidth = scanning ? 2.4 : 1.6
        ..strokeCap = StrokeCap.round,
    );

    final count = connectedCount.clamp(0, 6);
    for (var i = 0; i < count; i++) {
      final angle = (i + 1) * .92;
      final distance = radius * (.38 + ((i % 3) * .16));
      final point = Offset(
        center.dx + distance * math.cos(angle),
        center.dy + distance * math.sin(angle),
      );
      final color = i == 0 ? gold : green;
      canvas.drawCircle(point, 4, Paint()..color = color);
      canvas.drawCircle(
        point,
        9,
        Paint()..color = color.withValues(alpha: .15),
      );
    }
  }

  @override
  bool shouldRepaint(covariant RadarPainter oldDelegate) =>
      oldDelegate.progress != progress ||
      oldDelegate.connectedCount != connectedCount ||
      oldDelegate.scanning != scanning;
}
