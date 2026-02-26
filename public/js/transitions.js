(function () {
    const body = document.body;
    const DURATION_MS = 250;
    const INCOMING_KEY = 'incoming';
    const SKIP_ENTER_ANIMATION_KEY = 'page-skip-enter-animation';
    const ENTER_CLASSES = [
        'page-enter-from-right',
        'page-enter-from-left',
        'page-enter-from-bottom',
        'page-enter-from-top'
    ];

    if (!body) {
        return;
    }

    function applyIncomingTransition() {
        if (sessionStorage.getItem(SKIP_ENTER_ANIMATION_KEY) === '1') {
            sessionStorage.removeItem(SKIP_ENTER_ANIMATION_KEY);
            sessionStorage.removeItem(INCOMING_KEY);
            body.classList.remove(
                'page-enter-none',
                'page-enter-from-right',
                'page-enter-from-left',
                'page-enter-from-bottom',
                'page-enter-from-top'
            );
            body.style.opacity = '1';
            body.style.transform = '';
            return;
        }

        const incoming = sessionStorage.getItem(INCOMING_KEY) || 'right';
        sessionStorage.removeItem(INCOMING_KEY);

        let enterClass = 'page-enter-from-right';
        if (incoming === 'left') {
            enterClass = 'page-enter-from-left';
        } else if (incoming === 'bottom') {
            enterClass = 'page-enter-from-bottom';
        } else if (incoming === 'top') {
            enterClass = 'page-enter-from-top';
        }

        const cleanupEnter = function () {
            body.classList.remove(
                'page-enter-from-right',
                'page-enter-from-left',
                'page-enter-from-bottom',
                'page-enter-from-top'
            );
            body.style.opacity = '1';
            body.style.transform = '';
            body.removeEventListener('animationend', onEnterAnimationEnd);
        };

        const onEnterAnimationEnd = function (event) {
            if (event.target !== body) {
                return;
            }

            if (!ENTER_CLASSES.includes(enterClass)) {
                return;
            }

            cleanupEnter();
        };

        body.classList.add(enterClass);
        body.addEventListener('animationend', onEnterAnimationEnd);
        window.setTimeout(cleanupEnter, DURATION_MS + 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyIncomingTransition, { once: true });
    } else {
        applyIncomingTransition();
    }

    function canInterceptLink(link) {
        if (!link || !link.href) {
            return false;
        }

        const href = link.getAttribute('href') || '';
        if (href.startsWith('#') || href.startsWith('javascript:')) {
            return false;
        }

        if (link.target === '_blank') {
            return false;
        }

        if (link.hasAttribute('download')) {
            return false;
        }

        let url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return false;
        }

        if (url.origin !== window.location.origin) {
            return false;
        }

        return true;
    }

    function shouldSkipTransition(element) {
        if (!element) {
            return false;
        }

        if (element.hasAttribute('data-no-transition')) {
            return true;
        }

        const transitionValue = (element.getAttribute('data-transition') || '').trim();
        return transitionValue === 'none';
    }

    function getTransitionType(element) {
        if (!element) {
            return '';
        }

        const value = (element.getAttribute('data-transition') || '').trim();
        if (value === 'back' || value === 'up' || value === 'down') {
            return value;
        }

        return '';
    }

    function deriveTransitionType(element, fallbackType) {
        if (fallbackType) {
            return fallbackType;
        }

        if (element instanceof HTMLAnchorElement) {
            try {
                const url = new URL(element.href, window.location.href);
                const isFromEditPage = /posts\.php/i.test(window.location.pathname) && /[?&]action=edit/i.test(window.location.search);
                const isToDashboard = /dashboard\.php$/i.test(url.pathname);
                if (isFromEditPage && isToDashboard) {
                    return 'down';
                }
            } catch (error) {
                return '';
            }
        }

        return '';
    }

    function setIncomingDirection(transitionType) {
        if (transitionType === 'back') {
            sessionStorage.setItem(INCOMING_KEY, 'left');
        } else if (transitionType === 'up') {
            sessionStorage.setItem(INCOMING_KEY, 'bottom');
        } else if (transitionType === 'down') {
            sessionStorage.setItem(INCOMING_KEY, 'top');
        } else {
            sessionStorage.setItem(INCOMING_KEY, 'right');
        }
    }

    function leaveThen(transitionType, run) {
        if (
            body.classList.contains('page-leaving') ||
            body.classList.contains('page-leaving-right') ||
            body.classList.contains('page-leaving-up') ||
            body.classList.contains('page-leaving-down')
        ) {
            return;
        }

        body.classList.add('page-leaving');
        setIncomingDirection(transitionType);

        if (transitionType === 'back') {
            body.classList.add('page-leaving-right');
        } else if (transitionType === 'up') {
            body.classList.add('page-leaving-up');
        } else if (transitionType === 'down') {
            body.classList.add('page-leaving-down');
        }

        window.setTimeout(run, DURATION_MS);
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target.closest('a');
        if (!canInterceptLink(link)) {
            return;
        }

        if (shouldSkipTransition(link)) {
            sessionStorage.setItem(SKIP_ENTER_ANIMATION_KEY, '1');
            sessionStorage.removeItem(INCOMING_KEY);
            return;
        }

        const transitionType = deriveTransitionType(link, getTransitionType(link));
        event.preventDefault();
        leaveThen(transitionType, function () {
            window.location.href = link.href;
        });
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.dataset.transitioning === '1') {
            return;
        }

        if (shouldSkipTransition(form)) {
            sessionStorage.setItem(SKIP_ENTER_ANIMATION_KEY, '1');
            sessionStorage.removeItem(INCOMING_KEY);
            return;
        }

        const transitionType = deriveTransitionType(form, getTransitionType(form));
        event.preventDefault();
        form.dataset.transitioning = '1';

        leaveThen(transitionType, function () {
            form.submit();
        });
    }, true);

    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.documentElement.classList.remove.apply(document.documentElement.classList, ENTER_CLASSES);
        body.classList.remove('page-leaving', 'page-leaving-right', 'page-leaving-up', 'page-leaving-down');
        body.classList.remove.apply(body.classList, ENTER_CLASSES);
        body.style.opacity = '1';
        body.style.transform = '';
    });
})();
