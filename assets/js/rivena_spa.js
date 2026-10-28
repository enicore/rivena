/**
 * Copyright (C) 2025 Enicore Solutions (https://enicore.com)
 * This software is released under the MIT License.
 *
 * RivenaSpa: changes pages without reloading the browser page.
 *
 * A click on one of the site's links loads the page as JSON (GET with "Accept: application/json") and puts its HTML in
 * #content; the URL, the title and the back button work as usual. The server (Controller::sendPage()) answers
 * {"success": true, "data": {"title", "html", "data", "version"}} or {"success": false, "error", "redirect"}.
 * The first page comes whole, with its title, data and version in <script id="spa-page"> (w_spa_data()).
 *
 * - the page's data is in window.spa_page_data and in the spa-before-page-load and spa-after-page-load events
 * - links with the class "no-spa", responses that aren't JSON and a new version (a deploy) load the page normally
 * - pages without #content (e.g. the login page) are left alone
 * - the current page's links in .spa-page-menu get "active"; the body gets "page-<path>", and "spa-loading" meanwhile
 *
 * window.rivena_spa: navigate(url), reload(), replace(url) (changes the URL without loading the page).
 */

(() => {
    const trim = path => path.replace(/\/+$/, '');
    let baseTitle;
    let version;
    let request;
    let loadedPath;

    // reads the first page's title, data and version; pages without #content aren't RivenaSpa's
    function start() {
        if (!document.getElementById('content')) {
            return;
        }

        const page = JSON.parse(document.getElementById('spa-page')?.textContent || '{}');
        // the server's title is "<page's title> - <the layout's title>"
        baseTitle = page.title && document.title.startsWith(page.title + ' - ')
            ? document.title.slice(page.title.length + 3) : document.title;
        version = page.version;
        loadedPath = location.pathname + location.search;
        history.scrollRestoration = 'manual';
        window.spa_page_data = page.data;
        loaded(new URL(location.href));
        dispatch('spa-after-page-load', new URL(location.href), page.data);
    }

    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();

    // back and forward; a change of the hash only is an anchor on the same page
    window.addEventListener('popstate', event => {
        if (location.pathname + location.search !== loadedPath) {
            navigate(location.href, false, event.state?.scroll);
        }
    });

    document.addEventListener('click', event => {
        const a = event.target.closest?.('a[href]');

        // leave the link to the browser for new tabs, downloads, other sites and anchors on the same page
        if (!a || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey ||
            event.altKey || a.classList.contains('no-spa') || a.hasAttribute('download') ||
            (a.target && a.target !== '_self')) {
            return;
        }

        const url = new URL(a.href, location.href);

        if (url.origin !== location.origin || (url.hash && url.pathname + url.search === location.pathname +
            location.search)) {
            return;
        }

        event.preventDefault();
        navigate(url.href);
    });

    // loads the page into #content; push adds a history entry, scroll is the position to restore
    async function navigate(href, push = true, scroll = null) {
        const url = new URL(href, location.href);
        const content = document.getElementById('content');

        if (!content) {
            location.href = url.href;
            return;
        }

        // the current page's link reloads it, without a new history entry
        if (push && trim(url.href) !== trim(location.href)) {
            // remember the scroll position for the back button
            history.replaceState({ scroll: [scrollY, content.scrollTop] }, '');
            history.pushState(null, '', url.href);
        }

        // cancel the previous page load, so a slower one can't replace this page
        request?.abort();
        loadedPath = url.pathname + url.search;
        const current = request = new AbortController();
        document.body.classList.add('spa-loading');

        try {
            const response = await fetch(url.href, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                signal: current.signal,
            });

            // not a page of the SPA: load it normally
            if (!response.headers.get('Content-Type')?.includes('json')) {
                location.href = url.href;
                return;
            }

            const json = await response.json();

            if (json.redirect) {
                location.href = json.redirect;
                return;
            }

            if (!response.ok || !json.success) {
                throw new Error(json.error || `Error ${response.status}`);
            }

            const data = json.data || {};

            // new scripts or styles (a deploy): load the page normally, so the browser gets them
            if (version && data.version && data.version !== version) {
                location.href = url.href;
                return;
            }

            showTitle(data.title);
            window.spa_page_data = data.data;
            dispatch('spa-before-page-load', url, data.data);
            // a dialog open in the old content (e.g. after Back) can't unlock the page's scrolling any more
            document.body.classList.remove('modal-visible');
            content.innerHTML = data.html || '';
            loaded(url);
            dispatch('spa-after-page-load', url, data.data);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            console.error(error);
            showTitle('');
            content.textContent = error.message;
            loaded(url);
        } finally {
            if (current === request) {
                document.body.classList.remove('spa-loading');
            }
        }

        // the scroll position of the history entry, the anchor, or the top; in the next frame, after the page's scripts
        // rendered its content (Alpine's x-for), which would be too short for it before
        requestAnimationFrame(() => {
            const anchor = url.hash && document.getElementById(decodeURIComponent(url.hash.slice(1)));
            if (scroll) {
                scrollTo(0, scroll[0]);
                content.scrollTop = scroll[1];
            } else if (anchor) {
                anchor.scrollIntoView();
            } else {
                scrollTo(0, 0);
                content.scrollTop = 0;
            }
        });

        // for keyboard and screen reader users, the new page has the focus
        content.hasAttribute('tabindex') || content.setAttribute('tabindex', '-1');
        content.focus({ preventScroll: true });
    }

    // marks the page's menu links and sets the body's page class
    function loaded(url) {
        document.querySelectorAll('.spa-page-menu a[href]').forEach(a =>
            a.classList.toggle('active', trim(new URL(a.href, location.href).pathname) === trim(url.pathname)));
        document.body.classList.remove(...[...document.body.classList].filter(c => c.startsWith('page-')));
        document.body.classList.add('page-' + pageName(url));
    }

    // tells the page's scripts, with the page's data
    function dispatch(name, url, data) {
        document.dispatchEvent(new CustomEvent(name, { detail: { url: url.href, page: pageName(url), data } }));
    }

    // the page's name from its path: "users-add" for /users/add, "home" for /
    function pageName(url) {
        return trim(url.pathname).replace(/^\/+/, '').replace(/\//g, '-') || 'home';
    }

    // changes the current URL (and history entry) without loading the page
    function replace(href) {
        const url = new URL(href, location.href);
        history.replaceState(history.state, '', url.href);
        loadedPath = url.pathname + url.search;
    }

    // sets the title: "<page's title> - <the layout's title>"
    function showTitle(pageTitle) {
        document.title = pageTitle && baseTitle ? `${pageTitle} - ${baseTitle}` : pageTitle || baseTitle;
    }

    window.rivena_spa = { navigate, reload: () => navigate(location.href, false), replace };
})();
