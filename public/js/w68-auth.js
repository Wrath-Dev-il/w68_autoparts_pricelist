(() => {
    const root = document.documentElement;
    const panels = Array.from(document.querySelectorAll('[data-auth-panel]'));
    const switches = Array.from(document.querySelectorAll('[data-auth-switch]'));
    const otpModal = document.querySelector('[data-otp-modal]');
    const otpInput = document.querySelector('[data-otp-input]');

    /* =====================================================
       W68 auth loading animation
       ===================================================== */
    const loader = document.querySelector('[data-auth-loader]');
    const loaderPaths = loader ? Array.from(loader.querySelectorAll('[data-auth-loader-path]')) : [];
    const loaderPen = loader?.querySelector('[data-auth-loader-pen]');
    const loaderDot = loader?.querySelector('[data-auth-loader-dot]');
    const loaderAura = loader?.querySelector('[data-auth-loader-aura]');
    const loaderSparkleOne = loader?.querySelector('[data-auth-loader-sparkle-one]');
    const loaderSparkleTwo = loader?.querySelector('[data-auth-loader-sparkle-two]');

    let loaderLengths = [];
    let loaderStartTime = null;
    let loaderFrame = null;
    let loaderRestartTimer = null;
    let loaderRunning = false;

    const initializeLoaderPaths = () => {
        if (!loaderPaths.length) return;

        loaderLengths = loaderPaths.map((path) => {
            const length = path.getTotalLength();
            path.style.strokeDasharray = `${length} ${length}`;
            path.style.strokeDashoffset = String(length);
            return length;
        });
    };

    const loaderAnimate = (timestamp) => {
        if (!loaderRunning || !loaderPaths.length) return;
        if (!loaderStartTime) loaderStartTime = timestamp;

        const totalDuration = 2400;
        const elapsed = timestamp - loaderStartTime;
        const totalLength = loaderLengths.reduce((sum, value) => sum + value, 0) || 1;
        const ratios = loaderLengths.map((length) => length / totalLength);
        const progress = elapsed / totalDuration;

        if (progress >= 1) {
            loaderPaths.forEach((path) => {
                path.style.strokeDashoffset = '0';
            });

            loaderPen?.classList.remove('is-visible');

            loaderRestartTimer = window.setTimeout(() => {
                if (!loaderRunning) return;
                loaderStartTime = null;
                initializeLoaderPaths();
                loaderFrame = window.requestAnimationFrame(loaderAnimate);
            }, 800);

            return;
        }

        let accumulated = 0;

        loaderPaths.forEach((path, index) => {
            const start = accumulated;
            const end = accumulated + ratios[index];

            if (progress < start) {
                path.style.strokeDashoffset = String(loaderLengths[index]);
            } else if (progress >= end) {
                path.style.strokeDashoffset = '0';
            } else {
                const localProgress = (progress - start) / ratios[index];
                path.style.strokeDashoffset = String(loaderLengths[index] * (1 - localProgress));

                const point = path.getPointAtLength(loaderLengths[index] * localProgress);
                const color = path.dataset.loaderColor || '#FFD700';

                loaderPen?.classList.add('is-visible');
                loaderPen?.setAttribute('transform', `translate(${point.x}, ${point.y})`);
                loaderDot?.setAttribute('stroke', color);
                loaderAura?.setAttribute('fill', color);

                if (loaderSparkleOne) {
                    loaderSparkleOne.setAttribute('cx', String((Math.random() - 0.5) * 10));
                    loaderSparkleOne.setAttribute('cy', String((Math.random() - 0.5) * 10));
                }

                if (loaderSparkleTwo) {
                    loaderSparkleTwo.setAttribute('cx', String((Math.random() - 0.5) * 16));
                    loaderSparkleTwo.setAttribute('cy', String((Math.random() - 0.5) * 16));
                }
            }

            accumulated = end;
        });

        loaderFrame = window.requestAnimationFrame(loaderAnimate);
    };

    const startLoader = () => {
        if (!loader || !loaderPaths.length) return;

        if (loaderFrame) window.cancelAnimationFrame(loaderFrame);
        if (loaderRestartTimer) window.clearTimeout(loaderRestartTimer);

        loader.classList.remove('is-hidden');
        loader.setAttribute('aria-hidden', 'false');
        loaderRunning = true;
        loaderStartTime = null;
        initializeLoaderPaths();
        loaderPen?.classList.remove('is-visible');
        loaderFrame = window.requestAnimationFrame(loaderAnimate);
    };

    const hideLoader = () => {
        if (!loader) return;

        loaderRunning = false;
        if (loaderFrame) window.cancelAnimationFrame(loaderFrame);
        if (loaderRestartTimer) window.clearTimeout(loaderRestartTimer);
        loaderFrame = null;
        loaderRestartTimer = null;
        loader.classList.add('is-hidden');
        loader.setAttribute('aria-hidden', 'true');
        loaderPen?.classList.remove('is-visible');
    };

    initializeLoaderPaths();
    startLoader();

    window.addEventListener('load', () => {
        window.setTimeout(hideLoader, 260);
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            if (form.checkValidity()) startLoader();
        });
    });

    /* =====================================================
       Login / Register panel switching
       ===================================================== */
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

    /* =====================================================
       OTP modal visibility / iPad Safari visual viewport
       ===================================================== */
    const otpShouldOpen = Boolean(
        otpModal && (
            otpModal.dataset.otpOpen === 'true' ||
            !otpModal.hidden ||
            otpModal.classList.contains('is-open')
        )
    );

    const syncOtpViewport = () => {
        if (!otpModal || !otpShouldOpen) return;

        const viewport = window.visualViewport;

        if (viewport) {
            otpModal.style.setProperty('--w68-otp-vv-height', `${viewport.height}px`);
            otpModal.style.setProperty('--w68-otp-vv-width', `${viewport.width}px`);
            otpModal.style.setProperty('--w68-otp-vv-top', `${viewport.offsetTop}px`);
            otpModal.style.setProperty('--w68-otp-vv-left', `${viewport.offsetLeft}px`);
        } else {
            otpModal.style.setProperty('--w68-otp-vv-height', `${window.innerHeight}px`);
            otpModal.style.setProperty('--w68-otp-vv-width', `${window.innerWidth}px`);
            otpModal.style.setProperty('--w68-otp-vv-top', '0px');
            otpModal.style.setProperty('--w68-otp-vv-left', '0px');
        }
    };

    const forceOtpVisible = () => {
        if (!otpModal || !otpShouldOpen) return;

        otpModal.hidden = false;
        otpModal.removeAttribute('hidden');
        otpModal.classList.add('is-open');
        otpModal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('otp-is-open');
        document.body.classList.add('otp-is-open');

        /* The OTP must always win over the login loading animation. */
        hideLoader();
        syncOtpViewport();

        window.requestAnimationFrame(() => {
            otpModal.scrollTop = 0;
        });
    };

    if (otpShouldOpen) {
        forceOtpVisible();

        window.setTimeout(() => {
            forceOtpVisible();
            try {
                otpInput?.focus({ preventScroll: true });
            } catch (error) {
                otpInput?.focus();
            }
        }, 350);

        window.visualViewport?.addEventListener('resize', syncOtpViewport);
        window.visualViewport?.addEventListener('scroll', syncOtpViewport);
        window.addEventListener('orientationchange', () => {
            window.setTimeout(() => {
                forceOtpVisible();
            }, 180);
        });
        window.addEventListener('pageshow', forceOtpVisible);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) forceOtpVisible();
        });
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
