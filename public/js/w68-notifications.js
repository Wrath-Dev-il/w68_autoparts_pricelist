(function () {
    'use strict';

    var body = document.body;
    if (!body) return;

    var listUrl = String(body.getAttribute('data-notifications-url') || '');
    var readAllUrl = String(body.getAttribute('data-notifications-read-all-url') || '');

    function appBasePath() {
        var path = String(window.location.pathname || '');
        var publicMarker = '/public/';
        var markerIndex = path.toLowerCase().indexOf(publicMarker);
        if (markerIndex >= 0) {
            return path.slice(0, markerIndex + '/public'.length);
        }

        if (/\/public$/i.test(path)) {
            return path;
        }

        return '';
    }

    function normalizeAppUrl(url) {
        var value = String(url || '');
        if (!value) return value;
        if (/^https?:\/\//i.test(value)) return value;

        var base = appBasePath();
        if (value.charAt(0) === '/') {
            if (base && value.indexOf(base + '/') !== 0 && value !== base) {
                return base + value;
            }
            return value;
        }

        return (base ? base : '') + '/' + value.replace(/^\/+/, '');
    }

    listUrl = normalizeAppUrl(listUrl);
    readAllUrl = normalizeAppUrl(readAllUrl);
    if (!listUrl) return;

    var modal = document.querySelector('[data-notification-modal]');
    var list = document.querySelector('[data-notification-list]');
    var summary = document.querySelector('[data-notification-summary]');
    var readAllButton = document.querySelector('[data-notification-read-all]');
    var openButtons = Array.prototype.slice.call(document.querySelectorAll('[data-notification-open]'));
    var closeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-notification-close]'));
    var badgeNodes = Array.prototype.slice.call(document.querySelectorAll('[data-notification-badge]'));
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrf ? String(csrf.getAttribute('content') || '') : '';
    var homeNav = document.querySelector('[data-customer-nav]');
    var lastTouchOpenAt = 0;
    var pollTimer = null;
    var loading = false;

    if (!modal || !list) return;

    function updateVisualViewport() {
        var viewport = window.visualViewport;
        var width = Math.max(320, Math.round(viewport ? viewport.width : (window.innerWidth || 0)));
        var height = Math.max(320, Math.round(viewport ? viewport.height : (window.innerHeight || 0)));
        var top = Math.max(0, Math.round(viewport ? viewport.offsetTop : 0));
        var left = Math.max(0, Math.round(viewport ? viewport.offsetLeft : 0));

        document.documentElement.style.setProperty('--w68-notification-vv-width', width + 'px');
        document.documentElement.style.setProperty('--w68-notification-vv-height', height + 'px');
        document.documentElement.style.setProperty('--w68-notification-vv-top', top + 'px');
        document.documentElement.style.setProperty('--w68-notification-vv-left', left + 'px');
        modal.classList.add('is-visual-viewport');
    }

    function setModalOpen(open) {
        updateVisualViewport();
        modal.hidden = !open;
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        body.classList.toggle('w68-notifications-open', !!open);
    }

    function requestJson(url, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.cache = 'no-store';
        options.headers.Accept = 'application/json';
        options.headers['X-Requested-With'] = 'XMLHttpRequest';
        if (csrfToken && options.method && String(options.method).toUpperCase() !== 'GET') {
            options.headers['X-CSRF-TOKEN'] = csrfToken;
        }

        return fetch(url, options).then(function (response) {
            return response.text().then(function (text) {
                var payload = {};
                if (text) {
                    try {
                        payload = JSON.parse(text);
                    } catch (ignore) {
                        payload = {};
                    }
                }

                if (!response.ok) {
                    var message = payload && payload.message
                        ? payload.message
                        : ('Unable to load notifications. HTTP ' + response.status + '.');
                    throw new Error(message);
                }

                if (!payload || payload.ok === false) {
                    throw new Error((payload && payload.message) || 'Unable to load notifications.');
                }

                return payload;
            });
        });
    }

    function eventIcon(type) {
        if (type === 'ORDER_CANCELLED') return '×';
        if (type === 'WAYBILL_CREATED') return 'W';
        return '✓';
    }

    function eventClass(type) {
        if (type === 'ORDER_CANCELLED') return ' is-cancelled';
        if (type === 'WAYBILL_CREATED') return ' is-waybill';
        return '';
    }

    function make(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (typeof text !== 'undefined' && text !== null) node.textContent = String(text);
        return node;
    }

    function renderNotification(item) {
        var article = make('article', 'w68-notification-item' + (item.is_read ? '' : ' is-unread') + eventClass(item.event_type));
        article.setAttribute('data-notification-id', String(item.id));

        article.appendChild(make('div', 'w68-notification-item-icon', eventIcon(item.event_type)));

        var main = make('div', 'w68-notification-item-main');
        var head = make('div', 'w68-notification-item-head');
        head.appendChild(make('strong', '', item.title || 'Order Update'));
        var time = make('time', '', item.event_at || '');
        head.appendChild(time);
        main.appendChild(head);

        var meta = make('div', 'w68-notification-meta');
        meta.appendChild(make('span', '', 'ORDER: ' + (item.order_code || ('#' + item.order_id))));
        if (item.sales_number) meta.appendChild(make('span', '', 'SALES NOTE: ' + item.sales_number));
        main.appendChild(meta);

        main.appendChild(make('p', 'w68-notification-message', item.message || ''));

        var movement = make('div', 'w68-notification-movement');
        var steps = Array.isArray(item.movement) ? item.movement : [];
        steps.forEach(function (step, index) {
            if (index > 0) movement.appendChild(make('span', 'w68-notification-movement-arrow', '→'));
            movement.appendChild(make('span', 'w68-notification-movement-step', step));
        });
        main.appendChild(movement);

        var actions = make('div', 'w68-notification-actions');
        var view = make('a', 'w68-notification-view', 'VIEW ORDER');
        view.href = normalizeAppUrl(item.order_url || '#');
        actions.appendChild(view);

        if (!item.is_read) {
            var mark = make('button', 'w68-notification-mark-read', 'MARK AS READ');
            mark.type = 'button';
            mark.setAttribute('data-notification-mark-read', String(item.id));
            actions.appendChild(mark);
        } else {
            actions.appendChild(make('span', 'w68-notification-read-label', 'READ'));
        }

        main.appendChild(actions);
        article.appendChild(main);
        return article;
    }

    function applyUnreadState(unreadCount) {
        var count = Math.max(0, Number(unreadCount || 0));

        badgeNodes.forEach(function (badge) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count <= 0;
        });

        openButtons.forEach(function (button) {
            button.classList.toggle('has-unread', count > 0);
            button.setAttribute('aria-label', count > 0 ? ('Open notifications, ' + count + ' unread') : 'Open notifications');
        });

        if (homeNav) {
            homeNav.classList.toggle('has-unread-notifications', count > 0);
        }

        if (readAllButton) readAllButton.hidden = count <= 0;
        if (summary) {
            summary.textContent = count > 0
                ? (count + ' unread order update' + (count === 1 ? '' : 's'))
                : 'You are up to date.';
        }
    }

    function render(payload) {
        var items = payload && Array.isArray(payload.notifications) ? payload.notifications : [];
        applyUnreadState(payload ? payload.unread_count : 0);

        list.innerHTML = '';

        if (!items.length) {
            list.appendChild(make('div', 'w68-notification-empty', 'No Sales Note, waybill, or cancelled-order notifications yet.'));
            return;
        }

        items.forEach(function (item) {
            list.appendChild(renderNotification(item));
        });
    }

    function loadNotifications(showError) {
        if (loading) return Promise.resolve(null);
        loading = true;

        return requestJson(listUrl, { method: 'GET', credentials: 'same-origin' })
            .then(function (payload) {
                render(payload);
                loading = false;
                return payload;
            }, function (error) {
                loading = false;
                if (showError) {
                    list.innerHTML = '';
                    list.appendChild(make('div', 'w68-notification-error', error.message || 'Unable to load notifications.'));
                    if (summary) summary.textContent = 'Notification check failed.';
                }
                return null;
            });
    }

    function openModal(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        setModalOpen(true);
        loadNotifications(true);
    }

    function closeModal(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        setModalOpen(false);
    }

    openButtons.forEach(function (button) {
        button.addEventListener('touchend', function (event) {
            lastTouchOpenAt = Date.now();
            openModal(event);
        }, { passive: false });

        button.addEventListener('click', function (event) {
            if (Date.now() - lastTouchOpenAt < 650) {
                event.preventDefault();
                return;
            }
            openModal(event);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeModal);
        button.addEventListener('touchend', closeModal, { passive: false });
    });

    list.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-notification-mark-read]') : null;
        if (!button) return;

        event.preventDefault();
        var id = String(button.getAttribute('data-notification-mark-read') || '');
        if (!id) return;

        button.disabled = true;
        button.textContent = 'SAVING...';

        requestJson(listUrl.replace(/\/$/, '') + '/' + encodeURIComponent(id) + '/read', {
            method: 'POST',
            credentials: 'same-origin'
        }).then(function () {
            return loadNotifications(true);
        }).catch(function () {
            button.disabled = false;
            button.textContent = 'MARK AS READ';
        });
    });

    if (readAllButton && readAllUrl) {
        readAllButton.addEventListener('click', function (event) {
            event.preventDefault();
            readAllButton.disabled = true;
            readAllButton.textContent = 'SAVING...';

            requestJson(readAllUrl, {
                method: 'POST',
                credentials: 'same-origin'
            }).then(function () {
                return loadNotifications(true);
            }).then(function () {
                readAllButton.disabled = false;
                readAllButton.textContent = 'MARK ALL AS READ';
            }, function () {
                readAllButton.disabled = false;
                readAllButton.textContent = 'MARK ALL AS READ';
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal(event);
    });

    window.addEventListener('resize', updateVisualViewport, { passive: true });
    window.addEventListener('orientationchange', function () {
        window.setTimeout(updateVisualViewport, 80);
    }, { passive: true });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', updateVisualViewport, { passive: true });
        window.visualViewport.addEventListener('scroll', updateVisualViewport, { passive: true });
    }

    loadNotifications(false);

    pollTimer = window.setInterval(function () {
        if (!document.hidden) loadNotifications(false);
    }, 15000);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) loadNotifications(false);
    });

    window.addEventListener('beforeunload', function () {
        if (pollTimer) window.clearInterval(pollTimer);
    });
})();
