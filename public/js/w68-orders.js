// W68_ORDERS_UNSERVED_SEARCH_V109_20260930
(() => {
    const body = document.body;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const orderBase = String(body?.dataset.orderUpdateBase || '').replace(/\/$/, '');
    const payloadNode = document.getElementById('w68-orders-payload');

    let orders = [];
    try {
        const parsed = JSON.parse(payloadNode?.textContent || '[]');
        orders = Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.error('Unable to read W68 orders payload.', error);
    }

    const orderMap = new Map(orders.map((order) => [String(order.id), order]));
    const tabs = Array.from(document.querySelectorAll('[data-orders-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-orders-panel]'));
    const modal = document.querySelector('[data-order-modal]');
    const itemsBody = modal?.querySelector('[data-order-items-body]');
    const modalError = modal?.querySelector('[data-order-modal-error]');
    const saveButton = modal?.querySelector('[data-save-order]');
    const deleteOrderButton = modal?.querySelector('[data-delete-order]');
    const editLock = modal?.querySelector('[data-order-edit-lock]');
    const modalTotal = modal?.querySelector('[data-order-modal-total]');
    let currentOrder = null;

    const money = (value) => Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const safeQty = (value) => Math.max(1, Math.min(9999, Math.round(Number(value) || 1)));

    const setText = (selector, value) => {
        const node = modal?.querySelector(selector);
        if (node) node.textContent = String(value ?? '');
    };

    const showError = (message = '') => {
        if (!modalError) return;
        modalError.textContent = message;
        modalError.hidden = !message;
    };

    const apiRequest = async (url, method, payload = null) => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload === null ? null : JSON.stringify(payload),
        });

        if (!response.ok) {
            let message = `Request failed (${response.status}).`;
            try {
                const error = await response.json();
                if (error?.message) message = error.message;
                if (error?.errors) {
                    const first = Object.values(error.errors).flat().filter(Boolean)[0];
                    if (first) message = first;
                }
            } catch (_) {
                // Keep status message.
            }
            throw new Error(message);
        }

        return response.status === 204 ? null : response.json().catch(() => null);
    };

    const activateTab = (name, updateHash = true) => {
        const requested = name === 'received' ? 'invoiced' : name;
        const valid = ['to-ship', 'invoiced', 'bills', 'unserved', 'cancelled'].includes(requested) ? requested : 'to-ship';
        tabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.ordersTab === valid));
        panels.forEach((panel) => {
            const active = panel.dataset.ordersPanel === valid;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
            panel.setAttribute('aria-hidden', active ? 'false' : 'true');
            panel.style.display = active ? '' : 'none';
        });
        if (updateHash) {
            history.replaceState(null, '', `#${valid}`);
        }
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => activateTab(tab.dataset.ordersTab)));
    const initialTab = location.hash.replace('#', '');
    activateTab(initialTab || 'to-ship', false);

    // Client-side search for each Orders tab. Search uses the rendered record
    // text so it covers order IDs, dates, statuses, waybills and item details.
    const normalizeSearchText = (value) => String(value || '')
        .toLocaleLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

    document.querySelectorAll('[data-orders-search-input]').forEach((input) => {
        const panel = input.closest('[data-orders-panel]');
        if (!panel) return;

        const empty = panel.querySelector('[data-orders-search-empty]');
        const records = Array.from(panel.querySelectorAll('[data-order-search-record]'));

        const applySearch = () => {
            const query = normalizeSearchText(input.value);
            let visible = 0;

            records.forEach((record) => {
                const matches = !query || normalizeSearchText(record.textContent).includes(query);
                record.style.display = matches ? '' : 'none';
                if (matches) visible += 1;
            });

            if (empty) {
                empty.hidden = !query || visible > 0;
            }
        };

        input.addEventListener('input', applySearch);
        input.addEventListener('search', applySearch);
    });

    const createCell = (text, className = '') => {
        const td = document.createElement('td');
        if (className) td.className = className;
        const strong = document.createElement('strong');
        strong.textContent = text || '—';
        td.append(strong);
        return td;
    };

    const refreshModalTotal = () => {
        if (!currentOrder || !itemsBody || !modalTotal) return;
        let total = 0;
        itemsBody.querySelectorAll('tr').forEach((row) => {
            const price = Number(row.dataset.discountedPrice || 0);
            const input = row.querySelector('[data-order-qty]');
            const qty = input ? safeQty(input.value) : safeQty(row.dataset.quantity || 1);
            const lineTotal = price * qty;
            total += lineTotal;
            const lineTotalNode = row.querySelector('[data-line-total]');
            if (lineTotalNode) lineTotalNode.textContent = money(lineTotal);
        });
        modalTotal.textContent = money(total);
    };

    const renderOrderItems = (order) => {
        if (!itemsBody) return;
        itemsBody.innerHTML = '';

        (order.items || []).forEach((item) => {
            const row = document.createElement('tr');
            row.dataset.itemId = String(item.id);
            row.dataset.discountedPrice = String(item.discounted_unit_price || 0);
            row.dataset.quantity = String(item.quantity || 1);

            row.append(
                createCell(item.description),
                createCell(item.product_code),
                createCell(item.part_number),
                createCell(item.application),
                createCell(item.brand),
            );

            const priceCell = document.createElement('td');
            priceCell.className = 'order-unit-price';
            const original = Number(item.original_unit_price || 0);
            const discounted = Number(item.discounted_unit_price || 0);
            const discount = Number(item.discount_percent || 0);

            if (discount > 0 && discounted < original) {
                const oldPrice = document.createElement('span');
                oldPrice.className = 'original';
                oldPrice.textContent = money(original);
                priceCell.append(oldPrice);
            }

            const currentPrice = document.createElement('span');
            currentPrice.className = 'discounted';
            currentPrice.textContent = money(discounted || original);
            priceCell.append(currentPrice);

            if (discount > 0) {
                const off = document.createElement('small');
                off.textContent = `${discount.toFixed(2)}% DISCOUNT`;
                priceCell.append(off);
            }
            row.append(priceCell);

            const qtyCell = document.createElement('td');
            if (order.editable) {
                const input = document.createElement('input');
                input.type = 'number';
                input.min = '1';
                input.max = '9999';
                input.inputMode = 'numeric';
                input.className = 'order-qty-input';
                input.value = String(safeQty(item.quantity));
                input.dataset.orderQty = '1';
                input.addEventListener('input', refreshModalTotal);
                input.addEventListener('change', () => {
                    input.value = String(safeQty(input.value));
                    refreshModalTotal();
                });
                qtyCell.append(input);
            } else {
                const strong = document.createElement('strong');
                strong.textContent = String(item.quantity || 0);
                qtyCell.append(strong);
            }
            row.append(qtyCell);

            const totalCell = document.createElement('td');
            const totalStrong = document.createElement('strong');
            totalStrong.dataset.lineTotal = '1';
            totalStrong.textContent = money(item.total_price);
            totalCell.append(totalStrong);
            row.append(totalCell);

            const actionCell = document.createElement('td');
            if (order.editable) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'delete-item-button';
                remove.textContent = 'DELETE ITEM';
                remove.addEventListener('click', async () => {
                    if (!confirm(`Delete ${item.description || item.product_code || 'this item'} from the order?`)) return;
                    showError('');
                    remove.disabled = true;
                    remove.textContent = 'DELETING...';
                    try {
                        await apiRequest(`${orderBase}/${order.id}/items/${item.id}`, 'DELETE');
                        location.reload();
                    } catch (error) {
                        remove.disabled = false;
                        remove.textContent = 'DELETE ITEM';
                        showError(error.message);
                    }
                });
                actionCell.append(remove);
            } else {
                const readOnly = document.createElement('span');
                readOnly.className = 'readonly-label';
                readOnly.textContent = 'READ ONLY';
                actionCell.append(readOnly);
            }
            row.append(actionCell);

            itemsBody.append(row);
        });
    };

    const openOrder = (order) => {
        if (!modal || !order) return;
        currentOrder = order;
        showError('');
        setText('[data-order-modal-code]', order.order_code || `Order ${order.id}`);
        setText('[data-order-modal-sales-note]', order.sales_number || '—');
        setText('[data-order-modal-date]', order.date || '—');
        setText('[data-order-modal-status]', order.status_label || (order.received ? 'ORDERED' : 'PROCESSED'));
        setText('[data-order-modal-total]', money(order.total_amount));
        renderOrderItems(order);
        if (editLock) editLock.hidden = Boolean(order.editable);
        if (saveButton) saveButton.hidden = !order.editable;
        if (deleteOrderButton) {
            const canDelete = order.can_delete === true
                || String(order.can_delete || '') === '1';
            deleteOrderButton.hidden = !canDelete;
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        refreshModalTotal();
    };

    const closeOrder = () => {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        currentOrder = null;
        showError('');
    };

    const openOrderFromButton = (button) => {
        const order = orderMap.get(String(button?.dataset?.viewOrder || ''));
        if (order) openOrder(order);
    };

    let lastViewTouchAt = 0;
    document.addEventListener('touchend', (event) => {
        const button = event.target.closest?.('[data-view-order]');
        if (!button) return;
        event.preventDefault();
        lastViewTouchAt = Date.now();
        openOrderFromButton(button);
    }, { passive: false });

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-view-order]');
        if (!button) return;
        if (Date.now() - lastViewTouchAt < 650) return;
        event.preventDefault();
        openOrderFromButton(button);
    });

    document.querySelectorAll('[data-order-modal-close]').forEach((button) => button.addEventListener('click', closeOrder));

    saveButton?.addEventListener('click', async () => {
        if (!currentOrder?.editable || !itemsBody) return;
        const items = Array.from(itemsBody.querySelectorAll('tr')).map((row) => ({
            id: Number(row.dataset.itemId),
            quantity: safeQty(row.querySelector('[data-order-qty]')?.value || row.dataset.quantity),
        }));

        if (!items.length) return;
        showError('');
        saveButton.disabled = true;
        const oldLabel = saveButton.textContent;
        saveButton.textContent = 'SAVING...';
        try {
            await apiRequest(`${orderBase}/${currentOrder.id}`, 'PATCH', { items });
            location.reload();
        } catch (error) {
            saveButton.disabled = false;
            saveButton.textContent = oldLabel;
            showError(error.message);
        }
    });

    deleteOrderButton?.addEventListener('click', async () => {
        const canDelete = currentOrder?.can_delete === true
            || String(currentOrder?.can_delete || '') === '1';

        if (!canDelete) return;

        if (!confirm(`Delete order ${currentOrder.order_code}? This will also permanently delete its linked Open Sales Note and Sales Note items.`)) return;

        showError('');
        deleteOrderButton.disabled = true;
        const oldLabel = deleteOrderButton.textContent;
        deleteOrderButton.textContent = 'DELETING...';
        try {
            await apiRequest(`${orderBase}/${currentOrder.id}`, 'DELETE');
            location.reload();
        } catch (error) {
            deleteOrderButton.disabled = false;
            deleteOrderButton.textContent = oldLabel;
            showError(error.message);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) closeOrder();
    });

    /* =========================================================
       W68_ORDERS_TUTORIAL_20261028
       First-time ordering onboarding guide for Orders page.
       Teaches user about dashboard cards and tabs across 11 steps:
       1.) dashboard card to ship
       2.) to ship tab
       3.) order card
       4.) search bar
       5.) status
       6.) view button
       7.) invoiced tab and invoiced dashboard card (at the same time)
       8.) unserved tab and unserved items dashboard card
       9.) cancelled tab
       10.) bills and bills dashboard card
       11.) NOTIFICATION BELL ICON
       ========================================================= */
    function initOrdersTutorial() {
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

        var active = false;
        var currentStepIndex = 0;
        var currentTarget = null;
        var repositionTimer = null;
        var lastAdvanceAt = 0;

        hidePointer();
        robot.hidden = true;
        robot.style.display = 'none';
        if (tutorialCard) {
            tutorialCard.hidden = true;
            tutorialCard.style.display = 'none';
        }

        var robotImage = robot.querySelector('img');
        var robotGif = String(body.getAttribute('data-tutorial-robot-gif') || '');
        if (robotImage && robotGif) {
            robotImage.src = robotGif;
        }

        // ==========================
        // Target Resolvers
        // ==========================
        function getToShipCardTarget() {
            return document.querySelector('.orders-stat-card.orders-stat-to-ship');
        }

        function getToShipTabTarget() {
            return document.querySelector('[data-orders-tab="to-ship"]');
        }

        function getOrderCardTarget() {
            return document.querySelector('.orders-tab-panel[data-orders-panel="to-ship"] .order-summary-card')
                || document.querySelector('.order-summary-card')
                || document.querySelector('.orders-tab-panel[data-orders-panel="to-ship"] .orders-empty')
                || document.querySelector('.orders-tab-panel[data-orders-panel="to-ship"]');
        }

        function getSearchBarTarget() {
            return document.querySelector('.orders-tab-panel[data-orders-panel="to-ship"] .orders-search-bar')
                || document.querySelector('.orders-search-bar');
        }

        function getStatusTarget() {
            var card = document.querySelector('.orders-tab-panel[data-orders-panel="to-ship"] .order-summary-card')
                || document.querySelector('.order-summary-card');
            return (card && card.querySelector('.status-badge')) || getOrderCardTarget();
        }

        function getViewButtonTarget() {
            var card = document.querySelector('.orders-tab-panel[data-orders-panel="to-ship"] .order-summary-card')
                || document.querySelector('.order-summary-card');
            return (card && card.querySelector('.view-order-button')) || getOrderCardTarget();
        }

        function getGroupTarget(tabSelector, cardSelector) {
            var tab = document.querySelector(tabSelector);
            var card = document.querySelector(cardSelector);
            var parts = [card, tab].filter(Boolean);
            if (!parts.length) return null;
            if (parts.length === 1) return parts[0];

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
                node: card,
                elements: parts
            };
        }

        function getInvoicedGroupTarget() {
            return getGroupTarget('[data-orders-tab="invoiced"]', '.orders-stat-card.orders-stat-invoiced');
        }

        function getUnservedGroupTarget() {
            return getGroupTarget('[data-orders-tab="unserved"]', '.orders-stat-card.orders-stat-unserved');
        }

        function getCancelledTabTarget() {
            return document.querySelector('[data-orders-tab="cancelled"]');
        }

        function getBillsGroupTarget() {
            return getGroupTarget('[data-orders-tab="bills"]', '.orders-stat-card.orders-stat-bills');
        }

        function getNotificationBellTarget() {
            return document.querySelector('.w68-notification-bell')
                || document.querySelector('[data-notification-open]')
                || document.querySelector('.orders-notification-slot');
        }

        var steps = [
            {
                key: 'to-ship-card',
                pointerLabel: 'TO SHIP',
                kicker: 'W68 ORDERS GUIDE • STEP 1 OF 11',
                title: '1. To Ship Dashboard Card',
                body: 'This dashboard card shows your total count of submitted orders currently being processed and waiting to ship.',
                borderRadius: '16px',
                target: getToShipCardTarget
            },
            {
                key: 'to-ship-tab',
                pointerLabel: 'TAB',
                kicker: 'W68 ORDERS GUIDE • STEP 2 OF 11',
                title: '2. To Ship Tab',
                body: 'Click this tab anytime to view all open orders waiting for warehouse preparation and dispatch.',
                borderRadius: '999px',
                target: getToShipTabTarget
            },
            {
                key: 'order-card',
                pointerLabel: 'ORDER',
                kicker: 'W68 ORDERS GUIDE • STEP 3 OF 11',
                title: '3. Order Summary Card',
                body: 'Each order is listed here with its Order ID, Sales Note number, order date, and total amount.',
                borderRadius: '16px',
                target: getOrderCardTarget
            },
            {
                key: 'search-bar',
                pointerLabel: 'SEARCH',
                kicker: 'W68 ORDERS GUIDE • STEP 4 OF 11',
                title: '4. Search Bar',
                body: 'Use the search bar to locate orders quickly by Order ID, Sales Note, date, or item description.',
                borderRadius: '12px',
                target: getSearchBarTarget
            },
            {
                key: 'status',
                pointerLabel: 'STATUS',
                kicker: 'W68 ORDERS GUIDE • STEP 5 OF 11',
                title: '5. Order Status',
                body: 'Shows the live progress of your order: PROCESSED when received, or PARTIAL when being served in batches.',
                borderRadius: '999px',
                target: getStatusTarget
            },
            {
                key: 'view-button',
                pointerLabel: 'VIEW',
                kicker: 'W68 ORDERS GUIDE • STEP 6 OF 11',
                title: '6. View Button',
                body: 'Click VIEW to inspect full order details, review item quantities, or make adjustments while Open.',
                borderRadius: '999px',
                target: getViewButtonTarget
            },
            {
                key: 'invoiced-group',
                pointerLabel: 'INVOICED',
                kicker: 'W68 ORDERS GUIDE • STEP 7 OF 11',
                title: '7. Invoiced Tab & Dashboard Card',
                body: 'The Invoiced card and tab track completed invoice batches once sales order processing and dispatch are confirmed.',
                borderRadius: '16px',
                target: getInvoicedGroupTarget
            },
            {
                key: 'unserved-group',
                pointerLabel: 'UNSERVED',
                kicker: 'W68 ORDERS GUIDE • STEP 8 OF 11',
                title: '8. Unserved Items Tab & Dashboard Card',
                body: 'Tracks pending items from partial deliveries that are still waiting for warehouse restock and fulfillment.',
                borderRadius: '16px',
                target: getUnservedGroupTarget
            },
            {
                key: 'cancelled-tab',
                pointerLabel: 'CANCELLED',
                kicker: 'W68 ORDERS GUIDE • STEP 9 OF 11',
                title: '9. Cancelled Tab',
                body: 'View the history of cancelled orders or sales notes that could not be completed.',
                borderRadius: '999px',
                target: getCancelledTabTarget
            },
            {
                key: 'bills-group',
                pointerLabel: 'BILLS',
                kicker: 'W68 ORDERS GUIDE • STEP 10 OF 11',
                title: '10. Bills Tab & Dashboard Card',
                body: 'Monitor your statements, current and upcoming invoice dues, payment schedules, and accounting receipts.',
                borderRadius: '16px',
                target: getBillsGroupTarget
            },
            {
                key: 'notification-bell',
                pointerLabel: 'ALERTS',
                kicker: 'W68 ORDERS GUIDE • STEP 11 OF 11',
                title: '11. Notification Bell Icon',
                body: 'Click the notification bell icon to check real-time order updates, invoice notices, and important account alerts.',
                borderRadius: '50%',
                target: getNotificationBellTarget
            }
        ];

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

        function finishOrdersTutorial() {
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
            if (repositionTimer !== null) {
                window.clearTimeout(repositionTimer);
                repositionTimer = null;
            }
        }

        function scrollTargetIntoView(target) {
            var el = (target instanceof Element) ? target : (target && target.node instanceof Element ? target.node : null);
            if (!el) return;
            try {
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } catch (_) {
                el.scrollIntoView();
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

            if (robot) {
                var cardRect = tutorialCard.getBoundingClientRect();
                var robotWidth = robot.offsetWidth || 90;
                var robotHeight = robot.offsetHeight || 90;

                robot.style.transform = 'none';
                robot.style.left = Math.min(cardRect.right - robotWidth + 10, window.innerWidth - robotWidth - 4) + 'px';

                if (cardAtTop) {
                    robot.style.top = Math.min(cardRect.bottom - 10, viewportHeight - robotHeight - 4) + 'px';
                    robot.style.bottom = 'auto';
                } else {
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

            var step = steps[currentStepIndex] || {};
            var pad = 6;

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

            var label = step.pointerLabel || 'HERE';
            arrow.setAttribute('data-pointer-label', label);
        }

        function updateVisuals() {
            if (!active) return;
            var step = steps[currentStepIndex];
            if (!step) return;

            var target = typeof step.target === 'function' ? step.target() : step.target;
            if (!target) {
                hidePointer();
                positionTutorialCard(null);
                return;
            }

            var rect = (target instanceof Element)
                ? target.getBoundingClientRect()
                : (target && typeof target.top === 'number' ? target : null);

            positionPointer(target);
            positionTutorialCard(rect);
        }

        function scheduleReposition() {
            if (!active) return;
            if (repositionTimer !== null) {
                window.clearTimeout(repositionTimer);
            }
            repositionTimer = window.setTimeout(function () {
                repositionTimer = null;
                updateVisuals();
            }, 60);
        }

        function renderStep() {
            clearTarget();

            var step = steps[currentStepIndex];
            if (!step) {
                finishOrdersTutorial();
                return;
            }

            var isLast = currentStepIndex === steps.length - 1;

            if (kickerNode) kickerNode.textContent = step.kicker || 'W68 ORDERS GUIDE';
            if (titleNode) titleNode.textContent = step.title;
            if (bodyNode) bodyNode.textContent = step.body;

            if (nextButton) {
                nextButton.hidden = isLast;
                nextButton.style.display = isLast ? 'none' : '';
            }
            if (doneButton) {
                doneButton.hidden = !isLast;
                doneButton.style.display = isLast ? '' : 'none';
            }

            overlay.hidden = false;
            overlay.style.removeProperty('display');
            overlay.setAttribute('aria-hidden', 'false');

            robot.hidden = false;
            robot.style.removeProperty('display');

            if (tutorialCard) {
                tutorialCard.hidden = false;
                tutorialCard.style.removeProperty('display');
            }

            var target = typeof step.target === 'function' ? step.target() : step.target;
            if (target) {
                currentTarget = target;
                if (target instanceof Element) {
                    target.classList.add('w68-tutorial-target');
                } else if (target.elements && Array.isArray(target.elements)) {
                    target.elements.forEach(function (el) {
                        if (el instanceof Element) el.classList.add('w68-tutorial-target');
                    });
                }
                scrollTargetIntoView(target);
            }

            updateVisuals();
        }

        function nextStep() {
            if (!active) return;
            if (currentStepIndex >= steps.length - 1) {
                finishOrdersTutorial();
            } else {
                currentStepIndex++;
                renderStep();
            }
        }

        function advanceTutorialFromTap(event) {
            if (!active) return;
            var now = Date.now();
            if (now - lastAdvanceAt < 300) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                }
                return;
            }
            lastAdvanceAt = now;

            var target = event && event.target;
            if (target && target.closest && target.closest('[data-w68-tutorial-skip]')) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                }
                finishOrdersTutorial();
                return;
            }

            if (event) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
            }
            nextStep();
        }

        function startOrdersTutorial() {
            active = true;
            currentStepIndex = 0;
            activateTab('to-ship', false);
            renderStep();
        }

        window.W68OrdersTutorialStart = startOrdersTutorial;
        window.W68OrdersTutorialDismiss = finishOrdersTutorial;

        window.addEventListener('click', advanceTutorialFromTap, true);
        window.addEventListener('touchend', advanceTutorialFromTap, { capture: true, passive: false });

        if (nextButton) {
            nextButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                nextStep();
            });
        }

        if (doneButton) {
            doneButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                finishOrdersTutorial();
            });
        }

        if (skipButton) {
            skipButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                finishOrdersTutorial();
            });
        }

        window.addEventListener('resize', scheduleReposition, { passive: true });
        window.addEventListener('scroll', scheduleReposition, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleReposition, { passive: true });
            window.visualViewport.addEventListener('scroll', scheduleReposition, { passive: true });
        }

        if (body && (body.getAttribute('data-first-login-tutorial') === '1' || window.location.search.indexOf('tutorial=') !== -1)) {
            window.setTimeout(function () {
                startOrdersTutorial();
            }, 300);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOrdersTutorial);
    } else {
        initOrdersTutorial();
    }
})();
