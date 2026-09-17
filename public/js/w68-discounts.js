(() => {
    'use strict';

    const body = document.body;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const stateUrl = body?.dataset?.cartStateUrl || '';
    const itemBaseUrl = body?.dataset?.cartItemBaseUrl || '';
    const selectionUrl = body?.dataset?.cartSelectionUrl || '';

    const drawer = document.querySelector('[data-orders-cart-drawer]');
    const itemsNode = document.querySelector('[data-orders-cart-items]');
    const emptyNode = document.querySelector('[data-orders-cart-empty]');
    const totalNode = document.querySelector('[data-orders-cart-total]');
    const selectedTotalNode = document.querySelector('[data-orders-cart-selected-total]');
    const selectedCountNode = document.querySelector('[data-orders-cart-selected-count]');
    const selectAllNode = document.querySelector('[data-orders-cart-select-all]');
    const countNodes = document.querySelectorAll('[data-orders-cart-count]');

    if (!drawer || !itemsNode || !stateUrl || !itemBaseUrl) return;

    let items = [];
    let loading = false;

    const money = (value) => Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const safeQty = (value) => Math.max(1, Math.min(9999, Math.round(Number(value) || 1)));

    const unitPrice = (item) => {
        const original = Number(item?.price || 0);
        const discounted = Number(item?.discountedPrice ?? original);
        const percent = Number(item?.discountPercent || 0);
        return (percent > 0 || discounted < original) ? discounted : original;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const request = async (url, method = 'GET', payload = null) => {
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
            let message = `Cart request failed (${response.status}).`;
            try {
                const data = await response.json();
                if (data?.message) message = data.message;
                if (data?.errors) {
                    const first = Object.values(data.errors).flat().filter(Boolean)[0];
                    if (first) message = first;
                }
            } catch (_) {
                // Keep the HTTP status message.
            }
            throw new Error(message);
        }

        return response.status === 204 ? null : response.json().catch(() => null);
    };

    const render = () => {
        itemsNode.innerHTML = '';
        drawer.classList.toggle('is-empty', items.length === 0);
        if (emptyNode) emptyNode.hidden = items.length !== 0;

        let total = 0;
        let selectedTotal = 0;
        let selectedCount = 0;

        items.forEach((item) => {
            item.qty = safeQty(item.qty);
            const unit = unitPrice(item);
            const lineTotal = unit * item.qty;
            total += lineTotal;

            if (item.selected) {
                selectedCount += 1;
                selectedTotal += lineTotal;
            }

            const row = document.createElement('article');
            row.className = 'orders-cart-item';
            row.dataset.productId = String(item.id);

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = Boolean(item.selected);
            checkbox.setAttribute('aria-label', `Select ${item.description || item.productCode || 'cart item'}`);
            checkbox.addEventListener('change', async () => {
                const previous = item.selected;
                item.selected = checkbox.checked;
                render();
                try {
                    await request(`${itemBaseUrl}/${encodeURIComponent(item.id)}`, 'PATCH', { selected: item.selected });
                } catch (error) {
                    item.selected = previous;
                    render();
                    alert(error.message);
                }
            });

            const image = document.createElement('img');
            image.src = item.image || '';
            image.alt = item.description || item.productCode || 'Product';

            const main = document.createElement('div');
            main.className = 'orders-cart-item-main';

            const code = document.createElement('div');
            code.className = 'orders-cart-item-code';
            code.textContent = item.productCode || '—';

            const title = document.createElement('h3');
            title.textContent = item.description || '—';

            const meta = document.createElement('div');
            meta.className = 'orders-cart-item-meta';
            meta.innerHTML = [
                `<span><b>Part No.</b> ${escapeHtml(item.partNumber || '—')}</span>`,
                `<span><b>Application</b> ${escapeHtml(item.application || '—')}</span>`,
                `<span><b>Position</b> ${escapeHtml(item.position || '—')}</span>`,
                `<span><b>Brand</b> ${escapeHtml(item.brand || '—')}</span>`,
            ].join('');

            const priceRow = document.createElement('div');
            priceRow.className = 'orders-cart-price';
            const original = Number(item.price || 0);
            const discounted = Number(item.discountedPrice ?? original);
            const discount = Number(item.discountPercent || 0);

            if (discount > 0 || discounted < original) {
                const old = document.createElement('span');
                old.className = 'old';
                old.textContent = money(original);
                priceRow.append(old);
            }

            const current = document.createElement('span');
            current.className = 'current';
            current.textContent = money(unit);
            priceRow.append(current);

            if (discount > 0) {
                const off = document.createElement('small');
                off.textContent = `${discount.toFixed(2)}% DISCOUNT`;
                priceRow.append(off);
            }

            const actions = document.createElement('div');
            actions.className = 'orders-cart-item-actions';

            const minus = document.createElement('button');
            minus.type = 'button';
            minus.textContent = '−';
            minus.setAttribute('aria-label', 'Decrease quantity');

            const input = document.createElement('input');
            input.type = 'number';
            input.min = '1';
            input.max = '9999';
            input.inputMode = 'numeric';
            input.value = String(item.qty);

            const plus = document.createElement('button');
            plus.type = 'button';
            plus.textContent = '+';
            plus.setAttribute('aria-label', 'Increase quantity');

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove';
            remove.textContent = 'REMOVE';

            const saveQty = async (newQty) => {
                const previous = item.qty;
                item.qty = safeQty(newQty);
                render();
                try {
                    await request(`${itemBaseUrl}/${encodeURIComponent(item.id)}`, 'PATCH', { quantity: item.qty });
                } catch (error) {
                    item.qty = previous;
                    render();
                    alert(error.message);
                }
            };

            minus.addEventListener('click', () => saveQty(item.qty - 1));
            plus.addEventListener('click', () => saveQty(item.qty + 1));
            input.addEventListener('change', () => saveQty(input.value));
            remove.addEventListener('click', async () => {
                if (!confirm(`Remove ${item.description || item.productCode || 'this item'} from your cart?`)) return;
                try {
                    await request(`${itemBaseUrl}/${encodeURIComponent(item.id)}`, 'DELETE');
                    items = items.filter((cartItem) => String(cartItem.id) !== String(item.id));
                    render();
                } catch (error) {
                    alert(error.message);
                }
            });

            actions.append(minus, input, plus, remove);

            const line = document.createElement('div');
            line.className = 'orders-cart-line-total';
            line.textContent = `TOTAL ${money(lineTotal)}`;

            main.append(code, title, meta, priceRow, actions, line);
            row.append(checkbox, image, main);
            itemsNode.append(row);
        });

        countNodes.forEach((node) => { node.textContent = String(items.length); });
        if (totalNode) totalNode.textContent = money(total);
        if (selectedTotalNode) selectedTotalNode.textContent = money(selectedTotal);
        if (selectedCountNode) selectedCountNode.textContent = `${selectedCount} selected`;
        if (selectAllNode) {
            selectAllNode.checked = items.length > 0 && selectedCount === items.length;
            selectAllNode.indeterminate = selectedCount > 0 && selectedCount < items.length;
        }
    };

    const load = async () => {
        if (loading) return;
        loading = true;
        try {
            const data = await request(stateUrl);
            items = Array.isArray(data?.items) ? data.items : [];
            render();
        } catch (error) {
            console.error('Unable to load the W68 cart on the Discounts page.', error);
        } finally {
            loading = false;
        }
    };

    const open = () => {
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('orders-cart-open');
        load();
    };

    const close = () => {
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('orders-cart-open');
    };

    let lastTouchAt = 0;

    document.querySelectorAll('[data-orders-cart-open]').forEach((button) => {
        button.addEventListener('touchend', (event) => {
            event.preventDefault();
            event.stopPropagation();
            lastTouchAt = Date.now();
            open();
        }, { passive: false });

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (Date.now() - lastTouchAt < 650) return;
            open();
        });
    });

    document.querySelectorAll('[data-orders-cart-close]').forEach((button) => {
        button.addEventListener('touchend', (event) => {
            event.preventDefault();
            event.stopPropagation();
            close();
        }, { passive: false });

        button.addEventListener('click', (event) => {
            event.preventDefault();
            close();
        });
    });

    // Close the side cart when the user taps/clicks outside the panel.
    const closeFromOutside = (event) => {
        if (!drawer.classList.contains('open')) return;
        const target = event.target;
        if (target?.closest?.('.orders-cart-panel')) return;
        if (target?.closest?.('[data-orders-cart-open]')) return;
        close();
    };

    drawer.addEventListener('touchend', closeFromOutside, { passive: true });
    drawer.addEventListener('click', closeFromOutside);

    selectAllNode?.addEventListener('change', async () => {
        const selected = selectAllNode.checked;
        const previous = items.map((item) => item.selected);
        items.forEach((item) => { item.selected = selected; });
        render();

        try {
            await request(selectionUrl, 'PUT', { selected });
        } catch (error) {
            items.forEach((item, index) => { item.selected = previous[index]; });
            render();
            alert(error.message);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawer.classList.contains('open')) close();
    });

    load();
})();
