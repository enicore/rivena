<?php
// Test server for test_spa.py: php -S 127.0.0.1:PORT tests/browser/server.php
// Requests with "Accept: application/json" get RivenaSpa's JSON, the others the whole page (or a plain page).

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if ($path === '/rivena_spa.js') {
    header('Content-Type: text/javascript');
    readfile(__DIR__ . '/../../' . (getenv('RIVENA_SPA_FILE') ?: 'assets/js/rivena_spa.js'));
    return;
}

function json(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
}

// the pages: title, html, data
$pages = [
    '/' => ['Home', "<p id='home'>Home</p>", ['page' => 'home']],
    '/one' => ['One', "<p id='one'>One</p>", ['page' => 'one']],
    '/two' => ['Two', "<p id='two'>Two</p>", null],
    '/slow' => ['Slow', "<p id='slow'>Slow</p>", null],
    '/long' => ['Long', "<div style='height:3000px'>Long</div><p id='target'>Target</p>" .
        "<div style='height:3000px'></div><a id='same-anchor' href='/long#other'>same page</a><p id='other'>Other</p>",
        null],
    '/no-title' => ['', "<p id='no-title'>No title</p>", null],
    // its content is made by its script after it's shown, like Alpine's x-for
    '/rendered' => ['Rendered', "<div id='rendered'>Rendered</div>", null],
    '/new-version' => ['New version', "<p id='new-version'>New version</p>", null],
];
// the scripts and styles changed (a deploy) for this page
$version = $path === '/new-version' ? 'v2' : 'v1';

if (in_array($path, ['/plain', '/login-page'])) {
    echo "<!doctype html><title>Plain</title><p id='plain'>Plain page $path</p>";
    return;
}

// a page with the script but without #content (like a login page)
if ($path === '/no-content') {
    echo "<!doctype html><title>No content</title><script src='/rivena_spa.js'></script>" .
        "<a id='to-plain' href='/plain'>plain</a>";
    return;
}

if ($json) {
    $path === '/slow' && usleep(800000);

    if (isset($pages[$path])) {
        [$title, $html, $data] = $pages[$path];
        json(['success' => true, 'data' => ['title' => $title, 'data' => $data, 'version' => $version,
            'html' => $html]]);
        return;
    }

    match ($path) {
        '/fail' => json(['success' => false, 'error' => 'Broken'], 500),
        '/soft-error' => json(['success' => false, 'error' => 'Not allowed']),
        '/expired' => json(['success' => false, 'error' => 'Please log in.', 'redirect' => '/login-page'], 401),
        default => print("<p>Not JSON</p>"),
    };
    return;
}

[$title, $html, $data] = $pages[$path] ?? ['', '', null];
$fullTitle = $title ? "$title - Site" : 'Site';
$page = htmlspecialchars(json_encode(['title' => $title, 'data' => $data, 'version' => $version]), ENT_NOQUOTES);

// ?late=1 adds the script after the page has loaded
$script = isset($_GET['late'])
    ? "<script>addEventListener('load', () => setTimeout(() => document.head.append(Object.assign(" .
        "document.createElement('script'), {src: '/rivena_spa.js'})), 100));</script>"
    : "<script src='/rivena_spa.js'></script>";

echo <<<HTML
    <!doctype html>
    <html><head><title>$fullTitle</title>
    <script>
        window.events = [];
        document.addEventListener('spa-before-page-load', e => events.push(['before', e.detail.page,
            document.getElementById('content').textContent]));
        document.addEventListener('spa-after-page-load', e => events.push(['after', e.detail.page,
            document.getElementById('content').textContent, e.detail.data]));
        // like Alpine: after the content was inserted, in a microtask
        document.addEventListener('spa-after-page-load', e => e.detail.page === 'rendered' &&
            queueMicrotask(() => document.getElementById('rendered').style.height = '3000px'));
    </script>
    $script
    </head><body>
    <ul class="spa-page-menu"><li><a href="/">Home</a></li><li><a href="/one/">One</a></li></ul>
    <div id="links">
        <a id="to-one" href="/one">one</a> <a id="to-two" href="two">two</a> <a id="to-slow" href="/slow">slow</a>
        <a id="to-long" href="/long">long</a> <a id="to-anchor" href="/long#target">anchor</a>
        <a id="to-fail" href="/fail">fail</a> <a id="to-soft" href="/soft-error">soft</a>
        <a id="to-expired" href="/expired">expired</a> <a id="to-plain" href="/plain">plain</a>
        <a id="to-no-title" href="/no-title">no title</a> <a id="to-new-version" href="/new-version">new version</a>
        <a id="no-spa" class="no-spa" href="/one">no-spa</a> <a id="blank" target="_blank" href="/one">blank</a>
        <a id="download" download href="/one">download</a> <a id="mailto" href="mailto:a@example.com">mail</a>
        <a id="external" href="https://example.org/">external</a>
        <a id="protocol-relative" href="//example.org/">protocol-relative</a>
        <a id="hash" href="#target">hash</a>
    </div>
    <div id="content">$html</div>
    <script type="application/json" id="spa-page">$page</script>
    </body></html>
    HTML;
