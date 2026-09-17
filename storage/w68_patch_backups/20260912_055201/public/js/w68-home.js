(() => {
    const storageKey = 'w68_autoparts_cart_v1';

    const nav = document.querySelector('[data-customer-nav]');
    const navSearchToggle = document.querySelector('[data-nav-search-toggle]');
    const navSearchExtension = document.querySelector('[data-nav-search-extension]');
    const cartDrawer = document.querySelector('[data-cart-drawer]');
    const cartItemsNode = document.querySelector('[data-cart-items]');
    const cartEmptyNode = document.querySelector('[data-cart-empty]');
    const cartTotalNode = document.querySelector('[data-cart-total]');
    const cartSelectedTotalNode = document.querySelector('[data-cart-selected-total]');
    const cartCountNode = document.querySelector('[data-cart-count]');
    const cartSelectedCountNode = document.querySelector('[data-cart-selected-count]');
    const cartSelectAll = document.querySelector('[data-cart-select-all]');
    const processOrderButton = document.querySelector('[data-process-order]');

    const productModal = document.querySelector('[data-product-modal]');
    const cartItemModal = document.querySelector('[data-cart-item-modal]');
    const quantityModal = document.querySelector('[data-quantity-modal]');
    const processOrderModal = document.querySelector('[data-process-order-modal]');
    const cartMessage = document.querySelector('[data-cart-message]');
    const cartMessageText = document.querySelector('[data-cart-message-text]');
    const orderSuccess = document.querySelector('[data-order-success]');

    let lastScrollY = window.scrollY;
    let messageTimer = null;
    let orderSuccessTimer = null;
    let pendingQuantityProduct = null;

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
            if (!Array.isArray(value)) return [];

            return value.map((item) => ({
                ...item,
                qty: safeQty(item.qty),
                selected: Boolean(item.selected),
            }));
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

    const modalIsOpen = (modal) => modal && modal.hidden === false;

    const syncBodyLock = () => {
        const locked = cartDrawer?.classList.contains('open')
            || modalIsOpen(productModal)
            || modalIsOpen(cartItemModal)
            || modalIsOpen(quantityModal)
            || modalIsOpen(processOrderModal);

        document.body.style.overflow = locked ? 'hidden' : '';
    };

    /* =====================================================
       Three-state navigation:
       1) folded summary
       2) normal unfolded filters
       3) unfolded + Search Products extension

       Scrolling up does not unfold the navbar except when the
       user reaches the very top of the page.
       ===================================================== */
    const setSearchExtension = (open) => {
        if (!navSearchExtension || !navSearchToggle) return;

        navSearchExtension.hidden = !open;
        navSearchToggle.setAttribute('aria-expanded', String(open));
        navSearchToggle.classList.toggle('is-open', open);
        nav?.classList.toggle('is-search-extended', open);

        const helper = navSearchToggle.querySelector('small');
        const action = navSearchToggle.querySelector('strong');

        if (helper) {
            helper.textContent = open
                ? 'Search fields are open. Click / tap again to hide them.'
                : 'Click / tap here to extend Search Products';
        }

        if (action) {
            action.textContent = open ? 'HIDE SEARCH' : 'SEARCH PRODUCTS';
        }

        if (!open) {
            document.querySelectorAll('[data-recommend-list]').forEach((list) => {
                list.hidden = true;
                list.innerHTML = '';
            });
        }
    };

    const collapseNav = () => {
        if (!nav) return;

        setSearchExtension(false);
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

        if (currentY <= 8) {
            expandNav();
            setSearchExtension(false);
        } else if (
            currentY > 95
            && movingDown
            && !nav.classList.contains('is-collapsed')
        ) {
            collapseNav();
        }

        lastScrollY = currentY;
    };

    window.addEventListener('scroll', updateNavOnScroll, { passive: true });

    nav?.addEventListener('click', (event) => {
        if (!nav.classList.contains('is-collapsed')) return;
        if (event.target.closest('[data-cart-open]')) return;

        expandNav();
        setSearchExtension(false);
    });

    navSearchToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (nav?.classList.contains('is-collapsed')) {
            expandNav();
        }

        const shouldOpen = navSearchExtension?.hidden !== false;
        setSearchExtension(shouldOpen);

        if (shouldOpen) {
            window.setTimeout(() => {
                navSearchExtension?.scrollIntoView({
                    block: 'nearest',
                    behavior: 'smooth',
                });
            }, 40);
        }
    });

    updateNavOnScroll();

    /* =====================================================
       Filter dropdowns mirror their values into the matching
       search field, then apply the filter immediately.
       ===================================================== */
    document.querySelectorAll('[data-filter-select]').forEach((select) => {
        select.addEventListener('change', () => {
            const field = select.dataset.filterField || '';
            const input = document.querySelector(`[data-recommend-field="${CSS.escape(field)}"]`);

            if (input) input.value = select.value;

            showLoader();
            select.closest('form')?.requestSubmit();
        });
    });

    /* =====================================================
       Field-specific search recommendations
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

    const syncDropdownFromSearch = (input) => {
        const field = input.dataset.recommendField || '';
        const select = document.querySelector(`[data-filter-select][data-filter-field="${CSS.escape(field)}"]`);
        if (!select) return;

        const matchingOption = Array.from(select.options).find(
            (option) => option.value.toLowerCase() === input.value.trim().toLowerCase()
        );

        select.value = matchingOption ? matchingOption.value : '';
    };

    const renderRecommendations = (input, items) => {
        const host = input.closest('.recommendation-host');
        const list = host?.querySelector('[data-recommend-list]');
        if (!list) return;

        list.innerHTML = '';

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'recommendation-empty';
            empty.textContent = 'No matching selected W68 product data.';
            list.appendChild(empty);
            list.hidden = false;
            return;
        }

        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'recommendation-item recommendation-value-item';

            const image = document.createElement('img');
            image.src = item.image || '';
            image.alt = item.description || item.product_code || 'Product';
            image.loading = 'lazy';

            const copy = document.createElement('span');
            copy.className = 'recommendation-copy';

            const value = document.createElement('strong');
            value.textContent = item.value || '';

            const description = document.createElement('span');
            description.textContent = item.description || 'W68 Product';

            const meta = document.createElement('small');
            meta.textContent = [item.product_code, item.part_number, item.brand]
                .filter(Boolean)
                .join(' • ');

            copy.append(value, description, meta);
            button.append(image, copy);

            button.addEventListener('click', () => {
                input.value = item.value || '';
                syncDropdownFromSearch(input);
                closeRecommendationList(input);
                input.focus();
            });

            list.appendChild(button);
        });

        list.hidden = false;
    };

    const loadRecommendations = async (input) => {
        const value = input.value.trim();
        const field = input.dataset.recommendField || '';

        if (value.length < 1 || !recommendUrl || !field) {
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
                `${recommendUrl}${separator}field=${encodeURIComponent(field)}&q=${encodeURIComponent(value)}`,
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
            syncDropdownFromSearch(input);
            closeAllRecommendations(input);

            const oldTimer = recommendTimers.get(input);
            if (oldTimer) window.clearTimeout(oldTimer);

            recommendTimers.set(
                input,
                window.setTimeout(() => loadRecommendations(input), 150)
            );
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 1) loadRecommendations(input);
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeRecommendationList(input);
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.recommendation-host')) {
            closeAllRecommendations();
        }
    });

    /* =====================================================
       Add-to-cart + order success messages
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

    const showOrderSuccess = () => {
        if (!orderSuccess) return;

        orderSuccess.hidden = false;
        orderSuccess.setAttribute('aria-hidden', 'false');

        window.clearTimeout(orderSuccessTimer);
        orderSuccessTimer = window.setTimeout(() => {
            orderSuccess.hidden = true;
            orderSuccess.setAttribute('aria-hidden', 'true');
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

    const commitAddToCart = (product, qty = 1) => {
        if (!product?.id) return;

        const cart = readCart();
        const existing = cart.find((item) => String(item.id) === String(product.id));

        if (existing) {
            existing.qty = safeQty(Number(existing.qty || 0) + qty);
            Object.assign(existing, {
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
                selected: false,
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

    const setCartSelected = (id, selected) => {
        const cart = readCart();
        const item = cart.find((entry) => String(entry.id) === String(id));
        if (!item) return;
        item.selected = Boolean(selected);
        saveCart(cart);
    };

    const setAllCartSelected = (selected) => {
        const cart = readCart().map((item) => ({
            ...item,
            selected: Boolean(selected),
        }));
        saveCart(cart);
    };

    const removeCartItem = (id) => {
        saveCart(readCart().filter((item) => String(item.id) !== String(id)));
    };

    const openCart = () => {
        cartDrawer?.classList.add('open');
        cartDrawer?.setAttribute('aria-hidden', 'false');
        syncBodyLock();
    };

    const closeCart = () => {
        cartDrawer?.classList.remove('open');
        cartDrawer?.setAttribute('aria-hidden', 'true');
        syncBodyLock();
    };

    const openCartToProduct = (productId) => {
        openCart();

        window.setTimeout(() => {
            const row = cartItemsNode?.querySelector(
                `[data-cart-row-id="${CSS.escape(String(productId))}"]`
            );

            if (!row) return;
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.add('cart-focus');
            window.setTimeout(() => row.classList.remove('cart-focus'), 1900);
        }, 180);
    };

    /* =====================================================
       Quantity modal shown BEFORE add-to-cart
       ===================================================== */
    const updateQuantityModalTotal = () => {
        if (!quantityModal || !pendingQuantityProduct) return;

        const input = quantityModal.querySelector('[data-quantity-input]');
        const total = quantityModal.querySelector('[data-quantity-modal-total]');
        const qty = safeQty(input?.value || 1);

        if (input) input.value = String(qty);
        if (total) total.textContent = money(Number(pendingQuantityProduct.price || 0) * qty);
    };

    const openQuantityModal = (product) => {
        if (!quantityModal || !product?.id) return;

        pendingQuantityProduct = product;

        const image = quantityModal.querySelector('[data-quantity-modal-image]');
        const description = quantityModal.querySelector('[data-quantity-modal-description]');
        const code = quantityModal.querySelector('[data-quantity-modal-code]');
        const price = quantityModal.querySelector('[data-quantity-modal-price]');
        const input = quantityModal.querySelector('[data-quantity-input]');

        if (image) {
            image.src = product.image || '';
            image.alt = product.description || product.productCode || 'Product';
        }
        if (description) description.textContent = product.description || 'W68 Product';
        if (code) code.textContent = product.productCode || 'W68 PRODUCT';
        if (price) price.textContent = money(product.price);
        if (input) input.value = '1';

        updateQuantityModalTotal();
        quantityModal.hidden = false;
        quantityModal.setAttribute('aria-hidden', 'false');
        syncBodyLock();

        window.setTimeout(() => input?.focus(), 60);
    };

    const closeQuantityModal = () => {
        if (!quantityModal) return;
        quantityModal.hidden = true;
        quantityModal.setAttribute('aria-hidden', 'true');
        pendingQuantityProduct = null;
        syncBodyLock();
    };

    quantityModal?.querySelector('[data-quantity-minus]')?.addEventListener('click', () => {
        const input = quantityModal.querySelector('[data-quantity-input]');
        if (!input) return;
        input.value = String(safeQty(Number(input.value) - 1));
        updateQuantityModalTotal();
    });

    quantityModal?.querySelector('[data-quantity-plus]')?.addEventListener('click', () => {
        const input = quantityModal.querySelector('[data-quantity-input]');
        if (!input) return;
        input.value = String(safeQty(Number(input.value) + 1));
        updateQuantityModalTotal();
    });

    quantityModal?.querySelector('[data-quantity-input]')?.addEventListener('input', updateQuantityModalTotal);
    quantityModal?.querySelector('[data-quantity-input]')?.addEventListener('change', updateQuantityModalTotal);

    quantityModal?.querySelector('[data-quantity-confirm]')?.addEventListener('click', () => {
        if (!pendingQuantityProduct) return;
        const product = pendingQuantityProduct;
        const qty = safeQty(quantityModal.querySelector('[data-quantity-input]')?.value || 1);
        closeQuantityModal();
        commitAddToCart(product, qty);
    });

    /* =====================================================
       Product full-view modal
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
        syncBodyLock();
    };

    const closeProductModal = () => {
        if (!productModal) return;
        productModal.hidden = true;
        productModal.setAttribute('aria-hidden', 'true');
        syncBodyLock();
    };

    /* =====================================================
       Cart item full-view modal
       ===================================================== */
    const openCartItemModal = (item) => {
        if (!cartItemModal) return;

        const fields = {
            '[data-cart-item-modal-description]': item.description || '—',
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
            image.alt = item.description || item.productCode || 'Product';
        }

        cartItemModal.hidden = false;
        cartItemModal.setAttribute('aria-hidden', 'false');
        syncBodyLock();
    };

    const closeCartItemModal = () => {
        if (!cartItemModal) return;
        cartItemModal.hidden = true;
        cartItemModal.setAttribute('aria-hidden', 'true');
        syncBodyLock();
    };

    /* =====================================================
       Cart rendering with item checkbox + Select All
       ===================================================== */
    const renderCart = () => {
        const cart = readCart();
        const selected = cart.filter((item) => item.selected);
        const totalQty = cart.reduce((sum, item) => sum + safeQty(item.qty), 0);
        const grandTotal = cart.reduce(
            (sum, item) => sum + Number(item.price || 0) * safeQty(item.qty),
            0
        );
        const selectedTotal = selected.reduce(
            (sum, item) => sum + Number(item.price || 0) * safeQty(item.qty),
            0
        );

        if (cartCountNode) cartCountNode.textContent = String(totalQty);
        if (cartTotalNode) cartTotalNode.textContent = money(grandTotal);
        if (cartSelectedTotalNode) cartSelectedTotalNode.textContent = money(selectedTotal);
        if (cartSelectedCountNode) {
            cartSelectedCountNode.textContent = `${selected.length} selected`;
        }
        if (cartEmptyNode) cartEmptyNode.style.display = cart.length ? 'none' : 'block';
        if (processOrderButton) processOrderButton.disabled = selected.length === 0;

        if (cartSelectAll) {
            cartSelectAll.checked = cart.length > 0 && selected.length === cart.length;
            cartSelectAll.indeterminate = selected.length > 0 && selected.length < cart.length;
            cartSelectAll.disabled = cart.length === 0;
        }

        if (!cartItemsNode) return;
        cartItemsNode.innerHTML = '';

        cart.forEach((item) => {
            const row = document.createElement('article');
            row.className = 'cart-item-row';
            row.tabIndex = 0;
            row.dataset.cartRowId = String(item.id);
            row.classList.toggle('is-selected-for-order', Boolean(item.selected));

            const selectWrap = document.createElement('label');
            selectWrap.className = 'cart-item-check';
            selectWrap.setAttribute('aria-label', 'Select item for order');

            const select = document.createElement('input');
            select.type = 'checkbox';
            select.checked = Boolean(item.selected);
            select.setAttribute('aria-label', 'Select item for order');
            selectWrap.append(select);

            const image = document.createElement('img');
            image.className = 'cart-item-image';
            image.src = item.image || '';
            image.alt = item.description || item.productCode || 'Product';

            const copy = document.createElement('div');
            copy.className = 'cart-item-copy';

            const code = document.createElement('small');
            code.textContent = item.productCode || 'W68 PRODUCT';

            const description = document.createElement('h3');
            description.textContent = item.description || 'W68 Product';

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

            [selectWrap, select, minus, qtyInput, plus, remove].forEach((node) => {
                node.addEventListener('click', (event) => event.stopPropagation());
            });

            select.addEventListener('change', () => setCartSelected(item.id, select.checked));
            minus.addEventListener('click', () => setCartQty(item.id, safeQty(item.qty) - 1));
            plus.addEventListener('click', () => setCartQty(item.id, safeQty(item.qty) + 1));
            qtyInput.addEventListener('change', () => setCartQty(item.id, qtyInput.value));
            qtyInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    qtyInput.blur();
                }
            });
            remove.addEventListener('click', () => removeCartItem(item.id));

            qtyRow.append(minus, qtyInput, plus, remove);
            copy.append(code, description, priceLines, qtyRow);
            row.append(selectWrap, image, copy);

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

    cartSelectAll?.addEventListener('change', () => {
        setAllCartSelected(cartSelectAll.checked);
    });

    /* =====================================================
       Process order reminder / agreement
       ===================================================== */
    const updateProcessOrderModal = () => {
        if (!processOrderModal) return;

        const selected = readCart().filter((item) => item.selected);
        const total = selected.reduce(
            (sum, item) => sum + Number(item.price || 0) * safeQty(item.qty),
            0
        );

        const countNode = processOrderModal.querySelector('[data-process-order-count]');
        const totalNode = processOrderModal.querySelector('[data-process-order-total]');
        const agree = processOrderModal.querySelector('[data-process-agree]');
        const proceed = processOrderModal.querySelector('[data-process-proceed]');

        if (countNode) countNode.textContent = String(selected.length);
        if (totalNode) totalNode.textContent = money(total);
        if (agree) agree.checked = false;
        if (proceed) proceed.disabled = true;
    };

    const openProcessOrderModal = () => {
        const selected = readCart().filter((item) => item.selected);
        if (!selected.length || !processOrderModal) return;

        updateProcessOrderModal();
        processOrderModal.hidden = false;
        processOrderModal.setAttribute('aria-hidden', 'false');
        syncBodyLock();
    };

    const closeProcessOrderModal = () => {
        if (!processOrderModal) return;
        processOrderModal.hidden = true;
        processOrderModal.setAttribute('aria-hidden', 'true');
        syncBodyLock();
    };

    processOrderButton?.addEventListener('click', openProcessOrderModal);

    processOrderModal?.querySelector('[data-process-agree]')?.addEventListener('change', (event) => {
        const proceed = processOrderModal.querySelector('[data-process-proceed]');
        if (proceed) proceed.disabled = !event.target.checked;
    });

    processOrderModal?.querySelector('[data-process-proceed]')?.addEventListener('click', () => {
        const agree = processOrderModal.querySelector('[data-process-agree]');
        if (!agree?.checked) return;

        const remaining = readCart().filter((item) => !item.selected);
        closeProcessOrderModal();
        saveCart(remaining);
        showOrderSuccess();
    });

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
                openQuantityModal(product);
            });
        });

        card.querySelectorAll('[data-view-cart]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                openCartToProduct(product.id);
            });
        });
    });

    /* =====================================================
       Global modal/cart actions
       ===================================================== */
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

        if (event.target.closest('[data-quantity-modal-close]')) {
            closeQuantityModal();
            return;
        }

        if (event.target.closest('[data-process-order-close]')) {
            closeProcessOrderModal();
            return;
        }

        const modalAdd = event.target.closest('[data-product-modal-add]');
        if (modalAdd?._w68Product) {
            const product = modalAdd._w68Product;
            closeProductModal();
            openQuantityModal(product);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        if (modalIsOpen(processOrderModal)) {
            closeProcessOrderModal();
            return;
        }
        if (modalIsOpen(quantityModal)) {
            closeQuantityModal();
            return;
        }
        if (modalIsOpen(cartItemModal)) {
            closeCartItemModal();
            return;
        }
        if (modalIsOpen(productModal)) {
            closeProductModal();
            return;
        }
        if (cartDrawer?.classList.contains('open')) {
            closeCart();
        }
    });

    renderCart();
    syncProductCardStates();
    updateNavOnScroll();
})();
