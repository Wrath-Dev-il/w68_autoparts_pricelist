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
    const homeUrl = String(body?.dataset.homeUrl || window.location.href);
    const cartPrintBridgeUrl = String(body?.dataset.cartPrintBridgeUrl || '');
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
    // W68_CART_PRINT_LIST_20261012
    const cartPrintButton = document.querySelector('[data-cart-print-open]');
    const cartPrintModal = document.querySelector('[data-cart-print-modal]');
    const cartPrintSheet = document.querySelector('[data-cart-print-sheet]');
    const cartPrintAllButton = document.querySelector('[data-cart-print-all]');
    const cartPrintSelectedButton = document.querySelector('[data-cart-print-selected]');
    const cartPrintCloseButtons = Array.from(document.querySelectorAll('[data-cart-print-close]'));
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
    const appleUa = navigator.userAgent || '';
    const isAppleTouchDevice = /iPad|iPhone|iPod/i.test(appleUa)
        || (navigator.platform === 'MacIntel' && Number(navigator.maxTouchPoints || 0) > 1);
    const isIPadDevice = /iPad/i.test(appleUa)
        || (
            /Macintosh/i.test(appleUa)
            && Number(navigator.maxTouchPoints || 0) > 1
            && Math.min(window.screen?.width || window.innerWidth || 0, window.screen?.height || window.innerHeight || 0) >= 700
        );

    if (isAppleTouchDevice) {
        document.documentElement.classList.add('w68-apple-touch');
    }
    if (isIPadDevice) {
        document.documentElement.classList.add('w68-ipad');
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

    // W68_HOME_HIDE_ZERO_PRICE_ADD_TO_CART_20261003
    // ADD TO CART is allowed only when BOTH the regular and discounted price
    // are greater than zero. If either price is zero, hide/block the add flow.
    const canAddToCart = (item) => {
        const price = Number(item?.price || 0);
        const discounted = Number(item?.discountedPrice ?? price);

        return Number.isFinite(price)
            && Number.isFinite(discounted)
            && price > 0
            && discounted > 0;
    };

    const modalIsOpen = (modal) => modal && modal.hidden === false;

    const syncBodyLock = () => {
        const locked = cartDrawer?.classList.contains('open')
            || modalIsOpen(productModal)
            || modalIsOpen(cartItemModal)
            || modalIsOpen(quantityModal)
            || modalIsOpen(cartPrintModal);

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

        // W68_TUTORIAL_ADDED_MESSAGE_DURATION_20261027
        // Normal customers keep the 2-second confirmation. During the guided
        // tutorial, leave it visible long enough for the robot to explain it.
        const cartMessageDuration = document.body?.classList.contains('w68-tutorial-active')
            ? 3600
            : 2000;

        messageTimer = window.setTimeout(() => {
            cartMessage.hidden = true;
            cartMessage.setAttribute('aria-hidden', 'true');
        }, cartMessageDuration);
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
        if (!product?.id || !canAddToCart(product)) return;

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

    const isTutorialCartLocked = () => {
        const overlay = document.querySelector('[data-w68-tutorial]');
        return Boolean(overlay && !overlay.hidden && overlay.getAttribute('aria-hidden') !== 'true');
    };

    const closeCart = (force = false) => {
        if (!force && isTutorialCartLocked()) return;
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
        if (!quantityModal || !product?.id || !canAddToCart(product)) return;

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
        if (add) {
            const allowed = canAddToCart(product);
            add.hidden = !allowed;
            add.disabled = !allowed;
            add._w68Product = allowed ? product : null;
        }

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
        // W68_IPAD_PRINT_BUTTON_ALWAYS_TAPPABLE_20261015
        // Safari can suppress touch/click events on a form control that was
        // initially disabled. Keep PRINT CART LIST tappable and validate the
        // cart inside openCartPrintModal instead.
        if (cartPrintButton) cartPrintButton.disabled = false;
        if (cartPrintSelectedButton) cartPrintSelectedButton.disabled = selected.length === 0;

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
       W68_CART_PRINT_LIST_20261012
       - PRINT ALL uses every row currently in the cart.
       - PRINT SELECTED ONLY uses only checkbox-selected rows.
       - LESS is rendered only when at least one printed row has a discount.
       ===================================================== */
    const cartPrintLessPercent = (item) => {
        const stored = Number(item?.discountPercent || 0);
        if (Number.isFinite(stored) && stored > 0) return stored;

        const original = Number(item?.price || 0);
        const effective = Number(effectivePrice(item) || 0);
        if (original > 0 && effective >= 0 && effective < original) {
            return ((original - effective) / original) * 100;
        }

        return 0;
    };

    const closeCartPrintModal = () => {
        if (!cartPrintModal) return;
        cartPrintModal.hidden = true;
        cartPrintModal.setAttribute('aria-hidden', 'true');
        syncBodyLock();
    };

    const openCartPrintModal = () => {
        const cart = readCart();
        if (!cart.length || !cartPrintModal) {
            if (!cart.length) alert('Your cart is empty.');
            return;
        }

        if (cartPrintSelectedButton) {
            cartPrintSelectedButton.disabled = !cart.some((item) => item.selected);
        }

        cartPrintModal.hidden = false;
        cartPrintModal.setAttribute('aria-hidden', 'false');
        syncBodyLock();
    };

    const clearCartPrintSheet = () => {
        document.body.classList.remove('w68-cart-printing');
        if (!cartPrintSheet) return;
        cartPrintSheet.hidden = true;
        cartPrintSheet.setAttribute('aria-hidden', 'true');
        cartPrintSheet.innerHTML = '';
    };

    const buildCartPrintDocument = (printItems) => {
        const showLess = printItems.some((item) => hasDiscount(item));
        const total = printItems.reduce(
            (sum, item) => sum + (effectivePrice(item) * safeQty(item.qty)),
            0
        );

        const rows = printItems.map((item) => {
            const qty = safeQty(item.qty);
            const subtotal = effectivePrice(item) * qty;
            const less = cartPrintLessPercent(item);

            return `
                <tr>
                    <td>${escapeHtml(item.productCode || '—')}</td>
                    <td>${escapeHtml(item.partNumber || '—')}</td>
                    <td class="description">${escapeHtml(
                        [item.description, item.application, item.brand]
                            .map((value) => String(value || '').trim())
                            .filter(Boolean)
                            .join(' + ') || '—'
                    )}</td>
                    <td class="num">${qty}</td>
                    <td class="num">${money(item.price)}</td>
                    ${showLess ? `<td class="num">${less > 0 ? percent(less) + '%' : ''}</td>` : ''}
                    <td class="num">${money(subtotal)}</td>
                </tr>
            `;
        }).join('');

        const totalLabelColspan = showLess ? 6 : 5;

        return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>ERW Special Store - Cart List</title>
<style>
    @page { size: A4 portrait; margin: 10mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; color: #000; }
    body { font-family: Arial, Helvetica, sans-serif; }
    .print-toolbar {
        position: sticky;
        top: 0;
        z-index: 9999;
        width: 100%;
        min-height: 64px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: #064e3b;
        border-bottom: 3px solid #ffd633;
        box-shadow: 0 5px 16px rgba(0,0,0,.16);
    }
    .print-toolbar a,
    .print-toolbar button {
        min-width: 150px;
        min-height: 44px;
        padding: 0 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font: 700 13px Arial, Helvetica, sans-serif;
        text-decoration: none;
        cursor: pointer;
        touch-action: manipulation;
    }
    .print-toolbar a {
        border: 1px solid #fff;
        background: #fff;
        color: #800020;
    }
    .print-toolbar button {
        border: 1px solid #ffd633;
        background: #800020;
        color: #ffd633;
    }
    .print-content { padding: 18px 0 0; }
    .brand { margin: 0 0 20px; text-align: center; }
    @media (orientation: landscape) {
        .print-toolbar { min-height: 58px; padding: 7px 18px; }
        .print-toolbar a,
        .print-toolbar button { min-height: 42px; }
    }
    @media print {
        .print-toolbar { display: none !important; }
        .print-content { padding-top: 0 !important; }
    }
    .brand strong { display: block; font-size: 20px; line-height: 1.05; font-weight: 700; }
    .brand span { display: block; margin-top: 3px; font-size: 13px; line-height: 1.05; font-weight: 700; letter-spacing: .7px; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9px; }
    th, td { border: none !important; padding: 5px 6px; vertical-align: top; overflow-wrap: anywhere; }
    thead th { padding-top: 4px; padding-bottom: 8px; text-align: center; font-size: 8px; font-weight: 700; white-space: nowrap; }
    tbody td { padding-top: 5px; padding-bottom: 5px; }
    .description { text-align: left; }
    .num { text-align: right; white-space: nowrap; }
    th:nth-child(1) { width: 14%; }
    th:nth-child(2) { width: 14%; }
    th:nth-child(3) { width: 28%; }
    th:nth-child(4) { width: 7%; }
    th:nth-child(5) { width: 13%; }
    th:nth-last-child(2) { width: 10%; }
    th:last-child { width: 14%; }
    tfoot td { border: none !important; }
    .total-row td {
        padding-top: 10px;
        padding-bottom: 10px;
        border-top: 1px dashed #000 !important;
        border-bottom: 1px dashed #000 !important;
        font-size: 10px;
        font-weight: 700;
    }
    .total-row td:first-child { text-align: right; padding-right: 12px; }
</style>
</head>
<body>
    <header class="brand">
        <strong>ERW</strong>
        <span>SPECIAL STORE</span>
    </header>
    <table>
        <thead>
            <tr>
                <th>PRODUCT CODE</th>
                <th>PART NO.</th>
                <th>DESCRIPTION</th>
                <th>QTY</th>
                <th>UNIT PRICE</th>
                ${showLess ? '<th>LESS</th>' : ''}
                <th>SUB TOTAL</th>
            </tr>
        </thead>
        <tbody>${rows}</tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="${totalLabelColspan}">TOTAL</td>
                <td class="num">${money(total)}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>`;
    };

    const printCartDocument = (html) => {
        // W68_IPAD_CART_PRINT_RESTORE_20261015
        const ua = navigator.userAgent || '';
        const appleTouch = /iPad|iPhone|iPod/i.test(ua)
            || (/Macintosh/i.test(ua) && Number(navigator.maxTouchPoints || 0) > 1);

        if (appleTouch) {
            // Safari/AirPrint needs a real rendered document. Do not use the
            // Android tablet iframe popup or Printer Bridge on iPad/iPhone.
            const printWindow = window.open('', '_blank');

            if (!printWindow) {
                alert('Safari blocked the cart print window. Please allow pop-ups for this site and try again.');
                return;
            }

            try {
                printWindow.document.open();
                printWindow.document.write(html);
                printWindow.document.close();

                const runPrint = () => {
                    try {
                        printWindow.focus();
                        printWindow.print();
                    } catch (_) {
                        alert('Unable to open AirPrint on this iPad.');
                    }
                };

                if (typeof printWindow.requestAnimationFrame === 'function') {
                    printWindow.requestAnimationFrame(() => {
                        printWindow.requestAnimationFrame(() => {
                            printWindow.setTimeout(runPrint, 180);
                        });
                    });
                } else {
                    printWindow.setTimeout(runPrint, 250);
                }
            } catch (_) {
                try { printWindow.close(); } catch (__ignore) {}
                alert('Unable to prepare the cart print page on this iPad.');
            }

            return;
        }

        // Non-Apple touch tablets may still use the visible in-page preview
        // when the native Android Printer Bridge is not selected.
        const isWindows = /Windows NT/i.test(ua);
        let coarsePointer = false;
        try {
            coarsePointer = Boolean(window.matchMedia?.('(pointer: coarse)')?.matches);
        } catch (_) {}
        const hasTouch = Number(navigator.maxTouchPoints || 0) > 1 || coarsePointer;
        const useTabletPopup = hasTouch && !isWindows;

        if (useTabletPopup) {
            document.querySelector('[data-cart-print-preview-popup]')?.remove();

            const overlay = document.createElement('div');
            overlay.setAttribute('data-cart-print-preview-popup', '');
            Object.assign(overlay.style, {
                position: 'fixed',
                inset: '0',
                zIndex: '2147483600',
                display: 'grid',
                gridTemplateRows: '64px minmax(0, 1fr)',
                background: '#dfe5e2',
                overflow: 'hidden',
            });

            const toolbar = document.createElement('div');
            Object.assign(toolbar.style, {
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: '12px',
                padding: '8px 14px',
                background: '#064e3b',
                borderBottom: '3px solid #ffd633',
                boxShadow: '0 4px 16px rgba(0,0,0,.18)',
            });

            const backButton = document.createElement('button');
            backButton.type = 'button';
            backButton.textContent = 'BACK TO HOME';
            Object.assign(backButton.style, {
                minWidth: '150px',
                minHeight: '44px',
                padding: '0 18px',
                border: '1px solid #ffffff',
                borderRadius: '8px',
                background: '#ffffff',
                color: '#800020',
                font: '700 13px Arial, sans-serif',
                cursor: 'pointer',
                touchAction: 'manipulation',
            });

            const printButton = document.createElement('button');
            printButton.type = 'button';
            printButton.textContent = 'PRINT';
            Object.assign(printButton.style, {
                minWidth: '150px',
                minHeight: '44px',
                padding: '0 18px',
                border: '1px solid #ffd633',
                borderRadius: '8px',
                background: '#800020',
                color: '#ffd633',
                font: '700 13px Arial, sans-serif',
                cursor: 'pointer',
                touchAction: 'manipulation',
            });

            const frame = document.createElement('iframe');
            frame.title = 'Cart print preview';
            Object.assign(frame.style, {
                width: '100%',
                height: '100%',
                border: '0',
                background: '#ffffff',
                display: 'block',
            });

            toolbar.appendChild(backButton);
            toolbar.appendChild(printButton);
            overlay.appendChild(toolbar);
            overlay.appendChild(frame);
            document.body.appendChild(overlay);

            const frameDocument = frame.contentDocument || frame.contentWindow?.document;
            if (!frameDocument || !frame.contentWindow) {
                overlay.remove();
                alert('Unable to open the cart print popup on this tablet.');
                return;
            }

            frameDocument.open();
            frameDocument.write(html);
            frameDocument.close();

            backButton.addEventListener('click', () => {
                window.location.href = homeUrl;
            });

            printButton.addEventListener('click', () => {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (_) {
                    alert('Unable to open the printer from this tablet.');
                }
            });

            return;
        }

        // Windows/desktop browser flow.
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            alert('The browser blocked the cart print preview. Please allow pop-ups for this site and try again.');
            return;
        }

        try {
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
        } catch (_) {
            try { printWindow.close(); } catch (__ignore) {}
            alert('Unable to open the cart print preview on this device.');
        }
    };

    const shouldUseNativePrinterBridge = () => {
        if (!cartPrintBridgeUrl) return false;

        const ua = navigator.userAgent || '';
        if (/Windows NT/i.test(ua)) return false;

        const appleTouch = /iPad|iPhone|iPod/i.test(ua)
            || (/Macintosh/i.test(ua) && Number(navigator.maxTouchPoints || 0) > 1);
        if (appleTouch) return false;

        let coarsePointer = false;
        try {
            coarsePointer = Boolean(window.matchMedia?.('(pointer: coarse)')?.matches);
        } catch (_) {}

        return Number(navigator.maxTouchPoints || 0) > 1
            || coarsePointer
            || /Android|Xiaomi|MIUI/i.test(ua);
    };

    const printCartList = async (selectedOnly = false) => {
        const cart = readCart();
        const printItems = selectedOnly
            ? cart.filter((item) => item.selected)
            : cart;

        if (!printItems.length) {
            alert(selectedOnly
                ? 'Select at least one cart item before printing selected items.'
                : 'Your cart is empty.');
            return;
        }

        closeCartPrintModal();

        // W68_CART_PRINTER_BRIDGE_20261015
        // Xiaomi/Android/non-Windows touch tablets hand the cart to the native
        // bridge instead of opening Android's browser/system print interface.
        if (shouldUseNativePrinterBridge()) {
            await cartMutationQueue.catch(() => null);

            const target = new URL(cartPrintBridgeUrl, window.location.href);
            target.searchParams.set('mode', selectedOnly ? 'selected' : 'all');
            window.location.href = target.toString();
            return;
        }

        const printHtml = buildCartPrintDocument(printItems);
        printCartDocument(printHtml);
    };

    // W68_IPAD_PRINT_DIRECT_CONTROL_BINDING_20261015
    // Bind print controls at the element itself. Do not send these taps through
    // the global iPad capture bridge; Safari can swallow/retarget that path.
    const bindDirectCartPrintControl = (node, handler) => {
        if (!node || typeof handler !== 'function') return;

        let lastTouchAt = 0;

        node.addEventListener('touchend', (event) => {
            lastTouchAt = Date.now();
            event.preventDefault();
            event.stopPropagation();
            handler(event);
        }, { passive: false });

        node.addEventListener('click', (event) => {
            if (Date.now() - lastTouchAt < 700) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            handler(event);
        });
    };

    // W68_IPAD_PRINT_NATIVE_OPENER_20261015
    // Do not bind/preventDefault on the PRINT CART LIST anchor. Safari owns
    // that first tap and opens #w68-cart-print-choice through native :target.
    bindDirectCartPrintControl(cartPrintAllButton, () => {
        printCartList(false);
    });

    bindDirectCartPrintControl(cartPrintSelectedButton, () => {
        if (!cartPrintSelectedButton?.disabled) printCartList(true);
    });

    cartPrintCloseButtons.forEach((button) => {
        bindDirectCartPrintControl(button, closeCartPrintModal);
    });

    window.W68CartPrintOpen = openCartPrintModal;
    window.W68CartPrintAll = () => printCartList(false);
    window.W68CartPrintSelected = () => printCartList(true);

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
            const hasPendingTutorial = sessionStorage.getItem('w68_process_order_tutorial_pending') === '1';
            sessionStorage.removeItem('w68_process_order_tutorial_pending');
            const targetUrl = orderApi.review + (hasPendingTutorial ? (orderApi.review.includes('?') ? '&tutorial=1' : '?tutorial=1') : '');
            window.location.href = targetUrl;
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

        if (event.target.closest('[data-cart-print-open]')) {
            openCartPrintModal();
            return;
        }

        if (event.target.closest('[data-cart-print-close]')) {
            closeCartPrintModal();
            return;
        }

        if (event.target.closest('[data-cart-print-all]')) {
            printCartList(false);
            return;
        }

        if (event.target.closest('[data-cart-print-selected]')) {
            printCartList(true);
            return;
        }

        // Orders is a normal link. Do not open the cart when it is clicked.
        if (event.target.closest('[data-orders-link]')) {
            return;
        }

        if (event.target.closest('[data-cart-close]')) {
            if (isTutorialCartLocked()) return;
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
        if (isTutorialCartLocked()) return;
        const target = event.target;
        if (target?.closest?.('.cart-panel')) return;
        if (target?.closest?.('[data-cart-open]')) return;
        closeCart();
    };

    cartDrawer?.addEventListener('touchend', closeCartFromOutside, { passive: true });
    cartDrawer?.addEventListener('click', closeCartFromOutside);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        if (modalIsOpen(cartPrintModal)) {
            closeCartPrintModal();
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
            if (isTutorialCartLocked()) return;
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

    /* =====================================================
       W68_FIRST_LOGIN_TUTORIAL_20261024
       W68_TUTORIAL_EXACT_ACTION_FLOW_20261027
       Server-controlled walkthrough for account login #1, #2 and #3.
       Step 1 deliberately skips zero-price / already-carted products.
       ===================================================== */
    const isTutorialTargetUsable = (node) => {
        if (!(node instanceof Element) || node.hidden) return false;
        if (node.getAttribute('aria-hidden') === 'true') return false;

        const style = window.getComputedStyle(node);
        if (
            style.display === 'none'
            || style.visibility === 'hidden'
            || Number(style.opacity || 1) === 0
        ) {
            return false;
        }

        const rect = node.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    };

    const getFirstVisibleTutorialTarget = (selector) => (
        Array.from(document.querySelectorAll(selector))
            .find((node) => isTutorialTargetUsable(node))
        || null
    );

    // W68_TUTORIAL_PURCHASABLE_ITEM_TARGET_20261026
    // Never teach Add to Cart using a zero-price product. Search all rendered
    // product cards, use the effective customer price, and skip cards whose
    // ADD TO CART button is already hidden because the item is in the cart.
    // W68_TUTORIAL_PRODUCTS_GRID_ONLY_20261026
    // Teach Add to Cart from the main PRODUCTS section only. NEW ARRIVAL cards
    // are intentionally excluded even though they also use data-product-card.
    const getTutorialPurchasableAddButton = () => {
        const cards = Array.from(
            document.querySelectorAll('#products .product-grid [data-product-card]')
        );

        for (const card of cards) {
            if (!(card instanceof Element)) continue;

            const originalPrice = Number(card.dataset.productPrice || 0);
            const discountedPrice = Number(card.dataset.productDiscountedPrice || 0);
            const discountPercent = Number(card.dataset.productDiscountPercent || 0);
            const effective = (
                discountPercent > 0
                && discountedPrice > 0
                && (originalPrice <= 0 || discountedPrice < originalPrice)
            )
                ? discountedPrice
                : originalPrice;

            if (!Number.isFinite(effective) || effective <= 0) continue;

            const addButton = card.querySelector('[data-add-cart]');
            if (!isTutorialTargetUsable(addButton)) continue;

            return addButton;
        }

        return null;
    };

    const initFirstLoginTutorial = () => {
        if (String(body?.dataset.firstLoginTutorial || '0') !== '1') return;

        const overlay = document.querySelector('[data-w68-tutorial]');
        const spotlight = document.querySelector('[data-w68-tutorial-spotlight]');
        const arrow = document.querySelector('[data-w68-tutorial-arrow]');
        const robot = document.querySelector('[data-w68-tutorial-robot]');
        const kickerNode = document.querySelector('[data-w68-tutorial-kicker]');
        const titleNode = document.querySelector('[data-w68-tutorial-title]');
        const bodyNode = document.querySelector('[data-w68-tutorial-body]');
        const nextButton = document.querySelector('[data-w68-tutorial-next]');
        const doneButton = document.querySelector('[data-w68-tutorial-done]');
        const skipButton = document.querySelector('[data-w68-tutorial-skip]');

        if (
            !overlay
            || !spotlight
            || !arrow
            || !robot
            || !titleNode
            || !bodyNode
            || !nextButton
            || !doneButton
            || !skipButton
        ) {
            return;
        }

        // W68_TUTORIAL_POINTER_TOP_LAYER_20261026
        // Quantity modal/cart drawer live at z-index 30000-33000. Keeping the
        // pointer inside the tutorial overlay (z-index 15000) traps it behind
        // those commerce overlays. Promote only the non-interactive spotlight
        // and hand cursor to direct body children so they can render above the
        // modal without blocking the real controls.
        if (spotlight.parentElement !== document.body) {
            document.body.appendChild(spotlight);
        }
        if (arrow.parentElement !== document.body) {
            document.body.appendChild(arrow);
        }

        // W68_TUTORIAL_DIALOG_TOP_LAYER_20261026
        // Keep the robot and instruction card above the quantity modal and
        // cart drawer while leaving the real highlighted control clickable.
        const tutorialCard = document.querySelector('[data-w68-tutorial-card]');
        if (robot.parentElement !== document.body) {
            document.body.appendChild(robot);
        }
        if (tutorialCard && tutorialCard.parentElement !== document.body) {
            document.body.appendChild(tutorialCard);
        }

        robot.hidden = true;
        if (tutorialCard) tutorialCard.hidden = true;

        const loginNumber = Math.max(
            1,
            Math.min(3, Number.parseInt(String(body?.dataset.tutorialLoginNumber || '1'), 10) || 1)
        );

        if (kickerNode) {
            kickerNode.textContent = `W68 FIRST TIME GUIDE • LOGIN ${loginNumber} OF 3`;
        }

        const robotImage = robot.querySelector('img');
        const robotGif = String(body?.dataset.tutorialRobotGif || '');
        if (robotImage && robotGif) {
            robotImage.src = robotGif;
        }

        // W68_TUTORIAL_GUIDED_ACTION_SEQUENCE_20261026
        // Every actionable step advances only after the customer clicks the
        // exact control being taught. PROCESS ORDER remains informational.
        // W68_TUTORIAL_FLOW_REFINEMENT_20261027
        // Keep all cart steps tied to the exact product selected in Step 1.
        let tutorialProductId = null;

        const getTutorialCartRow = () => {
            if (tutorialProductId !== null && tutorialProductId !== '') {
                const specific = document.querySelector(
                    `[data-cart-row-id="${CSS.escape(String(tutorialProductId))}"]`
                );
                if (isTutorialTargetUsable(specific)) return specific;
            }

            return getFirstVisibleTutorialTarget('[data-cart-row-id]');
        };

        const getTutorialCartCheckbox = () => {
            const row = getTutorialCartRow();
            if (!(row instanceof Element)) return null;

            return row.querySelector(
                '.cart-item-check input[type="checkbox"], input[type="checkbox"]'
            );
        };

        const steps = [
            {
                key: 'welcome',
                title: 'Welcome to W68!',
                body: 'I will guide you using an item from the PRODUCTS section. Tap anywhere or click NEXT to start.',
                target: null,
                allowNext: true,
                showDone: false,
            },
            {
                key: 'add-to-cart',
                pointerLabel: 'ADD',
                title: 'Step 1: Add an item from Products',
                body: 'This item is from the PRODUCTS section. Click its highlighted ADD TO CART button.',
                target: () => getTutorialPurchasableAddButton(),
                allowNext: false,
                showDone: false,
            },
            {
                key: 'quantity-plus',
                pointerLabel: '+',
                title: 'Step 2: Try the + button',
                body: 'Click the highlighted PLUS button once. The guide will automatically move to the next control.',
                target: () => getFirstVisibleTutorialTarget('[data-quantity-plus]'),
                allowNext: false,
                showDone: false,
            },
            {
                key: 'quantity-minus',
                pointerLabel: '−',
                title: 'Step 3: Try the − button',
                body: 'Now click the highlighted MINUS button once. The guide will automatically continue.',
                target: () => getFirstVisibleTutorialTarget('[data-quantity-minus]'),
                allowNext: false,
                showDone: false,
            },
            {
                key: 'quantity-add',
                pointerLabel: 'ADD',
                title: 'Step 4: Add it to your cart',
                body: 'Now click the highlighted ADD TO CART button. The instruction box stays above the quantity dialog so this button remains fully visible.',
                target: () => getFirstVisibleTutorialTarget('[data-quantity-confirm]'),
                allowNext: false,
                showDone: false,
            },
            {
                key: 'added-success',
                title: 'Added to Cart confirmation',
                body: 'This confirmation appears whenever an item is successfully added to your cart. The W68 robot appears beside it so you can recognize this message.',
                target: () => getFirstVisibleTutorialTarget('[data-cart-message] .cart-message-card'),
                allowNext: false,
                showDone: false,
                autoAdvanceMs: 3000,
            },
            {
                key: 'cart-button',
                pointerLabel: 'OPEN',
                title: 'Step 5: Open your cart',
                body: 'Click the highlighted CART button. The guide will automatically continue when the cart opens.',
                target: () => getFirstVisibleTutorialTarget('[data-cart-open]'),
                allowNext: false,
                showDone: false,
            },
            {
                key: 'carted-item',
                pointerLabel: 'ITEM',
                title: 'Step 6: This is your carted item',
                body: 'This is the item you added. Tap anywhere or click NEXT to proceed to selecting it.',
                target: () => getTutorialCartRow()
                    || getFirstVisibleTutorialTarget('[data-cart-items]'),
                allowNext: true,
                showDone: false,
                autoAdvanceMs: 4000,
            },
            {
                key: 'select-item',
                pointerLabel: 'SELECT',
                title: 'Step 7: Select the item',
                body: 'Click this item checkbox so it is included when you use PROCESS ORDER.',
                target: () => getTutorialCartCheckbox(),
                allowNext: false,
                showDone: false,
            },
            {
                key: 'process-order',
                pointerLabel: 'HERE',
                title: 'Step 8: PROCESS ORDER',
                body: 'This is the PROCESS ORDER button. Tap anywhere or click it to complete this guide, then click PROCESS ORDER to continue.',
                target: () => getFirstVisibleTutorialTarget('[data-process-order]'),
                allowNext: false,
                showDone: true,
            },
        ];

        let currentStepIndex = 0;
        let currentTarget = null;
        let active = false;
        let repositionTimer = null;

        const clearTarget = () => {
            if (currentTarget instanceof Element) {
                currentTarget.classList.remove('w68-tutorial-target');
                currentTarget.classList.remove('w68-tutorial-no-click');
            }
            currentTarget = null;
        };

        const hidePointer = () => {
            spotlight.hidden = true;
            arrow.hidden = true;
        };

        const finishTutorial = () => {
            active = false;
            clearTarget();
            hidePointer();
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            overlay.classList.remove('is-finishable');
            overlay.classList.remove('is-welcome-stage');
            robot.hidden = true;
            if (tutorialCard) {
                tutorialCard.hidden = true;
                tutorialCard.classList.remove('is-welcome-stage');
            }
            resetTutorialCardPlacement();
            resetTutorialRobotPlacement();

            // W68_TUTORIAL_CART_PRINT_LAYER_FIX_20261026
            // Restore PRINT CART LIST after the walkthrough ends.
            document.documentElement.classList.remove('w68-tutorial-active');
            document.body?.classList.remove('w68-tutorial-active');

            if (repositionTimer !== null) {
                window.clearTimeout(repositionTimer);
                repositionTimer = null;
            }
        };

        const scrollTargetIntoView = (target) => {
            if (!(target instanceof Element)) return;

            const card = target.closest('[data-product-card]');
            const scrollTarget = card || target;

            try {
                scrollTarget.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'center',
                });
            } catch (_) {
                scrollTarget.scrollIntoView();
            }
        };

        // W68_TUTORIAL_HAND_CURSOR_20261026
        // Use a compact hand cursor beside the control instead of drawing a
        // long arrow/beam from the center robot across the screen.
        const positionPointer = (target) => {
            if (!active || !(target instanceof Element)) {
                hidePointer();
                return;
            }

            const rect = target.getBoundingClientRect();

            if (rect.width <= 0 || rect.height <= 0) {
                hidePointer();
                return;
            }

            const viewportWidth = window.visualViewport?.width
                || window.innerWidth
                || document.documentElement.clientWidth;
            const viewportHeight = window.visualViewport?.height
                || window.innerHeight
                || document.documentElement.clientHeight;

            const pad = 7;
            spotlight.hidden = false;
            spotlight.style.left = `${Math.max(4, rect.left - pad)}px`;
            spotlight.style.top = `${Math.max(4, rect.top - pad)}px`;
            spotlight.style.width = `${Math.max(34, rect.width + (pad * 2))}px`;
            spotlight.style.height = `${Math.max(34, rect.height + (pad * 2))}px`;

            const pointerWidth = 74;
            const pointerHeight = 48;
            const gap = 10;
            let left = rect.right + gap;
            let top = rect.top + (rect.height / 2) - (pointerHeight / 2);
            let side = 'right';

            if (left + pointerWidth > viewportWidth - 6) {
                left = rect.left - pointerWidth - gap;
                side = 'left';
            }

            if (left < 6) {
                left = Math.max(
                    6,
                    rect.left + (rect.width / 2) - (pointerWidth / 2)
                );
                top = rect.top - pointerHeight - gap;
                side = 'top';
            }

            if (top < 6) {
                top = Math.min(
                    viewportHeight - pointerHeight - 6,
                    rect.bottom + gap
                );
                side = 'bottom';
            }

            if (top + pointerHeight > viewportHeight - 6) {
                top = Math.max(
                    6,
                    rect.top - pointerHeight - gap
                );
                side = 'top';
            }

            arrow.hidden = false;
            arrow.dataset.pointerSide = side;
            arrow.style.left = `${Math.round(left)}px`;
            arrow.style.top = `${Math.round(top)}px`;
            arrow.style.width = `${pointerWidth}px`;
            arrow.style.height = `${pointerHeight}px`;
            arrow.style.transform = 'none';
        };

        const targetForStep = (step) => (
            typeof step?.target === 'function'
                ? step.target()
                : null
        );

        // W68_TUTORIAL_CART_DIALOG_POSITION_20261026
        // During cart-related steps, keep the instruction card INSIDE the
        // visible cart drawer and directly above PROCESS ORDER. This prevents
        // the card from sitting underneath / outside the right-side cart.
        const cartStageKeys = new Set([
            'carted-item',
            'select-item',
            'process-order',
        ]);

        const quantityStageKeys = new Set([
            'quantity-plus',
            'quantity-minus',
            'quantity-add',
        ]);

        const resetTutorialRobotPlacement = () => {
            robot.classList.remove('is-added-message', 'is-quantity-guide');

            [
                'left',
                'right',
                'top',
                'bottom',
                'width',
                'transform',
            ].forEach((property) => {
                robot.style.removeProperty(property);
            });
        };

        const resetTutorialCardPlacement = () => {
            if (!tutorialCard) return;

            tutorialCard.classList.remove('is-cart-stage', 'is-quantity-stage', 'is-added-success-stage');
            [
                'left',
                'right',
                'top',
                'bottom',
                'width',
                'transform',
            ].forEach((property) => {
                tutorialCard.style.removeProperty(property);
            });
        };

        const positionTutorialRobotForStep = (step) => {
            resetTutorialRobotPlacement();

            const key = String(step?.key || '');
            const viewportWidth = window.visualViewport?.width
                || window.innerWidth
                || document.documentElement.clientWidth;
            const viewportHeight = window.visualViewport?.height
                || window.innerHeight
                || document.documentElement.clientHeight;

            // W68_TUTORIAL_QUANTITY_ROBOT_POSITION_20261027
            // Keep the robot beside the quantity modal instead of covering
            // +, -, quantity, total or ADD TO CART.
            if (quantityStageKeys.has(key)) {
                const quantityCard = document.querySelector('.quantity-modal-card');
                if (!(quantityCard instanceof Element)) return;

                const rect = quantityCard.getBoundingClientRect();
                const robotWidth = viewportWidth <= 520 ? 78 : 112;
                const gap = viewportWidth <= 520 ? 4 : 12;

                let left = rect.right + gap;
                let top = rect.top + Math.min(rect.height * .34, 150);

                if (left + robotWidth > viewportWidth - 4) {
                    left = rect.left - robotWidth - gap;
                }

                if (left < 4) {
                    // Very narrow phones: park the robot in the free top-right
                    // corner instead of over any modal action.
                    left = Math.max(4, viewportWidth - robotWidth - 8);
                    top = Math.max(robotWidth * .52, rect.top - 12);
                }

                top = Math.max(
                    robotWidth * .54,
                    Math.min(viewportHeight - (robotWidth * .54), top)
                );

                robot.classList.add('is-quantity-guide');
                robot.style.width = `${robotWidth}px`;
                robot.style.left = `${Math.round(left)}px`;
                robot.style.top = `${Math.round(top)}px`;
                robot.style.transform = 'translate(0, -50%)';
                return;
            }

            // W68_TUTORIAL_ADDED_MESSAGE_ROBOT_20261027
            // Put the robot genuinely BESIDE the success card, not on top of it.
            if (key === 'added-success') {
                const messageCard = getFirstVisibleTutorialTarget(
                    '[data-cart-message] .cart-message-card'
                );
                if (!(messageCard instanceof Element)) return;

                const rect = messageCard.getBoundingClientRect();
                const robotWidth = viewportWidth <= 520 ? 88 : 118;
                const gap = 10;

                let left = rect.left - robotWidth - gap;
                if (left < 4) {
                    left = rect.right + gap;
                }
                if (left + robotWidth > viewportWidth - 4) {
                    left = Math.max(4, rect.left + (rect.width / 2) - (robotWidth / 2));
                }

                let top = rect.top + (rect.height / 2);
                top = Math.max(
                    robotWidth * .56,
                    Math.min(viewportHeight - (robotWidth * .56), top)
                );

                robot.classList.add('is-added-message');
                robot.style.width = `${robotWidth}px`;
                robot.style.left = `${Math.round(left)}px`;
                robot.style.top = `${Math.round(top)}px`;
                robot.style.transform = 'translate(0, -50%)';
            }
        };

        const positionTutorialCardForStep = (step) => {
            if (!tutorialCard) return;

            const key = String(step?.key || '');
            resetTutorialCardPlacement();

            // W68_TUTORIAL_QUANTITY_DIALOG_POSITION_20261027
            // Put the tutorial instruction OUTSIDE/ABOVE the quantity modal.
            // This keeps +, -, total and especially ADD TO CART fully visible.
            if (quantityStageKeys.has(key)) {
                const quantityCard = document.querySelector('.quantity-modal-card');

                if (!(quantityCard instanceof Element)) return;

                const modalRect = quantityCard.getBoundingClientRect();
                const viewportWidth = window.visualViewport?.width
                    || window.innerWidth
                    || document.documentElement.clientWidth;
                const viewportHeight = window.visualViewport?.height
                    || window.innerHeight
                    || document.documentElement.clientHeight;
                const horizontalGap = 10;

                tutorialCard.classList.add('is-quantity-stage');
                tutorialCard.style.width = `${Math.max(
                    250,
                    Math.min(modalRect.width, viewportWidth - 16)
                )}px`;
                tutorialCard.style.left = `${Math.round(
                    Math.max(8, Math.min(modalRect.left, viewportWidth - Math.min(modalRect.width, viewportWidth - 16) - 8))
                )}px`;
                tutorialCard.style.right = 'auto';
                tutorialCard.style.bottom = 'auto';
                tutorialCard.style.transform = 'none';

                window.requestAnimationFrame(() => {
                    if (!tutorialCard || !quantityStageKeys.has(String(steps[currentStepIndex]?.key || ''))) {
                        return;
                    }

                    const cardRect = tutorialCard.getBoundingClientRect();
                    const gap = 10;
                    let top = modalRect.top - cardRect.height - gap;

                    // Small phones may not have enough room above the quantity
                    // modal. Keep the guide at the top of the viewport instead
                    // of covering the ADD TO CART action at the bottom.
                    if (top < 8) {
                        top = 8;
                    }

                    top = Math.min(
                        top,
                        Math.max(8, viewportHeight - cardRect.height - 8)
                    );

                    tutorialCard.style.top = `${Math.round(top)}px`;
                });

                return;
            }

            if (key === 'added-success') {
                const messageCard = getFirstVisibleTutorialTarget(
                    '[data-cart-message] .cart-message-card'
                );

                tutorialCard.classList.add('is-added-success-stage');

                if (messageCard instanceof Element) {
                    const rect = messageCard.getBoundingClientRect();
                    const viewportWidth = window.visualViewport?.width
                        || window.innerWidth
                        || document.documentElement.clientWidth;

                    tutorialCard.style.width = `${Math.min(430, viewportWidth - 16)}px`;
                    tutorialCard.style.left = '50%';
                    tutorialCard.style.right = 'auto';
                    tutorialCard.style.bottom = 'max(8px, env(safe-area-inset-bottom))';
                    tutorialCard.style.top = 'auto';
                    tutorialCard.style.transform = 'translateX(-50%)';
                }

                return;
            }

            if (!cartStageKeys.has(key)) {
                return;
            }

            const cartPanel = document.querySelector('.cart-panel');
            const processButton = getFirstVisibleTutorialTarget('[data-process-order]');

            if (!(cartPanel instanceof Element) || !(processButton instanceof Element)) {
                return;
            }

            const panelRect = cartPanel.getBoundingClientRect();
            const buttonRect = processButton.getBoundingClientRect();
            const viewportHeight = window.visualViewport?.height
                || window.innerHeight
                || document.documentElement.clientHeight;

            const horizontalGap = 8;
            tutorialCard.classList.add('is-cart-stage');
            tutorialCard.style.left = `${Math.round(panelRect.left + horizontalGap)}px`;
            tutorialCard.style.right = 'auto';
            tutorialCard.style.bottom = 'auto';
            tutorialCard.style.width = `${Math.max(240, Math.round(panelRect.width - (horizontalGap * 2)))}px`;
            tutorialCard.style.transform = 'none';

            window.requestAnimationFrame(() => {
                if (!tutorialCard || !cartStageKeys.has(String(steps[currentStepIndex]?.key || ''))) {
                    return;
                }

                const cardRect = tutorialCard.getBoundingClientRect();
                const gapAboveButton = 10;
                const desiredTop = buttonRect.top - cardRect.height - gapAboveButton;
                const minimumTop = Math.max(8, panelRect.top + 8);
                const maximumTop = Math.max(
                    minimumTop,
                    viewportHeight - cardRect.height - 8
                );

                tutorialCard.style.top = `${Math.round(
                    Math.min(maximumTop, Math.max(minimumTop, desiredTop))
                )}px`;
            });
        };

        const renderStep = (index) => {
            const step = steps[index];

            if (!step) {
                finishTutorial();
                return;
            }

            currentStepIndex = index;
            titleNode.textContent = step.title;
            bodyNode.textContent = step.body;
            arrow.dataset.pointerLabel = String(step.pointerLabel || 'CLICK');
            nextButton.hidden = !step.allowNext;
            doneButton.hidden = !step.showDone;
            doneButton.textContent = step.key === 'process-order' ? 'PROCEED' : 'DONE';

            overlay.classList.remove('is-finishable');
            if (step.key === 'welcome') {
                overlay.classList.add('is-welcome-stage');
                if (tutorialCard) tutorialCard.classList.add('is-welcome-stage');
            } else {
                overlay.classList.remove('is-welcome-stage');
                if (tutorialCard) tutorialCard.classList.remove('is-welcome-stage');
            }

            positionTutorialCardForStep(step);
            positionTutorialRobotForStep(step);

            clearTarget();

            // W68_TUTORIAL_SELECTED_ITEM_SKIP_20261026
            // A newly-added/existing cart row may already be selected. Never
            // ask the customer to click a checked box just to satisfy the
            // tutorial, because that would deselect it.
            if (step.key === 'select-item') {
                const tutorialCheckbox = getTutorialCartCheckbox();

                if (tutorialCheckbox?.checked) {
                    window.setTimeout(() => {
                        if (active && currentStepIndex === index) {
                            renderStep(index + 1);
                        }
                    }, 650);
                    return;
                }
            }

            const target = targetForStep(step);

            if (!target) {
                hidePointer();

                if (step.key === 'add-to-cart') {
                    titleNode.textContent = 'No available item in Products';
                    bodyNode.textContent = 'This PRODUCTS page has no available ADD TO CART item with a price greater than 0. Move to another Products page and press NEXT.';
                }

                if (!step.showDone) {
                    nextButton.hidden = false;
                }
                return;
            }

            currentTarget = target;
            currentTarget.classList.add('w68-tutorial-target');

            if (step.key === 'added-success') {
                // Informational confirmation only: emphasize the success card
                // with the robot, but do not show a misleading CLICK hand.
                currentTarget.classList.remove('w68-tutorial-target');
                hidePointer();
            }

            scrollTargetIntoView(target);

            if (repositionTimer !== null) {
                window.clearTimeout(repositionTimer);
            }

            // Smooth scrolling needs a little time to finish before we measure
            // the button. Re-measure several times for Safari/iPad accuracy.
            [180, 420, 760].forEach((delay) => {
                window.setTimeout(() => {
                    if (
                        active
                        && currentTarget === target
                        && steps[currentStepIndex]?.key !== 'added-success'
                    ) {
                        positionPointer(target);
                    }
                }, delay);
            });

            if (Number(step.autoAdvanceMs || 0) > 0) {
                const expectedStepIndex = currentStepIndex;
                window.setTimeout(() => {
                    if (active && currentStepIndex === expectedStepIndex) {
                        renderStep(expectedStepIndex + 1);
                    }
                }, Number(step.autoAdvanceMs));
            }
        };

        nextButton.addEventListener('click', () => {
            if (!active) return;
            renderStep(currentStepIndex + 1);
        });

        doneButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (steps[currentStepIndex]?.key === 'process-order') {
                sessionStorage.setItem('w68_process_order_tutorial_pending', '1');
                finishTutorial();
                return;
            }

            finishTutorial();
        });
        skipButton.addEventListener('click', finishTutorial);

        const reposition = () => {
            if (!active || !currentTarget) return;

            if (repositionTimer !== null) {
                window.clearTimeout(repositionTimer);
            }

            repositionTimer = window.setTimeout(() => {
                const activeStep = steps[currentStepIndex];
                positionTutorialCardForStep(activeStep);
                positionTutorialRobotForStep(activeStep);

                if (activeStep?.key === 'added-success') {
                    hidePointer();
                } else {
                    positionPointer(currentTarget);
                }
            }, 45);
        };

        window.addEventListener('resize', reposition, { passive: true });
        window.addEventListener('scroll', reposition, { passive: true });
        window.visualViewport?.addEventListener('resize', reposition, { passive: true });
        window.visualViewport?.addEventListener('scroll', reposition, { passive: true });

        const isAllowedTutorialInteraction = (target) => {
            if (!(target instanceof Element)) return false;

            if (
                target.closest(
                    '[data-w68-tutorial-next], [data-w68-tutorial-done], [data-w68-tutorial-skip]'
                )
            ) {
                return true;
            }

            return currentTarget instanceof Element
                && (target === currentTarget || currentTarget.contains(target));
        };

        let lastStepAdvanceTime = 0;
        const advanceFromWelcome = () => {
            const now = Date.now();
            if (now - lastStepAdvanceTime < 350) return;
            if (!active || steps[currentStepIndex]?.key !== 'welcome') return;
            lastStepAdvanceTime = now;
            renderStep(1);
        };

        const advanceFromCartedItem = () => {
            const now = Date.now();
            if (now - lastStepAdvanceTime < 350) return;
            if (!active || steps[currentStepIndex]?.key !== 'carted-item') return;
            lastStepAdvanceTime = now;
            renderStep(8);
        };

        // W68_TUTORIAL_STRICT_ACTION_GATE_20261027
        // On touch devices pointer/touch starts can trigger underlying controls
        // before click. Swallow every unrelated interaction. Only the current
        // highlighted control and tutorial buttons are actionable.
        const blockUnrelatedTutorialInteraction = (event) => {
            if (!active || !(event.target instanceof Element)) return;

            // In welcome step, tapping or touching anywhere advances to add-to-cart
            if (steps[currentStepIndex]?.key === 'welcome') {
                if (event.target.closest('[data-w68-tutorial-skip]')) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                advanceFromWelcome();
                return;
            }

            // In carted-item step, tapping or touching anywhere advances to select-item (checkbox)
            if (steps[currentStepIndex]?.key === 'carted-item') {
                if (event.target.closest('[data-w68-tutorial-skip]')) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                advanceFromCartedItem();
                return;
            }

            if (isAllowedTutorialInteraction(event.target)) return;

            event.preventDefault();
            event.stopImmediatePropagation();
        };

        document.addEventListener('pointerdown', blockUnrelatedTutorialInteraction, true);
        document.addEventListener('touchstart', blockUnrelatedTutorialInteraction, {
            capture: true,
            passive: false,
        });

        document.addEventListener('click', (event) => {
            if (!active || !(event.target instanceof Element)) return;

            const step = steps[currentStepIndex];
            if (!step) return;

            // In welcome step, clicking/tapping anywhere advances to add-to-cart
            if (step.key === 'welcome') {
                if (event.target.closest('[data-w68-tutorial-skip]')) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                advanceFromWelcome();
                return;
            }

            // In carted-item step, clicking/tapping anywhere advances to select-item (checkbox)
            if (step.key === 'carted-item') {
                if (event.target.closest('[data-w68-tutorial-skip]')) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                advanceFromCartedItem();
                return;
            }

            // Random clicks/taps can never advance, finish, navigate or alter
            // the page while a tutorial step is waiting for another control.
            if (!isAllowedTutorialInteraction(event.target)) {
                if (step.key === 'process-order') {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    sessionStorage.setItem('w68_process_order_tutorial_pending', '1');
                    finishTutorial();
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            if (
                step.key === 'add-to-cart'
                && event.target.closest('#products [data-add-cart]')
            ) {
                const productCard = event.target.closest('[data-product-card]');
                tutorialProductId = String(productCard?.dataset.productId || '');

                // Normal product handler opens the quantity modal first.
                window.setTimeout(() => renderStep(2), 230);
                return;
            }

            if (
                step.key === 'quantity-plus'
                && event.target.closest('[data-quantity-plus]')
            ) {
                window.setTimeout(() => renderStep(3), 130);
                return;
            }

            if (
                step.key === 'quantity-minus'
                && event.target.closest('[data-quantity-minus]')
            ) {
                window.setTimeout(() => renderStep(4), 130);
                return;
            }

            if (
                step.key === 'quantity-add'
                && event.target.closest('[data-quantity-confirm]')
            ) {
                // The normal handler closes the quantity modal and displays
                // the 2-second ADDED TO CART confirmation. Teach that message
                // immediately while it is still visible.
                window.setTimeout(() => renderStep(5), 120);
                return;
            }

            if (
                step.key === 'cart-button'
                && event.target.closest('[data-cart-open]')
            ) {
                window.setTimeout(() => renderStep(7), 360);
                return;
            }

            if (step.key === 'carted-item') {
                // If the user tapped the cart item, or anywhere in the cart drawer / screen,
                // proceed immediately to pointing to the item checkbox.
                event.preventDefault();
                event.stopImmediatePropagation();
                window.setTimeout(() => renderStep(8), 100);
                return;
            }

            if (
                step.key === 'select-item'
                && event.target.closest(
                    '[data-cart-row-id] .cart-item-check input, [data-cart-row-id] input[type="checkbox"]'
                )
            ) {
                const checkbox = event.target.closest('input[type="checkbox"]');

                window.setTimeout(() => {
                    if (checkbox?.checked) {
                        renderStep(9);
                    } else {
                        bodyNode.textContent = 'Keep this item checkbox checked so it is included in PROCESS ORDER.';
                    }
                }, 260);
                return;
            }

            if (
                step.key === 'process-order'
                && event.target.closest('[data-process-order]')
            ) {
                // Customer clicked PROCESS ORDER in the tutorial!
                // Finalize the tutorial dialog without triggering the button action.
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                sessionStorage.setItem('w68_process_order_tutorial_pending', '1');
                finishTutorial();
                return;
            }
        }, true);

        const tutorialScrim = overlay.querySelector('.w68-tutorial-scrim');

        // W68_TUTORIAL_NO_BACKGROUND_ADVANCE_20261027
        // Clicking/touching the dark background never advances or finishes
        // the walkthrough. Only the required highlighted control, NEXT on the
        // welcome step, or FINISH on PROCESS ORDER can change tutorial state.
        tutorialScrim?.addEventListener('click', (event) => {
            if (!active) return;
            event.preventDefault();
            event.stopPropagation();
        });

        const openTutorial = () => {
            active = true;

            // W68_TUTORIAL_CART_PRINT_LAYER_FIX_20261026
            // The iPad print launcher intentionally uses an extreme z-index.
            // Hide print controls only while tutorial mode is active so the
            // tutorial hand/cursor can never be covered by PRINT CART LIST.
            document.documentElement.classList.add('w68-tutorial-active');
            document.body?.classList.add('w68-tutorial-active');

            overlay.hidden = false;
            overlay.setAttribute('aria-hidden', 'false');
            robot.hidden = false;
            if (tutorialCard) tutorialCard.hidden = false;
            renderStep(0);
        };

        cartHydrationPromise.finally(() => {
            window.setTimeout(openTutorial, 700);
        });
    };

    initNewItemsCarousel();
    initFirstLoginTutorial();

    renderCart();
    syncProductCardStates();
    updateNavOnScroll();

    cartHydrationPromise.finally(() => {
        renderCart();
        syncProductCardStates();
    });
})();
