(() => {
    const root = document.documentElement;
    const panels = Array.from(document.querySelectorAll('[data-auth-panel]'));
    const switches = Array.from(document.querySelectorAll('[data-auth-switch]'));
    const otpModal = document.querySelector('[data-otp-modal]');
    const otpInput = document.querySelector('[data-otp-input]');

    const setMode = (mode) => {
        const safeMode = mode === 'register' ? 'register' : 'login';

        root.dataset.authMode = safeMode;

        panels.forEach((panel) => {
            const active = panel.dataset.authPanel === safeMode;
            panel.hidden = !active;

            panel.querySelectorAll('input, button, select, textarea').forEach((control) => {
                if (control.matches('[data-auth-switch]')) return;
                control.disabled = !active;
            });
        });

        const firstInput = document.querySelector(
            `[data-auth-panel="${safeMode}"] input:not([type="hidden"])`
        );

        window.setTimeout(() => firstInput?.focus(), 40);
    };

    switches.forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.authSwitch));
    });

    setMode(root.dataset.authMode || 'login');

    if (otpModal && !otpModal.hidden) {
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => otpInput?.focus(), 80);
    }

    otpInput?.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
    });
})();
