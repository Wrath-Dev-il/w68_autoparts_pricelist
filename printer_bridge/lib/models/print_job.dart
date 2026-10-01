class W68PrintJob {
  const W68PrintJob({
    required this.version,
    required this.job,
    required this.receipt,
    required this.items,
  });

  final int version;
  final Map<String, dynamic> job;
  final W68Receipt receipt;
  final List<W68ReceiptItem> items;

  String get invoiceNo => receipt.invoiceNo;
  String get orderCode => _string(job['order_code']);

  factory W68PrintJob.fromJson(Map<String, dynamic> json) {
    final rows = (json['items'] as List<dynamic>? ?? const <dynamic>[])
        .whereType<Map>()
        .map((row) => W68ReceiptItem.fromJson(Map<String, dynamic>.from(row)))
        .toList(growable: false);

    return W68PrintJob(
      version: _int(json['version'], 1),
      job: Map<String, dynamic>.from(json['job'] as Map? ?? const {}),
      receipt: W68Receipt.fromJson(
        Map<String, dynamic>.from(json['receipt'] as Map? ?? const {}),
      ),
      items: rows,
    );
  }
}

class W68Receipt {
  const W68Receipt({
    required this.invoiceNo,
    required this.customerName,
    required this.customerAddress,
    required this.customerTin,
    required this.salesNumber,
    required this.date,
    required this.terms,
    required this.salesman,
    required this.rushText,
    required this.preparedBy,
    required this.packedBy,
    required this.checkedBy,
    required this.invoiceAmount,
    required this.additionalLess,
    required this.netAmount,
    required this.totalQty,
  });

  final String invoiceNo;
  final String customerName;
  final String customerAddress;
  final String customerTin;
  final String salesNumber;
  final String date;
  final String terms;
  final String salesman;
  final String rushText;
  final String preparedBy;
  final String packedBy;
  final String checkedBy;
  final double invoiceAmount;
  final double additionalLess;
  final double netAmount;
  final double totalQty;

  factory W68Receipt.fromJson(Map<String, dynamic> json) => W68Receipt(
        invoiceNo: _string(json['invoice_no']),
        customerName: _string(json['customer_name']),
        customerAddress: _string(json['customer_address']),
        customerTin: _string(json['customer_tin']),
        salesNumber: _string(json['sales_number']),
        date: _string(json['date']),
        terms: _string(json['terms']),
        salesman: _string(json['salesman']),
        rushText: _string(json['rush_text']),
        preparedBy: _string(json['prepared_by']),
        packedBy: _string(json['packed_by']),
        checkedBy: _string(json['checked_by']),
        invoiceAmount: _double(json['invoice_amount']),
        additionalLess: _double(json['additional_less']),
        netAmount: _double(json['net_amount']),
        totalQty: _double(json['total_qty']),
      );
}

class W68ReceiptItem {
  const W68ReceiptItem({
    required this.productId,
    required this.productCode,
    required this.priceCode,
    required this.partNumber,
    required this.description,
    required this.application,
    required this.position,
    required this.brand,
    required this.oum,
    required this.qty,
    required this.additionalQty,
    required this.unitPrice,
    required this.discountPercent,
    required this.additionalDiscountPercent,
    required this.printSubtotal,
    required this.additionalLess,
    required this.netTotal,
  });

  final int productId;
  final String productCode;
  final String priceCode;
  final String partNumber;
  final String description;
  final String application;
  final String position;
  final String brand;
  final String oum;
  final double qty;
  final double additionalQty;
  final double unitPrice;
  final double discountPercent;
  final double additionalDiscountPercent;
  final double printSubtotal;
  final double additionalLess;
  final double netTotal;

  String get receiptCode => priceCode.trim().isNotEmpty ? priceCode : productCode;

  factory W68ReceiptItem.fromJson(Map<String, dynamic> json) => W68ReceiptItem(
        productId: _int(json['product_id']),
        productCode: _string(json['product_code']),
        priceCode: _string(json['price_code']),
        partNumber: _string(json['part_number']),
        description: _string(json['description']),
        application: _string(json['application']),
        position: _string(json['position']),
        brand: _string(json['brand']),
        oum: _string(json['oum']),
        qty: _double(json['qty']),
        additionalQty: _double(json['additional_qty']),
        unitPrice: _double(json['unit_price']),
        discountPercent: _double(json['discount_percent']),
        additionalDiscountPercent: _double(json['additional_discount_percent']),
        printSubtotal: _double(json['print_subtotal']),
        additionalLess: _double(json['additional_less']),
        netTotal: _double(json['net_total']),
      );
}

String _string(Object? value) => value == null ? '' : value.toString();

int _int(Object? value, [int fallback = 0]) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

double _double(Object? value) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}
