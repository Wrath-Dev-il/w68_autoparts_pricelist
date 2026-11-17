(function () {
    var body = document.body;
    var viewMode = body && body.getAttribute('data-view-mode') === '1';
    var invoiceViewMode = body && body.getAttribute('data-invoice-view-mode') === '1';
    var autoInvoicePrintPreview = body && body.getAttribute('data-auto-invoice-print-preview') === '1';
    var pageProcessButton = document.querySelector('[data-final-process]');
    var viewPrintButton = document.querySelector('[data-view-print-preview]');
    var invoiceReceiptPrintButton = document.querySelector('[data-print-invoice-receipt]');
    var printerRadar = document.querySelector('[data-printer-radar]');
    var printerRadarOpenButton = document.querySelector('[data-printer-radar-open]');
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

    // W68_PROCESS_ORDER_SIGNOFF_MODAL_20261021
    var signoffModal = document.querySelector('[data-process-signoff-modal]');
    var orderedByInput = document.querySelector('[data-ordered-by], [data-prepared-by]');
    var preparedByInput = orderedByInput;
    var checkedByInput = document.querySelector('[data-confirmed-by], [data-checked-by]');
    var signoffError = document.querySelector('[data-process-signoff-error]');
    var signoffSubmit = document.querySelector('[data-process-signoff-submit]');
    var signoffCloseButtons = Array.prototype.slice.call(document.querySelectorAll('[data-process-signoff-close]'));

    // W68 v107 direct Shipment / Delivery Option controls.
    var deliveryZone = document.querySelector('[data-delivery-zone]');
    var deliverySummary = document.querySelector('[data-delivery-summary]');
    var deliveryDetail = document.querySelector('[data-delivery-detail]');
    var shipmentModal = document.querySelector('[data-shipment-modal]');
    var deliveryOpenButton = document.querySelector('[data-delivery-open]');
    var shipmentSave = document.querySelector('[data-shipment-save]');
    var shipmentEmpty = document.querySelector('[data-shipment-empty]');
    var shipmentOptions = Array.prototype.slice.call(document.querySelectorAll('[data-shipment-forwarder]'));
    var shipmentCancelButtons = Array.prototype.slice.call(document.querySelectorAll('[data-shipment-cancel]'));
    var deliverySelectedId = document.querySelector('[data-delivery-selected-id]');
    var deliverySelectedType = document.querySelector('[data-delivery-selected-type]');
    var deliverySelectedName = document.querySelector('[data-delivery-selected-name]');

    // W68_PROCESS_DELIVERY_CLOSED_BY_DEFAULT_20261007
    // The author CSS sets .shipment-modal to display:flex, which overrides the
    // browser's default closed <dialog> styling. Keep a dialog hidden whenever
    // it does not have the open attribute. SET DELIVERY OPTION still opens it
    // normally via showModal()/open, and closing removes open so it hides again.
    if (shipmentModal) {
        var shipmentClosedStyle = document.createElement('style');
        shipmentClosedStyle.setAttribute('data-w68-shipment-closed-style', '1');
        shipmentClosedStyle.textContent = '.shipment-modal:not([open]){display:none!important;}';
        document.head.appendChild(shipmentClosedStyle);

        if (!shipmentModal.hasAttribute('open')) {
            shipmentModal.setAttribute('aria-hidden', 'true');
        }
    }

    var selectedDeliveryType = '';
    var selectedShipmentId = 0;
    var selectedShipmentName = '';
    var draftDeliveryType = '';
    var draftShipmentId = 0;
    var draftShipmentName = '';

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

    function setSignoffError(message) {
        if (!signoffError) return;
        signoffError.textContent = message || 'Enter ORDERED BY and CHECKED BY before processing the order.';
        signoffError.hidden = false;
    }

    function clearSignoffError() {
        if (!signoffError) return;
        signoffError.hidden = true;
        signoffError.textContent = '';
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

    function isOrderConfirmationReady() {
        syncDeliveryStateFromDom();
        var termsAccepted = !!(termsCheckbox && termsCheckbox.checked);
        var deliveryReady = (selectedDeliveryType === 'rush' || selectedDeliveryType === 'regular')
            && selectedShipmentId > 0;
        var orderedByVal = orderedByInput ? String(orderedByInput.value || '').trim() : '';
        var checkedByVal = checkedByInput ? String(checkedByInput.value || '').trim() : '';
        var signoffReady = orderedByVal !== '' && checkedByVal !== '';

        return {
            ready: termsAccepted && deliveryReady && signoffReady,
            deliveryReady: deliveryReady,
            termsAccepted: termsAccepted,
            orderedByReady: orderedByVal !== '',
            checkedByReady: checkedByVal !== ''
        };
    }

    function updateConfirmProcessState() {
        if (!confirmProcess || viewMode) return;
        var status = isOrderConfirmationReady();
        confirmProcess.disabled = false;
        confirmProcess.classList.toggle('is-inactive', !status.ready);
        confirmProcess.setAttribute('aria-disabled', !status.ready ? 'true' : 'false');
    }

    function setTermsChecked(checked) {
        if (termsCheckbox) termsCheckbox.checked = !!checked;
        if (termsZone && termsZone.classList) termsZone.classList.toggle('is-agreed', !!checked);
        updateConfirmProcessState();
    }

    function shipmentTypeLabel(type) {
        return type === 'rush' ? 'RUSH' : (type === 'regular' ? 'REGULAR' : '');
    }

    function syncDeliveryStateFromDom() {
        if (!deliverySelectedId || !deliverySelectedType || !deliverySelectedName) return;

        var id = parseInt(deliverySelectedId.value || '0', 10) || 0;
        var type = String(deliverySelectedType.value || '').toLowerCase();
        var name = String(deliverySelectedName.value || '').trim();

        if (id > 0 && (type === 'rush' || type === 'regular') && name !== '') {
            selectedShipmentId = id;
            selectedDeliveryType = type;
            selectedShipmentName = name;
        }
    }

    function refreshDeliverySummary() {
        var ready = (selectedDeliveryType === 'rush' || selectedDeliveryType === 'regular')
            && selectedShipmentId > 0
            && selectedShipmentName !== '';

        if (deliveryZone && deliveryZone.classList) {
            deliveryZone.classList.toggle('is-set', ready);
        }

        if (deliverySummary) {
            deliverySummary.textContent = ready
                ? shipmentTypeLabel(selectedDeliveryType) + ' - ' + selectedShipmentName
                : 'NOT SET';
        }

        if (deliveryDetail) {
            deliveryDetail.textContent = ready
                ? 'Shipment selected. Tap SET DELIVERY OPTION to change it.'
                : 'Choose a Shipment under RUSH or REGULAR.';
        }

        updateConfirmProcessState();
    }

    function renderShipmentOptions() {
        var availableCount = 0;

        shipmentOptions.forEach(function (option) {
            var optionType = String(option.getAttribute('data-shipment-forwarder-type') || '').toLowerCase();
            var optionId = parseInt(option.getAttribute('data-shipment-id') || '0', 10) || 0;
            var available = optionId > 0 && (optionType === 'rush' || optionType === 'regular');

            option.hidden = !available;
            if (option.classList) option.classList.toggle('is-selected', available && optionId === draftShipmentId);
            if (available) availableCount++;
        });

        if (shipmentEmpty) {
            shipmentEmpty.hidden = availableCount > 0;
            shipmentEmpty.textContent = 'No Rush or Regular Shipments are available in W68 Masterlist.';
        }

        if (shipmentSave) {
            shipmentSave.disabled = !(
                (draftDeliveryType === 'rush' || draftDeliveryType === 'regular')
                && draftShipmentId > 0
                && draftShipmentName !== ''
            );
        }
    }

    function chooseShipment(option) {
        if (!option) return;

        var optionType = String(option.getAttribute('data-shipment-forwarder-type') || '').toLowerCase();
        if (optionType !== 'rush' && optionType !== 'regular') return;

        draftDeliveryType = optionType;
        draftShipmentId = parseInt(option.getAttribute('data-shipment-id') || '0', 10) || 0;
        draftShipmentName = String(option.getAttribute('data-shipment-name') || '').trim();
        renderShipmentOptions();
    }

    function openShipmentModal() {
        if (!shipmentModal || viewMode) return;

        draftDeliveryType = selectedDeliveryType;
        draftShipmentId = selectedShipmentId;
        draftShipmentName = selectedShipmentName;

        if (window.W68OpenShipmentModal) {
            window.W68OpenShipmentModal();
        } else {
            shipmentModal.hidden = false;
            shipmentModal.setAttribute('aria-hidden', 'false');
            if (body && body.classList) body.classList.add('shipment-modal-open');
        }

        renderShipmentOptions();
        syncProcessViewport();

        window.setTimeout(function () {
            var firstSelected = shipmentModal.querySelector('.shipment-option.is-selected');
            var firstAvailable = shipmentModal.querySelector('.shipment-option:not([hidden])');
            var focusTarget = firstSelected || firstAvailable || shipmentModal.querySelector('[data-shipment-cancel]');
            if (focusTarget && focusTarget.focus) focusTarget.focus();
        }, 0);
    }

    function closeShipmentModal() {
        if (!shipmentModal) return;

        if (window.W68CloseShipmentModal) {
            window.W68CloseShipmentModal();
        } else {
            shipmentModal.hidden = true;
            shipmentModal.setAttribute('aria-hidden', 'true');
            if (body && body.classList) body.classList.remove('shipment-modal-open');
        }

        if (deliveryOpenButton && deliveryOpenButton.focus) deliveryOpenButton.focus();
    }

    function saveShipmentSelection() {
        if (!draftDeliveryType || draftShipmentId < 1 || !draftShipmentName) return;

        selectedDeliveryType = draftDeliveryType;
        selectedShipmentId = draftShipmentId;
        selectedShipmentName = draftShipmentName;

        if (deliverySelectedId) deliverySelectedId.value = String(selectedShipmentId);
        if (deliverySelectedType) deliverySelectedType.value = selectedDeliveryType;
        if (deliverySelectedName) deliverySelectedName.value = selectedShipmentName;

        closeShipmentModal();
        clearConfirmError();
        refreshDeliverySummary();
    }

    function openConfirmModal() {
        // W68_PORTAL_NO_INVOICE_PREVIEW_V114_20261001
        // Invoice printing must never show the custom receipt preview.
        // Redirect every invoice caller straight to the native print flow.
        if (invoiceViewMode) {
            printInvoiceReceipt();
            return;
        }

        if (!confirmModal || !getCards().length) return;
        clearConfirmError();
        confirmModal.hidden = false;
        confirmModal.setAttribute('aria-hidden', 'false');
        syncProcessViewport();
        updateConfirmProcessState();
    }

    function closeConfirmModal() {
        if (!confirmModal) return;
        confirmModal.hidden = true;
        confirmModal.setAttribute('aria-hidden', 'true');
        if (window._w68PointerCleanup) {
            window._w68PointerCleanup();
        }
        if (shipmentModal) {
            if (window.W68CloseShipmentModal) {
                window.W68CloseShipmentModal();
            } else {
                try {
                    if (typeof shipmentModal.close === 'function' && shipmentModal.open) {
                        shipmentModal.close();
                    } else {
                        shipmentModal.removeAttribute('open');
                    }
                } catch (error) {
                    shipmentModal.removeAttribute('open');
                }
                shipmentModal.setAttribute('aria-hidden', 'true');
            }
        }
        if (body && body.classList) body.classList.remove('shipment-modal-open');
        clearConfirmError();
    }

    function openTermsModal() {}
    function cancelTerms() {}
    function agreeTerms() {}

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

    function pointToMissingField(target, label, message) {
        if (!target) return;
        if (confirmError) {
            confirmError.textContent = message;
            confirmError.hidden = false;
        }

        if (window._w68PointerCleanup) {
            window._w68PointerCleanup();
        }

        target.classList.add('w68-missing-field-target');

        var cleanup = function () {
            target.classList.remove('w68-missing-field-target');
            window._w68PointerCleanup = null;
        };

        window._w68PointerCleanup = cleanup;
        var timer = window.setTimeout(cleanup, 4500);

        var onInteract = function () {
            window.clearTimeout(timer);
            cleanup();
            target.removeEventListener('input', onInteract);
            target.removeEventListener('change', onInteract);
            target.removeEventListener('click', onInteract);
        };

        target.addEventListener('input', onInteract, { once: true });
        target.addEventListener('change', onInteract, { once: true });
        target.addEventListener('click', onInteract, { once: true });
    }

    function onFinalizeOrderClicked(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        syncDeliveryStateFromDom();
        var status = isOrderConfirmationReady();
        updateConfirmProcessState();

        if (!status.ready) {
            if (!status.deliveryReady) {
                pointToMissingField(deliveryZone || deliveryOpenButton, 'DELIVERY', 'Choose a Delivery Option before finalizing.');
                return;
            }
            if (!status.termsAccepted) {
                pointToMissingField(termsZone || termsCheckbox, 'CONFIRM', 'Please check "Check to confirm order".');
                return;
            }
            if (!status.orderedByReady) {
                var target = orderedByInput ? (orderedByInput.closest('.order-signoff-field') || orderedByInput) : null;
                pointToMissingField(target, 'ORDERED', 'Enter ORDERED BY before finalizing.');
                if (orderedByInput && orderedByInput.focus) orderedByInput.focus();
                return;
            }
            if (!status.checkedByReady) {
                var target = checkedByInput ? (checkedByInput.closest('.order-signoff-field') || checkedByInput) : null;
                pointToMissingField(target, 'CONFIRMED', 'Enter CONFIRMED BY before finalizing.');
                if (checkedByInput && checkedByInput.focus) checkedByInput.focus();
                return;
            }
            return;
        }

        submitOrder();
    }

    var orderSubmitting = false;
    function submitOrder() {
        if (viewMode || !confirmProcess || !processUrl || orderSubmitting) return;

        syncDeliveryStateFromDom();
        var status = isOrderConfirmationReady();
        if (!status.ready) {
            onFinalizeOrderClicked();
            return;
        }

        var orderedBy = orderedByInput ? String(orderedByInput.value || '').trim() : '';
        var checkedBy = checkedByInput ? String(checkedByInput.value || '').trim() : '';

        clearConfirmError();
        orderSubmitting = true;
        showProcessLoader();

        // Give Safari/iPad two paint frames so the W68 animation is visibly
        // rendered before the network/database work begins.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                requestJson(processUrl, 'POST', {
                    forwarder_id: selectedShipmentId,
                    ordered_by: orderedBy,
                    prepared_by: orderedBy,
                    checked_by: checkedBy,
                    confirmed_by: checkedBy
                })
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
                        orderSubmitting = false;
                        hideProcessLoader();
                        updateConfirmProcessState();
                        setConfirmError(error && error.message ? error.message : 'Unable to process this order. Please try again.');
                    });
            });
        });
    }

    document.addEventListener('w68:delivery-selected', function (event) {
        var detail = event && event.detail ? event.detail : {};

        selectedShipmentId = parseInt(detail.id || '0', 10) || 0;
        selectedDeliveryType = String(detail.type || '').toLowerCase();
        selectedShipmentName = String(detail.name || '').trim();

        refreshDeliverySummary();
    }, false);

    // W68_PROCESS_DELIVERY_TAP_FIX_V133_20261001
    // iPad/Safari can be unreliable when a button inside one fixed modal opens
    // another fixed modal through only a delegated document click. Bind the
    // delivery controls directly, while still keeping the delegated fallback.
    function bindProcessTap(node, handler) {
        if (!node || typeof handler !== 'function') return;

        var lastTouchAt = 0;

        node.addEventListener('touchend', function (event) {
            lastTouchAt = Date.now();
            event.preventDefault();
            event.stopPropagation();
            handler(event);
        }, { passive: false });

        node.addEventListener('click', function (event) {
            if (Date.now() - lastTouchAt < 650) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            handler(event);
        }, false);
    }

    bindProcessTap(deliveryOpenButton, function () {
        openShipmentModal();
    });

    shipmentOptions.forEach(function (option) {
        bindProcessTap(option, function () {
            chooseShipment(option);
        });
    });

    bindProcessTap(shipmentSave, function () {
        if (!shipmentSave.disabled) saveShipmentSelection();
    });

    shipmentCancelButtons.forEach(function (button) {
        bindProcessTap(button, function () {
            closeShipmentModal();
        });
    });

    bindProcessTap(confirmProcess, function (event) {
        onFinalizeOrderClicked(event);
    });

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

        // Delivery controls are bound directly above for reliable iPad/Safari
        // touch behavior. Do not run them again through delegated click logic.

        var termsCancel = closestFrom(event.target, '[data-terms-cancel]');
        if (termsCancel) {
            event.preventDefault();
            cancelTerms();
            return;
        }

        var termsTrigger = closestFrom(event.target, '[data-terms-zone]');
        if (termsTrigger && event.target !== termsCheckbox) {
            event.preventDefault();
            if (termsCheckbox) {
                termsCheckbox.checked = !termsCheckbox.checked;
                setTermsChecked(termsCheckbox.checked);
                clearConfirmError();
                if (window._w68PointerCleanup) window._w68PointerCleanup();
            }
            return;
        }

        var submit = closestFrom(event.target, '[data-confirm-process]');
        if (submit) {
            event.preventDefault();
            onFinalizeOrderClicked(event);
            return;
        }
    }, false);

    if (pageProcessButton) {
        pageProcessButton.addEventListener('click', function (event) {
            event.preventDefault();
            if (pageProcessButton.disabled) return;
            openConfirmModal();
        }, false);
    }

    function setPrinterRadarVisible(visible) {
        if (!printerRadar) return;
        printerRadar.hidden = !visible;
        printerRadar.setAttribute('aria-hidden', visible ? 'false' : 'true');
        if (body && body.classList) {
            body.classList.toggle('printer-radar-open', !!visible);
        }
    }

    var invoicePrintInProgress = false;
    var invoicePrintStarted = false;
    var invoicePrintFallbackTimer = null;

    function prepareInvoiceReceiptForPrint() {
        if (!invoiceViewMode || !body) return false;

        // The receipt lives inside the old modal container, but the modal must
        // never be visible on screen. Explicitly remove the HTML hidden state so
        // print media can render the receipt, while V114/V115 screen CSS keeps
        // the container invisible to the user.
        if (confirmModal) {
            confirmModal.hidden = false;
            confirmModal.setAttribute('aria-hidden', 'true');
        }

        body.classList.add('w68-invoice-printing');
        return true;
    }

    function cleanupInvoicePrint() {
        invoicePrintInProgress = false;
        invoicePrintStarted = false;

        if (invoicePrintFallbackTimer) {
            window.clearTimeout(invoicePrintFallbackTimer);
            invoicePrintFallbackTimer = null;
        }

        setPrinterRadarVisible(false);

        if (body) {
            body.classList.remove('w68-invoice-printing');
        }

        if (confirmModal) {
            confirmModal.hidden = true;
            confirmModal.setAttribute('aria-hidden', 'true');
        }
    }

    function invokeNativeInvoicePrint() {
        if (!prepareInvoiceReceiptForPrint()) return;

        invoicePrintInProgress = true;
        invoicePrintStarted = false;
        setPrinterRadarVisible(true);

        // Force layout while still inside the trusted click/touch event. Do not
        // put window.print() behind setTimeout/rAF; Safari/iPadOS and some Chrome
        // configurations can drop the user activation and silently ignore it.
        if (printerRadar) {
            void printerRadar.offsetWidth;
        }

        try {
            window.print();
        } catch (error) {
            console.error('Native invoice print failed:', error);
            invoicePrintInProgress = false;
            if (printerRadarOpenButton) {
                printerRadarOpenButton.hidden = false;
            }
        }

        // If beforeprint did not fire, keep the radar visible and reveal a
        // direct-tap fallback. This handles automatic ?print=1 navigation where
        // there is no transferable browser user gesture after page navigation.
        if (!invoicePrintStarted) {
            invoicePrintFallbackTimer = window.setTimeout(function () {
                if (!invoicePrintStarted && printerRadarOpenButton) {
                    printerRadarOpenButton.hidden = false;
                }
            }, 700);
        }
    }

    function printInvoiceReceipt() {
        if (!invoiceViewMode || !body || invoicePrintInProgress) return;
        if (printerRadarOpenButton) printerRadarOpenButton.hidden = true;
        invokeNativeInvoicePrint();
    }

    window.addEventListener('beforeprint', function () {
        if (!invoiceViewMode) return;
        invoicePrintStarted = true;
        if (printerRadarOpenButton) printerRadarOpenButton.hidden = true;
        setPrinterRadarVisible(false);
    }, false);

    window.addEventListener('afterprint', function () {
        if (!invoiceViewMode) return;
        cleanupInvoicePrint();
    }, false);

    if (printerRadarOpenButton) {
        printerRadarOpenButton.hidden = true;
        printerRadarOpenButton.addEventListener('click', function (event) {
            event.preventDefault();
            invoicePrintInProgress = false;
            invokeNativeInvoicePrint();
        }, false);
        printerRadarOpenButton.addEventListener('touchend', function (event) {
            event.preventDefault();
            invoicePrintInProgress = false;
            invokeNativeInvoicePrint();
        }, { passive: false });
    }

    if (invoiceReceiptPrintButton) {
        var receiptPrintTouchAt = 0;
        invoiceReceiptPrintButton.addEventListener('touchend', function (event) {
            event.preventDefault();
            receiptPrintTouchAt = Date.now();
            printInvoiceReceipt();
        }, { passive: false });
        invoiceReceiptPrintButton.addEventListener('click', function (event) {
            if (Date.now() - receiptPrintTouchAt < 600) return;
            event.preventDefault();
            printInvoiceReceipt();
        }, false);
    }

    if (viewPrintButton) {
        var printTouchAt = 0;
        viewPrintButton.addEventListener('touchend', function (event) {
            event.preventDefault();
            printTouchAt = Date.now();
            if (viewPrintButton.disabled) return;
            if (invoiceViewMode) {
                printInvoiceReceipt();
                return;
            }
            openConfirmModal();
        }, false);
        viewPrintButton.addEventListener('click', function (event) {
            if (Date.now() - printTouchAt < 600) return;
            event.preventDefault();
            if (viewPrintButton.disabled) return;
            if (invoiceViewMode) {
                printInvoiceReceipt();
                return;
            }
            openConfirmModal();
        }, false);
    }

    if (termsCheckbox) {
        termsCheckbox.addEventListener('change', function () {
            setTermsChecked(termsCheckbox.checked);
            clearConfirmError();
            if (window._w68PointerCleanup) window._w68PointerCleanup();
        }, false);
    }

    if (orderedByInput) {
        orderedByInput.addEventListener('input', function () {
            updateConfirmProcessState();
            clearConfirmError();
            if (window._w68PointerCleanup) window._w68PointerCleanup();
        }, false);
    }

    if (checkedByInput) {
        checkedByInput.addEventListener('input', function () {
            updateConfirmProcessState();
            clearConfirmError();
            if (window._w68PointerCleanup) window._w68PointerCleanup();
        }, false);
    }


    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (
            shipmentModal &&
            (
                shipmentModal.open === true ||
                shipmentModal.hasAttribute('open') ||
                shipmentModal.getAttribute('aria-hidden') === 'false'
            )
        ) {
            closeShipmentModal();
            return;
        }
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
    refreshDeliverySummary();

    if (invoiceViewMode && autoInvoicePrintPreview) {
        printInvoiceReceipt();
    }
})();
