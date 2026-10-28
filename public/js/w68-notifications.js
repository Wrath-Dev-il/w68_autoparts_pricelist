/* W68_PRICELIST_SOA_NOTIFICATION_20260918 */
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
    var tutorialSeenUrl = String(body.getAttribute('data-notifications-tutorial-seen-url') || '');
    if (!tutorialSeenUrl && listUrl) {
        tutorialSeenUrl = listUrl.replace(/\/$/, '') + '/tutorial-seen';
    }
    tutorialSeenUrl = normalizeAppUrl(tutorialSeenUrl);
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

    function isBillingEvent(type) {
        return type === 'BILL_CURRENT' || type === 'BILL_DUE_SOON';
    }

    function eventIcon(type) {
        if (type === 'ORDER_CANCELLED') return '×';
        if (type === 'WAYBILL_CREATED') return 'W';
        if (type === 'SOA_AUTO_SENT' || isBillingEvent(type)) return '₱';
        return '✓';
    }

    function eventClass(type) {
        if (type === 'ORDER_CANCELLED') return ' is-cancelled';
        if (type === 'WAYBILL_CREATED') return ' is-waybill';
        if (type === 'SOA_AUTO_SENT' || isBillingEvent(type)) return ' is-soa';
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
        if (item.event_type === 'SOA_AUTO_SENT') {
            meta.appendChild(make('span', '', 'PAYMENT REMINDER'));
            meta.appendChild(make('span', '', 'STATEMENT OF ACCOUNT'));
        } else if (item.event_type === 'BILL_CURRENT') {
            meta.appendChild(make('span', '', 'BILLING'));
            meta.appendChild(make('span', '', 'CURRENT BILL'));
        } else if (item.event_type === 'BILL_DUE_SOON') {
            meta.appendChild(make('span', '', 'PAYMENT REMINDER'));
            meta.appendChild(make('span', '', 'DUE SOON'));
        } else {
            meta.appendChild(make('span', '', 'ORDER: ' + (item.order_code || ('#' + item.order_id))));
            if (item.sales_number) meta.appendChild(make('span', '', 'SALES NOTE: ' + item.sales_number));
        }
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
        if (item.order_url) {
            var viewLabel = isBillingEvent(item.event_type) ? 'VIEW BILLS' : 'VIEW ORDER';
            var view = make('a', 'w68-notification-view', viewLabel);
            view.href = normalizeAppUrl(item.order_url);
            actions.appendChild(view);
        }

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
                ? (count + ' unread notification' + (count === 1 ? '' : 's'))
                : 'You are up to date.';
        }
    }

    function render(payload) {
        var items = payload && Array.isArray(payload.notifications) ? payload.notifications : [];
        applyUnreadState(payload ? payload.unread_count : 0);

        list.innerHTML = '';

        if (!items.length) {
            list.appendChild(make('div', 'w68-notification-empty', 'No Sales Note, waybill, cancelled-order, bill, or payment reminder notifications yet.'));
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
                if (payload && payload.show_tutorial) {
                    scheduleNotificationTutorial();
                }
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
        if (window.W68IsNotificationTutorialActive && window.W68IsNotificationTutorialActive()) {
            if (window.W68NotificationTutorialAdvance) {
                window.W68NotificationTutorialAdvance(event);
            }
            return;
        }
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        setModalOpen(false);
    }

    openButtons.forEach(function (button) {
        button.addEventListener('touchend', function (event) {
            if (window.W68IsNotificationTutorialActive && window.W68IsNotificationTutorialActive()) {
                if (window.W68NotificationTutorialAdvance) {
                    window.W68NotificationTutorialAdvance(event);
                }
                return;
            }
            lastTouchOpenAt = Date.now();
            openModal(event);
        }, { passive: false });

        button.addEventListener('click', function (event) {
            if (window.W68IsNotificationTutorialActive && window.W68IsNotificationTutorialActive()) {
                if (window.W68NotificationTutorialAdvance) {
                    window.W68NotificationTutorialAdvance(event);
                }
                return;
            }
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

    /* =========================================================
       W68_NOTIFICATION_TUTORIAL_20261028
       First-time Notification onboarding walkthrough.
       Shared across Home, Orders, and Discounts pages.
       1.) Teaches user about the Notification Bell
       2.) Auto-opens notification modal on next/tap, explains updates
       3.) Explains View Details & Mark as Read action buttons
       4.) Explains Mark All as Read & Dismissal
       ========================================================= */
    var tutorialActive = false;
    var tutorialTriggered = false;
    var tutorialCurrentStep = 0;
    var tutorialTarget = null;
    var tutorialRepositionTimer = null;
    var tutorialLastAdvanceAt = 0;

    function initNotificationTutorial() {
        var overlay = document.querySelector('[data-w68-notification-tutorial]');
        var spotlight = document.querySelector('[data-w68-notification-tutorial-spotlight]');
        var arrow = document.querySelector('[data-w68-notification-tutorial-arrow]');
        var robot = document.querySelector('[data-w68-notification-tutorial-robot]');
        var tutorialCard = document.querySelector('[data-w68-notification-tutorial-card]');
        var kickerNode = document.querySelector('[data-w68-notification-tutorial-kicker]');
        var titleNode = document.querySelector('[data-w68-notification-tutorial-title]');
        var bodyNode = document.querySelector('[data-w68-notification-tutorial-body]');
        var nextButton = document.querySelector('[data-w68-notification-tutorial-next]');
        var doneButton = document.querySelector('[data-w68-notification-tutorial-done]');
        var skipButton = document.querySelector('[data-w68-notification-tutorial-skip]');

        if (!overlay || !spotlight || !arrow || !robot || !titleNode || !bodyNode) {
            return;
        }

        if (spotlight.parentElement !== document.body) document.body.appendChild(spotlight);
        if (arrow.parentElement !== document.body) document.body.appendChild(arrow);
        if (robot.parentElement !== document.body) document.body.appendChild(robot);
        if (tutorialCard && tutorialCard.parentElement !== document.body) document.body.appendChild(tutorialCard);
        if (overlay.parentElement !== document.body) document.body.appendChild(overlay);

        hidePointer();
        robot.hidden = true;
        robot.style.display = 'none';
        if (tutorialCard) {
            tutorialCard.hidden = true;
            tutorialCard.style.display = 'none';
        }

        var robotImage = robot.querySelector('img');
        var robotGif = String(body.getAttribute('data-tutorial-robot-gif') || '');
        if (robotImage && robotGif && !robotImage.getAttribute('src')) {
            robotImage.src = robotGif;
        }

        function getBellTarget() {
            return document.querySelector('.w68-notification-bell')
                || document.querySelector('[data-notification-open]')
                || document.querySelector('.orders-notification-slot');
        }

        function getNotificationItemTarget() {
            return document.querySelector('.w68-notification-item')
                || document.querySelector('[data-notification-list]')
                || document.querySelector('.w68-notification-card');
        }

        function getNotificationActionsTarget() {
            var firstItem = document.querySelector('.w68-notification-item');
            if (firstItem) {
                var actions = firstItem.querySelector('.w68-notification-actions');
                if (actions) return actions;
                var view = firstItem.querySelector('.w68-notification-view');
                if (view) return view;
            }
            return document.querySelector('[data-notification-mark-read]')
                || document.querySelector('.w68-notification-view')
                || getNotificationItemTarget();
        }

        function getHeaderActionsTarget() {
            return document.querySelector('.w68-notification-header-actions')
                || document.querySelector('[data-notification-read-all]')
                || document.querySelector('.w68-notification-close')
                || document.querySelector('.w68-notification-header');
        }

        var steps = [
            {
                key: 'notification-bell',
                pointerLabel: 'BELL',
                kicker: 'W68 NOTIFICATIONS GUIDE • STEP 1 OF 4',
                title: '1. Notification Bell',
                body: 'This notification bell alerts you whenever you have updates on your orders, sales notes, waybills, and billing statements. Click the bell to open your updates.',
                borderRadius: '50%',
                target: getBellTarget,
                onEnter: function () {
                    setModalOpen(false);
                }
            },
            {
                key: 'notification-item',
                pointerLabel: 'UPDATE',
                kicker: 'W68 NOTIFICATIONS GUIDE • STEP 2 OF 4',
                title: '2. Order & Account Updates',
                body: 'Each notification displays real-time progress on your orders—including order confirmations, sales notes, waybill releases, and payment reminders.',
                borderRadius: '16px',
                target: getNotificationItemTarget,
                onEnter: function () {
                    setModalOpen(true);
                    loadNotifications(true);
                }
            },
            {
                key: 'notification-actions',
                pointerLabel: 'ACTIONS',
                kicker: 'W68 NOTIFICATIONS GUIDE • STEP 3 OF 4',
                title: '3. View Details & Mark Read',
                body: 'Click VIEW ORDER or VIEW BILLS to jump directly to your order details. Once you have seen the update, click MARK AS READ to clear the unread badge.',
                borderRadius: '14px',
                target: getNotificationActionsTarget,
                onEnter: function () {
                    setModalOpen(true);
                }
            },
            {
                key: 'header-actions',
                pointerLabel: 'HEADER',
                kicker: 'W68 NOTIFICATIONS GUIDE • STEP 4 OF 4',
                title: '4. Mark All as Read & Dismiss',
                body: 'Click MARK ALL AS READ to clear all notification badges at once, or tap the close button (×) anytime to close this notification panel.',
                borderRadius: '12px',
                target: getHeaderActionsTarget,
                onEnter: function () {
                    setModalOpen(true);
                }
            }
        ];

        function clearTarget() {
            if (tutorialTarget) {
                if (tutorialTarget instanceof Element) {
                    tutorialTarget.classList.remove('w68-notification-tutorial-target');
                } else if (tutorialTarget.elements && Array.isArray(tutorialTarget.elements)) {
                    tutorialTarget.elements.forEach(function (el) {
                        if (el instanceof Element) el.classList.remove('w68-notification-tutorial-target');
                    });
                }
            }
            document.querySelectorAll('.w68-notification-tutorial-target, .w68-notification-tutorial-highlight-part').forEach(function (el) {
                el.classList.remove('w68-notification-tutorial-target', 'w68-notification-tutorial-highlight-part');
            });
            tutorialTarget = null;
        }

        function hidePointer() {
            spotlight.hidden = true;
            spotlight.style.display = 'none';
            arrow.hidden = true;
            arrow.style.display = 'none';
        }

        function showPointer() {
            spotlight.hidden = false;
            spotlight.style.removeProperty('display');
            arrow.hidden = false;
            arrow.style.removeProperty('display');
        }

        function finishNotificationTutorial() {
            tutorialActive = false;
            clearTarget();
            hidePointer();
            overlay.hidden = true;
            overlay.style.display = 'none';
            overlay.setAttribute('aria-hidden', 'true');
            robot.hidden = true;
            robot.style.display = 'none';
            if (tutorialCard) {
                tutorialCard.hidden = true;
                tutorialCard.style.display = 'none';
                tutorialCard.style.removeProperty('top');
                tutorialCard.style.removeProperty('bottom');
            }
            if (robot) {
                robot.style.removeProperty('top');
                robot.style.removeProperty('bottom');
                robot.style.removeProperty('left');
                robot.style.removeProperty('right');
                robot.style.removeProperty('transform');
            }
            if (tutorialRepositionTimer !== null) {
                window.clearTimeout(tutorialRepositionTimer);
                tutorialRepositionTimer = null;
            }

            try {
                sessionStorage.setItem('w68_notification_tutorial_done', '1');
            } catch (_) {}

            var url = tutorialSeenUrl || (listUrl.replace(/\/$/, '') + '/tutorial-seen');
            if (url) {
                requestJson(url, {
                    method: 'POST',
                    credentials: 'same-origin'
                }).catch(function () {});
            }
        }

        function scrollTargetIntoView(target) {
            var el = (target instanceof Element) ? target : (target && target.node instanceof Element ? target.node : null);
            if (!el) return;
            try {
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } catch (_) {
                el.scrollIntoView();
            }
        }

        function positionTutorialCard(rect) {
            if (!tutorialCard) return;

            var viewportHeight = (window.visualViewport && window.visualViewport.height)
                || window.innerHeight
                || document.documentElement.clientHeight;

            var cardAtTop = false;

            if (rect && rect.top > viewportHeight * 0.45) {
                tutorialCard.style.top = 'max(14px, env(safe-area-inset-top, 14px))';
                tutorialCard.style.bottom = 'auto';
                cardAtTop = true;
            } else {
                tutorialCard.style.bottom = 'max(16px, env(safe-area-inset-bottom, 16px))';
                tutorialCard.style.top = 'auto';
            }

            if (robot) {
                var cardRect = tutorialCard.getBoundingClientRect();
                var robotWidth = robot.offsetWidth || 90;
                var robotHeight = robot.offsetHeight || 90;

                robot.style.transform = 'none';
                robot.style.left = Math.min(cardRect.right - robotWidth + 10, window.innerWidth - robotWidth - 4) + 'px';

                if (cardAtTop) {
                    robot.style.top = Math.min(cardRect.bottom - 10, viewportHeight - robotHeight - 4) + 'px';
                    robot.style.bottom = 'auto';
                } else {
                    robot.style.bottom = 'auto';
                    robot.style.top = Math.max(4, cardRect.top - robotHeight + 10) + 'px';
                }
            }
        }

        function positionPointer(target) {
            if (!tutorialActive || !target) {
                hidePointer();
                return;
            }

            var rect = (target instanceof Element)
                ? target.getBoundingClientRect()
                : (target && typeof target.top === 'number' ? target : null);

            if (!rect || rect.width <= 0 || rect.height <= 0) {
                hidePointer();
                return;
            }

            var viewportWidth = (window.visualViewport && window.visualViewport.width)
                || window.innerWidth
                || document.documentElement.clientWidth;
            var viewportHeight = (window.visualViewport && window.visualViewport.height)
                || window.innerHeight
                || document.documentElement.clientHeight;

            var step = steps[tutorialCurrentStep] || {};
            var pad = 6;

            showPointer();
            spotlight.style.left = Math.max(2, rect.left - pad) + 'px';
            spotlight.style.top = Math.max(2, rect.top - pad) + 'px';
            spotlight.style.width = Math.max(30, rect.width + (pad * 2)) + 'px';
            spotlight.style.height = Math.max(30, rect.height + (pad * 2)) + 'px';
            spotlight.style.borderRadius = step.borderRadius || '14px';

            var pointerWidth = 74;
            var pointerHeight = 48;
            var gap = 10;
            var left = rect.right + gap;
            var top = rect.top + (rect.height / 2) - (pointerHeight / 2);
            var side = 'right';

            if (left + pointerWidth > viewportWidth - 6) {
                left = rect.left - pointerWidth - gap;
                side = 'left';
            }

            if (left < 6) {
                left = Math.max(6, Math.min(viewportWidth - pointerWidth - 6, rect.left + (rect.width / 2) - (pointerWidth / 2)));
                top = rect.top - pointerHeight - gap;
                side = 'top';
            }

            if (top < 6) {
                top = Math.min(viewportHeight - pointerHeight - 6, rect.bottom + gap);
                side = 'bottom';
            }

            if (top + pointerHeight > viewportHeight - 6) {
                top = Math.max(6, rect.top - pointerHeight - gap);
                side = 'top';
            }

            arrow.setAttribute('data-pointer-side', side);
            arrow.style.left = Math.round(left) + 'px';
            arrow.style.top = Math.round(top) + 'px';
            arrow.style.width = pointerWidth + 'px';
            arrow.style.height = pointerHeight + 'px';
            arrow.style.transform = 'none';

            var label = step.pointerLabel || 'HERE';
            arrow.setAttribute('data-pointer-label', label);
        }

        function updateVisuals() {
            if (!tutorialActive) return;
            var step = steps[tutorialCurrentStep];
            if (!step) return;

            var target = typeof step.target === 'function' ? step.target() : step.target;
            if (!target) {
                hidePointer();
                positionTutorialCard(null);
                return;
            }

            var rect = (target instanceof Element)
                ? target.getBoundingClientRect()
                : (target && typeof target.top === 'number' ? target : null);

            positionPointer(target);
            positionTutorialCard(rect);
        }

        function scheduleReposition() {
            if (!tutorialActive) return;
            if (tutorialRepositionTimer !== null) {
                window.clearTimeout(tutorialRepositionTimer);
            }
            tutorialRepositionTimer = window.setTimeout(function () {
                tutorialRepositionTimer = null;
                updateVisuals();
            }, 60);
        }

        function renderStep() {
            clearTarget();

            var step = steps[tutorialCurrentStep];
            if (!step) {
                finishNotificationTutorial();
                return;
            }

            var isLast = tutorialCurrentStep === steps.length - 1;

            if (kickerNode) kickerNode.textContent = step.kicker || 'W68 NOTIFICATIONS GUIDE';
            if (titleNode) titleNode.textContent = step.title;
            if (bodyNode) bodyNode.textContent = step.body;

            if (nextButton) {
                nextButton.hidden = isLast;
                nextButton.style.display = isLast ? 'none' : '';
            }
            if (doneButton) {
                doneButton.hidden = !isLast;
                doneButton.style.display = isLast ? '' : 'none';
            }

            if (typeof step.onEnter === 'function') {
                step.onEnter();
            }

            overlay.hidden = false;
            overlay.style.removeProperty('display');
            overlay.setAttribute('aria-hidden', 'false');

            robot.hidden = false;
            robot.style.removeProperty('display');

            if (tutorialCard) {
                tutorialCard.hidden = false;
                tutorialCard.style.removeProperty('display');
            }

            window.setTimeout(function () {
                var target = typeof step.target === 'function' ? step.target() : step.target;
                if (target) {
                    tutorialTarget = target;
                    if (target instanceof Element) {
                        target.classList.add('w68-notification-tutorial-target');
                    } else if (target.elements && Array.isArray(target.elements)) {
                        target.elements.forEach(function (el) {
                            if (el instanceof Element) el.classList.add('w68-notification-tutorial-target');
                        });
                    }
                    scrollTargetIntoView(target);
                }
                updateVisuals();
            }, 100);
        }

        function nextStep() {
            if (!tutorialActive) return;
            if (tutorialCurrentStep >= steps.length - 1) {
                finishNotificationTutorial();
            } else {
                tutorialCurrentStep++;
                renderStep();
            }
        }

        function advanceTutorialFromTap(event) {
            if (!tutorialActive) return;
            var now = Date.now();
            if (now - tutorialLastAdvanceAt < 300) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
                }
                return;
            }
            tutorialLastAdvanceAt = now;

            var target = event && event.target;
            if (target && target.closest && target.closest('[data-w68-notification-tutorial-skip]')) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
                }
                finishNotificationTutorial();
                return;
            }

            if (event) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
            }
            nextStep();
        }

        function startNotificationTutorial() {
            tutorialActive = true;
            tutorialCurrentStep = 0;
            renderStep();
        }

        window.W68NotificationTutorialStart = startNotificationTutorial;
        window.W68NotificationTutorialDismiss = finishNotificationTutorial;
        window.W68NotificationTutorialAdvance = advanceTutorialFromTap;
        window.W68IsNotificationTutorialActive = function () {
            return tutorialActive;
        };

        window.addEventListener('click', advanceTutorialFromTap, true);
        window.addEventListener('touchend', advanceTutorialFromTap, { capture: true, passive: false });

        if (nextButton) {
            nextButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                nextStep();
            });
        }

        if (doneButton) {
            doneButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                finishNotificationTutorial();
            });
        }

        if (skipButton) {
            skipButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                finishNotificationTutorial();
            });
        }

        window.addEventListener('resize', scheduleReposition, { passive: true });
        window.addEventListener('scroll', scheduleReposition, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleReposition, { passive: true });
            window.visualViewport.addEventListener('scroll', scheduleReposition, { passive: true });
        }
    }

    function isOtherTutorialActive() {
        var homeTut = document.querySelector('[data-w68-first-login-tutorial]:not([hidden])');
        var ordersTut = document.querySelector('[data-w68-tutorial]:not([hidden])');
        var processTut = document.querySelector('[data-w68-process-order-tutorial]:not([hidden])');
        return !!(homeTut || ordersTut || processTut);
    }

    function scheduleNotificationTutorial() {
        if (tutorialTriggered || tutorialActive) return;
        try {
            if (sessionStorage.getItem('w68_notification_tutorial_done') === '1') {
                if (window.location.search.indexOf('tutorial=notification') === -1) {
                    return;
                }
            }
        } catch (_) {}

        if (isOtherTutorialActive()) {
            window.setTimeout(scheduleNotificationTutorial, 1500);
            return;
        }

        tutorialTriggered = true;
        if (window.W68NotificationTutorialStart) {
            window.W68NotificationTutorialStart();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotificationTutorial);
    } else {
        initNotificationTutorial();
    }

    if (window.location.search.indexOf('tutorial=notification') !== -1) {
        window.setTimeout(function () {
            scheduleNotificationTutorial();
        }, 500);
    }

    window.addEventListener('beforeunload', function () {
        if (pollTimer) window.clearInterval(pollTimer);
    });
})();
