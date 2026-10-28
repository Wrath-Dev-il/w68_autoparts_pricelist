<div class="w68-notification-modal" data-notification-modal hidden aria-hidden="true">
    <button type="button" class="w68-notification-backdrop" data-notification-close aria-label="Close notifications"></button>

    <section class="w68-notification-card" role="dialog" aria-modal="true" aria-labelledby="w68-notification-title">
        <header class="w68-notification-header">
            <div>
                <span>ACCOUNT UPDATES</span>
                <h2 id="w68-notification-title">Notifications</h2>
                <small>Updates from your W68 orders, current bills, due-soon reminders, and statements of account.</small>
            </div>

            <div class="w68-notification-header-actions">
                <button type="button" class="w68-notification-read-all" data-notification-read-all hidden>MARK ALL AS READ</button>
                <button type="button" class="w68-notification-close" data-notification-close aria-label="Close notifications">&times;</button>
            </div>
        </header>

        <div class="w68-notification-summary">
            <strong data-notification-summary>Checking order updates...</strong>
        </div>

        <div class="w68-notification-list" data-notification-list>
            <div class="w68-notification-loading">Loading notifications...</div>
        </div>
    </section>
</div>

@php
    $notificationRobotAsset = is_file(public_path('images/w68_robot_tutorial_mascot.gif'))
        ? asset('images/w68_robot_tutorial_mascot.gif')
        : asset('images/w68_robot_tutorial_fallback.svg');
@endphp

{{-- W68_NOTIFICATION_TUTORIAL_20261028 --}}
<div class="w68-notification-tutorial-overlay" data-w68-notification-tutorial hidden aria-hidden="true">
    <div class="w68-notification-tutorial-scrim" data-w68-notification-tutorial-scrim></div>
    <div class="w68-notification-tutorial-spotlight" data-w68-notification-tutorial-spotlight hidden></div>
    <div class="w68-notification-tutorial-arrow" data-w68-notification-tutorial-arrow hidden><span></span></div>

    <div class="w68-notification-tutorial-robot" data-w68-notification-tutorial-robot>
        <img
            src="{{ $notificationRobotAsset }}"
            data-w68-notification-tutorial-robot-image
            data-fallback="{{ asset('images/w68_robot_tutorial_fallback.svg') }}"
            onerror="if(this.dataset.fallback && this.src !== this.dataset.fallback){this.src=this.dataset.fallback;}"
            alt="W68 notification tutorial robot"
        >
    </div>

    <section
        class="w68-notification-tutorial-card"
        data-w68-notification-tutorial-card
        role="dialog"
        aria-modal="true"
        aria-labelledby="w68-notification-tutorial-title"
    >
        <span class="w68-notification-tutorial-kicker" data-w68-notification-tutorial-kicker>W68 NOTIFICATIONS GUIDE</span>
        <h2 id="w68-notification-tutorial-title" data-w68-notification-tutorial-title>Notification Updates</h2>
        <p data-w68-notification-tutorial-body>Stay informed about your order shipments, sales notes, and billing updates.</p>
        <div class="w68-notification-tutorial-actions">
            <button type="button" class="w68-notification-tutorial-skip" data-w68-notification-tutorial-skip>SKIP</button>
            <button type="button" class="w68-notification-tutorial-next" data-w68-notification-tutorial-next>NEXT</button>
            <button type="button" class="w68-notification-tutorial-done" data-w68-notification-tutorial-done hidden>DONE</button>
        </div>
    </section>
</div>

