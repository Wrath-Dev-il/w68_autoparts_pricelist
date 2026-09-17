(() => {
    const storageKey = 'w68_autoparts_cart_v1';
    const countNode = document.querySelector('[data-cart-count]');
    const drawer = document.querySelector('[data-cart-drawer]');
    const itemsNode = document.querySelector('[data-cart-items]');
    const emptyNode = document.querySelector('[data-cart-empty]');
    const totalNode = document.querySelector('[data-cart-total]');
    const toast = document.querySelector('[data-toast]');

    const money = (value) => new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));

    const readCart = () => {
        try {
            const data = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(data) ? data : [];
        } catch (_) {
            return [];
        }
    };

    const saveCart = (cart) => {
        localStorage.setItem(storageKey, JSON.stringify(cart));
        renderCart();
    };

    const showToast = (message) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.remove('show'), 1300);
    };

    const addItem = (button) => {
        const id = String(button.dataset.id || '');
        if (!id) return;

        const cart = readCart();
        const existing = cart.find((item) => item.id === id);
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({
                id,
                name: button.dataset.name || 'W68 Product',
                price: Number(button.dataset.price || 0),
                qty: 1,
            });
        }

        saveCart(cart);
        showToast('Added to cart');
    };

    const updateItem = (id, delta) => {
        const cart = readCart();
        const item = cart.find((entry) => entry.id === id);
        if (!item) return;
        item.qty += delta;
        saveCart(cart.filter((entry) => entry.qty > 0));
    };

    const removeItem = (id) => {
        saveCart(readCart().filter((entry) => entry.id !== id));
    };

    const renderCart = () => {
        const cart = readCart();
        const count = cart.reduce((sum, item) => sum + Number(item.qty || 0), 0);
        const total = cart.reduce((sum, item) => sum + Number(item.price || 0) * Number(item.qty || 0), 0);

        if (countNode) countNode.textContent = String(count);
        if (totalNode) totalNode.textContent = money(total);
        if (emptyNode) emptyNode.style.display = cart.length ? 'none' : 'block';
        if (!itemsNode) return;

        itemsNode.innerHTML = '';
        cart.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'cart-item';

            const title = document.createElement('strong');
            title.textContent = item.name;

            const price = document.createElement('span');
            price.textContent = money(Number(item.price) * Number(item.qty));

            const controls = document.createElement('div');
            controls.className = 'cart-item-controls';

            const minus = document.createElement('button');
            minus.type = 'button';
            minus.textContent = '−';
            minus.addEventListener('click', () => updateItem(item.id, -1));

            const qty = document.createElement('b');
            qty.textContent = String(item.qty);

            const plus = document.createElement('button');
            plus.type = 'button';
            plus.textContent = '+';
            plus.addEventListener('click', () => updateItem(item.id, 1));

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => removeItem(item.id));

            controls.append(minus, qty, plus, remove);
            row.append(title, price, controls);
            itemsNode.append(row);
        });
    };

    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-add-cart]');
        if (add && !add.disabled) addItem(add);

        if (event.target.closest('[data-cart-open]')) {
            drawer?.classList.add('open');
            drawer?.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        if (event.target.closest('[data-cart-close]')) {
            drawer?.classList.remove('open');
            drawer?.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawer?.classList.contains('open')) {
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    });

    renderCart();
})();
