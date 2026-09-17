(() => {
    const storageKey = 'w68_autoparts_cart_v1';

    const nav = document.querySelector('[data-customer-nav]');
    const navExpand = document.querySelector('[data-nav-expand]');
    const cartDrawer = document.querySelector('[data-cart-drawer]');
    const cartItemsNode = document.querySelector('[data-cart-items]');
    const cartEmptyNode = document.querySelector('[data-cart-empty]');
    const cartTotalNode = document.querySelector('[data-cart-total]');
    const cartCountNode = document.querySelector('[data-cart-count]');

    const productModal = document.querySelector('[data-product-modal]');
    const cartItemModal = document.querySelector('[data-cart-item-modal]');
    const cartMessage = document.querySelector('[data-cart-message]');
    const cartMessageText = document.querySelector('[data-cart-message-text]');

    let lastScrollY = window.scrollY;
    let messageTimer = null;

    const money = (value) => new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

    const safeQty = (value) => {
        const qty = Math.floor(Number(value || 1));
        return Number.isFinite(qty) ? Math.max(1, Math.min(qty, 9999)) : 1;
    };

    const readCart = () => {
        try {
            const value = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(value) ? value : [];
        } catch (_) {
            return [];
        }
    };

    const saveCart = (cart) => {
        localStorage.setItem(storageKey, JSON.stringify(cart));
        renderCart();
    };

    const showCartMessage = (name) => {
        if (!cartMessage) return;

        if (cartMessageText) {
            cartMessageText.textContent = `${name || 'Item'} was added successfully.`;
        }

        cartMessage.hidden = false;
        cartMessage.setAttribute('aria-hidden', 'false');

        window.clearTimeout(messageTimer);
        messageTimer = window.setTimeout(() => {
            cartMessage.hidden = true;
            cartMessage.setAttribute('aria-hidden', 'true');
        }, 2000);
    };

    const getProductData = (card) => ({
        id: String(card?.dataset.productId || ''),
        name: String(card?.dataset.productName || card?.dataset.productDescription || 'W68 Product'),
        image: String(card?.dataset.productImage || ''),
        productCode: String(card?.dataset.productProductCode || ''),
        partNumber: String(card?.dataset.productPartNumber || ''),
        description: String(card?.dataset.productDescription || ''),
        application: String(card?.dataset.productApplication || ''),
        specification: String(card?.dataset.productSpecification || ''),
        position: String(card?.dataset.productPosition || ''),
        brand: String(card?.dataset.productBrand || ''),
        price: Number(card?.dataset.productPrice || 0),
    });

    const addToCart = (product, qty = 1) => {
        if (!product?.id) return;

        const cart = readCart();
        const existing = cart.find((item) => String(item.id) === String(product.id));

        if (existing) {
            existing.qty = safeQty(Number(existing.qty || 0) + qty);

            // Enrich older cart records created by the original storefront.
            Object.assign(existing, {
                name: product.name || existing.name,
                image: product.image || existing.image || '',
                productCode: product.productCode || existing.productCode || '',
                partNumber: product.partNumber || existing.partNumber || '',
                description: product.description || existing.description || '',
                application: product.application || existing.application || '',
                specification: product.specification || existing.specification || '',
                position: product.position || existing.position || '',
                brand: product.brand || existing.brand || '',
                price: Number(product.price || existing.price || 0),
            });
        } else {
            cart.push({
                ...product,
                qty: safeQty(qty),
            });
        }

        saveCart(cart);
        showCartMessage(product.name);
    };

    const setCartQty = (id, value) => {
        const cart = readCart();
        const item = cart.find((entry) => String(entry.id) === String(id));
        if (!item) return;

        item.qty = safeQty(value);
        saveCart(cart);
    };

    const removeCartItem = (id) => {
        saveCart(readCart().filter((item) => String(item.id) !== String(id)));
    };

    const openCart = () => {
        cartDrawer?.classList.add('open');
        cartDrawer?.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeCart = () => {
        cartDrawer?.classList.remove('open');
        cartDrawer?.setAttribute('aria-hidden', 'true');

        if (productModal?.hidden !== false && cartItemModal?.hidden !== false) {
            document.body.style.overflow = '';
        }
    };

    const openProductModal = (product) => {
        if (!productModal) return;

        const fields = {
            '[data-product-modal-name]': product.name || product.description || 'Product',
            '[data-product-modal-code]': product.productCode || '—',
            '[data-product-modal-part]': product.partNumber || '—',
            '[data-product-modal-application]': product.application || '—',
            '[data-product-modal-specification]': product.specification || '—',
            '[data-product-modal-position]': product.position || '—',
            '[data-product-modal-brand]': product.brand || '—',
        };

        Object.entries(fields).forEach(([selector, value]) => {
            const node = productModal.querySelector(selector);
            if (node) node.textContent = value;
        });

        const image = productModal.querySelector('[data-product-modal-image]');
        if (image) {
            image.src = product.image || '';
            image.alt = product.description || product.name || 'Product';
        }

        const add = productModal.querySelector('[data-product-modal-add]');
        if (add) {
            add._w68Product = product;
        }

        productModal.hidden = false;
        productModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeProductModal = () => {
        if (!productModal) return;
        productModal.hidden = true;
        productModal.setAttribute('aria-hidden', 'true');

        if (!cartDrawer?.classList.contains('open')) {
            document.body.style.overflow = '';
        }
    };

    const openCartItemModal = (item) => {
        if (!cartItemModal) return;

        const fields = {
            '[data-cart-item-modal-name]': item.name || item.description || 'Product',
            '[data-cart-item-modal-code]': item.productCode || '—',
            '[data-cart-item-modal-part]': item.partNumber || '—',
            '[data-cart-item-modal-application]': item.application || '—',
            '[data-cart-item-modal-specification]': item.specification || '—',
            '[data-cart-item-modal-position]': item.position || '—',
            '[data-cart-item-modal-brand]': item.brand || '—',
            '[data-cart-item-modal-price]': money(item.price),
            '[data-cart-item-modal-qty]': String(safeQty(item.qty)),
            '[data-cart-item-modal-total]': money(Number(item.price || 0) * safeQty(item.qty)),
        };

        Object.entries(fields).forEach(([selector, value]) => {
            const node = cartItemModal.querySelector(selector);
            if (node) node.textContent = value;
        });

        const image = cartItemModal.querySelector('[data-cart-item-modal-image]');
        if (image) {
            image.src = item.image || '';
            image.alt = item.description || item.name || 'Product';
        }

        cartItemModal.hidden = false;
        cartItemModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeCartItemModal = () => {
        if (!cartItemModal) return;
        cartItemModal.hidden = true;
        cartItemModal.setAttribute('aria-hidden', 'true');

        if (!cartDrawer?.classList.contains('open')) {
            document.body.style.overflow = '';
        }
    };

    const renderCart = () => {
        const cart = readCart();
        const totalQty = cart.reduce((sum, item) => sum + safeQty(item.qty), 0);
        const grandTotal = cart.reduce(
            (sum, item) => sum + Number(item.price || 0) * safeQty(item.qty),
            0
        );

        if (cartCountNode) cartCountNode.textContent = String(totalQty);
        if (cartTotalNode) cartTotalNode.textContent = money(grandTotal);
        if (cartEmptyNode) cartEmptyNode.style.display = cart.length ? 'none' : 'block';
        if (!cartItemsNode) return;

        cartItemsNode.innerHTML = '';

        cart.forEach((item) => {
            const row = document.createElement('article');
            row.className = 'cart-item-row';
            row.tabIndex = 0;

            const image = document.createElement('img');
            image.className = 'cart-item-image';
            image.src = item.image || '';
            image.alt = item.description || item.name || 'Product';

            const copy = document.createElement('div');
            copy.className = 'cart-item-copy';

            const code = document.createElement('small');
            code.textContent = item.productCode || 'W68 PRODUCT';

            const name = document.createElement('h3');
            name.textContent = item.description || item.name || 'W68 Product';

            const priceLines = document.createElement('div');
            priceLines.className = 'cart-price-lines';

            const unitLabel = document.createElement('span');
            unitLabel.textContent = 'Price per piece';

            const unitValue = document.createElement('strong');
            unitValue.textContent = money(item.price);

            const totalLabel = document.createElement('span');
            totalLabel.textContent = 'Line total';

            const totalValue = document.createElement('strong');
            totalValue.textContent = money(Number(item.price || 0) * safeQty(item.qty));

            priceLines.append(unitLabel, unitValue, totalLabel, totalValue);

            const qtyRow = document.createElement('div');
            qtyRow.className = 'cart-qty-row';

            const minus = document.createElement('button');
            minus.type = 'button';
            minus.textContent = '−';
            minus.setAttribute('aria-label', 'Decrease quantity');

            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.min = '1';
            qtyInput.max = '9999';
            qtyInput.inputMode = 'numeric';
            qtyInput.value = String(safeQty(item.qty));
            qtyInput.setAttribute('aria-label', 'Ordered quantity');

            const plus = document.createElement('button');
            plus.type = 'button';
            plus.textContent = '+';
            plus.setAttribute('aria-label', 'Increase quantity');

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'cart-remove';
            remove.textContent = 'REMOVE';

            [minus, qtyInput, plus, remove].forEach((node) => {
                node.addEventListener('click', (event) => event.stopPropagation());
            });

            minus.addEventListener('click', () => {
                setCartQty(item.id, safeQty(item.qty) - 1);
            });

            plus.addEventListener('click', () => {
                setCartQty(item.id, safeQty(item.qty) + 1);
            });

            qtyInput.addEventListener('change', () => {
                setCartQty(item.id, qtyInput.value);
            });

            qtyInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    qtyInput.blur();
                }
            });

            remove.addEventListener('click', () => removeCartItem(item.id));

            qtyRow.append(minus, qtyInput, plus, remove);
            copy.append(code, name, priceLines, qtyRow);
            row.append(image, copy);

            row.addEventListener('click', () => openCartItemModal(item));
            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openCartItemModal(item);
                }
            });

            cartItemsNode.append(row);
        });
    };

    // Auto-submit navigation dropdown filters.
    document.querySelectorAll('[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => {
            select.closest('form')?.requestSubmit();
        });
    });

    // Fold navigation while scrolling down; unfold while scrolling up.
    const updateNavOnScroll = () => {
        if (!nav) return;

        const currentY = window.scrollY;
        const movingDown = currentY > lastScrollY + 6;
        const movingUp = currentY < lastScrollY - 6;

        if (currentY < 35) {
            nav.classList.remove('is-collapsed');
        } else if (movingDown) {
            nav.classList.add('is-collapsed');
        } else if (movingUp) {
            nav.classList.remove('is-collapsed');
        }

        navExpand?.setAttribute(
            'aria-expanded',
            String(!nav.classList.contains('is-collapsed'))
        );

        lastScrollY = currentY;
    };

    window.addEventListener('scroll', updateNavOnScroll, { passive: true });

    navExpand?.addEventListener('click', () => {
        nav?.classList.toggle('is-collapsed');

        navExpand.setAttribute(
            'aria-expanded',
            String(!nav?.classList.contains('is-collapsed'))
        );
    });

    // Product cards.
    document.querySelectorAll('[data-product-card]').forEach((card) => {
        const product = getProductData(card);

        card.addEventListener('click', (event) => {
            if (event.target.closest('button, input, select, a, label')) return;
            openProductModal(product);
        });

        card.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            if (event.target.closest('button, input, select, a, label')) return;

            event.preventDefault();
            openProductModal(product);
        });

        card.querySelectorAll('[data-add-cart]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                addToCart(product);
            });
        });
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-cart-open]')) {
            openCart();
            return;
        }

        if (event.target.closest('[data-cart-close]')) {
            closeCart();
            return;
        }

        if (event.target.closest('[data-product-modal-close]')) {
            closeProductModal();
            return;
        }

        if (event.target.closest('[data-cart-item-modal-close]')) {
            closeCartItemModal();
            return;
        }

        const modalAdd = event.target.closest('[data-product-modal-add]');
        if (modalAdd?._w68Product) {
            addToCart(modalAdd._w68Product);
            closeProductModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        if (cartItemModal && !cartItemModal.hidden) {
            closeCartItemModal();
            return;
        }

        if (productModal && !productModal.hidden) {
            closeProductModal();
            return;
        }

        if (cartDrawer?.classList.contains('open')) {
            closeCart();
        }
    });

    renderCart();
    updateNavOnScroll();
})();
