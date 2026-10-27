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
    var preparedByInput = document.querySelector('[data-prepared-by]');
    var checkedByInput = document.querySelector('[data-checked-by]');
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
        signoffError.textContent = message || 'Enter PREPARED BY and CHECKED BY before processing the order.';
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

    function updateConfirmProcessState() {
        if (!confirmProcess || viewMode) return;
        var termsAccepted = !!(termsCheckbox && termsCheckbox.checked);
        var deliveryReady = (selectedDeliveryType === 'rush' || selectedDeliveryType === 'regular')
            && selectedShipmentId > 0;
        confirmProcess.disabled = !(termsAccepted && deliveryReady);
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
        if (!viewMode) setTermsChecked(false);
        confirmModal.hidden = false;
        confirmModal.setAttribute('aria-hidden', 'false');
        syncProcessViewport();

        if (window.W68TriggerConfirmTutorial) {
            window.W68TriggerConfirmTutorial();
        }
    }

    function closeConfirmModal() {
        if (!confirmModal) return;
        confirmModal.hidden = true;
        confirmModal.setAttribute('aria-hidden', 'true');
        if (window.W68CancelConfirmTutorial) {
            window.W68CancelConfirmTutorial();
        }
        if (termsModal) {
            termsModal.hidden = true;
            termsModal.setAttribute('aria-hidden', 'true');
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

    function openSignoffModal() {
        if (viewMode || !signoffModal || !confirmProcess || !processUrl) return;

        syncDeliveryStateFromDom();
        updateConfirmProcessState();

        if (confirmProcess.disabled) return;

        clearConfirmError();
        clearSignoffError();

        signoffModal.hidden = false;
        signoffModal.setAttribute('aria-hidden', 'false');
        if (body && body.classList) body.classList.add('process-signoff-open');
        syncProcessViewport();

        window.setTimeout(function () {
            if (preparedByInput && preparedByInput.focus) preparedByInput.focus();
        }, 0);
    }

    function closeSignoffModal() {
        if (!signoffModal) return;

        signoffModal.hidden = true;
        signoffModal.setAttribute('aria-hidden', 'true');
        if (body && body.classList) body.classList.remove('process-signoff-open');
        clearSignoffError();

        if (confirmProcess && confirmProcess.focus) confirmProcess.focus();
    }

    function submitOrder() {
        if (viewMode || !confirmProcess || !processUrl) return;

        syncDeliveryStateFromDom();
        updateConfirmProcessState();

        if (confirmProcess.disabled) return;

        var preparedBy = preparedByInput ? String(preparedByInput.value || '').trim() : '';
        var checkedBy = checkedByInput ? String(checkedByInput.value || '').trim() : '';

        if (!preparedBy) {
            setSignoffError('Enter PREPARED BY before processing the order.');
            if (preparedByInput && preparedByInput.focus) preparedByInput.focus();
            return;
        }

        if (!checkedBy) {
            setSignoffError('Enter CHECKED BY before processing the order.');
            if (checkedByInput && checkedByInput.focus) checkedByInput.focus();
            return;
        }

        if (!selectedDeliveryType || selectedShipmentId < 1) {
            setConfirmError('Choose a Shipment before processing the order.');
            return;
        }

        clearConfirmError();
        clearSignoffError();
        if (signoffSubmit) signoffSubmit.disabled = true;
        showProcessLoader();

        // Give Safari/iPad two paint frames so the W68 animation is visibly
        // rendered before the network/database work begins.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                requestJson(processUrl, 'POST', {
                    forwarder_id: selectedShipmentId,
                    prepared_by: preparedBy,
                    checked_by: checkedBy
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
                        hideProcessLoader();
                        if (signoffSubmit) signoffSubmit.disabled = false;
                        setTermsChecked(termsCheckbox && termsCheckbox.checked);
                        setSignoffError(error && error.message ? error.message : 'Unable to process this order. Please try again.');
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

    bindProcessTap(confirmProcess, function () {
        if (!confirmProcess.disabled) openSignoffModal();
    });

    bindProcessTap(signoffSubmit, function () {
        if (!signoffSubmit.disabled) submitOrder();
    });

    signoffCloseButtons.forEach(function (button) {
        bindProcessTap(button, function () {
            closeSignoffModal();
        });
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
            openSignoffModal();
            return;
        }

        var signoffSubmitButton = closestFrom(event.target, '[data-process-signoff-submit]');
        if (signoffSubmitButton) {
            event.preventDefault();
            submitOrder();
            return;
        }

        var signoffClose = closestFrom(event.target, '[data-process-signoff-close]');
        if (signoffClose) {
            event.preventDefault();
            closeSignoffModal();
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
        termsCheckbox.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openTermsModal();
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

    /* =====================================================
       W68_PROCESS_ORDER_TUTORIAL_20261027
       Process Order review page walkthrough for first logins:
       Phase 1 (Review Page):
       1.) Product Information in group: Description, Product Code, Part Number, Application, Brand
       2.) Pricing in group: Price, Qty, Total
       3.) Individually:
           3.1) Total items
           3.2) Total price
           3.3) Delete button (protected from deleting, exact position preserved)
           3.4) Process Order button (protected from submitting)
       When the dialog ends in process order page, the cursor vanishes.
       Then if the user clicks the Process Order button on the review page,
       the Order Confirmation modal appears and the tutorial continues (Phase 2):
       1.) Print preview
       2.) Delivery Option
       3.) Terms and agreement
       ===================================================== */
    function initProcessOrderTutorial() {
        if (!body || body.getAttribute('data-first-login-tutorial') !== '1' || viewMode) {
            return;
        }

        var overlay = document.querySelector('[data-w68-tutorial]');
        var spotlight = document.querySelector('[data-w68-tutorial-spotlight]');
        var arrow = document.querySelector('[data-w68-tutorial-arrow]');
        var robot = document.querySelector('[data-w68-tutorial-robot]');
        var tutorialCard = document.querySelector('[data-w68-tutorial-card]');
        var kickerNode = document.querySelector('[data-w68-tutorial-kicker]');
        var titleNode = document.querySelector('[data-w68-tutorial-title]');
        var bodyNode = document.querySelector('[data-w68-tutorial-body]');
        var nextButton = document.querySelector('[data-w68-tutorial-next]');
        var doneButton = document.querySelector('[data-w68-tutorial-done]');
        var skipButton = document.querySelector('[data-w68-tutorial-skip]');

        if (!overlay || !spotlight || !arrow || !robot || !titleNode || !bodyNode) {
            return;
        }

        if (spotlight.parentElement !== document.body) document.body.appendChild(spotlight);
        if (arrow.parentElement !== document.body) document.body.appendChild(arrow);
        if (robot.parentElement !== document.body) document.body.appendChild(robot);
        if (tutorialCard && tutorialCard.parentElement !== document.body) document.body.appendChild(tutorialCard);

        hidePointer();
        robot.hidden = true;
        robot.style.display = 'none';
        if (tutorialCard) {
            tutorialCard.hidden = true;
            tutorialCard.style.display = 'none';
        }

        var loginNumber = Math.max(
            1,
            Math.min(3, parseInt(String(body.getAttribute('data-tutorial-login-number') || '1'), 10) || 1)
        );

        var robotImage = robot.querySelector('img');
        var robotGif = String(body.getAttribute('data-tutorial-robot-gif') || '');
        if (robotImage && robotGif) {
            robotImage.src = robotGif;
        }

        // ==========================
        // Targets for Phase 1 (Review)
        // ==========================
        function getProductInfoGroupTarget() {
            var item = document.querySelector('[data-process-item]');
            if (!item) return document.querySelector('.process-order-panel') || document.body;
            var desc = item.querySelector('[data-tutorial-description]') || item.querySelector('.process-description-row');
            var meta = item.querySelector('[data-tutorial-meta]') || item.querySelector('.process-meta-stack');
            var brand = item.querySelector('[data-tutorial-brand]') || item.querySelector('.process-brand-cell');

            var parts = [desc, meta, brand].filter(Boolean);
            if (parts.length > 0) {
                var top = Infinity;
                var left = Infinity;
                var right = -Infinity;
                var bottom = -Infinity;

                parts.forEach(function (el) {
                    var r = el.getBoundingClientRect();
                    if (r.top < top) top = r.top;
                    if (r.left < left) left = r.left;
                    if (r.right > right) right = r.right;
                    if (r.bottom > bottom) bottom = r.bottom;
                    el.classList.add('w68-tutorial-highlight-part');
                });

                return {
                    top: top,
                    left: left,
                    width: right - left,
                    height: bottom - top,
                    right: right,
                    bottom: bottom,
                    node: desc || meta,
                    elements: parts
                };
            }
            return desc || meta || item;
        }

        function getMathAndPricingTarget() {
            var item = document.querySelector('[data-process-item]');
            if (!item) return document.querySelector('[data-tutorial-grand-summary]') || document.querySelector('.process-grand-summary');
            var math = item.querySelector('[data-tutorial-math]') || item.querySelector('.process-order-math');
            return math || item;
        }

        function getTotalItemsTarget() {
            var target = document.querySelector('[data-tutorial-total-items]') ||
                (document.querySelector('[data-total-items]') ? document.querySelector('[data-total-items]').parentElement : null);
            return target || document.querySelector('[data-tutorial-grand-summary]');
        }

        function getTotalPriceTarget() {
            var target = document.querySelector('[data-tutorial-total-price]') ||
                (document.querySelector('[data-total-price]') ? document.querySelector('[data-total-price]').parentElement : null);
            return target || document.querySelector('[data-tutorial-grand-summary]');
        }

        function getDeleteButtonTarget() {
            var item = document.querySelector('[data-process-item]');
            if (!item) return document.querySelector('[data-remove-selected]');
            return item.querySelector('[data-remove-selected]');
        }

        function getProcessButtonTarget() {
            return document.querySelector('[data-final-process]');
        }

        // ==========================
        // Targets for Phase 2 (Confirmation Modal)
        // ==========================
        function getPrintPreviewTarget() {
            if (!confirmModal) return null;
            return confirmModal.querySelector('[data-tutorial-print-preview]') ||
                confirmModal.querySelector('.order-print-preview') ||
                confirmModal.querySelector('.order-print-table-wrap') ||
                confirmModal.querySelector('.order-confirm-card');
        }

        function getDeliveryOptionTarget() {
            if (!confirmModal) return null;
            return confirmModal.querySelector('[data-delivery-zone]');
        }

        function getTermsAgreementTarget() {
            if (!confirmModal) return null;
            return confirmModal.querySelector('[data-terms-zone]');
        }

        // Step definitions
        var reviewSteps = [
            {
                key: 'product-info-group',
                pointerLabel: 'INFO',
                kicker: 'W68 ORDER REVIEW GUIDE • STEP 1 OF 6',
                title: '1. Product Information',
                body: 'Review the Description, Product Code, Part Number, Application, and Brand for this item.',
                borderRadius: '14px',
                target: getProductInfoGroupTarget
            },
            {
                key: 'pricing-group',
                pointerLabel: 'PRICING',
                kicker: 'W68 ORDER REVIEW GUIDE • STEP 2 OF 6',
                title: '2. Price, Qty & Total',
                body: 'Check the Unit Price, ordered Quantity, and line Total for this item.',
                borderRadius: '12px',
                target: getMathAndPricingTarget
            },
            {
                key: 'total-items',
                pointerLabel: 'ITEMS',
                kicker: 'W68 ORDER REVIEW GUIDE • STEP 3 OF 6',
                title: '3. Total Items',
                body: 'This shows the total count of items in your order.',
                borderRadius: '12px',
                target: getTotalItemsTarget
            },
            {
                key: 'total-price',
                pointerLabel: 'TOTAL',
                kicker: 'W68 ORDER REVIEW GUIDE • STEP 4 OF 6',
                title: '4. Total Price',
                body: 'This shows the grand total price for all items in your order.',
                borderRadius: '12px',
                target: getTotalPriceTarget
            },
            {
                key: 'delete-button',
                pointerLabel: 'DELETE',
                kicker: 'W68 ORDER REVIEW GUIDE • STEP 5 OF 6',
                title: '5. Delete Button',
                body: 'This button removes an item from your order review. During this guide, it is protected so clicking it will not delete your item.',
                borderRadius: '50%',
                target: getDeleteButtonTarget
            },
            {
                key: 'process-order',
                pointerLabel: 'ORDER',
                kicker: 'W68 ORDER REVIEW GUIDE • STEP 6 OF 6',
                title: '6. PROCESS ORDER Button',
                body: 'Click PROCESS ORDER when you are ready to review your order details. Tap anywhere to close this guide, then click PROCESS ORDER to continue.',
                borderRadius: '999px',
                target: getProcessButtonTarget
            }
        ];

        var confirmSteps = [
            {
                key: 'confirm-print-preview',
                pointerLabel: 'PREVIEW',
                kicker: 'W68 ORDER CONFIRMATION • STEP 1 OF 3',
                title: '1. Print Preview',
                body: 'Review the print preview here to verify the product details, quantities, discounts, and total invoice amount.',
                borderRadius: '14px',
                target: getPrintPreviewTarget
            },
            {
                key: 'confirm-delivery-option',
                pointerLabel: 'DELIVERY',
                kicker: 'W68 ORDER CONFIRMATION • STEP 2 OF 3',
                title: '2. Delivery Option',
                body: 'Select your preferred forwarder under RUSH or REGULAR by tapping SET DELIVERY OPTION.',
                borderRadius: '12px',
                target: getDeliveryOptionTarget
            },
            {
                key: 'confirm-terms-agreement',
                pointerLabel: 'TERMS',
                kicker: 'W68 ORDER CONFIRMATION • STEP 3 OF 3',
                title: '3. Terms and Agreement',
                body: 'Review and check the Terms and Agreement to unlock the final PROCESS ORDER button.',
                borderRadius: '12px',
                target: getTermsAgreementTarget
            }
        ];

        var currentPhase = 'review'; // 'review' | 'confirm' | 'done'
        var currentStepIndex = 0;
        var currentTarget = null;
        var active = false;
        var confirmPhasePending = false;
        var repositionTimer = null;

        function clearTarget() {
            if (currentTarget) {
                if (currentTarget instanceof Element) {
                    currentTarget.classList.remove('w68-tutorial-target');
                } else if (currentTarget.elements && Array.isArray(currentTarget.elements)) {
                    currentTarget.elements.forEach(function (el) {
                        if (el instanceof Element) el.classList.remove('w68-tutorial-target');
                    });
                }
            }
            document.querySelectorAll('.w68-tutorial-target, .w68-tutorial-highlight-part').forEach(function (el) {
                el.classList.remove('w68-tutorial-target', 'w68-tutorial-highlight-part');
            });
            currentTarget = null;
        }

        function hidePointer() {
            spotlight.hidden = true;
            spotlight.style.display = 'none';
            arrow.hidden = true;
            arrow.style.display = 'none';
        }

        function showPointer() {
            spotlight.hidden = false;
            spotlight.style.removeProperty('display');
            arrow.hidden = false;
            arrow.style.removeProperty('display');
        }

        function finishReviewPageTutorial() {
            active = false;
            clearTarget();
            hidePointer();
            overlay.hidden = true;
            overlay.style.display = 'none';
            overlay.setAttribute('aria-hidden', 'true');
            robot.hidden = true;
            robot.style.display = 'none';
            if (tutorialCard) {
                tutorialCard.hidden = true;
                tutorialCard.style.display = 'none';
                tutorialCard.style.removeProperty('top');
                tutorialCard.style.removeProperty('bottom');
            }
            if (robot) {
                robot.style.removeProperty('top');
                robot.style.removeProperty('bottom');
                robot.style.removeProperty('left');
                robot.style.removeProperty('right');
                robot.style.removeProperty('transform');
            }

            confirmPhasePending = true;

            if (repositionTimer !== null) {
                window.clearTimeout(repositionTimer);
                repositionTimer = null;
            }
        }

        function finishConfirmModalTutorial() {
            active = false;
            currentPhase = 'done';
            confirmPhasePending = false;
            clearTarget();
            hidePointer();
            overlay.hidden = true;
            overlay.style.display = 'none';
            overlay.setAttribute('aria-hidden', 'true');
            robot.hidden = true;
            robot.style.display = 'none';
            if (tutorialCard) {
                tutorialCard.hidden = true;
                tutorialCard.style.display = 'none';
                tutorialCard.style.removeProperty('top');
                tutorialCard.style.removeProperty('bottom');
            }
            if (robot) {
                robot.style.removeProperty('top');
                robot.style.removeProperty('bottom');
                robot.style.removeProperty('left');
                robot.style.removeProperty('right');
                robot.style.removeProperty('transform');
            }
            body.classList.remove('is-confirm-tutorial-active');

            if (repositionTimer !== null) {
                window.clearTimeout(repositionTimer);
                repositionTimer = null;
            }
        }

        function dismissTutorialEntirely() {
            confirmPhasePending = false;
            if (currentPhase === 'confirm') {
                finishConfirmModalTutorial();
            } else {
                finishReviewPageTutorial();
                confirmPhasePending = false;
            }
        }

        function scrollTargetIntoView(target) {
            var el = (target instanceof Element) ? target : (target && target.node instanceof Element ? target.node : null);
            if (!el) return;

            var card = el.closest('[data-process-item]');
            var scrollEl = card || el;

            try {
                scrollEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } catch (_) {
                scrollEl.scrollIntoView();
            }
        }

        function positionTutorialCard(rect) {
            if (!tutorialCard) return;

            var viewportHeight = (window.visualViewport && window.visualViewport.height)
                || window.innerHeight
                || document.documentElement.clientHeight;

            var cardAtTop = false;

            if (rect && rect.top > viewportHeight * 0.45) {
                tutorialCard.style.top = 'max(14px, env(safe-area-inset-top, 14px))';
                tutorialCard.style.bottom = 'auto';
                cardAtTop = true;
            } else {
                tutorialCard.style.bottom = 'max(16px, env(safe-area-inset-bottom, 16px))';
                tutorialCard.style.top = 'auto';
            }

            // Dock robot to the card's top-right shoulder
            if (robot) {
                var cardRect = tutorialCard.getBoundingClientRect();
                var robotWidth = robot.offsetWidth || 90;
                var robotHeight = robot.offsetHeight || 90;

                // Position robot on the right shoulder of the card
                robot.style.transform = 'none';
                robot.style.left = Math.min(cardRect.right - robotWidth + 10, window.innerWidth - robotWidth - 4) + 'px';

                if (cardAtTop) {
                    // Card is at top, robot sits just below the card
                    robot.style.top = Math.min(cardRect.bottom - 10, viewportHeight - robotHeight - 4) + 'px';
                    robot.style.bottom = 'auto';
                } else {
                    // Card is at bottom, robot sits just above the card
                    robot.style.bottom = 'auto';
                    robot.style.top = Math.max(4, cardRect.top - robotHeight + 10) + 'px';
                }
            }
        }

        function positionPointer(target) {
            if (!active || !target) {
                hidePointer();
                return;
            }

            var rect = (target instanceof Element)
                ? target.getBoundingClientRect()
                : (target && typeof target.top === 'number' ? target : null);

            if (!rect || rect.width <= 0 || rect.height <= 0) {
                hidePointer();
                return;
            }

            var viewportWidth = (window.visualViewport && window.visualViewport.width)
                || window.innerWidth
                || document.documentElement.clientWidth;
            var viewportHeight = (window.visualViewport && window.visualViewport.height)
                || window.innerHeight
                || document.documentElement.clientHeight;

            var currentSteps = currentPhase === 'confirm' ? confirmSteps : reviewSteps;
            var step = currentSteps[currentStepIndex] || {};
            var pad = step.key === 'delete-button' ? 5 : 7;

            showPointer();
            spotlight.style.left = Math.max(2, rect.left - pad) + 'px';
            spotlight.style.top = Math.max(2, rect.top - pad) + 'px';
            spotlight.style.width = Math.max(30, rect.width + (pad * 2)) + 'px';
            spotlight.style.height = Math.max(30, rect.height + (pad * 2)) + 'px';
            spotlight.style.borderRadius = step.borderRadius || '14px';

            var pointerWidth = 74;
            var pointerHeight = 48;
            var gap = 10;
            var left = rect.right + gap;
            var top = rect.top + (rect.height / 2) - (pointerHeight / 2);
            var side = 'right';

            if (left + pointerWidth > viewportWidth - 6) {
                left = rect.left - pointerWidth - gap;
                side = 'left';
            }

            if (left < 6) {
                left = Math.max(6, Math.min(viewportWidth - pointerWidth - 6, rect.left + (rect.width / 2) - (pointerWidth / 2)));
                top = rect.top - pointerHeight - gap;
                side = 'top';
            }

            if (top < 6) {
                top = Math.min(viewportHeight - pointerHeight - 6, rect.bottom + gap);
                side = 'bottom';
            }

            if (top + pointerHeight > viewportHeight - 6) {
                top = Math.max(6, rect.top - pointerHeight - gap);
                side = 'top';
            }

            arrow.setAttribute('data-pointer-side', side);
            arrow.style.left = Math.round(left) + 'px';
            arrow.style.top = Math.round(top) + 'px';
            arrow.style.width = pointerWidth + 'px';
            arrow.style.height = pointerHeight + 'px';
            arrow.style.transform = 'none';

            positionTutorialCard(rect);
        }

        function renderStep(index) {
            var currentSteps = currentPhase === 'confirm' ? confirmSteps : reviewSteps;
            var step = currentSteps[index];
            if (!step) {
                if (currentPhase === 'confirm') {
                    finishConfirmModalTutorial();
                } else {
                    finishReviewPageTutorial();
                }
                return;
            }

            currentStepIndex = index;
            if (kickerNode) {
                kickerNode.textContent = currentPhase === 'confirm'
                    ? step.kicker
                    : 'W68 ORDER REVIEW GUIDE • LOGIN ' + loginNumber + ' OF 3';
            }
            titleNode.textContent = step.title;
            bodyNode.textContent = step.body;
            arrow.setAttribute('data-pointer-label', String(step.pointerLabel || 'LOOK'));

            var isLast = index >= currentSteps.length - 1;
            if (nextButton) nextButton.hidden = isLast;
            if (doneButton) {
                doneButton.hidden = !isLast;
                doneButton.textContent = currentPhase === 'confirm' ? 'FINISH' : 'NEXT';
            }

            clearTarget();

            var target = typeof step.target === 'function' ? step.target() : null;
            if (target) {
                currentTarget = target;
                if (target instanceof Element) {
                    target.classList.add('w68-tutorial-target');
                }

                scrollTargetIntoView(target);
                window.setTimeout(function () {
                    positionPointer(currentTarget);
                }, 180);
            } else {
                hidePointer();
            }
        }

        function nextStep() {
            var currentSteps = currentPhase === 'confirm' ? confirmSteps : reviewSteps;
            if (currentStepIndex < currentSteps.length - 1) {
                renderStep(currentStepIndex + 1);
            } else {
                if (currentPhase === 'confirm') {
                    finishConfirmModalTutorial();
                } else {
                    finishReviewPageTutorial();
                }
            }
        }

        function startConfirmModalTutorial() {
            if (!confirmModal || confirmModal.hidden || viewMode) return;

            currentPhase = 'confirm';
            active = true;
            body.classList.add('is-confirm-tutorial-active');

            overlay.hidden = false;
            overlay.style.removeProperty('display');
            overlay.setAttribute('aria-hidden', 'false');
            robot.hidden = false;
            robot.style.removeProperty('display');
            if (tutorialCard) {
                tutorialCard.hidden = false;
                tutorialCard.style.removeProperty('display');
            }

            renderStep(0);
        }

        window.W68TriggerConfirmTutorial = function () {
            if (confirmPhasePending) {
                confirmPhasePending = false;
                window.setTimeout(function () {
                    startConfirmModalTutorial();
                }, 220);
            }
        };

        window.W68CancelConfirmTutorial = function () {
            if (active && currentPhase === 'confirm') {
                finishConfirmModalTutorial();
            }
        };

        // Capturing click listener
        document.addEventListener('click', function (event) {
            if (!active) return;

            // If user clicked SKIP
            if (event.target && event.target.closest && event.target.closest('[data-w68-tutorial-skip]')) {
                dismissTutorialEntirely();
                return;
            }

            // In Phase 1, Step 6: if user clicked the PROCESS ORDER button directly
            if (currentPhase === 'review' && currentStepIndex === reviewSteps.length - 1) {
                if (event.target && event.target.closest && event.target.closest('[data-final-process]')) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    finishReviewPageTutorial();
                    openConfirmModal();
                    return;
                }
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            nextStep();
        }, true);

        // Touch protection for mobile and iPad
        document.addEventListener('touchstart', function (event) {
            if (!active) return;
            if (event.target && event.target.closest && event.target.closest('[data-w68-tutorial-skip]')) {
                return;
            }
            if (event.target && event.target.closest && event.target.closest('[data-remove-selected], [data-final-process], [data-delivery-open], [data-confirm-process]')) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, { passive: false, capture: true });

        if (skipButton) skipButton.addEventListener('click', dismissTutorialEntirely);
        if (doneButton) {
            doneButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                nextStep();
            });
        }
        if (nextButton) {
            nextButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                nextStep();
            });
        }

        function reposition() {
            if (!active || !currentTarget) return;
            if (repositionTimer !== null) window.clearTimeout(repositionTimer);
            repositionTimer = window.setTimeout(function () {
                positionPointer(currentTarget);
            }, 50);
        }

        window.addEventListener('resize', reposition, { passive: true });
        window.addEventListener('scroll', reposition, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', reposition, { passive: true });
            window.visualViewport.addEventListener('scroll', reposition, { passive: true });
        }

        // Start Phase 1 on the review page
        currentPhase = 'review';
        active = true;
        overlay.hidden = false;
        overlay.style.removeProperty('display');
        overlay.setAttribute('aria-hidden', 'false');
        robot.hidden = false;
        robot.style.removeProperty('display');
        if (tutorialCard) {
            tutorialCard.hidden = false;
            tutorialCard.style.removeProperty('display');
        }

        window.setTimeout(function () {
            renderStep(0);
        }, 350);
    }

    initProcessOrderTutorial();
})();
