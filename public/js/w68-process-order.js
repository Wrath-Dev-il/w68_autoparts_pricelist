(function () {
    var body = document.body;
    var viewMode = body && body.getAttribute('data-view-mode') === '1';
    var pageProcessButton = document.querySelector('[data-final-process]');
    var viewPrintButton = document.querySelector('[data-view-print-preview]');
    var errorNode = document.querySelector('[data-process-error]');
    var loading = document.querySelector('[data-process-loading]');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var processUrl = body ? String(body.getAttribute('data-process-url') || '') : '';
    var ordersUrl = body ? String(body.getAttribute('data-orders-url') || '') : '';
    var itemsScroll = document.querySelector('.process-items-scroll');
    var liveEmpty = document.querySelector('[data-live-empty-state]');
    var headingCount = document.querySelector('.process-heading-count strong');
    var totalItemsNode = document.querySelector('[data-total-items]');
    var totalPriceNode = document.querySelector('[data-total-price]');

    var confirmModal = document.querySelector('[data-order-confirm-modal]');
    var confirmProcess = document.querySelector('[data-confirm-process]');
    var confirmError = document.querySelector('[data-confirm-error]');
    var termsCheckbox = document.querySelector('[data-terms-checkbox]');
    var termsZone = document.querySelector('[data-terms-zone]');
    var termsModal = document.querySelector('[data-terms-modal]');

    var loaderPaths = loading ? Array.prototype.slice.call(loading.querySelectorAll('[data-w68-process-loader-path]')) : [];
    var loaderPen = loading ? loading.querySelector('[data-w68-process-loader-pen]') : null;
    var loaderDot = loading ? loading.querySelector('[data-w68-process-loader-dot]') : null;
    var loaderAura = loading ? loading.querySelector('[data-w68-process-loader-aura]') : null;
    var loaderSparkleOne = loading ? loading.querySelector('[data-w68-process-loader-sparkle-one]') : null;
    var loaderSparkleTwo = loading ? loading.querySelector('[data-w68-process-loader-sparkle-two]') : null;
    var loaderLengths = [];
    var loaderStartTime = null;
    var loaderFrame = null;
    var loaderRestartTimer = null;
    var loaderRunning = false;

    function syncProcessViewport() {
        var viewport = window.visualViewport;
        var width = viewport && viewport.width ? viewport.width : window.innerWidth;
        var height = viewport && viewport.height ? viewport.height : window.innerHeight;
        var top = viewport && typeof viewport.offsetTop === 'number' ? viewport.offsetTop : 0;
        var left = viewport && typeof viewport.offsetLeft === 'number' ? viewport.offsetLeft : 0;

        if (!height || !document.documentElement) return;

        document.documentElement.style.setProperty('--process-viewport-height', Math.round(height) + 'px');
        document.documentElement.style.setProperty('--process-loader-top', Math.round(top + (height / 2)) + 'px');
        document.documentElement.style.setProperty('--process-loader-left', Math.round(left + (width / 2)) + 'px');
        document.documentElement.style.setProperty('--process-loader-max-width', Math.max(240, Math.round(width - 40)) + 'px');
    }

    syncProcessViewport();
    window.addEventListener('resize', syncProcessViewport, false);
    window.addEventListener('orientationchange', function () {
        window.setTimeout(syncProcessViewport, 80);
        window.setTimeout(syncProcessViewport, 320);
    }, false);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncProcessViewport, false);
        window.visualViewport.addEventListener('scroll', syncProcessViewport, false);
    }

    function closestFrom(target, selector) {
        if (!target) return null;
        if (target.closest) return target.closest(selector);
        while (target && target.nodeType === 1) {
            if (target.matches && target.matches(selector)) return target;
            target = target.parentElement;
        }
        return null;
    }

    function setError(message) {
        if (!errorNode) return;
        errorNode.textContent = message || 'Unable to update this order. Please try again.';
        errorNode.hidden = false;
    }

    function clearError() {
        if (!errorNode) return;
        errorNode.hidden = true;
        errorNode.textContent = '';
    }

    function setConfirmError(message) {
        if (!confirmError) return;
        confirmError.textContent = message || 'Unable to process this order. Please try again.';
        confirmError.hidden = false;
    }

    function clearConfirmError() {
        if (!confirmError) return;
        confirmError.hidden = true;
        confirmError.textContent = '';
    }

    function money(value) {
        var number = Number(value || 0);
        if (!isFinite(number)) number = 0;
        return number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function requestJson(url, method, payload) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload || {})
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) {
                    var validation = '';
                    if (data && data.errors) {
                        validation = Object.keys(data.errors).map(function (key) {
                            var value = data.errors[key];
                            return Array.isArray(value) ? value.join(' ') : String(value || '');
                        }).join(' ');
                    }
                    throw new Error(validation || data.message || ('Request failed (' + response.status + ').'));
                }
                return data;
            });
        });
    }

    function getCards() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-process-item]'));
    }

    function qtyFromCard(card) {
        var node = card.querySelector('[data-qty-value]');
        var qty = parseInt(node ? node.textContent : '1', 10);
        if (!qty || qty < 1) qty = 1;
        return qty;
    }

    function setCardBusy(card, busy) {
        var control = card.querySelector('[data-remove-selected]');
        if (control) control.disabled = !!busy;
        if (card.classList) card.classList.toggle('is-updating', !!busy);
    }

    function refreshSummary() {
        var cards = getCards();
        var totalQty = 0;
        var totalPrice = 0;

        cards.forEach(function (card) {
            var qty = qtyFromCard(card);
            var unitPrice = parseFloat(card.getAttribute('data-unit-price') || '0') || 0;
            totalQty += qty;
            totalPrice += unitPrice * qty;
        });

        if (headingCount) headingCount.textContent = String(cards.length);
        if (totalItemsNode) totalItemsNode.textContent = String(totalQty);
        if (totalPriceNode) totalPriceNode.textContent = money(totalPrice);
        if (pageProcessButton) pageProcessButton.disabled = cards.length === 0;
        if (viewPrintButton) viewPrintButton.disabled = cards.length === 0;

        if (itemsScroll) itemsScroll.hidden = cards.length === 0;
        if (liveEmpty) liveEmpty.hidden = cards.length !== 0;
    }

    function removeFromSelected(card) {
        if (viewMode || !card || (card.classList && card.classList.contains('is-updating'))) return;
        var url = String(card.getAttribute('data-update-url') || '');
        if (!url) return;

        clearError();
        setCardBusy(card, true);

        requestJson(url, 'PATCH', { selected: false })
            .then(function () {
                if (card.parentNode) card.parentNode.removeChild(card);
                refreshSummary();
                window.location.reload();
            })
            .catch(function (error) {
                setError(error && error.message ? error.message : 'Unable to remove this item from selected items.');
                setCardBusy(card, false);
            });
    }

    function setTermsChecked(checked) {
        if (termsCheckbox) termsCheckbox.checked = !!checked;
        if (termsZone && termsZone.classList) termsZone.classList.toggle('is-agreed', !!checked);
        if (confirmProcess) confirmProcess.disabled = !checked;
    }

    function openConfirmModal() {
        if (!confirmModal || !getCards().length) return;
        clearConfirmError();
        if (!viewMode) setTermsChecked(false);
        confirmModal.hidden = false;
        confirmModal.setAttribute('aria-hidden', 'false');
        syncProcessViewport();
    }

    function closeConfirmModal() {
        if (!confirmModal) return;
        confirmModal.hidden = true;
        confirmModal.setAttribute('aria-hidden', 'true');
        if (termsModal) {
            termsModal.hidden = true;
            termsModal.setAttribute('aria-hidden', 'true');
        }
        if (!viewMode) setTermsChecked(false);
        clearConfirmError();
    }

    function openTermsModal() {
        if (!termsModal) return;
        termsModal.hidden = false;
        termsModal.setAttribute('aria-hidden', 'false');
    }

    function cancelTerms() {
        if (termsModal) {
            termsModal.hidden = true;
            termsModal.setAttribute('aria-hidden', 'true');
        }
        setTermsChecked(false);
    }

    function agreeTerms() {
        if (termsModal) {
            termsModal.hidden = true;
            termsModal.setAttribute('aria-hidden', 'true');
        }
        setTermsChecked(true);
    }

    function initializeLoaderPaths() {
        loaderLengths = [];
        if (!loaderPaths.length) return;
        loaderPaths.forEach(function (path) {
            var length = path.getTotalLength();
            path.style.strokeDasharray = length + ' ' + length;
            path.style.strokeDashoffset = String(length);
            loaderLengths.push(length);
        });
    }

    function loaderAnimate(timestamp) {
        if (!loaderRunning || !loaderPaths.length) return;
        if (!loaderStartTime) loaderStartTime = timestamp;

        var totalDuration = 2400;
        var elapsed = timestamp - loaderStartTime;
        var totalLength = loaderLengths.reduce(function (sum, value) { return sum + value; }, 0) || 1;
        var progress = elapsed / totalDuration;

        if (progress >= 1) {
            loaderPaths.forEach(function (path) { path.style.strokeDashoffset = '0'; });
            if (loaderPen && loaderPen.classList) loaderPen.classList.remove('is-visible');
            loaderRestartTimer = window.setTimeout(function () {
                if (!loaderRunning) return;
                loaderStartTime = null;
                initializeLoaderPaths();
                loaderFrame = window.requestAnimationFrame(loaderAnimate);
            }, 450);
            return;
        }

        var accumulated = 0;
        loaderPaths.forEach(function (path, index) {
            var ratio = loaderLengths[index] / totalLength;
            var start = accumulated;
            var end = accumulated + ratio;

            if (progress < start) {
                path.style.strokeDashoffset = String(loaderLengths[index]);
            } else if (progress >= end) {
                path.style.strokeDashoffset = '0';
            } else {
                var localProgress = ratio > 0 ? (progress - start) / ratio : 1;
                var pathLength = loaderLengths[index];
                path.style.strokeDashoffset = String(pathLength * (1 - localProgress));
                var point = path.getPointAtLength(pathLength * localProgress);
                var color = path.getAttribute('data-loader-color') || '#FFD700';

                if (loaderPen) {
                    if (loaderPen.classList) loaderPen.classList.add('is-visible');
                    loaderPen.setAttribute('transform', 'translate(' + point.x + ', ' + point.y + ')');
                }
                if (loaderDot) loaderDot.setAttribute('stroke', color);
                if (loaderAura) loaderAura.setAttribute('fill', color);
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
    }

    function startLoaderAnimation() {
        if (!loading || !loaderPaths.length) return;
        if (loaderFrame) window.cancelAnimationFrame(loaderFrame);
        if (loaderRestartTimer) window.clearTimeout(loaderRestartTimer);
        loaderRunning = true;
        loaderStartTime = null;
        initializeLoaderPaths();
        if (loaderPen && loaderPen.classList) loaderPen.classList.remove('is-visible');
        loaderFrame = window.requestAnimationFrame(loaderAnimate);
    }

    function showProcessLoader() {
        if (!loading) return;
        syncProcessViewport();
        loading.hidden = false;
        loading.style.display = 'block';
        loading.setAttribute('aria-hidden', 'false');
        startLoaderAnimation();
    }

    function hideProcessLoader() {
        if (!loading) return;
        loaderRunning = false;
        if (loaderFrame) window.cancelAnimationFrame(loaderFrame);
        if (loaderRestartTimer) window.clearTimeout(loaderRestartTimer);
        loaderFrame = null;
        loaderRestartTimer = null;
        if (loaderPen && loaderPen.classList) loaderPen.classList.remove('is-visible');
        loading.hidden = true;
        loading.style.display = 'none';
        loading.setAttribute('aria-hidden', 'true');
    }

    function submitOrder() {
        if (viewMode || !confirmProcess || confirmProcess.disabled || !processUrl) return;

        clearConfirmError();
        confirmProcess.disabled = true;
        showProcessLoader();

        // Give Safari/iPad two paint frames so the W68 animation is visibly
        // rendered before the network/database work begins.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                requestJson(processUrl, 'POST', {})
                    .then(function (data) {
                        var redirectUrl = data && data.redirect_url ? String(data.redirect_url) : (ordersUrl || 'orders');

                        // W68 runs from /w68_Pricelist/public on XAMPP. A root-relative
                        // value such as /orders sends the browser to the Apache document
                        // root and causes a 404. Keep W68 navigation relative to the
                        // current public directory unless the server returns a full URL.
                        if (!/^https?:\/\//i.test(redirectUrl)) {
                            redirectUrl = redirectUrl.replace(/^\/+/, '');
                        }

                        window.location.replace(redirectUrl);
                    })
                    .catch(function (error) {
                        hideProcessLoader();
                        setTermsChecked(termsCheckbox && termsCheckbox.checked);
                        setConfirmError(error && error.message ? error.message : 'Unable to process this order. Please try again.');
                    });
            });
        });
    }

    document.addEventListener('click', function (event) {
        var remove = closestFrom(event.target, '[data-remove-selected]');
        if (remove) {
            var card = closestFrom(remove, '[data-process-item]');
            if (!card) return;
            event.preventDefault();
            event.stopPropagation();
            removeFromSelected(card);
            return;
        }

        var confirmClose = closestFrom(event.target, '[data-order-confirm-close]');
        if (confirmClose) {
            event.preventDefault();
            closeConfirmModal();
            return;
        }

        var termsCancel = closestFrom(event.target, '[data-terms-cancel]');
        if (termsCancel) {
            event.preventDefault();
            cancelTerms();
            return;
        }

        var termsAgree = closestFrom(event.target, '[data-terms-agree]');
        if (termsAgree) {
            event.preventDefault();
            agreeTerms();
            return;
        }

        var termsTrigger = closestFrom(event.target, '[data-terms-zone]');
        if (termsTrigger) {
            event.preventDefault();
            openTermsModal();
            return;
        }

        var submit = closestFrom(event.target, '[data-confirm-process]');
        if (submit) {
            event.preventDefault();
            submitOrder();
        }
    }, false);

    if (pageProcessButton) {
        pageProcessButton.addEventListener('click', function (event) {
            event.preventDefault();
            if (pageProcessButton.disabled) return;
            openConfirmModal();
        }, false);
    }

    if (viewPrintButton) {
        var printTouchAt = 0;
        viewPrintButton.addEventListener('touchend', function (event) {
            event.preventDefault();
            printTouchAt = Date.now();
            openConfirmModal();
        }, false);
        viewPrintButton.addEventListener('click', function (event) {
            if (Date.now() - printTouchAt < 600) return;
            event.preventDefault();
            if (viewPrintButton.disabled) return;
            openConfirmModal();
        }, false);
    }

    if (termsCheckbox) {
        termsCheckbox.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openTermsModal();
        }, false);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (termsModal && !termsModal.hidden) {
            cancelTerms();
            return;
        }
        if (confirmModal && !confirmModal.hidden) {
            closeConfirmModal();
        }
    });

    hideProcessLoader();
    refreshSummary();
})();
