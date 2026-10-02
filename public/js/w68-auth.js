(function () {
    'use strict';

    var root = document.documentElement;
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-auth-panel]'));
    var switches = Array.prototype.slice.call(document.querySelectorAll('[data-auth-switch]'));

    var otpModal = document.querySelector('[data-otp-modal]');
    var otpInput = document.querySelector('[data-otp-input]');
    var otpCountdown = document.querySelector('[data-otp-countdown]');
    var otpActionForm = document.querySelector('[data-otp-action-form]');
    var otpActionButton = document.querySelector('[data-otp-action-button]');
    var countdownTimer = null;

    function setMode(mode) {
        var selected = mode === 'register' ? 'register' : 'login';

        root.setAttribute('data-auth-mode', selected);

        panels.forEach(function (panel) {
            var active = panel.getAttribute('data-auth-panel') === selected;
            var controls;
            var i;

            panel.hidden = !active;
            controls = panel.querySelectorAll('input, select, textarea, button');

            for (i = 0; i < controls.length; i += 1) {
                if (controls[i].hasAttribute('data-auth-switch')) {
                    continue;
                }

                controls[i].disabled = !active;
            }
        });
    }

    switches.forEach(function (button) {
        button.addEventListener('click', function () {
            setMode(button.getAttribute('data-auth-switch'));
        });
    });

    setMode(root.getAttribute('data-auth-mode') || 'login');

    Array.prototype.slice.call(document.querySelectorAll('[data-password-toggle]')).forEach(function (button) {
        button.addEventListener('click', function () {
            var inputId = button.getAttribute('aria-controls');
            var input = inputId ? document.getElementById(inputId) : null;
            var willShow;

            if (!input) {
                return;
            }

            willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.textContent = willShow ? 'HIDE' : 'SHOW';
            button.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
        });
    });

    if (otpInput) {
        otpInput.addEventListener('input', function () {
            otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    function closeOtp() {
        if (!otpModal) {
            return;
        }

        otpModal.classList.remove('is-open');
        otpModal.hidden = true;
        otpModal.style.display = 'none';
        otpModal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('otp-is-open');

        if (document.body) {
            document.body.classList.remove('otp-is-open');
        }
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-otp-cancel]')).forEach(function (button) {
        button.addEventListener('click', closeOtp);
    });

    function openOtp() {
        if (!otpModal) {
            return;
        }

        otpModal.hidden = false;
        otpModal.removeAttribute('hidden');
        otpModal.classList.add('is-open');
        otpModal.style.setProperty('display', 'flex', 'important');
        otpModal.style.setProperty('visibility', 'visible', 'important');
        otpModal.style.setProperty('opacity', '1', 'important');
        otpModal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('otp-is-open');

        if (document.body) {
            document.body.classList.add('otp-is-open');
        }
    }

    function setOtpExpired(expired) {
        var verifyAction;
        var resendAction;

        if (!otpActionForm || !otpActionButton) {
            return;
        }

        verifyAction = otpActionForm.getAttribute('data-verify-action') || otpActionForm.action;
        resendAction = otpActionForm.getAttribute('data-resend-action') || otpActionForm.action;

        if (expired) {
            otpActionForm.action = resendAction;
            otpActionButton.textContent = 'RESEND';
            otpActionButton.setAttribute('aria-label', 'Resend OTP');

            if (otpInput) {
                otpInput.required = false;
                otpInput.disabled = true;
            }

            return;
        }

        otpActionForm.action = verifyAction;
        otpActionButton.textContent = 'VERIFY';
        otpActionButton.setAttribute('aria-label', 'Verify OTP');

        if (otpInput) {
            otpInput.disabled = false;
            otpInput.required = true;
        }
    }

    function startOtpCountdown() {
        var expiresAt;
        var serverNow;
        var browserServerOffset;
        var localDeadline;

        if (!otpModal || !otpCountdown) {
            return;
        }

        if (countdownTimer) {
            window.clearInterval(countdownTimer);
            countdownTimer = null;
        }

        expiresAt = Number(otpModal.getAttribute('data-otp-expires-at') || 0);
        serverNow = Number(otpModal.getAttribute('data-server-now') || 0);

        if (!expiresAt || !serverNow) {
            otpCountdown.textContent = '00:00';
            setOtpExpired(true);
            return;
        }

        browserServerOffset = Date.now() - (serverNow * 1000);
        localDeadline = (expiresAt * 1000) + browserServerOffset;

        function renderCountdown() {
            var totalSeconds = Math.max(0, Math.ceil((localDeadline - Date.now()) / 1000));
            var minutes = Math.floor(totalSeconds / 60);
            var seconds = totalSeconds % 60;

            otpCountdown.textContent =
                String(minutes).replace(/^([0-9])$/, '0$1') + ':' +
                String(seconds).replace(/^([0-9])$/, '0$1');

            if (totalSeconds <= 0) {
                if (countdownTimer) {
                    window.clearInterval(countdownTimer);
                    countdownTimer = null;
                }

                setOtpExpired(true);
                return;
            }

            setOtpExpired(false);
        }

        renderCountdown();

        if (localDeadline > Date.now()) {
            countdownTimer = window.setInterval(renderCountdown, 1000);
        }
    }

    function shouldOpenOtp() {
        if (!otpModal) {
            return false;
        }

        return (
            otpModal.getAttribute('data-otp-open') === 'true' ||
            !otpModal.hidden ||
            otpModal.classList.contains('is-open')
        );
    }

    if (shouldOpenOtp()) {
        openOtp();
        startOtpCountdown();

        window.setTimeout(function () {
            if (!otpInput || otpInput.disabled) {
                return;
            }

            try {
                otpInput.focus();
            } catch (error) {
                // Older Safari may reject focus while the keyboard is changing.
            }
        }, 160);
    }

    window.addEventListener('pageshow', function () {
        if (!otpModal || otpModal.getAttribute('data-otp-open') !== 'true') {
            return;
        }

        openOtp();
        startOtpCountdown();
    });
})();