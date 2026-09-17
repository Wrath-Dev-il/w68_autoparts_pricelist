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
        const valid = ['to-ship', 'invoiced', 'returns', 'cancelled'].includes(requested) ? requested : 'to-ship';
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
        if (deleteOrderButton) deleteOrderButton.hidden = !order.editable;
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
        if (!currentOrder?.editable) return;
        if (!confirm(`Delete order ${currentOrder.order_code}? This also deletes its linked Open Sales Note.`)) return;

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
})();
