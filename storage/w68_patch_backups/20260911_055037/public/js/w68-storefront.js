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

    const setupBrandCarousel = (root) => {
        const viewport = root.querySelector('[data-carousel-viewport]');
        const track = root.querySelector('[data-carousel-track]');
        const prev = root.querySelector('[data-carousel-prev]');
        const next = root.querySelector('[data-carousel-next]');
        const items = Array.from(track?.children || []);
        if (!viewport || !track || items.length < 2) return;

        let index = 0;
        const interval = Number(root.dataset.interval || 2000);
        let timer = null;

        const visibleCount = () => {
            const first = items[0];
            const gap = parseFloat(getComputedStyle(track).gap || '0');
            const itemWidth = first.getBoundingClientRect().width + gap;
            return Math.max(1, Math.floor((viewport.clientWidth + gap) / itemWidth));
        };

        const maxIndex = () => Math.max(0, items.length - visibleCount());

        const go = (target) => {
            const max = maxIndex();
            index = target > max ? 0 : target < 0 ? max : target;
            const first = items[0];
            const gap = parseFloat(getComputedStyle(track).gap || '0');
            const itemWidth = first.getBoundingClientRect().width + gap;
            track.style.transform = `translateX(${-index * itemWidth}px)`;
        };

        const start = () => {
            stop();
            timer = window.setInterval(() => go(index + 1), interval);
        };

        const stop = () => {
            if (timer) window.clearInterval(timer);
            timer = null;
        };

        prev?.addEventListener('click', () => { go(index - 1); start(); });
        next?.addEventListener('click', () => { go(index + 1); start(); });
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);
        window.addEventListener('resize', () => go(index));
        start();
    };

    const setupSlideCarousel = (root) => {
        const slides = Array.from(root.querySelectorAll('[data-slide]'));
        const prev = root.querySelector('[data-slide-prev]');
        const next = root.querySelector('[data-slide-next]');
        const dotPrev = root.querySelector('[data-slide-dot-action="prev"]');
        const dotCurrent = root.querySelector('[data-slide-dot-action="current"]');
        const dotNext = root.querySelector('[data-slide-dot-action="next"]');

        if (slides.length < 2) return;

        let index = Math.max(0, slides.findIndex((slide) => slide.classList.contains('active')));
        const interval = Number(root.dataset.interval || 2000);
        let timer = null;

        const loadSlideImage = (slide) => {
            const image = slide?.querySelector('img[data-hero-src]');
            if (!image || image.dataset.heroLoaded === '1') return;

            image.src = image.dataset.heroSrc;
            image.dataset.heroLoaded = '1';
        };

        const show = (target) => {
            index = (target + slides.length) % slides.length;

            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('active', slideIndex === index);
            });

            loadSlideImage(slides[index]);

            // Preload only the next slide so a large 2026 catalog does not load every image at once.
            const nextIndex = (index + 1) % slides.length;
            window.setTimeout(() => loadSlideImage(slides[nextIndex]), 120);

            if (dotCurrent) {
                dotCurrent.classList.add('active');
                dotCurrent.setAttribute('aria-current', 'true');
            }
        };

        const stop = () => {
            if (timer) window.clearInterval(timer);
            timer = null;
        };

        const start = () => {
            stop();
            timer = window.setInterval(() => show(index + 1), interval);
        };

        const goPrev = () => {
            show(index - 1);
            start();
        };

        const goNext = () => {
            show(index + 1);
            start();
        };

        prev?.addEventListener('click', goPrev);
        next?.addEventListener('click', goNext);
        dotPrev?.addEventListener('click', goPrev);
        dotNext?.addEventListener('click', goNext);
        dotCurrent?.addEventListener('click', start);

        if (root.dataset.noPause !== 'true') {
            root.addEventListener('mouseenter', stop);
            root.addEventListener('mouseleave', start);
            root.addEventListener('focusin', stop);
            root.addEventListener('focusout', start);
        }

        show(index);
        start();
    };


    const navSearchForm = document.querySelector('[data-nav-search-form]');
    const navSearchInput = document.querySelector('[data-nav-search-input]');
    const navSuggestions = document.querySelector('[data-search-suggestions]');
    let navSearchTimer = null;
    let navSearchController = null;

    const hideNavSuggestions = () => {
        if (!navSuggestions || !navSearchInput) return;
        navSuggestions.hidden = true;
        navSuggestions.innerHTML = '';
        navSearchInput.setAttribute('aria-expanded', 'false');
    };

    const suggestionMoney = (value) => {
        const number = Number(value || 0);
        return `₱${number.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };

    const renderNavSuggestions = (items) => {
        if (!navSuggestions || !navSearchInput) return;

        if (!Array.isArray(items) || items.length === 0) {
            navSuggestions.innerHTML = '<div class="search-suggestion-empty">No matching W68 products found.</div>';
            navSuggestions.hidden = false;
            navSearchInput.setAttribute('aria-expanded', 'true');
            return;
        }

        navSuggestions.innerHTML = items.map((item) => {
            const description = String(item.description || '').replace(/[<>&"]/g, (char) => ({
                '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;'
            }[char]));
            const rawCode = String(item.product_code || '');
            const code = rawCode.replace(/[<>&"]/g, (char) => ({
                '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;'
            }[char]));
            const part = String(item.part_number || '').replace(/[<>&"]/g, (char) => ({
                '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;'
            }[char]));
            const brand = String(item.brand || '').replace(/[<>&"]/g, (char) => ({
                '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;'
            }[char]));
            const url = String(item.url || '#').replace(/"/g, '&quot;');

            const name = String(item.name || description).replace(/[<>&"]/g, (char) => ({
                '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;'
            }[char]));

            return `
                <a class="search-suggestion-item" href="${url}" role="option">
                    <div class="search-suggestion-copy">
                        <strong>${name}</strong>
                        <span><em>Product Code:</em> ${code || '—'}</span>
                        <span><em>Description:</em> ${description}</span>
                        <span><em>Brand:</em> ${brand || '—'}</span>
                        <span>${part ? `Part No. ${part}` : ''}</span>
                    </div>
                    <b>${suggestionMoney(item.price)}</b>
                </a>
            `;
        }).join('');

        navSuggestions.hidden = false;
        navSearchInput.setAttribute('aria-expanded', 'true');
    };

    const fetchNavSuggestions = async () => {
        if (!navSearchForm || !navSearchInput || !navSuggestions) return;

        const query = navSearchInput.value.trim();
        if (!query) {
            hideNavSuggestions();
            return;
        }

        if (navSearchController) navSearchController.abort();
        navSearchController = new AbortController();

        const endpoint = navSearchForm.dataset.suggestionsUrl;
        if (!endpoint) return;

        try {
            const url = new URL(endpoint, window.location.href);
            url.searchParams.set('q', query);

            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' },
                signal: navSearchController.signal
            });

            if (!response.ok) throw new Error('Suggestion request failed');
            renderNavSuggestions(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') hideNavSuggestions();
        }
    };

    navSearchInput?.addEventListener('input', () => {
        window.clearTimeout(navSearchTimer);
        navSearchTimer = window.setTimeout(fetchNavSuggestions, 180);
    });

    navSearchInput?.addEventListener('focus', () => {
        if (navSearchInput.value.trim()) fetchNavSuggestions();
    });

    navSearchForm?.addEventListener('submit', () => {
        hideNavSuggestions();
    });

    document.addEventListener('click', (event) => {
        if (navSearchForm && !navSearchForm.contains(event.target)) {
            hideNavSuggestions();
        }
    });

    const productModal = document.querySelector('[data-product-modal]');
    const productModalImage = productModal?.querySelector('[data-product-modal-image]');
    const productModalName = productModal?.querySelector('[data-product-modal-name]');
    const productModalBrand = productModal?.querySelector('[data-product-modal-brand]');
    const productModalPart = productModal?.querySelector('[data-product-modal-part]');
    const productModalApplication = productModal?.querySelector('[data-product-modal-application]');
    const productModalPosition = productModal?.querySelector('[data-product-modal-position]');
    const productModalCode = productModal?.querySelector('[data-product-modal-code]');
    const productModalSpecification = productModal?.querySelector('[data-product-modal-specification]');
    const productModalPrice = productModal?.querySelector('[data-product-modal-price]');
    const productModalAdd = productModal?.querySelector('[data-product-modal-add]');

    const closeProductModal = () => {
        if (!productModal) return;
        productModal.hidden = true;
        productModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    const openProductModal = (card) => {
        if (!productModal || !card) return;

        const data = card.dataset;
        const fallbackImage = document.querySelector('.brand img')?.src || '';

        if (productModalImage) {
            productModalImage.src = data.productImage || fallbackImage;
            productModalImage.alt = data.productName || 'Product';
            productModalImage.onerror = () => {
                productModalImage.onerror = null;
                if (fallbackImage) productModalImage.src = fallbackImage;
            };
        }

        if (productModalName) productModalName.textContent = data.productName || 'Product';
        if (productModalBrand) productModalBrand.textContent = data.productBrand || '—';
        if (productModalPart) productModalPart.textContent = data.productPartNumber || '—';
        if (productModalApplication) productModalApplication.textContent = data.productApplication || '—';
        if (productModalPosition) productModalPosition.textContent = data.productPosition || '—';
        if (productModalCode) productModalCode.textContent = data.productCode || '—';
        if (productModalSpecification) productModalSpecification.textContent = data.productSpecification || '—';
        if (productModalPrice) productModalPrice.textContent = money(data.productPrice || 0);

        if (productModalAdd) {
            productModalAdd.dataset.id = data.productId || '';
            productModalAdd.dataset.name = data.productName || 'Product';
            productModalAdd.dataset.price = data.productPrice || '0';
        }

        productModal.hidden = false;
        productModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        productModal.querySelector('[data-product-modal-close]')?.focus();
    };

    document.querySelectorAll('[data-product-card]').forEach((card) => {
        card.addEventListener('click', (event) => {
            if (event.target.closest('button, a, input, select, textarea, label')) return;
            openProductModal(card);
        });

        card.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            if (event.target.closest('button, a, input, select, textarea')) return;
            event.preventDefault();
            openProductModal(card);
        });
    });

    document.addEventListener('click', (event) => {
        const modalClose = event.target.closest('[data-product-modal-close]');
        if (modalClose) {
            closeProductModal();
            return;
        }

        const add = event.target.closest('[data-add-cart]');
        if (add) {
            addItem(add);
            return;
        }

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
        if (event.key !== 'Escape') return;

        if (productModal && !productModal.hidden) {
            closeProductModal();
            return;
        }

        if (drawer?.classList.contains('open')) {
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    });

    document.querySelectorAll('[data-carousel]').forEach(setupBrandCarousel);
    document.querySelectorAll('[data-slide-carousel]').forEach(setupSlideCarousel);
    renderCart();
})();
