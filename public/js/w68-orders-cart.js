(function () {
    'use strict';

    var body = document.body;
    if (!body) return;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var stateUrl = body.getAttribute('data-cart-state-url') || '';
    var itemBaseUrl = body.getAttribute('data-cart-item-base-url') || '';
    var selectionUrl = body.getAttribute('data-cart-selection-url') || '';

    var drawer = document.querySelector('[data-orders-cart-drawer]');
    var itemsNode = document.querySelector('[data-orders-cart-items]');
    var emptyNode = document.querySelector('[data-orders-cart-empty]');
    var totalNode = document.querySelector('[data-orders-cart-total]');
    var selectedTotalNode = document.querySelector('[data-orders-cart-selected-total]');
    var selectedCountNode = document.querySelector('[data-orders-cart-selected-count]');
    var selectAllNode = document.querySelector('[data-orders-cart-select-all]');
    var countNodes = document.querySelectorAll('[data-orders-cart-count]');
    var processOrderLink = document.querySelector('.orders-cart-shop');
    var processOrderUrl = stateUrl.replace(/\/home\/cart\/state(?:\?.*)?$/, '/process-order');

    // W68_CART_PRINT_LIST_20261012
    var cartPrintButton = document.querySelector('[data-cart-print-open]');
    var cartPrintModal = document.querySelector('[data-cart-print-modal]');
    var cartPrintSheet = document.querySelector('[data-cart-print-sheet]');
    var cartPrintAllButton = document.querySelector('[data-cart-print-all]');
    var cartPrintSelectedButton = document.querySelector('[data-cart-print-selected]');
    var cartPrintCloseButtons = document.querySelectorAll('[data-cart-print-close]');

    if (!drawer || !itemsNode) return;

    var items = [];
    var loading = false;
    var lastOpenAt = 0;
    var pendingMutationCount = 0;

    // W68_ORDERS_CART_PROCESS_ORDER_FIX_20261007
    // The Orders drawer previously used the GO TO SHOP link, which pointed to
    // /home. Reuse the current portal base path and send selected cart items to
    // the same Process Order review page used by the Home cart.
    if (processOrderLink && processOrderUrl) {
        processOrderLink.setAttribute('href', processOrderUrl);
        processOrderLink.textContent = 'PROCESS ORDER';
    }

    // Orders receives the current account cart directly from Laravel.
    // Render it immediately so the drawer is never empty just because
    // Safari/iPad delayed or blocked the cart-state AJAX refresh.
    var initialPayload = document.getElementById('w68-orders-cart-payload');
    if (initialPayload) {
        try {
            var decodedInitial = JSON.parse(initialPayload.textContent || '[]');
            if (Array.isArray(decodedInitial)) items = decodedInitial;
        } catch (initialError) {
            if (window.console && console.error) console.error('Unable to read the initial W68 Orders cart.', initialError);
        }
    }

    function money(value) {
        return Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function safeQty(value) {
        return Math.max(1, Math.min(9999, Math.round(Number(value) || 1)));
    }

    function unitPrice(item) {
        var original = Number(item && item.price ? item.price : 0);
        var discounted = item && item.discountedPrice !== undefined && item.discountedPrice !== null
            ? Number(item.discountedPrice)
            : original;
        var percent = Number(item && item.discountPercent ? item.discountPercent : 0);
        return (percent > 0 || discounted < original) ? discounted : original;
    }

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function request(url, method, payload, success, failure) {
        if (!url) {
            if (failure) failure(new Error('Cart URL is missing.'));
            return;
        }

        var requestMethod = String(method || 'GET').toUpperCase();
        var tracksMutation = requestMethod !== 'GET';
        var mutationFinished = false;

        if (tracksMutation) pendingMutationCount += 1;

        function finishMutation() {
            if (!tracksMutation || mutationFinished) return;
            mutationFinished = true;
            pendingMutationCount = Math.max(0, pendingMutationCount - 1);
        }

        var xhr = new XMLHttpRequest();
        xhr.open(requestMethod, url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (csrfToken) xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        if (payload !== null && payload !== undefined) {
            xhr.setRequestHeader('Content-Type', 'application/json');
        }

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            finishMutation();

            if (xhr.status >= 200 && xhr.status < 300) {
                var data = null;
                if (xhr.responseText) {
                    try { data = JSON.parse(xhr.responseText); } catch (ignore) {}
                }
                if (success) success(data);
                return;
            }

            var message = 'Cart request failed (' + xhr.status + ').';
            try {
                var errorData = JSON.parse(xhr.responseText || '{}');
                if (errorData.message) message = errorData.message;
                if (errorData.errors) {
                    for (var key in errorData.errors) {
                        if (!Object.prototype.hasOwnProperty.call(errorData.errors, key)) continue;
                        var err = errorData.errors[key];
                        if (err && err.length) {
                            message = err[0];
                            break;
                        }
                    }
                }
            } catch (ignore2) {}
            if (failure) failure(new Error(message));
        };

        xhr.onerror = function () {
            finishMutation();
            if (failure) failure(new Error('Unable to connect to the cart.'));
        };

        xhr.send(payload === null || payload === undefined ? null : JSON.stringify(payload));
    }

    function render() {
        itemsNode.innerHTML = '';
        if (items.length === 0) drawer.classList.add('is-empty');
        else drawer.classList.remove('is-empty');
        if (emptyNode) emptyNode.hidden = items.length !== 0;

        var total = 0;
        var selectedTotal = 0;
        var selectedCount = 0;

        items.forEach(function (item) {
            item.qty = safeQty(item.qty);
            var unit = unitPrice(item);
            var lineTotal = unit * item.qty;
            total += lineTotal;

            if (item.selected) {
                selectedCount += 1;
                selectedTotal += lineTotal;
            }

            var row = document.createElement('article');
            row.className = 'orders-cart-item';
            row.setAttribute('data-product-id', String(item.id));

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = Boolean(item.selected);
            checkbox.setAttribute('aria-label', 'Select ' + (item.description || item.productCode || 'cart item'));
            checkbox.addEventListener('change', function () {
                var previous = item.selected;
                item.selected = checkbox.checked;
                render();
                request(itemBaseUrl + '/' + encodeURIComponent(item.id), 'PATCH', { selected: item.selected }, null, function (error) {
                    item.selected = previous;
                    render();
                    alert(error.message);
                });
            });

            var image = document.createElement('img');
            image.src = item.image || '';
            image.alt = item.description || item.productCode || 'Product';

            var main = document.createElement('div');
            main.className = 'orders-cart-item-main';

            var code = document.createElement('div');
            code.className = 'orders-cart-item-code';
            code.textContent = item.productCode || '—';

            var title = document.createElement('h3');
            title.textContent = item.description || '—';

            var meta = document.createElement('div');
            meta.className = 'orders-cart-item-meta';
            meta.innerHTML =
                '<span><b>Part No.</b> ' + escapeHtml(item.partNumber || '—') + '</span>' +
                '<span><b>Application</b> ' + escapeHtml(item.application || '—') + '</span>' +
                '<span><b>Position</b> ' + escapeHtml(item.position || '—') + '</span>' +
                '<span><b>Brand</b> ' + escapeHtml(item.brand || '—') + '</span>';

            var priceRow = document.createElement('div');
            priceRow.className = 'orders-cart-price';
            var original = Number(item.price || 0);
            var discounted = item.discountedPrice !== undefined && item.discountedPrice !== null ? Number(item.discountedPrice) : original;
            var discount = Number(item.discountPercent || 0);

            if (discount > 0 || discounted < original) {
                var old = document.createElement('span');
                old.className = 'old';
                old.textContent = money(original);
                priceRow.appendChild(old);
            }

            var current = document.createElement('span');
            current.className = 'current';
            current.textContent = money(unit);
            priceRow.appendChild(current);

            if (discount > 0) {
                var off = document.createElement('small');
                off.textContent = discount.toFixed(2) + '% DISCOUNT';
                priceRow.appendChild(off);
            }

            var actions = document.createElement('div');
            actions.className = 'orders-cart-item-actions';

            var minus = document.createElement('button');
            minus.type = 'button';
            minus.textContent = '−';
            minus.setAttribute('aria-label', 'Decrease quantity');

            var input = document.createElement('input');
            input.type = 'number';
            input.min = '1';
            input.max = '9999';
            input.setAttribute('inputmode', 'numeric');
            input.value = String(item.qty);

            var plus = document.createElement('button');
            plus.type = 'button';
            plus.textContent = '+';
            plus.setAttribute('aria-label', 'Increase quantity');

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove';
            remove.textContent = 'REMOVE';

            function saveQty(newQty) {
                var previous = item.qty;
                item.qty = safeQty(newQty);
                render();
                request(itemBaseUrl + '/' + encodeURIComponent(item.id), 'PATCH', { quantity: item.qty }, null, function (error) {
                    item.qty = previous;
                    render();
                    alert(error.message);
                });
            }

            minus.addEventListener('click', function () { saveQty(item.qty - 1); });
            plus.addEventListener('click', function () { saveQty(item.qty + 1); });
            input.addEventListener('change', function () { saveQty(input.value); });
            remove.addEventListener('click', function () {
                if (!confirm('Remove ' + (item.description || item.productCode || 'this item') + ' from your cart?')) return;
                request(itemBaseUrl + '/' + encodeURIComponent(item.id), 'DELETE', null, function () {
                    items = items.filter(function (cartItem) { return String(cartItem.id) !== String(item.id); });
                    render();
                }, function (error) {
                    alert(error.message);
                });
            });

            actions.appendChild(minus);
            actions.appendChild(input);
            actions.appendChild(plus);
            actions.appendChild(remove);

            var line = document.createElement('div');
            line.className = 'orders-cart-line-total';
            line.textContent = 'TOTAL ' + money(lineTotal);

            main.appendChild(code);
            main.appendChild(title);
            main.appendChild(meta);
            main.appendChild(priceRow);
            main.appendChild(actions);
            main.appendChild(line);
            row.appendChild(checkbox);
            row.appendChild(image);
            row.appendChild(main);
            itemsNode.appendChild(row);
        });

        for (var i = 0; i < countNodes.length; i += 1) countNodes[i].textContent = String(items.length);
        if (totalNode) totalNode.textContent = money(total);
        if (selectedTotalNode) selectedTotalNode.textContent = money(selectedTotal);
        if (selectedCountNode) selectedCountNode.textContent = selectedCount + ' selected';
        if (selectAllNode) {
            selectAllNode.checked = items.length > 0 && selectedCount === items.length;
            selectAllNode.indeterminate = selectedCount > 0 && selectedCount < items.length;
        }
        if (processOrderLink) {
            processOrderLink.setAttribute('aria-disabled', selectedCount > 0 ? 'false' : 'true');
        }
        if (cartPrintButton) cartPrintButton.disabled = items.length === 0;
        if (cartPrintSelectedButton) cartPrintSelectedButton.disabled = selectedCount === 0;
    }

    function load() {
        if (loading || !stateUrl) return;
        loading = true;
        request(stateUrl, 'GET', null, function (data) {
            // Only replace the server-rendered cart when the response actually
            // contains a cart array. This prevents a malformed/redirected iPad
            // response from wiping the items that Laravel already loaded.
            if (data && Array.isArray(data.items)) {
                items = data.items;
            }
            loading = false;
            render();
        }, function (error) {
            loading = false;
            render();
            if (window.console && console.error) console.error('Unable to refresh the W68 cart on Orders.', error);
        });
    }

    function openCart(event) {
        if (event) {
            if (event.preventDefault) event.preventDefault();
            if (event.stopPropagation) event.stopPropagation();
        }

        var now = Date.now ? Date.now() : new Date().getTime();
        if (now - lastOpenAt < 250 && drawer.classList.contains('open')) return false;
        lastOpenAt = now;

        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        body.classList.add('orders-cart-open');
        load();
        return false;
    }

    function closeCart(event) {
        if (event) {
            if (event.preventDefault) event.preventDefault();
            if (event.stopPropagation) event.stopPropagation();
        }
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        body.classList.remove('orders-cart-open');
        return false;
    }

    function cartItemHasDiscount(item) {
        var original = Number(item && item.price ? item.price : 0);
        var discounted = item && item.discountedPrice !== undefined && item.discountedPrice !== null
            ? Number(item.discountedPrice)
            : original;
        var discount = Number(item && item.discountPercent ? item.discountPercent : 0);

        return discount > 0 || (original > 0 && discounted >= 0 && discounted < original);
    }

    function cartPrintLessPercent(item) {
        var stored = Number(item && item.discountPercent ? item.discountPercent : 0);
        if (isFinite(stored) && stored > 0) return stored;

        var original = Number(item && item.price ? item.price : 0);
        var effective = unitPrice(item);
        if (original > 0 && effective >= 0 && effective < original) {
            return ((original - effective) / original) * 100;
        }

        return 0;
    }

    function closeCartPrintModal(event) {
        if (event) {
            if (event.preventDefault) event.preventDefault();
            if (event.stopPropagation) event.stopPropagation();
        }
        if (!cartPrintModal) return false;
        cartPrintModal.hidden = true;
        cartPrintModal.setAttribute('aria-hidden', 'true');
        return false;
    }

    function openCartPrintModal(event) {
        if (event) {
            if (event.preventDefault) event.preventDefault();
            if (event.stopPropagation) event.stopPropagation();
        }

        if (!items.length || !cartPrintModal) {
            if (!items.length) alert('Your cart is empty.');
            return false;
        }

        if (cartPrintSelectedButton) {
            cartPrintSelectedButton.disabled = !items.some(function (item) {
                return Boolean(item.selected);
            });
        }

        cartPrintModal.hidden = false;
        cartPrintModal.setAttribute('aria-hidden', 'false');
        return false;
    }

    function clearCartPrintSheet() {
        body.classList.remove('w68-cart-printing');
        if (!cartPrintSheet) return;
        cartPrintSheet.hidden = true;
        cartPrintSheet.setAttribute('aria-hidden', 'true');
        cartPrintSheet.innerHTML = '';
    }

    function buildCartPrintSheet(printItems) {
        if (!cartPrintSheet) return false;

        var showLess = printItems.some(function (item) {
            return cartItemHasDiscount(item);
        });
        var total = 0;
        var rows = '';

        printItems.forEach(function (item) {
            var qty = safeQty(item.qty);
            var subtotal = unitPrice(item) * qty;
            var less = cartPrintLessPercent(item);
            total += subtotal;

            rows +=
                '<tr>' +
                    '<td>' + escapeHtml(item.productCode || '—') + '</td>' +
                    '<td>' + escapeHtml(item.partNumber || '—') + '</td>' +
                    '<td class="cart-print-description">' + escapeHtml(item.description || '—') + '</td>' +
                    '<td class="cart-print-number">' + qty + '</td>' +
                    '<td class="cart-print-number">' + money(item.price) + '</td>' +
                    (showLess
                        ? '<td class="cart-print-number">' + (less > 0 ? money(less) + '%' : '') + '</td>'
                        : '') +
                    '<td class="cart-print-number">' + money(subtotal) + '</td>' +
                '</tr>';
        });

        var totalLabelColspan = showLess ? 6 : 5;

        cartPrintSheet.innerHTML =
            '<header class="cart-print-brand">' +
                '<strong>ERW</strong>' +
                '<span>SPECIAL STORE</span>' +
            '</header>' +
            '<table class="cart-print-table">' +
                '<thead><tr>' +
                    '<th>PRODUCT CODE</th>' +
                    '<th>PART NO.</th>' +
                    '<th>DESCRIPTION</th>' +
                    '<th>QTY</th>' +
                    '<th>UNIT PRICE</th>' +
                    (showLess ? '<th>LESS</th>' : '') +
                    '<th>SUB TOTAL</th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
                '<tfoot><tr class="cart-print-total-row">' +
                    '<td colspan="' + totalLabelColspan + '">TOTAL</td>' +
                    '<td class="cart-print-number">' + money(total) + '</td>' +
                '</tr></tfoot>' +
            '</table>';

        return true;
    }

    function printCartList(selectedOnly, event) {
        if (event) {
            if (event.preventDefault) event.preventDefault();
            if (event.stopPropagation) event.stopPropagation();
        }

        var printItems = selectedOnly
            ? items.filter(function (item) { return Boolean(item.selected); })
            : items.slice();

        if (!printItems.length) {
            alert(selectedOnly
                ? 'Select at least one cart item before printing selected items.'
                : 'Your cart is empty.');
            return false;
        }

        if (!buildCartPrintSheet(printItems)) return false;

        closeCartPrintModal();
        cartPrintSheet.hidden = false;
        cartPrintSheet.setAttribute('aria-hidden', 'false');
        body.classList.add('w68-cart-printing');

        var cleanup = function () {
            clearCartPrintSheet();
        };
        window.addEventListener('afterprint', cleanup, { once: true });

        try {
            window.print();
        } catch (error) {
            window.removeEventListener('afterprint', cleanup);
            clearCartPrintSheet();
            alert('Unable to open the print dialog on this device.');
        }

        return false;
    }

    function bindCartPrintTap(node, handler) {
        if (!node || typeof handler !== 'function') return;

        var lastTouchAt = 0;

        node.addEventListener('touchend', function (event) {
            lastTouchAt = Date.now ? Date.now() : new Date().getTime();
            event.preventDefault();
            event.stopPropagation();
            handler(event);
        }, { passive: false });

        node.addEventListener('click', function (event) {
            var now = Date.now ? Date.now() : new Date().getTime();
            if (now - lastTouchAt < 650) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }
            handler(event);
        }, false);
    }

    bindCartPrintTap(cartPrintButton, openCartPrintModal);
    bindCartPrintTap(cartPrintAllButton, function (event) {
        printCartList(false, event);
    });
    bindCartPrintTap(cartPrintSelectedButton, function (event) {
        if (!cartPrintSelectedButton.disabled) printCartList(true, event);
    });
    for (var pc = 0; pc < cartPrintCloseButtons.length; pc += 1) {
        bindCartPrintTap(cartPrintCloseButtons[pc], closeCartPrintModal);
    }

    function openProcessOrder(event) {
        if (!processOrderLink || !processOrderUrl) return;
        if (event && event.preventDefault) event.preventDefault();
        if (event && event.stopPropagation) event.stopPropagation();

        if (!items.some(function (item) { return Boolean(item.selected); })) {
            alert('Select at least one cart item before processing the order.');
            return false;
        }

        processOrderLink.setAttribute('aria-busy', 'true');

        var waitStartedAt = Date.now ? Date.now() : new Date().getTime();
        var continueToReview = function () {
            var now = Date.now ? Date.now() : new Date().getTime();
            if (pendingMutationCount > 0 && now - waitStartedAt < 5000) {
                window.setTimeout(continueToReview, 40);
                return;
            }

            processOrderLink.removeAttribute('aria-busy');

            if (!items.some(function (item) { return Boolean(item.selected); })) {
                alert('No cart item is selected for processing.');
                return;
            }

            window.location.href = processOrderUrl;
        };

        continueToReview();
        return false;
    }

    if (processOrderLink) {
        processOrderLink.addEventListener('click', openProcessOrder, false);
    }

    window.W68OrdersCartOpen = openCart;
    window.W68OrdersCartClose = closeCart;
    window.W68OrdersCart = { open: openCart, close: closeCart, reload: load };

    var openButtons = document.querySelectorAll('[data-orders-cart-open]');
    for (var b = 0; b < openButtons.length; b += 1) {
        openButtons[b].addEventListener('touchstart', openCart, false);
        openButtons[b].addEventListener('click', openCart, false);
    }

    var closeButtons = document.querySelectorAll('[data-orders-cart-close]');
    for (var c = 0; c < closeButtons.length; c += 1) {
        closeButtons[c].addEventListener('touchstart', closeCart, false);
        closeButtons[c].addEventListener('click', closeCart, false);
    }

    // Close the side cart when the user taps/clicks outside the panel.
    // Keep the opener excluded so iPad Safari does not close it on the same tap.
    function closeFromOutside(event) {
        if (!drawer.classList.contains('open')) return;
        var target = event.target;
        if (target && target.closest && target.closest('.orders-cart-panel')) return;
        if (target && target.closest && target.closest('[data-orders-cart-open]')) return;
        closeCart(event);
    }

    drawer.addEventListener('touchend', closeFromOutside, false);
    drawer.addEventListener('click', closeFromOutside, false);

    if (selectAllNode) {
        selectAllNode.addEventListener('change', function () {
            var selected = selectAllNode.checked;
            var previous = items.map(function (item) { return item.selected; });
            items.forEach(function (item) { item.selected = selected; });
            render();

            request(selectionUrl, 'PUT', { selected: selected }, null, function (error) {
                items.forEach(function (item, index) { item.selected = previous[index]; });
                render();
                alert(error.message);
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (!(event.key === 'Escape' || event.keyCode === 27)) return;

        if (cartPrintModal && !cartPrintModal.hidden) {
            closeCartPrintModal(event);
            return;
        }

        if (drawer.classList.contains('open')) closeCart(event);
    });

    render();
    load();
}());
