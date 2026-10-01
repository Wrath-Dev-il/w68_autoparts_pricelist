import 'dart:io';

void main() {
  final root = Directory.current;

  _patchIos(root);
  _patchAndroid(root);

  stdout.writeln('W68 native platform configuration applied.');
}

void _patchIos(Directory root) {
  final plist = File('${root.path}/ios/Runner/Info.plist');

  if (!plist.existsSync()) {
    stdout.writeln('iOS runner not found; skipping Info.plist patch.');
    return;
  }

  var text = plist.readAsStringSync();

  if (!text.contains('<key>NSLocalNetworkUsageDescription</key>')) {
    text = _insertBeforeTopLevelDictClose(
      text,
      '''
    <!-- W68_PRINTER_BRIDGE_IOS_V118 -->
    <key>NSLocalNetworkUsageDescription</key>
    <string>W68 Printer Bridge scans your local network for printers you choose to use.</string>
''',
    );
  }

  const bonjourServices = <String>[
    '_ipp._tcp',
    '_ipps._tcp',
    '_printer._tcp',
  ];

  if (!text.contains('<key>NSBonjourServices</key>')) {
    text = _insertBeforeTopLevelDictClose(
      text,
      '''
    <key>NSBonjourServices</key>
    <array>
        <string>_ipp._tcp</string>
        <string>_ipps._tcp</string>
        <string>_printer._tcp</string>
    </array>
''',
    );
  } else {
    for (final service in bonjourServices) {
      if (!text.contains('<string>$service</string>')) {
        text = _appendStringToArrayForKey(
          text,
          'NSBonjourServices',
          service,
        );
      }
    }
  }

  if (!text.contains('<key>CFBundleURLTypes</key>')) {
    text = _insertBeforeTopLevelDictClose(
      text,
      '''
    <key>CFBundleURLTypes</key>
    <array>
        <dict>
            <key>CFBundleTypeRole</key>
            <string>Editor</string>
            <key>CFBundleURLName</key>
            <string>com.w68autoparts.printerbridge</string>
            <key>CFBundleURLSchemes</key>
            <array>
                <string>w68print</string>
            </array>
        </dict>
    </array>
''',
    );
  } else if (!text.contains('<string>w68print</string>')) {
    text = _appendDictionaryToArrayForKey(
      text,
      'CFBundleURLTypes',
      '''
        <dict>
            <key>CFBundleTypeRole</key>
            <string>Editor</string>
            <key>CFBundleURLName</key>
            <string>com.w68autoparts.printerbridge</string>
            <key>CFBundleURLSchemes</key>
            <array>
                <string>w68print</string>
            </array>
        </dict>
''',
    );
  }

  plist.writeAsStringSync(text);

  final podfile = File('${root.path}/ios/Podfile');
  if (podfile.existsSync()) {
    var podText = podfile.readAsStringSync();
    if (!podText.contains(RegExp(r'^\s*use_frameworks!\s*$', multiLine: true))) {
      const target = "target 'Runner' do";
      if (podText.contains(target)) {
        podText = podText.replaceFirst(
          target,
          "$target\n  use_frameworks!",
        );
        podfile.writeAsStringSync(podText);
      }
    }
  }

  stdout.writeln('Configured iOS URL scheme + Local Network + Bonjour.');
}

void _patchAndroid(Directory root) {
  final manifest = File(
    '${root.path}/android/app/src/main/AndroidManifest.xml',
  );

  if (!manifest.existsSync()) {
    stdout.writeln('Android runner not found; skipping manifest patch.');
    return;
  }

  var text = manifest.readAsStringSync();

  const permissions = <String>[
    'android.permission.INTERNET',
    'android.permission.ACCESS_NETWORK_STATE',
    'android.permission.ACCESS_WIFI_STATE',
    'android.permission.CHANGE_WIFI_MULTICAST_STATE',
  ];

  for (final permission in permissions) {
    if (!text.contains('android:name="$permission"')) {
      final manifestOpenEnd = text.indexOf('>');
      if (manifestOpenEnd < 0) {
        throw StateError('AndroidManifest.xml has no manifest opening tag.');
      }

      text = text.replaceRange(
        manifestOpenEnd + 1,
        manifestOpenEnd + 1,
        '\n    <uses-permission android:name="$permission" />',
      );
    }
  }

  if (!text.contains('android:scheme="w68print"')) {
    final activityClose = text.indexOf('</activity>');
    if (activityClose < 0) {
      throw StateError('AndroidManifest.xml has no activity element.');
    }

    const intent = '''
            <!-- W68_PRINTER_BRIDGE_ANDROID_V118 -->
            <intent-filter android:autoVerify="false">
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data android:scheme="w68print" android:host="job" />
            </intent-filter>
''';

    text = text.replaceRange(
      activityClose,
      activityClose,
      intent,
    );
  }

  manifest.writeAsStringSync(text);
  stdout.writeln('Configured Android deep link + network permissions.');
}

String _insertBeforeTopLevelDictClose(
  String text,
  String insertion,
) {
  final plistClose = text.lastIndexOf('</plist>');
  if (plistClose < 0) {
    throw StateError('Info.plist has no closing plist tag.');
  }

  final dictClose = text.lastIndexOf('</dict>', plistClose);
  if (dictClose < 0) {
    throw StateError('Info.plist has no closing top-level dict.');
  }

  return text.replaceRange(
    dictClose,
    dictClose,
    insertion,
  );
}

String _appendStringToArrayForKey(
  String text,
  String key,
  String value,
) {
  return _appendToArrayForKey(
    text,
    key,
    '        <string>$value</string>\n',
  );
}

String _appendDictionaryToArrayForKey(
  String text,
  String key,
  String dictionary,
) {
  return _appendToArrayForKey(
    text,
    key,
    dictionary,
  );
}

String _appendToArrayForKey(
  String text,
  String key,
  String insertion,
) {
  final keyIndex = text.indexOf('<key>$key</key>');
  if (keyIndex < 0) {
    throw StateError('Info.plist key $key was not found.');
  }

  final arrayOpen = text.indexOf('<array>', keyIndex);
  if (arrayOpen < 0) {
    throw StateError('Info.plist key $key has no array value.');
  }

  final arrayClose = _matchingArrayClose(text, arrayOpen);
  return text.replaceRange(
    arrayClose,
    arrayClose,
    insertion,
  );
}

int _matchingArrayClose(String text, int arrayOpen) {
  final tokenPattern = RegExp(r'<array>|</array>');
  var depth = 0;

  for (final match in tokenPattern.allMatches(text, arrayOpen)) {
    if (match.group(0) == '<array>') {
      depth++;
      continue;
    }

    depth--;
    if (depth == 0) {
      return match.start;
    }
  }

  throw StateError('Info.plist array is not balanced.');
}
