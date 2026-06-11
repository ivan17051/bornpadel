/**
 * Born Padel — page transition loading screen
 */
(function () {
    const loader = document.getElementById('page-loader');
    if (!loader) {
        return;
    }

    const show = function () {
        loader.classList.add('is-active');
        loader.setAttribute('aria-hidden', 'false');
    };

    const hide = function () {
        loader.classList.remove('is-active');
        loader.setAttribute('aria-hidden', 'true');
    };

    const shouldShowForLink = function (link) {
        if (!link || !link.href) {
            return false;
        }

        if (link.target === '_blank' || link.hasAttribute('download')) {
            return false;
        }

        if (link.dataset.noLoader !== undefined) {
            return false;
        }

        const href = link.getAttribute('href');
        if (!href || href === '#' || href.indexOf('#') === 0) {
            return false;
        }

        if (link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-lte-toggle')) {
            return false;
        }

        let url;
        try {
            url = new URL(link.href, window.location.origin);
        } catch (e) {
            return false;
        }

        if (url.origin !== window.location.origin) {
            return false;
        }

        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
            return false;
        }

        return true;
    };

    const shouldShowForForm = function (form) {
        if (!form || form.dataset.noLoader !== undefined || form.dataset.ajax !== undefined) {
            return false;
        }

        if (form.getAttribute('method') && form.getAttribute('method').toLowerCase() === 'dialog') {
            return false;
        }

        return true;
    };

    show();

    window.addEventListener('load', function () {
        window.setTimeout(hide, 150);
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[href]');
        if (!shouldShowForLink(link)) {
            return;
        }

        show();
    });

    document.addEventListener('submit', function (event) {
        if (!shouldShowForForm(event.target)) {
            return;
        }

        show();
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            hide();
        }
    });

    window.BornPadelPageLoader = {
        show: show,
        hide: hide,
    };
})();
