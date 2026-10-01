# W68 Printer Bridge

W68 Printer Bridge is the native companion for w68_autoparts_pricelist invoice printing.

## Implemented behavior

The bridge receives a short-lived W68 printer job from the Laravel portal.

It combines two real printer sources:

1. Printers already exposed by the operating system through the Flutter printing layer.
2. Live Bonjour/mDNS scans for IPP, IPPS, and printer services on the local network.

Discovered network printers are TCP-checked. Reachable printers are shown with the W68 navigation green background (#064e3b) and yellow printer icon (#ffe36e).

The selected printer is printed to directly when the platform permits direct printing. When direct printing is unavailable, W68 falls back to the operating system printer picker.

The bridge does not collect or store printer Wi-Fi passwords, PINs, or printer administrator credentials. Those remain in the operating-system or printer authentication flow.

## Secure Laravel handoff

The portal creates a row in core4_sales_order.w68_printer_bridge_jobs only when an authenticated customer presses PRINT.

Security characteristics:

- 32 random bytes / 256-bit raw token.
- Only SHA-256 of the token is stored.
- Token expires after 10 minutes.
- Job is tied to the exact portal order, customer, and sales_order invoice.
- Native job endpoint uses no-store response headers.
- Expired jobs are removed as new jobs are created.
- Browser printing remains available as fallback.

Normal flow:

Orders
-> Invoiced
-> PRINT
-> secure printer job
-> w68print://job
-> W68 Printer Bridge
-> real printer discovery
-> direct print or native picker

## Required Flutter version

The checked-in source targets Dart 3.12 or newer.

## Generate Android, iOS, and Windows runner folders

Windows PowerShell:

    cd printer_bridge
    powershell -ExecutionPolicy Bypass -File .\scripts\bootstrap.ps1

macOS / Linux:

    cd printer_bridge
    bash ./scripts/bootstrap.sh

Then:

    flutter doctor
    flutter pub get
    flutter analyze

## Android

Apply platform/android/AndroidManifest.snippet.xml to android/app/src/main/AndroidManifest.xml.

Build:

    flutter build apk --release

For Play Store:

    flutter build appbundle --release

## iPhone / iPad

Apply platform/ios/Info.plist.snippet.xml to ios/Runner/Info.plist.

Inside the Runner target in ios/Podfile also add:

    use_frameworks!

Then on macOS:

    flutter pub get
    cd ios
    pod install
    cd ..
    flutter build ios --release

The first scan can trigger the iOS/iPadOS Local Network permission prompt. Allow it for Bonjour discovery.

## Windows

Build:

    flutter build windows --release

Register the custom protocol:

    powershell -ExecutionPolicy Bypass -File .\platform\windows\register-w68print.ps1 -ExePath ".\build\windows\x64\runner\Release\w68_printer_bridge.exe"

A production installer should perform the same w68print protocol registration.

## Testing

1. Deploy the Laravel Printer Bridge web changes.
2. Install the native bridge on the device.
3. Sign into the Pricelist portal.
4. Open Orders -> Invoiced.
5. Press PRINT.
6. The bridge loads the exact invoice and scans printers.
7. Reachable printers appear green with a yellow icon.
8. Tap a printer to print.
9. Use NATIVE PICKER for vendor-managed printers.

## Printer discovery limitations

This is real discovery, not a simulated list.

AirPrint, IPP, IPPS, and Bonjour printers can normally be found by the Wi-Fi scan.

Installed/shared printers exposed by the operating system can be found through system printer enumeration on supported platforms.

A proprietary printer that does not advertise IPP/Bonjour may first require its manufacturer's driver or mobile print service. In that case use NATIVE PICKER.

The bridge never scans for or requests a Wi-Fi password itself.
