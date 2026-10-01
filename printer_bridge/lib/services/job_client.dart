import 'dart:convert';
import 'dart:io';

import '../models/print_job.dart';

class PrinterJobClient {
  const PrinterJobClient();

  Future<W68PrintJob> fetch(Uri jobUrl) async {
    if (jobUrl.scheme != 'https' && jobUrl.scheme != 'http') {
      throw const FormatException('Printer job URL must use HTTP or HTTPS.');
    }

    final client = HttpClient()
      ..connectionTimeout = const Duration(seconds: 8)
      ..idleTimeout = const Duration(seconds: 10);

    try {
      final request = await client.getUrl(jobUrl);
      request.headers.set(HttpHeaders.acceptHeader, 'application/json');
      request.headers.set(HttpHeaders.cacheControlHeader, 'no-store');
      final response = await request.close();
      final body = await response.transform(utf8.decoder).join();

      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw HttpException(
          'W68 printer job failed (${response.statusCode}): $body',
          uri: jobUrl,
        );
      }

      final decoded = jsonDecode(body);
      if (decoded is! Map) {
        throw const FormatException('W68 printer job response is invalid.');
      }

      return W68PrintJob.fromJson(Map<String, dynamic>.from(decoded));
    } finally {
      client.close(force: true);
    }
  }
}
