(() => {
    const body = document.body;
    const cartAccountKey = String(body?.dataset.cartAccountKey || 'unknown')
        .replace(/[^a-zA-Z0-9_-]/g, '_');
    const storageKey = `w68_autoparts_cart_v2_${cartAccountKey}`;
    const cartStorageReady = body?.dataset.cartStorageReady === '1';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const cartApi = {
        add: String(body?.dataset.cartAddUrl || ''),
        itemBase: String(body?.dataset.cartItemBaseUrl || ''),
        selection: String(body?.dataset.cartSelectionUrl || ''),
        removeSelected: String(body?.dataset.cartRemoveSelectedUrl || ''),
        sync: String(body?.dataset.cartSyncUrl || ''),
        state: String(body?.dataset.cartStateUrl || ''),
    };
    const orderApi = {
        process: String(body?.dataset.orderProcessUrl || ''),
        review: String(body?.dataset.processOrderReviewUrl || ''),
        orders: String(body?.dataset.ordersUrl || ''),
    };
    const serverCart = (() => {
        const raw = String(body?.dataset.serverCart || '[]');

        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            // Compatibility with the previous v56 markup, which escaped the
            // JSON twice and left literal &quot; sequences in dataset.serverCart.
            try {
                const textarea = document.createElement('textarea');
                textarea.innerHTML = raw;
                const parsed = JSON.parse(textarea.value || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (__error) {
                return [];
            }
        }
    })();
    let cartMutationQueue = Promise.resolve();

    const nav = document.querySelector('[data-customer-nav]');
    const collapsedNavOpen = document.querySelector('[data-nav-collapsed-open], [data-nav-expand-trigger]');
    const cartDrawer = document.querySelector('[data-cart-drawer]');
    const cartItemsNode = document.querySelector('[data-cart-items]');
    const cartEmptyNode = document.querySelector('[data-cart-empty]');
    const cartTotalNode = document.querySelector('[data-cart-total]');
    const cartSelectedTotalNode = document.querySelector('[data-cart-selected-total]');
    const cartCountNodes = document.querySelectorAll('[data-cart-count]');
    const cartSelectedCountNode = document.querySelector('[data-cart-selected-count]');
    const cartSelectAll = document.querySelector('[data-cart-select-all]');
    const processOrderButton = document.querySelector('[data-process-order]');
    const brandDiscounts = (() => {
        try {
            const parsed = JSON.parse(document.body?.dataset.brandDiscounts || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
                ? parsed
                : {};
        } catch (_) {
            return {};
        }
    })();

    const productModal = document.querySelector('[data-product-modal]');
    const cartItemModal = document.querySelector('[data-cart-item-modal]');
    const quantityModal = document.querySelector('[data-quantity-modal]');
    const cartMessage = document.querySelector('[data-cart-message]');
    const cartMessageText = document.querySelector('[data-cart-message-text]');
    const orderSuccess = document.querySelector('[data-order-success]');

    let lastScrollY = window.scrollY;
    let messageTimer = null;
    let orderSuccessTimer = null;
    let pendingQuantityProduct = null;

    /* =====================================================
       W68 v64 — iPad / iOS reliable touch + visual viewport
       ===================================================== */
    const isAppleTouchDevice = /iPad|iPhone|iPod/i.test(navigator.userAgent || '')
        || (navigator.platform === 'MacIntel' && Number(navigator.maxTouchPoints || 0) > 1);

    if (isAppleTouchDevice) {
        document.documentElement.classList.add('w68-apple-touch');
    }

    const syncVisualViewport = () => {
        const viewport = window.visualViewport;
        const width = Math.max(320, Math.round(viewport?.width || window.innerWidth || 0));
        const height = Math.max(320, Math.round(viewport?.height || window.innerHeight || 0));
        const top = Math.max(0, Math.round(viewport?.offsetTop || 0));
        const left = Math.max(0, Math.round(viewport?.offsetLeft || 0));

        const pageX = Number(window.scrollX || window.pageXOffset || 0);
        const pageY = Number(window.scrollY || window.pageYOffset || 0);
        const loaderCenterX = Math.round(pageX + left + (width / 2));
        const loaderCenterY = Math.round(pageY + top + (height / 2));

        document.documentElement.style.setProperty('--w68-vv-width', `${width}px`);
        document.documentElement.style.setProperty('--w68-vv-height', `${height}px`);
        document.documentElement.style.setProperty('--w68-vv-top', `${top}px`);
        document.documentElement.style.setProperty('--w68-vv-left', `${left}px`);
        document.documentElement.style.setProperty('--w68-loader-center-x', `${loaderCenterX}px`);
        document.documentElement.style.setProperty('--w68-loader-center-y', `${loaderCenterY}px`);
    };

    syncVisualViewport();
    window.addEventListener('resize', syncVisualViewport, { passive: true });
    window.addEventListener('scroll', syncVisualViewport, { passive: true });
    window.addEventListener('orientationchange', () => window.setTimeout(syncVisualViewport, 80), { passive: true });
    window.visualViewport?.addEventListener('resize', syncVisualViewport, { passive: true });
    window.visualViewport?.addEventListener('scroll', syncVisualViewport, { passive: true });

    /*
     * iPad Safari sometimes drops the delayed click after a touch while the
     * sticky navbar is changing size.  Bridge only short, stationary taps.
     * The bridge fires a normal programmatic click so all existing desktop
     * behavior remains the single source of truth.
     */
    if (isAppleTouchDevice) {
        let touchStart = null;
        let bridgeLockedUntil = 0;

        const tappableSelector = [
            '[data-cart-open]',
            '[data-add-cart]',
            '[data-view-cart]',
            '[data-product-modal-add]',
            '[data-product-modal-close]',
            '[data-cart-item-modal-close]',
            '[data-quantity-modal-close]',
            '[data-quantity-confirm]',
            '[data-quantity-minus]',
            '[data-quantity-plus]',
            '[data-process-order]',
            '[data-process-order-close]',
            '[data-process-proceed]'
        ].join(',');

        document.addEventListener('touchstart', (event) => {
            if (event.touches.length !== 1) {
                touchStart = null;
                return;
            }

            const touch = event.touches[0];
            touchStart = {
                x: touch.clientX,
                y: touch.clientY,
                time: Date.now(),
                target: event.target,
            };
        }, { passive: true, capture: true });

        document.addEventListener('touchend', (event) => {
            if (!touchStart || event.changedTouches.length !== 1) {
                touchStart = null;
                return;
            }

            const touch = event.changedTouches[0];
            const moved = Math.hypot(touch.clientX - touchStart.x, touch.clientY - touchStart.y);
            const elapsed = Date.now() - touchStart.time;
            const fallbackTarget = touchStart.target;
            touchStart = null;

            if (moved > 14 || elapsed > 700) return;

            const target = document.elementFromPoint(touch.clientX, touch.clientY) || fallbackTarget;
            if (!(target instanceof Element)) return;

            const control = target.closest(tappableSelector);
            const cartRow = target.closest('[data-cart-row-id]');
            const productCard = target.closest('[data-product-card]');

            let clickTarget = control;
            if (!clickTarget && cartRow && !target.closest('button, input, select, a, label')) {
                clickTarget = cartRow;
            }
            if (!clickTarget && productCard && !target.closest('button, input, select, a, label')) {
                clickTarget = productCard;
            }
            if (!clickTarget) return;

            event.preventDefault();
            event.stopPropagation();

            if (Date.now() < bridgeLockedUntil) return;
            bridgeLockedUntil = Date.now() + 420;

            window.setTimeout(() => {
                syncVisualViewport();
                clickTarget.click();
            }, 0);
        }, { passive: false, capture: true });
    }

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
        syncVisualViewport();
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
        link.addEventListener('click', () => {
            const target = new URL(link.href, window.location.href);
            const current = new URL(window.location.href);

            if (target.pathname === current.pathname && target.search === current.search) {
                hideLoader();
                return;
            }

            showLoader();

            window.setTimeout(() => {
                if (document.visibilityState === 'visible') {
                    hideLoader();
                }
            }, 8000);
        });
    });

    /* =====================================================
       Utilities
       ===================================================== */
    const money = (value) => new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

    const percent = (value) => new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

    const normalizeBrand = (brand) => String(brand || '')
        .trim()
        .replace(/\s+/g, ' ')
        .toLowerCase();

    const discountForBrand = (brand) => {
        const normalized = normalizeBrand(brand);

        if (!normalized || !Object.prototype.hasOwnProperty.call(brandDiscounts, normalized)) {
            return 0;
        }

        const discount = Number(brandDiscounts[normalized] || 0);

        return Number.isFinite(discount) ? Math.max(0, Math.min(discount, 100)) : 0;
    };

    const discountedPrice = (price, discountPercent) => {
        const value = Number(price || 0);
        const discount = Number(discountPercent || 0);

        if (!Number.isFinite(value) || value <= 0 || discount <= 0) {
            return Number.isFinite(value) ? value : 0;
        }

        return Math.round((value - (value * (discount / 100))) * 100) / 100;
    };

    const safeQty = (value) => {
        const qty = Math.floor(Number(value || 1));
        return Number.isFinite(qty) ? Math.max(1, Math.min(qty, 9999)) : 1;
    };

    const readCart = () => {
        try {
            const value = JSON.parse(localStorage.getItem(storageKey) || '[]');
            if (!Array.isArray(value)) return [];

            const hasBrandDiscountMap = Object.keys(brandDiscounts).length > 0;

            return value.map((item) => {
                const price = Number(item.price || 0);
                const mappedDiscount = discountForBrand(item.brand);
                const storedDiscount = Number(item.discountPercent || 0);

                // Current customer-brand discounts remain authoritative. If the
                // discount map cannot be read for any reason, keep the valid
                // discount already supplied by the server/product card instead
                // of making the discounted price disappear from the cart.
                const discountPercent = mappedDiscount > 0
                    ? mappedDiscount
                    : (!hasBrandDiscountMap && Number.isFinite(storedDiscount)
                        ? Math.max(0, Math.min(storedDiscount, 100))
                        : 0);

                const calculatedDiscountedPrice = discountedPrice(price, discountPercent);
                const storedDiscountedPrice = Number(item.discountedPrice ?? calculatedDiscountedPrice);
                const finalDiscountedPrice = discountPercent > 0
                    ? calculatedDiscountedPrice
                    : (
                        Number.isFinite(storedDiscountedPrice)
                        && storedDiscountedPrice > 0
                        && storedDiscountedPrice < price
                            ? storedDiscountedPrice
                            : price
                    );

                return {
                    ...item,
                    qty: safeQty(item.qty),
                    selected: Boolean(item.selected),
                    price,
                    discountPercent,
                    discountedPrice: finalDiscountedPrice,
                };
            });
        } catch (_) {
            return [];
        }
    };

    const writeCart = (cart) => {
        localStorage.setItem(storageKey, JSON.stringify(cart));
    };

    // Do not erase a browser cart just because the database returned an empty
    // cart. That can happen when the user logs out immediately after adding an
    // item and the previous request is still in flight. In that case keep the
    // account-scoped local cart and push it back to core4_sales_order below.
    let needsInitialCartSync = false;

    if (cartStorageReady) {
        const localCartBeforeServer = readCart();

        if (serverCart.length > 0) {
            writeCart(serverCart);
        } else if (localCartBeforeServer.length > 0) {
            needsInitialCartSync = true;
        } else {
            writeCart([]);
        }
    }

    const cartRequest = async (url, method, payload = null) => {
        if (!cartStorageReady || !url) return null;

        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload === null ? null : JSON.stringify(payload),
        });

        if (!response.ok) {
            let message = `Unable to save cart (${response.status}).`;

            try {
                const error = await response.json();
                message = error?.message || message;
            } catch (_) {
                // Keep the HTTP status message.
            }

            throw new Error(message);
        }

        if (response.status === 204) return null;
        return response.json().catch(() => null);
    };

    const queueCartMutation = (mutation) => {
        if (!cartStorageReady) return Promise.resolve(null);

        const next = cartMutationQueue
            .catch(() => null)
            .then(mutation)
            .catch((error) => {
                console.error('W68 cart database save failed:', error);
                return null;
            });

        cartMutationQueue = next;
        return next;
    };

    const cartItemUrl = (productId) => `${cartApi.itemBase}/${encodeURIComponent(String(productId))}`;

    const cartSyncPayload = () => ({
        items: readCart().map((item) => ({
            product_id: Number(item.id),
            cart_id: Number(item.cartId || 0) > 0 ? Number(item.cartId) : null,
            quantity: safeQty(item.qty),
            selected: Boolean(item.selected),
        })).filter((item) => Number.isFinite(item.product_id) && item.product_id > 0),
    });

    const syncCurrentCartToServer = async () => {
        if (!cartStorageReady || !cartApi.sync) {
            return null;
        }

        const response = await cartRequest(cartApi.sync, 'PUT', cartSyncPayload());
        if (response && Array.isArray(response.items)) {
            // Server is authoritative. This also removes any browser item whose
            // cart primary key is already linked to a processed W68 order.
            writeCart(response.items);
            renderCart();
            syncProductCardStates();
        }
        return response;
    };

    let cartHydrationPromise = Promise.resolve(null);

    const hydrateCartFromServer = async () => {
        if (!cartStorageReady || !cartApi.state) {
            return null;
        }

        try {
            const state = await cartRequest(cartApi.state, 'GET');
            const items = Array.isArray(state?.items) ? state.items : [];
            const localItems = readCart();

            if (items.length > 0) {
                // The database is the source of truth after a fresh login.
                writeCart(items);
            } else if (localItems.length > 0) {
                // Preserve a browser cart only when the account's database cart
                // is truly empty, then immediately restore it to the database.
                await syncCurrentCartToServer();
            }

            return items;
        } catch (error) {
            console.error('W68 cart restore failed:', error);
            return null;
        }
    };

    if (needsInitialCartSync) {
        queueCartMutation(syncCurrentCartToServer);
    }

    cartHydrationPromise = hydrateCartFromServer();

    /*
     * A normal item add/update is still saved immediately. Before LOGOUT we
     * additionally flush the complete visible cart and wait for the database
     * response. This guarantees that logging out right after an Add To Cart
     * click cannot make that item disappear on the next login.
     */
    document.querySelectorAll('form.nav-logout-form').forEach((form) => {
        let logoutApproved = false;

        form.addEventListener('submit', async (event) => {
            if (logoutApproved || !cartStorageReady || !cartApi.sync) {
                return;
            }

            event.preventDefault();
            showLoader();

            try {
                await cartHydrationPromise.catch(() => null);
                await cartMutationQueue.catch(() => null);
                await syncCurrentCartToServer();
            } catch (error) {
                console.error('W68 final cart sync before logout failed:', error);
                // The account-scoped localStorage copy remains intact and will
                // be recovered/synced after the same account logs in again.
            } finally {
                logoutApproved = true;
                form.submit();
            }
        }, true);
    });

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
        discountPercent: Number(card?.dataset.productDiscountPercent || 0),
        discountedPrice: Number(card?.dataset.productDiscountedPrice ?? card?.dataset.productPrice ?? 0),
    });

    const hasDiscount = (item) => {
        const price = Number(item?.price || 0);
        const discounted = Number(item?.discountedPrice || 0);
        const percentOff = Number(item?.discountPercent || 0);

        return percentOff > 0
            || (price > 0 && discounted > 0 && discounted < price);
    };

    const effectivePrice = (item) => {
        const price = Number(item?.price || 0);
        const discounted = Number(item?.discountedPrice || 0);

        return hasDiscount(item) && discounted > 0
            ? discounted
            : price;
    };

    const modalIsOpen = (modal) => modal && modal.hidden === false;

    const syncBodyLock = () => {
        const locked = cartDrawer?.classList.contains('open')
            || modalIsOpen(productModal)
            || modalIsOpen(cartItemModal)
            || modalIsOpen(quantityModal);

        document.body.style.overflow = locked ? 'hidden' : '';
    };

    /* =====================================================
       Folded navigation
       - scrolling down folds
       - scrolling upward does not unfold
       - reaching the very top unfolds
       - clicking folded navbar unfolds it
       ===================================================== */
    const collapseNav = () => {
        if (!nav) return;

        nav.classList.add('is-collapsed');
        nav.querySelector('[data-nav-collapsed-summary]')?.setAttribute('aria-hidden', 'false');
        closeAllRecommendations();
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
        } else if (
            currentY > 95
            && movingDown
            && !nav.classList.contains('is-collapsed')
        ) {
            // Always fold again when the user continues scrolling down.
            // Do not keep the navbar permanently expanded just because a
            // search input still has focus from a previous interaction.
            collapseNav();
            document.activeElement?.matches?.('[data-recommend-input]')
                && document.activeElement.blur();
        }

        lastScrollY = currentY;
    };

    window.addEventListener('scroll', updateNavOnScroll, { passive: true });

    const openCollapsedNavigation = (event = null) => {
        if (!nav?.classList.contains('is-collapsed')) return;

        if (event?.target?.closest?.('[data-cart-open], [data-orders-link]')) {
            return;
        }

        expandNav();
        closeAllRecommendations();
        lastScrollY = window.scrollY;

        // Important: do NOT auto-focus the first search field here.
        // Auto-focus was the reason the expanded navbar could remain open
        // after the user started scrolling down again.
        collapsedNavOpen?.blur?.();
    };

    /*
     * v69: do not expand on pointerdown. Expanding a sticky header before
     * click is dispatched can move the button away from the pointer and make
     * the browser click an underlying navigation link, which looks like a
     * page reload. Handle the real summary button only.
     */
    let collapsedNavTouchAt = 0;

    collapsedNavOpen?.addEventListener('touchend', (event) => {
        if (!nav?.classList.contains('is-collapsed')) return;

        event.preventDefault();
        event.stopPropagation();
        collapsedNavTouchAt = Date.now();
        openCollapsedNavigation(event);
    }, { passive: false });

    collapsedNavOpen?.addEventListener('click', (event) => {
        if (!nav?.classList.contains('is-collapsed')) return;

        event.preventDefault();
        event.stopPropagation();

        if (Date.now() - collapsedNavTouchAt < 650) {
            return;
        }

        openCollapsedNavigation(event);
    });

    collapsedNavOpen?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        if (event.target.closest('[data-cart-open], [data-orders-link]')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        openCollapsedNavigation(event);
    });

    updateNavOnScroll();


    /* =====================================================
       Searchable dropdown recommendations
       - 1+ typed character opens a dropdown
       - click/tap suggestion fills only that search field
       - arrow keys + Enter supported
       - Product Code / Part Number use exact product image
       - Brand / Application / Position / Description use
         random matching selected-product representative image
       ===================================================== */
    const recommendationUrl =
        document.body.dataset.searchSuggestionUrl
        || document.body.dataset.recommendUrl
        || '';

    if (!recommendationUrl) {
        console.error(
            'W68 search recommendations are disabled because the suggestion URL is missing.'
        );
    }

    const closeAllRecommendations = (except = null) => {
        document.querySelectorAll('[data-recommend-list]').forEach((list) => {
            if (list === except) return;

            list.hidden = true;
            list.innerHTML = '';

            const input = list.closest('.recommendation-host')?.querySelector('[data-recommend-input]');
            input?.setAttribute('aria-expanded', 'false');
            input?.removeAttribute('aria-activedescendant');
        });
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const suggestionSubtitle = (item, field) => {
        const parts = [];

        if (field !== 'product_code' && item.product_code) {
            parts.push(item.product_code);
        }

        if (field !== 'part_number' && item.part_number) {
            parts.push(item.part_number);
        }

        if (field !== 'brand' && item.brand) {
            parts.push(item.brand);
        }

        if (field !== 'application' && item.application) {
            parts.push(item.application);
        }

        return parts.filter(Boolean).slice(0, 3).join(' \u2022 ');
    };

    const renderRecommendations = (input, list, items) => {
        list.innerHTML = '';

        if (!Array.isArray(items) || !items.length) {
            list.innerHTML = `
                <div class="search-recommendation-empty">
                    No matching recommendation found.
                </div>
            `;
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            return;
        }

        items.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'search-recommendation-item';
            button.setAttribute('role', 'option');
            button.id = `w68-recommend-${input.dataset.recommendField}-${index}`;
            button.dataset.recommendValue = item.value || '';

            const image = document.createElement('img');
            image.className = 'search-recommendation-image';
            image.src = item.image || '';
            image.alt = item.description || item.value || 'W68 product';
            image.loading = 'lazy';

            const copy = document.createElement('span');
            copy.className = 'search-recommendation-copy';

            const primary = document.createElement('strong');
            primary.textContent = item.value || '';

            const secondary = document.createElement('small');
            secondary.textContent = suggestionSubtitle(
                item,
                input.dataset.recommendField || ''
            ) || item.description || 'Matching W68 product';

            copy.append(primary, secondary);

            const price = document.createElement('b');
            price.className = 'search-recommendation-price';
            price.textContent = item.price
                ? Number(item.price).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                })
                : '';

            button.append(image, copy, price);

            button.addEventListener('mousedown', (event) => {
                // Keep input focus from closing dropdown before click runs.
                event.preventDefault();
            });

            button.addEventListener('click', () => {
                input.value = item.value || '';
                list.hidden = true;
                list.innerHTML = '';
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
                input.focus({ preventScroll: true });
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            list.append(button);
        });

        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const showRecommendationLoading = (input, list) => {
        list.innerHTML = `
            <div class="search-recommendation-loading">
                <span></span>
                <b>Searching W68 products...</b>
            </div>
        `;
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const fetchRecommendations = async (input, list) => {
        const term = input.value.trim();
        const field = input.dataset.recommendField || '';

        if (!recommendationUrl || term.length < 1 || !field) {
            list.hidden = true;
            list.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
            return;
        }

        const requestId = String(Date.now()) + Math.random();
        input.dataset.recommendRequest = requestId;

        showRecommendationLoading(input, list);

        try {
            const url = new URL(recommendationUrl, window.location.origin);
            url.searchParams.set('q', term);
            url.searchParams.set('field', field);

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Suggestion request failed: ${response.status}`);
            }

            const payload = await response.json();

            if (input.dataset.recommendRequest !== requestId) {
                return;
            }

            closeAllRecommendations(list);
            renderRecommendations(input, list, payload.items || []);
        } catch (error) {
            console.error('W68 search recommendation error:', error);

            if (input.dataset.recommendRequest !== requestId) {
                return;
            }

            list.innerHTML = `
                <div class="search-recommendation-empty">
                    Unable to load recommendations.
                </div>
            `;
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
    };

    document.querySelectorAll('[data-recommend-input]').forEach((input) => {
        const host = input.closest('.recommendation-host');
        const list = host?.querySelector('[data-recommend-list]');

        if (!list) return;

        let timer = null;
        let activeIndex = -1;

        const recommendationButtons = () =>
            Array.from(list.querySelectorAll('.search-recommendation-item'));

        const setActive = (index) => {
            const buttons = recommendationButtons();
            if (!buttons.length) return;

            activeIndex = Math.max(0, Math.min(index, buttons.length - 1));

            buttons.forEach((button, buttonIndex) => {
                button.classList.toggle('is-active', buttonIndex === activeIndex);
            });

            const activeButton = buttons[activeIndex];

            if (activeButton) {
                input.setAttribute('aria-activedescendant', activeButton.id);
                activeButton.scrollIntoView({ block: 'nearest' });
            }
        };

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 1) {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => {
                    fetchRecommendations(input, list);
                }, 100);
            }
        });

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            activeIndex = -1;

            const term = input.value.trim();

            if (term.length < 1) {
                list.hidden = true;
                list.innerHTML = '';
                input.setAttribute('aria-expanded', 'false');
                return;
            }

            timer = window.setTimeout(() => {
                fetchRecommendations(input, list);
            }, 180);
        });

        input.addEventListener('keydown', (event) => {
            const buttons = recommendationButtons();

            if (event.key === 'ArrowDown') {
                event.preventDefault();

                if (list.hidden) {
                    fetchRecommendations(input, list);
                    return;
                }

                setActive(activeIndex + 1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(activeIndex <= 0 ? buttons.length - 1 : activeIndex - 1);
                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0 && buttons[activeIndex]) {
                event.preventDefault();
                buttons[activeIndex].click();
                return;
            }

            if (event.key === 'Escape') {
                list.hidden = true;
                list.innerHTML = '';
                input.setAttribute('aria-expanded', 'false');
                activeIndex = -1;
            }
        });

        input.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (!list.matches(':hover')) {
                    list.hidden = true;
                    input.setAttribute('aria-expanded', 'false');
                }
            }, 140);
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

    const showOrderSuccess = (message = '') => {
        if (!orderSuccess) return;

        const messageNode = orderSuccess.querySelector('[data-order-success-text]');
        if (messageNode && message) {
            messageNode.textContent = message;
        }

        orderSuccess.hidden = false;
        orderSuccess.setAttribute('aria-hidden', 'false');

        window.clearTimeout(orderSuccessTimer);
        orderSuccessTimer = window.setTimeout(() => {
            orderSuccess.hidden = true;
            orderSuccess.setAttribute('aria-hidden', 'true');
        }, 3200);
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

        const quantityToAdd = safeQty(qty);
        const cart = readCart();
        const existing = cart.find((item) => String(item.id) === String(product.id));

        if (existing) {
            existing.qty = safeQty(Number(existing.qty || 0) + quantityToAdd);
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
                discountPercent: Number(product.discountPercent || existing.discountPercent || 0),
                discountedPrice: Number(product.discountedPrice ?? existing.discountedPrice ?? product.price ?? existing.price ?? 0),
            });
        } else {
            cart.push({
                ...product,
                qty: quantityToAdd,
                selected: false,
            });
        }

        saveCart(cart);
        queueCartMutation(() => cartRequest(cartApi.add, 'POST', {
            product_id: Number(product.id),
            quantity: quantityToAdd,
        })).then((response) => {
            const cartId = Number(response?.cart_id || 0);
            if (!Number.isFinite(cartId) || cartId <= 0) return;

            const latestCart = readCart();
            const latestItem = latestCart.find((item) => String(item.id) === String(product.id));
            if (!latestItem) return;

            latestItem.cartId = cartId;
            if (Number.isFinite(Number(response?.quantity))) {
                latestItem.qty = safeQty(response.quantity);
            }
            saveCart(latestCart);
        });
        showCartMessage(product.description || product.productCode || 'Item');
    };

    const setCartQty = (id, value) => {
        const cart = readCart();
        const item = cart.find((entry) => String(entry.id) === String(id));
        if (!item) return;

        item.qty = safeQty(value);
        saveCart(cart);

        queueCartMutation(() => cartRequest(cartItemUrl(id), 'PATCH', {
            quantity: item.qty,
        }));
    };

    const setCartSelected = (id, selected) => {
        const cart = readCart();
        const item = cart.find((entry) => String(entry.id) === String(id));
        if (!item) return;

        item.selected = Boolean(selected);
        saveCart(cart);

        queueCartMutation(() => cartRequest(cartItemUrl(id), 'PATCH', {
            selected: item.selected,
        }));
    };

    const setAllCartSelected = (selected) => {
        const cart = readCart().map((item) => ({
            ...item,
            selected: Boolean(selected),
        }));
        saveCart(cart);

        queueCartMutation(() => cartRequest(cartApi.selection, 'PUT', {
            selected: Boolean(selected),
        }));
    };

    const removeCartItem = (id) => {
        saveCart(readCart().filter((item) => String(item.id) !== String(id)));
        queueCartMutation(() => cartRequest(cartItemUrl(id), 'DELETE'));
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
    const updateQuantityModalTotal = (normalize = false) => {
        if (!quantityModal || !pendingQuantityProduct) return;

        const input = quantityModal.querySelector('[data-quantity-input]');
        const total = quantityModal.querySelector('[data-quantity-modal-total]');

        if (!input) return;

        /*
         * IMPORTANT:
         * While the user is typing, allow the field to be temporarily blank.
         * The old code immediately changed an empty value back to "1",
         * which made it impossible to delete the default 1 and type a new qty.
         */
        const raw = String(input.value ?? '').trim();

        if (raw === '') {
            if (normalize) {
                input.value = '1';

                if (total) {
                    total.textContent = money(
                        effectivePrice(pendingQuantityProduct)
                    );
                }
            } else if (total) {
                total.textContent = money(0);
            }

            return;
        }

        const numeric = Math.floor(Number(raw));

        if (!Number.isFinite(numeric) || numeric < 1) {
            if (normalize) {
                input.value = '1';

                if (total) {
                    total.textContent = money(
                        effectivePrice(pendingQuantityProduct)
                    );
                }
            } else if (total) {
                total.textContent = money(0);
            }

            return;
        }

        const qty = Math.min(numeric, 9999);

        if (normalize) {
            input.value = String(qty);
        }

        if (total) {
            total.textContent = money(
                effectivePrice(pendingQuantityProduct) * qty
            );
        }
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
        if (price) {
            price.textContent = hasDiscount(product)
                ? `DISCOUNTED PRICE (${percent(product.discountPercent)}%) ${money(effectivePrice(product))}`
                : money(product.price);
        }
        if (input) input.value = '1';

        updateQuantityModalTotal();
        quantityModal.hidden = false;
        quantityModal.setAttribute('aria-hidden', 'false');
        syncBodyLock();

        if (!isAppleTouchDevice) {
            window.setTimeout(() => input?.focus(), 60);
        } else {
            syncVisualViewport();
        }
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

        const current = input.value.trim() === ''
            ? 1
            : safeQty(input.value);

        input.value = String(Math.max(1, current - 1));
        updateQuantityModalTotal(true);
    });

    quantityModal?.querySelector('[data-quantity-plus]')?.addEventListener('click', () => {
        const input = quantityModal.querySelector('[data-quantity-input]');
        if (!input) return;

        const current = input.value.trim() === ''
            ? 0
            : safeQty(input.value);

        input.value = String(Math.min(9999, current + 1));
        updateQuantityModalTotal(true);
    });

    const quantityInput = quantityModal?.querySelector('[data-quantity-input]');

    /*
     * INPUT: do NOT force "1" back while typing.
     * CHANGE / BLUR: normalize invalid or blank values to minimum 1.
     */
    quantityInput?.addEventListener('input', () => {
        updateQuantityModalTotal(false);
    });

    quantityInput?.addEventListener('change', () => {
        updateQuantityModalTotal(true);
    });

    quantityInput?.addEventListener('blur', () => {
        updateQuantityModalTotal(true);
    });

    quantityInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            updateQuantityModalTotal(true);
            quantityModal
                ?.querySelector('[data-quantity-confirm]')
                ?.click();
        }
    });

    quantityModal?.querySelector('[data-quantity-confirm]')?.addEventListener('click', () => {
        if (!pendingQuantityProduct) return;

        const product = pendingQuantityProduct;
        const input = quantityModal.querySelector('[data-quantity-input]');

        updateQuantityModalTotal(true);

        const qty = safeQty(input?.value || 1);

        closeQuantityModal();
        commitAddToCart(product, qty);
    });

    /* =====================================================
       Product full-view modal
       ===================================================== */
    const openProductModal = (product) => {
        if (!productModal) return;

        const fields = {
            '[data-product-modal-description]': product.description || '\u2014',
            '[data-product-modal-code]': product.productCode || '\u2014',
            '[data-product-modal-part]': product.partNumber || '\u2014',
            '[data-product-modal-application]': product.application || '\u2014',
            '[data-product-modal-brand]': product.brand || '\u2014',
            '[data-product-modal-position]': product.position || '\u2014',
            '[data-product-modal-specification]': product.specification || '\u2014',
        };

        Object.entries(fields).forEach(([selector, value]) => {
            const node = productModal.querySelector(selector);
            if (node) node.textContent = value;
        });

        const productPrice = productModal.querySelector('[data-product-modal-price]');
        const productDiscountRow = productModal.querySelector('[data-product-modal-discount-row]');
        const productDiscountLabel = productModal.querySelector('[data-product-modal-discount-label]');
        const productDiscountPrice = productModal.querySelector('[data-product-modal-discounted-price]');
        const productHasDiscount = hasDiscount(product);

        if (productPrice) {
            productPrice.textContent = money(product.price);
            productPrice.classList.toggle('is-discounted', productHasDiscount);
        }

        if (productDiscountRow) {
            productDiscountRow.hidden = !productHasDiscount;
        }

        if (productDiscountLabel) {
            productDiscountLabel.textContent = productHasDiscount
                ? `DISCOUNTED PRICE (${percent(product.discountPercent)}%)`
                : 'DISCOUNTED PRICE';
        }

        if (productDiscountPrice) {
            productDiscountPrice.textContent = money(effectivePrice(product));
        }

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
            '[data-cart-item-modal-description]': item.description || '\u2014',
            '[data-cart-item-modal-code]': item.productCode || '\u2014',
            '[data-cart-item-modal-part]': item.partNumber || '\u2014',
            '[data-cart-item-modal-application]': item.application || '\u2014',
            '[data-cart-item-modal-position]': item.position || '\u2014',
            '[data-cart-item-modal-specification]': item.specification || '\u2014',
            '[data-cart-item-modal-brand]': item.brand || '\u2014',
            '[data-cart-item-modal-price]': money(item.price),
            '[data-cart-item-modal-qty]': String(safeQty(item.qty)),
            '[data-cart-item-modal-total]': money(effectivePrice(item) * safeQty(item.qty)),
        };

        Object.entries(fields).forEach(([selector, value]) => {
            const node = cartItemModal.querySelector(selector);
            if (node) node.textContent = value;
        });

        const originalPrice = cartItemModal.querySelector('[data-cart-item-modal-price]');
        const discountRow = cartItemModal.querySelector('[data-cart-item-modal-discount-row]');
        const discountLabel = cartItemModal.querySelector('[data-cart-item-modal-discount-label]');
        const discountPrice = cartItemModal.querySelector('[data-cart-item-modal-discounted-price]');
        const itemHasDiscount = hasDiscount(item);

        if (originalPrice) {
            originalPrice.classList.toggle('is-discounted', itemHasDiscount);
        }

        if (discountRow) {
            discountRow.hidden = !itemHasDiscount;
        }

        if (discountLabel) {
            discountLabel.textContent = itemHasDiscount
                ? `DISCOUNTED PRICE (${percent(item.discountPercent)}%)`
                : 'DISCOUNTED PRICE';
        }

        if (discountPrice) {
            discountPrice.textContent = money(effectivePrice(item));
        }

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
        /*
         * Cart badge counts PRODUCT LINES / DISTINCT ITEMS,
         * not the sum of ordered quantities.
         *
         * Example:
         * 1 product with Qty 20 = cart badge 1.
         */
        const cartItemCount = cart.length;

        const grandTotal = cart.reduce(
            (sum, item) => sum + effectivePrice(item) * safeQty(item.qty),
            0
        );
        const selectedTotal = selected.reduce(
            (sum, item) => sum + effectivePrice(item) * safeQty(item.qty),
            0
        );

        cartCountNodes.forEach((node) => {
            node.textContent = String(cartItemCount);
        });
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

            const attributes = document.createElement('div');
            attributes.className = 'cart-item-attributes';

            [
                ['PART NUMBER', item.partNumber],
                ['APPLICATION', item.application],
                ['POSITION', item.position],
                ['BRAND', item.brand],
            ].forEach(([label, value]) => {
                const attribute = document.createElement('div');
                const labelNode = document.createElement('span');
                const valueNode = document.createElement('strong');

                labelNode.textContent = label;
                valueNode.textContent = value || '\u2014';
                attribute.append(labelNode, valueNode);
                attributes.append(attribute);
            });

            const priceLines = document.createElement('div');
            priceLines.className = 'cart-price-lines';

            const unitLabel = document.createElement('span');
            unitLabel.textContent = 'PRICE';
            const unitValue = document.createElement('strong');
            unitValue.className = hasDiscount(item) ? 'original-price is-discounted' : 'original-price';
            unitValue.textContent = money(item.price);
            const discountLabel = document.createElement('span');
            discountLabel.textContent = `DISCOUNTED PRICE (${percent(item.discountPercent)}%)`;
            const discountValue = document.createElement('strong');
            discountValue.className = 'discount-price';
            discountValue.textContent = money(effectivePrice(item));
            const totalLabel = document.createElement('span');
            totalLabel.textContent = 'TOTAL';
            const totalValue = document.createElement('strong');
            totalValue.className = 'cart-discounted-total';
            // Keep the label as TOTAL, but always calculate it from the
            // customer's effective (discounted when applicable) unit price.
            totalValue.textContent = money(effectivePrice(item) * safeQty(item.qty));

            priceLines.append(unitLabel, unitValue);

            if (hasDiscount(item)) {
                priceLines.append(discountLabel, discountValue);
            }

            priceLines.append(totalLabel, totalValue);

            const qtyRow = document.createElement('div');
            qtyRow.className = 'cart-qty-row';

            const minus = document.createElement('button');
            minus.type = 'button';
            minus.textContent = '\u2212';
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
            copy.append(code, description, attributes, priceLines, qtyRow);
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
       Process Order review page
       ===================================================== */
    const openProcessOrderReview = async () => {
        const selected = readCart().filter((item) => item.selected);
        if (!selected.length || !orderApi.review) return;

        processOrderButton.disabled = true;

        try {
            // Quantity/selection writes are queued. Finish them first so the
            // review page reads the exact same selected database cart rows.
            await cartMutationQueue.catch(() => null);
            window.location.href = orderApi.review;
        } finally {
            window.setTimeout(() => {
                const hasSelection = readCart().some((item) => item.selected);
                if (processOrderButton) processOrderButton.disabled = !hasSelection;
            }, 900);
        }
    };

    processOrderButton?.addEventListener('click', openProcessOrderReview);

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

        // Orders is a normal link. Do not open the cart when it is clicked.
        if (event.target.closest('[data-orders-link]')) {
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

        const modalAdd = event.target.closest('[data-product-modal-add]');
        if (modalAdd?._w68Product) {
            const product = modalAdd._w68Product;
            closeProductModal();
            openQuantityModal(product);
        }
    });

    // Side-cart behavior: touching/clicking anywhere outside the cart panel
    // closes the drawer. The opener itself is excluded so iPad Safari does not
    // immediately close the drawer on the same tap that opened it.
    const closeCartFromOutside = (event) => {
        if (!cartDrawer?.classList.contains('open')) return;
        const target = event.target;
        if (target?.closest?.('.cart-panel')) return;
        if (target?.closest?.('[data-cart-open]')) return;
        closeCart();
    };

    cartDrawer?.addEventListener('touchend', closeCartFromOutside, { passive: true });
    cartDrawer?.addEventListener('click', closeCartFromOutside);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

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

    /* =====================================================
       New Items auto carousel
       - advances one product every 1.5 seconds
       - uses requestAnimationFrame for a smoother Safari/iPad motion
       - keeps manual swipe/scroll available and resumes afterward
       ===================================================== */
    const initNewItemsCarousel = () => {
        const track = document.querySelector('.new-items-track');
        if (!track) return;

        const cards = Array.prototype.slice.call(track.querySelectorAll('.new-item-card'));
        if (cards.length < 2) return;

        const INTERVAL_MS = 1500;
        const ANIMATION_MS = 780;
        let timer = null;
        let resumeTimer = null;
        let animationFrame = null;
        let currentIndex = 0;
        let isAnimating = false;

        const maxScrollLeft = () => Math.max(0, track.scrollWidth - track.clientWidth);

        const cardTargetLeft = (index) => {
            const first = cards[0];
            const card = cards[index];
            if (!first || !card) return 0;
            return Math.max(0, Math.min(card.offsetLeft - first.offsetLeft, maxScrollLeft()));
        };

        const easeInOutCubic = (progress) => {
            if (progress < 0.5) return 4 * progress * progress * progress;
            return 1 - Math.pow(-2 * progress + 2, 3) / 2;
        };

        const cancelAnimation = () => {
            if (animationFrame !== null) {
                window.cancelAnimationFrame(animationFrame);
                animationFrame = null;
            }
            isAnimating = false;
        };

        const animateScrollTo = (targetLeft, duration = ANIMATION_MS) => {
            cancelAnimation();

            const startLeft = track.scrollLeft;
            const distance = targetLeft - startLeft;
            if (Math.abs(distance) < 1) {
                track.scrollLeft = targetLeft;
                return;
            }

            const startTime = window.performance && typeof window.performance.now === 'function'
                ? window.performance.now()
                : Date.now();

            isAnimating = true;

            const step = (now) => {
                const elapsed = Math.max(0, now - startTime);
                const progress = Math.min(1, elapsed / duration);
                track.scrollLeft = startLeft + (distance * easeInOutCubic(progress));

                if (progress < 1) {
                    animationFrame = window.requestAnimationFrame(step);
                    return;
                }

                track.scrollLeft = targetLeft;
                animationFrame = null;
                isAnimating = false;
            };

            animationFrame = window.requestAnimationFrame(step);
        };

        const syncIndexFromScroll = () => {
            const current = track.scrollLeft;
            let nearestIndex = 0;
            let nearestDistance = Number.POSITIVE_INFINITY;

            cards.forEach((card, index) => {
                const distance = Math.abs(cardTargetLeft(index) - current);
                if (distance < nearestDistance) {
                    nearestDistance = distance;
                    nearestIndex = index;
                }
            });

            currentIndex = nearestIndex;
        };

        const advance = () => {
            if (document.hidden || maxScrollLeft() <= 2 || isAnimating) return;

            syncIndexFromScroll();

            const atEnd = track.scrollLeft >= maxScrollLeft() - 4;
            if (atEnd) {
                currentIndex = 0;
                animateScrollTo(0, 900);
                return;
            }

            currentIndex = Math.min(currentIndex + 1, cards.length - 1);
            animateScrollTo(cardTargetLeft(currentIndex));
        };

        const stop = () => {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        };

        const start = () => {
            stop();
            timer = window.setInterval(advance, INTERVAL_MS);
        };

        const pauseForInteraction = () => {
            stop();
            cancelAnimation();
            if (resumeTimer !== null) window.clearTimeout(resumeTimer);
            resumeTimer = window.setTimeout(() => {
                syncIndexFromScroll();
                start();
            }, 3000);
        };

        // Let the customer manually swipe/scroll without fighting autoplay.
        track.addEventListener('touchstart', pauseForInteraction, { passive: true });
        track.addEventListener('pointerdown', pauseForInteraction, { passive: true });
        track.addEventListener('wheel', pauseForInteraction, { passive: true });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stop();
                cancelAnimation();
            } else {
                syncIndexFromScroll();
                start();
            }
        });

        window.addEventListener('resize', () => {
            cancelAnimation();
            syncIndexFromScroll();
            track.scrollLeft = cardTargetLeft(currentIndex);
        }, { passive: true });

        start();
    };

    initNewItemsCarousel();

    renderCart();
    syncProductCardStates();
    updateNavOnScroll();

    cartHydrationPromise.finally(() => {
        renderCart();
        syncProductCardStates();
    });
})();
