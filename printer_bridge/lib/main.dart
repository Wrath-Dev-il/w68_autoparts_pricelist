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
      duration: const Duration(milliseconds: 3400),
    )..repeat();

    _initializeLinks();
    WidgetsBinding.instance.addPostFrameCallback((_) => _scanPrinters());
  }

  Future<void> _initializeLinks() async {
    try {
      final initial = await appLinks.getInitialLink();
      if (initial != null) await _handleLink(initial);
    } catch (e) {
      _setError('Unable to read the W68 print request: $e');
    }

    linkSubscription = appLinks.uriLinkStream.listen(
      _handleLink,
      onError: (Object e) {
        _setError('Unable to read the W68 print request: $e');
      },
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
      _setError('The W68 print request is invalid.');
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
        status = 'Scanning for available printers…';
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

    final previousSelection = selectedPrinterKey;

    setState(() {
      scanning = true;
      error = null;
      status = 'Scanning printers within the local network…';
    });

    try {
      final found = await discovery.discover();
      if (!mounted) return;

      final available = found.where((printer) => printer.connected).toList();
      String? nextSelection;

      if (available.isNotEmpty) {
        final previousMatches = available
            .where((printer) => printer.key == previousSelection)
            .toList();

        if (previousMatches.isNotEmpty) {
          nextSelection = previousMatches.first.key;
        } else {
          final defaults =
              available.where((printer) => printer.isDefault).toList();
          nextSelection =
              defaults.isNotEmpty ? defaults.first.key : available.first.key;
        }
      }

      final selected = _selectedPrinterFrom(available, nextSelection);

      setState(() {
        printers = available;
        selectedPrinterKey = nextSelection;
        scanning = false;

        if (available.isEmpty) {
          status = 'NO PRINTER AVAILABLE WITHIN RANGE / NETWORK';
        } else if (available.length == 1) {
          status = '${available.first.displayName} selected automatically.';
        } else {
          status =
              '${available.length} printers found. ${selected?.displayName ?? available.first.displayName} selected.';
        }
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        printers = const [];
        selectedPrinterKey = null;
        scanning = false;
      });

      _setError('Printer scan failed: $e');
    }
  }

  BridgePrinter? _selectedPrinterFrom(
    List<BridgePrinter> source,
    String? key,
  ) {
    if (key == null) return null;

    for (final printer in source) {
      if (printer.key == key) return printer;
    }

    return null;
  }

  BridgePrinter? get selectedPrinter =>
      _selectedPrinterFrom(printers, selectedPrinterKey);

  Future<void> _openReceiptDialog() async {
    final currentJob = job;
    final target = selectedPrinter;

    if (currentJob == null || target == null || printing) return;

    var copies = 1;
    final controller = TextEditingController(text: '1');

    await showDialog<void>(
      context: context,
      barrierDismissible: !printing,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (dialogContext, setDialogState) {
            void updateCopies(int next) {
              copies = next.clamp(1, 20);
              controller.text = copies.toString();
              controller.selection = TextSelection.collapsed(
                offset: controller.text.length,
              );
              setDialogState(() {});
            }

            return Dialog(
              insetPadding: const EdgeInsets.all(12),
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  maxWidth: 1050,
                  maxHeight: 850,
                ),
                child: Column(
                  children: [
                    Container(
                      padding: const EdgeInsets.fromLTRB(16, 12, 10, 12),
                      color: green,
                      child: Row(
                        children: [
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'PRINT RECEIPT',
                                  style: TextStyle(
                                    color: gold,
                                    fontSize: 17,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                SizedBox(height: 2),
                                Text(
                                  'A4 PORTRAIT • 10 MM W68 DOCUMENT MARGINS',
                                  style: TextStyle(
                                    color: Color(0xFFD8EEE6),
                                    fontSize: 9,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: printing
                                ? null
                                : () => Navigator.of(dialogContext).pop(),
                            color: gold,
                            icon: const Icon(Icons.close),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: LayoutBuilder(
                        builder: (context, constraints) {
                          final compact = constraints.maxWidth < 720;

                          final controls = _receiptControls(
                            target: target,
                            copies: copies,
                            controller: controller,
                            onMinus: () => updateCopies(copies - 1),
                            onPlus: () => updateCopies(copies + 1),
                            onChanged: (value) {
                              final parsed = int.tryParse(value) ?? 1;
                              updateCopies(parsed);
                            },
                          );

                          final preview = Container(
                            color: const Color(0xFFE8EEEB),
                            padding: const EdgeInsets.all(8),
                            child: PdfPreview(
                              key: ValueKey<int>(copies),
                              build: (_) => ReceiptPdf.build(
                                currentJob,
                                PdfPageFormat.a4,
                                copies: copies,
                              ),
                              initialPageFormat: PdfPageFormat.a4,
                              canChangePageFormat: false,
                              canChangeOrientation: false,
                              canDebug: false,
                              allowPrinting: false,
                              allowSharing: false,
                              pdfFileName:
                                  'W68-Invoice-${currentJob.invoiceNo}.pdf',
                            ),
                          );

                          if (compact) {
                            return Column(
                              children: [
                                controls,
                                const Divider(height: 1),
                                Expanded(child: preview),
                              ],
                            );
                          }

                          return Row(
                            children: [
                              SizedBox(width: 270, child: controls),
                              const VerticalDivider(width: 1),
                              Expanded(child: preview),
                            ],
                          );
                        },
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: const BoxDecoration(
                        border: Border(
                          top: BorderSide(color: Color(0xFFD8E2DD)),
                        ),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              'Selected printer: ${target.displayName}',
                              style: const TextStyle(
                                color: green,
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          FilledButton.icon(
                            onPressed: printing
                                ? null
                                : () async {
                                    final sent = await _directPrint(
                                      target,
                                      copies,
                                    );

                                    if (!dialogContext.mounted) return;
                                    if (sent) Navigator.of(dialogContext).pop();
                                  },
                            style: FilledButton.styleFrom(
                              backgroundColor: green,
                              foregroundColor: gold,
                              minimumSize: const Size(140, 46),
                            ),
                            icon: const Icon(Icons.print),
                            label: Text(
                              printing ? 'PRINTING…' : 'PRINT',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );

    controller.dispose();
  }

  Widget _receiptControls({
    required BridgePrinter target,
    required int copies,
    required TextEditingController controller,
    required VoidCallback onMinus,
    required VoidCallback onPlus,
    required ValueChanged<String> onChanged,
  }) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _settingCard('SELECTED PRINTER', target.displayName),
          const SizedBox(height: 9),
          _settingCard('PAPER SIZE', 'A4 PORTRAIT'),
          const SizedBox(height: 9),
          _settingCard('DOCUMENT MARGINS', '10 MM W68 LAYOUT'),
          const SizedBox(height: 9),
          Container(
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFD8E2DD)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'COPIES',
                  style: TextStyle(
                    color: Color(0xFF6E7D75),
                    fontSize: 8,
                    fontWeight: FontWeight.w900,
                    letterSpacing: .7,
                  ),
                ),
                const SizedBox(height: 7),
                Row(
                  children: [
                    IconButton.filled(
                      onPressed: copies > 1 ? onMinus : null,
                      style: IconButton.styleFrom(
                        backgroundColor: green,
                        foregroundColor: gold,
                      ),
                      icon: const Icon(Icons.remove),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: controller,
                        keyboardType: TextInputType.number,
                        textAlign: TextAlign.center,
                        onChanged: onChanged,
                        decoration: const InputDecoration(
                          isDense: true,
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filled(
                      onPressed: copies < 20 ? onPlus : null,
                      style: IconButton.styleFrom(
                        backgroundColor: green,
                        foregroundColor: gold,
                      ),
                      icon: const Icon(Icons.add),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          const Text(
            'The preview shows the complete A4 receipt. Multiple pages/copies can be scrolled inside the preview.',
            style: TextStyle(
              color: Color(0xFF6E7D75),
              fontSize: 9,
              height: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _settingCard(String label, String value) {
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: green,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0x55FFE36E)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFFBFE4D8),
              fontSize: 8,
              fontWeight: FontWeight.w900,
              letterSpacing: .7,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            value,
            style: const TextStyle(
              color: gold,
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  Future<bool> _directPrint(
    BridgePrinter target,
    int copies,
  ) async {
    final currentJob = job;
    if (currentJob == null || printing) return false;

    setState(() {
      printing = true;
      error = null;
      status = 'Printing ${currentJob.invoiceNo} to ${target.displayName}…';
    });

    try {
      final bytes = await ReceiptPdf.build(
        currentJob,
        PdfPageFormat.a4,
        copies: copies,
      );

      final sent = await Printing.directPrintPdf(
        printer: target.printer,
        name: 'W68 Invoice ${currentJob.invoiceNo}',
        format: PdfPageFormat.a4,
        usePrinterSettings: false,
        onLayout: (_) async => bytes,
      );

      if (!mounted) return sent;

      setState(() {
        printing = false;
        status = sent
            ? 'Print job sent to ${target.displayName}.'
            : 'The printer did not accept the W68 print job.';
      });

      if (!sent) {
        _setError(
          'Direct printing failed for ${target.displayName}. '
          'Check that the printer is online and reachable, then scan again.',
        );
      }

      return sent;
    } catch (e) {
      if (!mounted) return false;
      setState(() => printing = false);
      _setError('Unable to print directly to ${target.displayName}: $e');
      return false;
    }
  }

  void _setError(String message) {
    if (!mounted) return;

    setState(() {
      error = message;
      status = 'Printer Bridge needs attention.';
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
    final target = selectedPrinter;
    final canPrint =
        currentJob != null && target != null && !scanning && !printing;

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
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w900,
              ),
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
              _radar(),
              const SizedBox(height: 10),
              Text(
                status,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: printers.isEmpty && !scanning ? maroon : green,
                  fontSize: 11,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (error != null) ...[
                const SizedBox(height: 12),
                _errorBox(error!),
              ],
              const SizedBox(height: 14),
              OutlinedButton.icon(
                onPressed: scanning ? null : _scanPrinters,
                icon: const Icon(Icons.radar),
                label: Text(scanning ? 'SCANNING…' : 'SCAN AGAIN'),
              ),
              const SizedBox(height: 18),
              const Text(
                'AVAILABLE PRINTERS',
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
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(11),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF8DC),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE8D98F)),
                ),
                child: const Text(
                  'RECEIPT FORMAT: A4 PORTRAIT • 10 MM W68 DOCUMENT MARGINS',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: green,
                    fontSize: 9,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(height: 10),
              FilledButton.icon(
                onPressed: canPrint ? _openReceiptDialog : null,
                style: FilledButton.styleFrom(
                  backgroundColor: green,
                  foregroundColor: gold,
                  minimumSize: const Size.fromHeight(50),
                ),
                icon: const Icon(Icons.receipt_long),
                label: Text(
                  printers.isEmpty
                      ? 'NO PRINTER AVAILABLE'
                      : 'PRINT RECEIPT',
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    letterSpacing: .5,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _radar() {
    const positions = <Alignment>[
      Alignment(-.64, -.55),
      Alignment(.66, -.54),
      Alignment(.72, .52),
      Alignment(-.60, .58),
      Alignment(0, -.78),
      Alignment(.02, .78),
    ];

    return Center(
      child: SizedBox(
        width: 280,
        height: 280,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Positioned.fill(
              child: AnimatedBuilder(
                animation: radarController,
                builder: (context, child) => CustomPaint(
                  painter: RadarPainter(
                    progress: radarController.value,
                    printerCount: printers.length,
                    scanning: scanning,
                  ),
                ),
              ),
            ),
            for (var index = 0;
                index < printers.length.clamp(0, positions.length);
                index++)
              Align(
                alignment: positions[index],
                child: Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: printers[index].key == selectedPrinterKey
                        ? green
                        : Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: printers[index].key == selectedPrinterKey
                          ? gold
                          : green,
                      width: 2,
                    ),
                  ),
                  child: Icon(
                    Icons.print,
                    size: 20,
                    color: printers[index].key == selectedPrinterKey
                        ? gold
                        : green,
                  ),
                ),
              ),
            Container(
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
          ],
        ),
      ),
    );
  }

  Widget _jobCard(W68PrintJob? currentJob) {
    return Container(
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
  }

  Widget _errorBox(String message) {
    return Container(
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
  }

  Widget _emptyPrinters() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFFFEEEE),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE6B8B8)),
      ),
      child: const Column(
        children: [
          Icon(Icons.print_disabled, color: maroon, size: 30),
          SizedBox(height: 7),
          Text(
            'NO PRINTER AVAILABLE WITHIN RANGE / NETWORK',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: maroon,
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
          SizedBox(height: 4),
          Text(
            'Make sure the printer is powered on and reachable from this device, then tap SCAN AGAIN.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Color(0xFF6E7D75),
              fontSize: 9,
              height: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _printerCard(BridgePrinter printer) {
    final selected = printer.key == selectedPrinterKey;

    return Container(
      margin: const EdgeInsets.only(bottom: 9),
      decoration: BoxDecoration(
        color: selected ? green : Colors.white,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(
          color: selected ? gold : const Color(0xFFDCE6E1),
          width: selected ? 3 : 1,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(13),
        onTap: printing
            ? null
            : () {
                setState(() {
                  selectedPrinterKey = printer.key;
                  status = '${printer.displayName} selected.';
                });
              },
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            children: [
              Icon(
                selected
                    ? Icons.radio_button_checked
                    : Icons.radio_button_unchecked,
                color: selected ? gold : green,
                size: 24,
              ),
              const SizedBox(width: 11),
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: selected
                      ? const Color(0x1FFFE36E)
                      : const Color(0xFFEAF6F1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  Icons.print,
                  color: selected ? gold : green,
                  size: 25,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      printer.displayName,
                      style: TextStyle(
                        color: selected ? Colors.white : green,
                        fontSize: 12,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${printer.source} • AVAILABLE',
                      style: TextStyle(
                        color: selected ? gold : const Color(0xFF6E7D75),
                        fontSize: 9,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              if (printer.isDefault)
                Text(
                  'DEFAULT',
                  style: TextStyle(
                    color: selected ? gold : green,
                    fontSize: 8,
                    fontWeight: FontWeight.w900,
                  ),
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
    required this.printerCount,
    required this.scanning,
  });

  final double progress;
  final int printerCount;
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
      ).createShader(
        Rect.fromCircle(center: center, radius: radius),
      );

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

    if (printerCount == 0 && scanning) {
      canvas.drawCircle(
        center,
        radius * (.25 + ((progress * .7) % .65)),
        Paint()
          ..color = green.withValues(alpha: .22)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2,
      );
    }
  }

  @override
  bool shouldRepaint(covariant RadarPainter oldDelegate) =>
      oldDelegate.progress != progress ||
      oldDelegate.printerCount != printerCount ||
      oldDelegate.scanning != scanning;
}
