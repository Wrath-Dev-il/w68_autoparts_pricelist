(() => {
    const root = document.documentElement;
    const panels = Array.from(document.querySelectorAll('[data-auth-panel]'));
    const switches = Array.from(document.querySelectorAll('[data-auth-switch]'));
    const otpModal = document.querySelector('[data-otp-modal]');
    const otpInput = document.querySelector('[data-otp-input]');

    const setMode = (mode) => {
        const selected = mode === 'register' ? 'register' : 'login';
        root.dataset.authMode = selected;

        panels.forEach((panel) => {
            const active = panel.dataset.authPanel === selected;
            panel.hidden = !active;

            panel.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = !active;
            });
        });

        window.setTimeout(() => {
            document.querySelector(
                `[data-auth-panel="${selected}"] input:not([type="hidden"])`
            )?.focus();
        }, 40);
    };

    switches.forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.authSwitch));
    });

    setMode(root.dataset.authMode || 'login');

    if (otpModal && !otpModal.hidden) {
        document.body.style.overflow = 'hidden';

        window.setTimeout(() => {
            otpInput?.focus();
        }, 80);
    }

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const inputId = button.getAttribute('aria-controls');
            const input = inputId ? document.getElementById(inputId) : null;

            if (!input) return;

            const willShow = input.type === 'password';

            input.type = willShow ? 'text' : 'password';
            button.textContent = willShow ? 'HIDE' : 'SHOW';
            button.setAttribute(
                'aria-label',
                willShow ? 'Hide password' : 'Show password'
            );

            input.focus({ preventScroll: true });
        });
    });

    otpInput?.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
    });
})();
