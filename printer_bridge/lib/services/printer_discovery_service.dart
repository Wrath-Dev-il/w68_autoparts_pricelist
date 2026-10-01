import 'dart:io';

import 'package:multicast_dns/multicast_dns.dart';
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
      : (printer.name ?? '').trim().toLowerCase();

  String get displayName {
    final value = (printer.name ?? '').trim();
    return value.isNotEmpty ? value : printer.url;
  }

  bool get connected => reachable || printer.isAvailable;
  bool get isDefault => printer.isDefault;
}

class PrinterDiscoveryService {
  static const serviceTypes = <String>[
    '_ipp._tcp.local',
    '_ipps._tcp.local',
    '_printer._tcp.local',
  ];

  Future<List<BridgePrinter>> discover() async {
    final merged = <String, BridgePrinter>{};
    await _discoverSystemPrinters(merged);
    await _discoverMdnsPrinters(merged);

    final printers = merged.values.toList()
      ..sort((a, b) {
        final aScore = _score(a);
        final bScore = _score(b);
        if (aScore != bScore) return aScore.compareTo(bScore);
        return a.displayName.toLowerCase().compareTo(b.displayName.toLowerCase());
      });
    return printers;
  }

  int _score(BridgePrinter p) {
    if (p.isDefault && p.connected) return 0;
    if (p.connected) return 1;
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
      // mDNS discovery and the native picker remain available.
    }
  }

  Future<void> _discoverMdnsPrinters(
    Map<String, BridgePrinter> target,
  ) async {
    final client = MDnsClient();
    try {
      await client.start(onError: (_) {});

      for (final type in serviceTypes) {
        final ptrs = await client
            .lookup<PtrResourceRecord>(
              ResourceRecordQuery.serverPointer(type),
              timeout: const Duration(milliseconds: 1400),
            )
            .toList();

        for (final ptr in ptrs) {
          final services = await client
              .lookup<SrvResourceRecord>(
                ResourceRecordQuery.service(ptr.domainName),
                timeout: const Duration(milliseconds: 700),
              )
              .toList();
          if (services.isEmpty) continue;

          final service = services.first;
          final txtRecords = await client
              .lookup<TxtResourceRecord>(
                ResourceRecordQuery.text(ptr.domainName),
                timeout: const Duration(milliseconds: 450),
              )
              .toList();
          final txt = _parseTxt(
            txtRecords.map((record) => record.text).join('\u0000'),
          );

          final host = service.target.replaceFirst(RegExp(r'\.$'), '');
          final path = (txt['rp'] ?? 'ipp/print').replaceFirst(RegExp(r'^/+'), '');
          final secure = type.startsWith('_ipps');
          final scheme = secure ? 'ipps' : 'ipp';
          final url = '$scheme://$host:${service.port}/$path';
          final reachable = await _isReachable(host, service.port);

          _merge(
            target,
            BridgePrinter(
              printer: Printer(
                url: url,
                name: _displayName(ptr.domainName, type),
                model: txt['ty'] ?? txt['product'],
                location: txt['note'] ?? 'Wi-Fi / Bonjour',
                comment: secure ? 'IPP Secure' : 'IPP / AirPrint',
                isDefault: false,
                isAvailable: reachable,
              ),
              source: 'WI-FI IPP',
              reachable: reachable,
            ),
          );
        }
      }
    } catch (_) {
      // Local-network permission can be granted and the user can scan again.
    } finally {
      client.stop();
    }
  }

  void _merge(Map<String, BridgePrinter> target, BridgePrinter candidate) {
    if (candidate.key.isEmpty) return;
    final existing = target[candidate.key];
    if (existing == null || (!existing.connected && candidate.connected)) {
      target[candidate.key] = candidate;
    }
  }

  Map<String, String> _parseTxt(String raw) {
    final values = <String, String>{};
    for (final part in raw.split(RegExp(r'[\x00\r\n]+'))) {
      final equals = part.indexOf('=');
      if (equals <= 0) continue;
      final key = part.substring(0, equals).trim().toLowerCase();
      final value = part.substring(equals + 1).trim();
      if (key.isNotEmpty && value.isNotEmpty) values[key] = value;
    }
    return values;
  }

  String _displayName(String domain, String type) {
    var name = domain;
    final suffix = '.$type';
    if (name.toLowerCase().endsWith(suffix.toLowerCase())) {
      name = name.substring(0, name.length - suffix.length);
    }
    return name
        .replaceAll(r'\032', ' ')
        .replaceAll(r'\.', '.')
        .replaceFirst(RegExp(r'\.$'), '')
        .trim();
  }

  Future<bool> _isReachable(String host, int port) async {
    Socket? socket;
    try {
      socket = await Socket.connect(
        host,
        port,
        timeout: const Duration(milliseconds: 700),
      );
      return true;
    } catch (_) {
      return false;
    } finally {
      socket?.destroy();
    }
  }
}
