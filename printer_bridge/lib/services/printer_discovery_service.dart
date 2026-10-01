import 'dart:async';
import 'dart:io';

import 'package:bonsoir/bonsoir.dart';
import 'package:printing/printing.dart';

class BridgePrinter {
  const BridgePrinter({
    required this.printer,
    required this.source,
    required this.reachable,
  });

  final Printer printer;
  final String source;
  final bool reachable;

  String get key => printer.url.trim().isNotEmpty
      ? printer.url.trim().toLowerCase()
      : printer.name.trim().toLowerCase();

  String get displayName {
    final value = printer.name.trim();
    return value.isNotEmpty ? value : printer.url;
  }

  bool get connected => reachable || printer.isAvailable;
  bool get isDefault => printer.isDefault;
}

class PrinterDiscoveryService {
  static const serviceTypes = <String>[
    '_ipp._tcp',
    '_ipps._tcp',
    '_printer._tcp',
  ];

  Future<List<BridgePrinter>> discover() async {
    final merged = <String, BridgePrinter>{};

    await _discoverSystemPrinters(merged);

    await Future.wait(
      serviceTypes.map(
        (type) => _discoverBonjourType(merged, type),
      ),
    );

    final printers = merged.values.toList()
      ..sort((a, b) {
        final aScore = _score(a);
        final bScore = _score(b);
        if (aScore != bScore) return aScore.compareTo(bScore);
        return a.displayName
            .toLowerCase()
            .compareTo(b.displayName.toLowerCase());
      });

    return printers;
  }

  int _score(BridgePrinter printer) {
    if (printer.isDefault && printer.connected) return 0;
    if (printer.connected) return 1;
    return 2;
  }

  Future<void> _discoverSystemPrinters(
    Map<String, BridgePrinter> target,
  ) async {
    try {
      final info = await Printing.info();
      if (!info.canListPrinters) return;

      for (final printer in await Printing.listPrinters()) {
        _merge(
          target,
          BridgePrinter(
            printer: printer,
            source: 'SYSTEM',
            reachable: printer.isAvailable,
          ),
        );
      }
    } catch (_) {
      // Native Bonjour discovery and the native printer picker remain
      // available even when this platform does not expose printer enumeration.
    }
  }

  Future<void> _discoverBonjourType(
    Map<String, BridgePrinter> target,
    String type,
  ) async {
    final discovery = BonsoirDiscovery(
      type: type,
      printLogs: false,
    );

    StreamSubscription<BonsoirDiscoveryEvent>? subscription;
    final resolutions = <Future<void>>[];
    final additions = <Future<void>>[];

    try {
      await discovery.initialize();
      final stream = discovery.eventStream;
      if (stream == null) return;

      subscription = stream.listen((event) {
        if (event is BonsoirDiscoveryServiceFoundEvent) {
          final service = event.service;
          if (service != null) {
            resolutions.add(
              _resolveQuietly(
                service,
                discovery.serviceResolver,
              ),
            );
          }
          return;
        }

        if (event is BonsoirDiscoveryServiceResolvedEvent) {
          additions.add(
            _addResolvedService(
              target,
              event.service,
              type,
            ),
          );
          return;
        }

        if (event is BonsoirDiscoveryServiceUpdatedEvent) {
          final service = event.service;
          if (service != null) {
            additions.add(
              _addResolvedService(
                target,
                service,
                type,
              ),
            );
          }
        }
      });

      await discovery.start();

      // Give native Bonjour enough time to surface more than the already
      // connected/default printer on iPadOS.
      await Future<void>.delayed(const Duration(seconds: 5));

      if (resolutions.isNotEmpty) {
        await Future.wait(List<Future<void>>.from(resolutions));
      }

      // Resolved events are delivered asynchronously by the native platform.
      await Future<void>.delayed(const Duration(milliseconds: 700));
    } catch (_) {
      // On iOS/iPadOS the first attempt can be interrupted while the Local
      // Network permission alert is being answered. The user can scan again.
    } finally {
      try {
        await discovery.stop();
      } catch (_) {}

      await subscription?.cancel();

      if (additions.isNotEmpty) {
        await Future.wait(List<Future<void>>.from(additions));
      }
    }
  }

  Future<void> _resolveQuietly(
    BonsoirService service,
    ServiceResolver resolver,
  ) async {
    try {
      await service.resolve(resolver);
    } catch (_) {}
  }

  Future<void> _addResolvedService(
    Map<String, BridgePrinter> target,
    BonsoirService service,
    String type,
  ) async {
    final attributes = <String, String>{
      for (final entry in service.attributes.entries)
        entry.key.toLowerCase(): entry.value,
    };

    final host = _preferredHost(service);
    if (host.isEmpty || service.port <= 0) return;

    final secure = type == '_ipps._tcp';
    final scheme = secure ? 'ipps' : 'ipp';
    final rp = (attributes['rp'] ?? 'ipp/print')
        .replaceFirst(RegExp(r'^/+'), '');

    final url = Uri(
      scheme: scheme,
      host: host,
      port: service.port,
      path: '/$rp',
    ).toString();

    final reachable = await _isReachable(host, service.port);

    _merge(
      target,
      BridgePrinter(
        printer: Printer(
          url: url,
          name: service.name,
          model: attributes['ty'] ?? attributes['product'],
          location: attributes['note'] ?? 'Wi-Fi / Bonjour',
          comment: secure ? 'IPP Secure / AirPrint' : 'IPP / AirPrint',
          isDefault: false,
          isAvailable: reachable,
        ),
        source: 'WI-FI BONJOUR',
        reachable: reachable,
      ),
    );
  }

  String _preferredHost(BonsoirService service) {
    final addresses = service.hostAddresses;

    for (final address in addresses) {
      final value = address.trim();
      if (value.contains('.') && !value.startsWith('169.254.')) {
        return value;
      }
    }

    final hostname = (service.hostname ?? '').trim();
    if (hostname.isNotEmpty) {
      return hostname.replaceFirst(RegExp(r'\.$'), '');
    }

    for (final address in addresses) {
      final value = address.trim();
      if (value.isNotEmpty) return value;
    }

    return '';
  }

  void _merge(
    Map<String, BridgePrinter> target,
    BridgePrinter candidate,
  ) {
    if (candidate.key.isEmpty) return;

    final existing = target[candidate.key];
    if (existing == null || (!existing.connected && candidate.connected)) {
      target[candidate.key] = candidate;
    }
  }

  Future<bool> _isReachable(String host, int port) async {
    Socket? socket;

    try {
      socket = await Socket.connect(
        host,
        port,
        timeout: const Duration(milliseconds: 900),
      );
      return true;
    } catch (_) {
      return false;
    } finally {
      socket?.destroy();
    }
  }
}
