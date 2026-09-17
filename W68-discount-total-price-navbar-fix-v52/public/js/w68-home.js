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
    };
    const serverCart = (() => {
        try {
            const parsed = JSON.parse(body?.dataset.serverCart || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            return [];
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

            return value.map((item) => {
                const price = Number(item.price || 0);
                const discountPercent = discountForBrand(item.brand);

                return {
                    ...item,
                    qty: safeQty(item.qty),
                    selected: Boolean(item.selected),
                    price,
                    discountPercent,
                    discountedPrice: discountedPrice(price, discountPercent),
                };
            });
        } catch (_) {
            return [];
        }
    };

    const writeCart = (cart) => {
        localStorage.setItem(storageKey, JSON.stringify(cart));
    };

    if (cartStorageReady) {
        // The database is authoritative for this exact logged-in account.
        writeCart(serverCart);
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

    const hasDiscount = (item) => Number(item?.discountPercent || 0) > 0;

    const effectivePrice = (item) => hasDiscount(item)
        ? Number(item.discountedPrice || 0)
        : Number(item.price || 0);

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

        if (event?.target?.closest?.('[data-cart-open]')) {
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

    // Pointer-down capture makes the folded header react to both mouse clicks
    // and touch taps immediately, including desktop/laptop browsers where a
    // normal click could be swallowed after the sticky header changes size.
    nav?.addEventListener('pointerdown', (event) => {
        if (!nav.classList.contains('is-collapsed')) return;
        if (event.target.closest('[data-cart-open]')) return;
        openCollapsedNavigation(event);
    }, true);

    nav?.addEventListener('click', (event) => {
        openCollapsedNavigation(event);
    });

    collapsedNavOpen?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        if (event.target.closest('[data-cart-open]')) {
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
        }));
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
            '[data-product-modal-specification]': product.specification || '\u2014',
            '[data-product-modal-brand]': product.brand || '\u2014',
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

        const discountRow = cartItemModal.querySelector('[data-cart-item-modal-discount-row]');
        const discountPrice = cartItemModal.querySelector('[data-cart-item-modal-discounted-price]');

        if (discountRow && discountPrice) {
            discountRow.hidden = !hasDiscount(item);
            discountPrice.textContent = hasDiscount(item)
                ? `${money(effectivePrice(item))} (${percent(item.discountPercent)}%)`
                : money(item.price);
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
       Process order reminder / agreement
       ===================================================== */
    const updateProcessOrderModal = () => {
        if (!processOrderModal) return;

        const selected = readCart().filter((item) => item.selected);
        const total = selected.reduce(
            (sum, item) => sum + effectivePrice(item) * safeQty(item.qty),
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
        queueCartMutation(() => cartRequest(cartApi.removeSelected, 'DELETE'));
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
