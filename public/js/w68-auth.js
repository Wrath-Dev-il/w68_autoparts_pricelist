(() => {
    const root = document.documentElement;
    const panels = [...document.querySelectorAll('[data-auth-panel]')];
    const switches = [...document.querySelectorAll('[data-auth-switch]')];
    const otpModal = document.querySelector('[data-otp-modal]');
    const otpInput = document.querySelector('[data-otp-input]');
    const otpCountdown = document.querySelector('[data-otp-countdown]');
    const activeOtpActions = document.querySelector('[data-otp-active-actions]');
    const expiredOtpActions = document.querySelector('[data-otp-expired-actions]');

    const setMode = (mode) => {
        const selected = mode === 'register' ? 'register' : 'login';
        root.dataset.authMode = selected;

        panels.forEach((panel) => {
            const active = panel.dataset.authPanel === selected;
            panel.hidden = !active;

            panel.querySelectorAll('input, select, textarea, button').forEach((control) => {
                if (control.matches('[data-auth-switch]')) return;
                control.disabled = !active;
            });
        });
    };

    switches.forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.authSwitch));
    });

    setMode(root.dataset.authMode || 'login');

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            if (!input) return;

            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.textContent = willShow ? 'HIDE' : 'SHOW';
            button.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
        });
    });

    otpInput?.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
    });

    const closeOtp = () => {
        if (!otpModal) return;
        otpModal.classList.remove('is-open');
        otpModal.hidden = true;
        otpModal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('otp-is-open');
        document.body.classList.remove('otp-is-open');
    };

    document.querySelectorAll('[data-otp-cancel]').forEach((button) => {
        button.addEventListener('click', closeOtp);
    });

    const openOtp = () => {
        if (!otpModal) return;
        otpModal.hidden = false;
        otpModal.removeAttribute('hidden');
        otpModal.classList.add('is-open');
        otpModal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('otp-is-open');
        document.body.classList.add('otp-is-open');
    };

    let countdownTimer = null;

    const startOtpCountdown = () => {
        if (!otpModal || !otpCountdown) return;

        const expiresAt = Number(otpModal.dataset.otpExpiresAt || 0);
        const serverNow = Number(otpModal.dataset.serverNow || 0);

        if (!expiresAt || !serverNow) {
            otpCountdown.textContent = '00:00';
            activeOtpActions?.setAttribute('hidden', 'hidden');
            expiredOtpActions?.removeAttribute('hidden');
            return;
        }

        const localDeadline = Date.now() + Math.max(0, (expiresAt - serverNow) * 1000);

        const render = () => {
            const totalSeconds = Math.max(0, Math.ceil((localDeadline - Date.now()) / 1000));
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;

            otpCountdown.textContent =
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');

            if (totalSeconds <= 0) {
                if (countdownTimer) {
                    clearInterval(countdownTimer);
                    countdownTimer = null;
                }
                activeOtpActions?.setAttribute('hidden', 'hidden');
                expiredOtpActions?.removeAttribute('hidden');
            } else {
                activeOtpActions?.removeAttribute('hidden');
                expiredOtpActions?.setAttribute('hidden', 'hidden');
            }
        };

        render();
        countdownTimer = setInterval(render, 1000);
    };

    const otpShouldOpen = Boolean(
        otpModal && (
            otpModal.dataset.otpOpen === 'true' ||
            !otpModal.hidden ||
            otpModal.classList.contains('is-open')
        )
    );

    if (otpShouldOpen) {
        openOtp();
        startOtpCountdown();

        setTimeout(() => {
            try {
                otpInput?.focus({ preventScroll: true });
            } catch (error) {
                otpInput?.focus();
            }
        }, 160);
    }
})();