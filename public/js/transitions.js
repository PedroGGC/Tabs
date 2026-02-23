(function () {
    const body = document.body;

    if (!body) {
        return;
    }

    const TRANSITION_MS = 280;

    function isInternalLink(link) {
        if (!link || !link.href) {
            return false;
        }

        if (link.target && link.target !== '_self') {
            return false;
        }

        if (link.hasAttribute('download')) {
            return false;
        }

        if ((link.getAttribute('href') || '').startsWith('#')) {
            return false;
        }

        if ((link.getAttribute('href') || '').startsWith('javascript:')) {
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

        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash !== '') {
            return false;
        }

        return true;
    }

    function startLeaveTransition(onDone) {
        if (body.classList.contains('page-leaving')) {
            return;
        }

        body.classList.add('page-leaving');
        window.setTimeout(onDone, TRANSITION_MS);
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target.closest('a');
        if (!isInternalLink(link)) {
            return;
        }

        event.preventDefault();
        startLeaveTransition(function () {
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

        const action = form.getAttribute('action') || window.location.href;

        let actionUrl;
        try {
            actionUrl = new URL(action, window.location.href);
        } catch (error) {
            return;
        }

        if (actionUrl.origin !== window.location.origin) {
            return;
        }

        if (!form.checkValidity()) {
            return;
        }

        event.preventDefault();
        form.dataset.transitioning = '1';

        startLeaveTransition(function () {
            form.submit();
        });
    }, true);

    window.addEventListener('pageshow', function () {
        body.classList.remove('page-leaving');
    });
})();