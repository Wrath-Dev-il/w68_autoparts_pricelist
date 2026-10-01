<?php

return [
    /*
    |--------------------------------------------------------------------------
    | W68 Printer Bridge installers
    |--------------------------------------------------------------------------
    |
    | Set these to the signed installer/distribution URLs for each platform.
    | iOS/iPadOS must be an App Store or TestFlight URL for the signed app.
    |
    */
    'install' => [
        'ios' => env('W68_PRINTER_BRIDGE_IOS_INSTALL_URL', ''),
        'android' => env('W68_PRINTER_BRIDGE_ANDROID_INSTALL_URL', ''),
        'windows' => env('W68_PRINTER_BRIDGE_WINDOWS_INSTALL_URL', ''),
    ],
];
