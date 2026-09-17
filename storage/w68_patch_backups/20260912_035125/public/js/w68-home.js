(() => {
    const storageKey = 'w68_autoparts_cart_v1';

    const nav = document.querySelector('[data-customer-nav]');
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

    /* =====================================================
       W68 loading animation
       ===================================================== */
    const loader = document.querySelector('[data-page-loader]');
    const loaderPaths = loader ? Array.from(loader.querySelectorAll('[data-loader-path]')) : [];
    const loaderPen = loader?.querySelector('[data-loader-pen]');
    const loaderDot = loader?.querySelector('[data-loader-dot]');
    const loaderAura = loader?.querySelector('[data-loader-aura]');
    const loaderSparkleOne = loader?.querySelector('[data-loader-sparkle-one]');
    const loaderSparkleTwo = loader?.querySelector('[data-loader-sparkle-two]');

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

    const startLoaderAnimation = () => {
        if (!loader || !loaderPaths.length) return;

        if (loaderFrame) window.cancelAnimationFrame(loaderFrame);
        if (loaderRestartTimer) window.clearTimeout(loaderRestartTimer);

        loaderRunning = true;
        loaderStartTime = null;
        initializeLoaderPaths();
        loaderPen?.classList.remove('is-visible');
        loaderFrame = window.requestAnimationFrame(loaderAnimate);
    };

    const showLoader = () => {
        if (!loader) return;
        loader.classList.remove('is-hidden');
        loader.setAttribute('aria-hidden', 'false');
        startLoaderAnimation();
    };

    const hideLoader = () => {
        if (!loader) return;

        loaderRunning = false;
        if (loaderFrame) window.cancelAnimationFrame(loaderFrame);
        if (loaderRestartTimer) window.clearTimeout(loaderRestartTimer);
        loaderFrame = null;
        loaderRestartTimer = null;

        loaderPen?.classList.remove('is-visible');
        loader.classList.add('is-hidden');
        loader.setAttribute('aria-hidden', 'true');
    };

    initializeLoaderPaths();
    startLoaderAnimation();

    window.addEventListener('load', () => {
        window.setTimeout(hideLoader, 220);
    });

    document.querySelectorAll('[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', showLoader);
    });

    document.querySelectorAll('[data-loading-link]').forEach((link) => {
        link.addEventListener('click', showLoader);
    });

    /* =====================================================
       Utilities
       ===================================================== */
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

    const writeCart = (cart) => {
        localStorage.setItem(storageKey, JSON.stringify(cart));
    };

    const getProductData = (card) => ({
        id: String(card?.dataset.productId || ''),
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

    /* =====================================================
       Folded navigation
       - folds while scrolling down
       - NEVER unfolds merely because user scrolls up
       - click folded bar to unfold
       ===================================================== */
    const collapseNav = () => {
        if (!nav) return;
        nav.classList.add('is-collapsed');
        nav.querySelector('[data-nav-collapsed-summary]')?.setAttribute('aria-hidden', 'false');
    };

    const expandNav = () => {
        if (!nav) return;
        nav.classList.remove('is-collapsed');
        nav.querySelector('[data-nav-collapsed-summary]')?.setAttribute('aria-hidden', 'true');
    };

    const updateNavOnScroll = () => {
        if (!nav) return;

        const currentY = window.scrollY;
        const movingDown = currentY > lastScrollY + 7;

        if (currentY > 95 && movingDown && !nav.classList.contains('is-collapsed')) {
            collapseNav();
        }

        // Intentionally no auto-expand when scrolling upward.
        lastScrollY = currentY;
    };

    window.addEventListener('scroll', updateNavOnScroll, { passive: true });

    nav?.addEventListener('click', (event) => {
        if (!nav.classList.contains('is-collapsed')) return;

        // Cart remains independent while the navigation is folded.
        if (event.target.closest('[data-cart-open]')) return;

        expandNav();
    });

    /* =====================================================
       Navbar dropdown filters
       ===================================================== */
    document.querySelectorAll('[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => {
            showLoader();
            select.closest('form')?.requestSubmit();
        });
    });

    /* =====================================================
       Search recommendations
       ===================================================== */
    const recommendUrl = document.body.dataset.recommendUrl || '';
    const recommendTimers = new WeakMap();
    const recommendControllers = new WeakMap();

    const closeRecommendationList = (input) => {
        const host = input?.closest('.recommendation-host');
        const list = host?.querySelector('[data-recommend-list]');

        if (!list) return;
        list.hidden = true;
        list.innerHTML = '';
    };

    const closeAllRecommendations = (exceptInput = null) => {
        document.querySelectorAll('[data-recommend-input]').forEach((input) => {
            if (input !== exceptInput) closeRecommendationList(input);
        });
    };

    const renderRecommendations = (input, items) => {
        const host = input.closest('.recommendation-host');
        const list = host?.querySelector('[data-recommend-list]');
        if (!list) return;

        list.innerHTML = '';

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'recommendation-empty';
            empty.textContent = 'No selected W68 product recommendation found.';
            list.appendChild(empty);
            list.hidden = false;
            return;
        }

        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'recommendation-item';

            const image = document.createElement('img');
            image.src = item.image || '';
            image.alt = item.description || item.product_code || 'Product';
            image.loading = 'lazy';

            const copy = document.createElement('span');
            copy.className = 'recommendation-copy';

            const code = document.createElement('strong');
            code.textContent = item.product_code || 'W68 PRODUCT';

            const description = document.createElement('span');
            description.textContent = item.description || 'W68 Product';

            const meta = document.createElement('small');
            meta.textContent = [item.part_number, item.brand, item.application]
                .filter(Boolean)
                .join(' • ');

            const price = document.createElement('b');
            price.className = 'recommendation-price';
            price.textContent = money(item.price);

            copy.append(code, description, meta);
            button.append(image, copy, price);

            button.addEventListener('click', () => {
                showLoader();
                window.location.href = item.url;
            });

            list.appendChild(button);
        });

        list.hidden = false;
    };

    const loadRecommendations = async (input) => {
        const value = input.value.trim();

        if (value.length < 2 || !recommendUrl) {
            closeRecommendationList(input);
            return;
        }

        const previous = recommendControllers.get(input);
        previous?.abort();

        const controller = new AbortController();
        recommendControllers.set(input, controller);

        try {
            const separator = recommendUrl.includes('?') ? '&' : '?';
            const response = await fetch(
                `${recommendUrl}${separator}q=${encodeURIComponent(value)}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                }
            );

            if (!response.ok) return;

            const payload = await response.json();

            // Ignore stale results if the user kept typing.
            if (input.value.trim() !== value) return;

            renderRecommendations(input, Array.isArray(payload.items) ? payload.items : []);
        } catch (error) {
            if (error?.name !== 'AbortError') {
                console.error('W68 recommendation error:', error);
            }
        }
    };

    document.querySelectorAll('[data-recommend-input]').forEach((input) => {
        input.addEventListener('input', () => {
            closeAllRecommendations(input);

            const oldTimer = recommendTimers.get(input);
            if (oldTimer) window.clearTimeout(oldTimer);

            recommendTimers.set(
                input,
                window.setTimeout(() => loadRecommendations(input), 180)
            );
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 2) loadRecommendations(input);
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeRecommendationList(input);
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.recommendation-host')) {
            closeAllRecommendations();
        }
    });

    /* =====================================================
       Add-to-cart confirmation
       ===================================================== */
    const showCartMessage = (description) => {
        if (!cartMessage) return;

        if (cartMessageText) {
            cartMessageText.textContent = `${description || 'Item'} was added successfully.`;
        }

        cartMessage.hidden = false;
        cartMessage.setAttribute('aria-hidden', 'false');

        window.clearTimeout(messageTimer);
        messageTimer = window.setTimeout(() => {
            cartMessage.hidden = true;
            cartMessage.setAttribute('aria-hidden', 'true');
        }, 2000);
    };

    /* =====================================================
       Cart state + product-card visual sync
       ===================================================== */
    const syncProductCardStates = () => {
        const cartIds = new Set(readCart().map((item) => String(item.id)));

        document.querySelectorAll('[data-product-card]').forEach((card) => {
            const inCart = cartIds.has(String(card.dataset.productId || ''));
            card.classList.toggle('is-in-cart', inCart);

            const viewButton = card.querySelector('[data-view-cart]');
            if (viewButton) viewButton.hidden = !inCart;
        });
    };

    const saveCart = (cart) => {
        writeCart(cart);
        renderCart();
        syncProductCardStates();
    };

    const addToCart = (product, qty = 1) => {
        if (!product?.id) return;

        const cart = readCart();
        const existing = cart.find((item) => String(item.id) === String(product.id));

        if (existing) {
            existing.qty = safeQty(Number(existing.qty || 0) + qty);

            Object.assign(existing, {
                image: product.image || existing.image || '',
                productCode: product.productCode || existing.productCode || '',
                partNumber: product.partNumber || existing.partNumber || '',
                description: product.description || existing.description || existing.name || '',
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
        showCartMessage(product.description || product.productCode || 'Item');
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

    const focusCartItem = (id) => {
        window.requestAnimationFrame(() => {
            const row = Array.from(document.querySelectorAll('[data-cart-row-id]'))
                .find((node) => String(node.dataset.cartRowId) === String(id));

            if (!row) return;

            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.add('cart-focus');
            window.setTimeout(() => row.classList.remove('cart-focus'), 1700);
        });
    };

    const openCartForItem = (id) => {
        renderCart();
        openCart();
        window.setTimeout(() => focusCartItem(id), 90);
    };

    /* =====================================================
       Product details modal
       ===================================================== */
    const openProductModal = (product) => {
        if (!productModal) return;

        const fields = {
            '[data-product-modal-description]': product.description || '—',
            '[data-product-modal-code]': product.productCode || '—',
            '[data-product-modal-part]': product.partNumber || '—',
            '[data-product-modal-application]': product.application || '—',
            '[data-product-modal-specification]': product.specification || '—',
            '[data-product-modal-brand]': product.brand || '—',
        };

        Object.entries(fields).forEach(([selector, value]) => {
            const node = productModal.querySelector(selector);
            if (node) node.textContent = value;
        });

        const image = productModal.querySelector('[data-product-modal-image]');
        if (image) {
            image.src = product.image || '';
            image.alt = product.description || product.productCode || 'Product';
        }

        const add = productModal.querySelector('[data-product-modal-add]');
        if (add) add._w68Product = product;

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

    /* =====================================================
       Cart item full-view modal
       ===================================================== */
    const openCartItemModal = (item) => {
        if (!cartItemModal) return;

        const fields = {
            '[data-cart-item-modal-description]': item.description || item.name || '—',
            '[data-cart-item-modal-code]': item.productCode || '—',
            '[data-cart-item-modal-part]': item.partNumber || '—',
            '[data-cart-item-modal-application]': item.application || '—',
            '[data-cart-item-modal-specification]': item.specification || '—',
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
            image.alt = item.description || item.name || item.productCode || 'Product';
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

    /* =====================================================
       Cart rendering
       ===================================================== */
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
            row.dataset.cartRowId = String(item.id);

            const image = document.createElement('img');
            image.className = 'cart-item-image';
            image.src = item.image || '';
            image.alt = item.description || item.name || item.productCode || 'Product';

            const copy = document.createElement('div');
            copy.className = 'cart-item-copy';

            const code = document.createElement('small');
            code.textContent = item.productCode || 'W68 PRODUCT';

            const description = document.createElement('h3');
            description.textContent = item.description || item.name || 'W68 Product';

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
            copy.append(code, description, priceLines, qtyRow);
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

    /* =====================================================
       Product card bindings
       ===================================================== */
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

        card.querySelectorAll('[data-view-cart]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                openCartForItem(product.id);
            });
        });
    });

    /* =====================================================
       Global click / keyboard behavior
       ===================================================== */
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-cart-open]')) {
            event.stopPropagation();
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

        closeAllRecommendations();

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
    syncProductCardStates();
})();
