"""
Browser tests of RivenaSpa (assets/js/rivena_spa.js) with Playwright and PHP's built-in server.

    /opt/playwright/bin/python tests/browser/test_spa.py
    RIVENA_SPA_FILE=assets/dist/rivena_spa.min.js /opt/playwright/bin/python tests/browser/test_spa.py  (the built file)
"""
import os
import socket
import subprocess
import time
import unittest

from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


class SpaTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        with socket.socket() as s:
            s.bind(('127.0.0.1', 0))
            port = s.getsockname()[1]
        cls.base = f'http://127.0.0.1:{port}'
        # several workers, so a slow page doesn't hold the next one back
        cls.server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', 'tests/browser/server.php'], cwd=ROOT,
                                      env={**os.environ, 'PHP_CLI_SERVER_WORKERS': '4'},
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(50):
            try:
                socket.create_connection(('127.0.0.1', port)).close()
                break
            except OSError:
                time.sleep(0.1)
        cls.playwright = sync_playwright().start()
        cls.browser = cls.playwright.chromium.launch()

    @classmethod
    def tearDownClass(cls):
        cls.browser.close()
        cls.playwright.stop()
        cls.server.terminate()
        cls.server.wait()

    def setUp(self):
        self.context = self.browser.new_context(viewport={'width': 800, 'height': 600})
        self.page = self.context.new_page()
        # RivenaSpa's page loads
        self.loads = []
        self.page.on('request', lambda r: 'application/json' in r.headers.get('accept', '') and
                     self.loads.append(r.url.replace(self.base, '')))

    def tearDown(self):
        self.context.close()

    def open(self, path='/'):
        self.page.goto(self.base + path)
        self.page.wait_for_function("document.body.classList.contains('page-home')")

    def click(self, selector, **options):
        self.page.click(selector, **options)
        self.page.wait_for_timeout(300)

    def content(self):
        return self.page.text_content('#content')

    def menu(self):
        return self.page.eval_on_selector_all('.spa-page-menu a', 'links => links.map(a => a.className)')

    def path(self):
        return self.page.evaluate('location.pathname + location.hash')

    def test_first_load(self):
        self.open()

        # the first page comes from the server, no request for it
        self.assertEqual('Home', self.content())
        self.assertEqual([], self.loads)
        self.assertEqual('Home - Site', self.page.title())
        self.assertEqual({'page': 'home'}, self.page.evaluate('window.spa_page_data'))
        self.assertEqual('page-home', self.page.evaluate('document.body.className'))
        self.assertEqual(['active', ''], self.menu())
        self.assertEqual([['after', 'home', 'Home', {'page': 'home'}]], self.page.evaluate('events'))

        # the next pages: the events come before and after the content changes, with the data
        self.click('#to-one')
        self.assertEqual([['before', 'one', 'Home'], ['after', 'one', 'One', {'page': 'one'}]],
                         self.page.evaluate('events.slice(1)'))
        self.assertEqual('content', self.page.evaluate('document.activeElement.id'))

    def test_late_script(self):
        # the script is loaded after DOMContentLoaded
        self.open('/?late=1')
        self.assertEqual('Home', self.content())
        self.assertEqual('Home - Site', self.page.title())

    def test_navigation(self):
        self.open()
        self.click('#to-one')

        self.assertEqual('One', self.content())
        self.assertEqual('/one', self.path())
        self.assertEqual('One - Site', self.page.title())
        self.assertEqual('page-one', self.page.evaluate('document.body.className'))
        self.assertEqual({'page': 'one'}, self.page.evaluate('window.spa_page_data'))
        # the menu link has a trailing slash
        self.assertEqual(['', 'active'], self.menu())

        # a relative link, and the same page again reloads it without a new history entry
        self.click('#to-two')
        self.assertEqual('/two', self.path())
        length = self.page.evaluate('history.length')
        self.click('#to-two')
        self.assertEqual(['/one', '/two', '/two'], self.loads)
        self.assertEqual(length, self.page.evaluate('history.length'))

        self.page.evaluate('rivena_spa.navigate("/one")')
        self.page.wait_for_timeout(300)
        self.assertEqual('One', self.content())
        self.page.evaluate('rivena_spa.reload()')
        self.page.wait_for_timeout(300)
        self.assertEqual(['/one', '/two', '/two', '/one', '/one'], self.loads)

    def test_links_left_to_browser(self):
        self.open()
        # records whether RivenaSpa took the click, and stops the browser from following the link (middle clicks
        # aren't click events)
        self.page.evaluate("addEventListener('click', e => { window.taken = e.defaultPrevented; e.preventDefault() })")

        for selector, options in [('#to-one', {'modifiers': ['Control']}), ('#to-one', {'modifiers': ['Meta']}),
                                  ('#to-one', {'modifiers': ['Shift']}),
                                  ('#no-spa', {}), ('#blank', {}), ('#download', {}), ('#mailto', {}),
                                  ('#external', {}), ('#protocol-relative', {}), ('#hash', {})]:
            self.page.evaluate('window.taken = null')
            self.page.click(selector, **options)
            self.assertIs(False, self.page.evaluate('window.taken'), f'{selector} {options}')

        self.page.click('#to-one')
        self.assertIs(True, self.page.evaluate('window.taken'))

    def test_race(self):
        # a slow page that answers after the next one doesn't replace it
        self.open()
        self.page.click('#to-slow')
        self.page.wait_for_timeout(100)
        self.click('#to-one')
        self.page.wait_for_timeout(1000)

        self.assertEqual('One', self.content())
        self.assertEqual('/one', self.path())
        self.assertEqual('One - Site', self.page.title())
        self.assertFalse(self.page.evaluate("document.body.classList.contains('spa-loading')"))

    def test_errors(self):
        self.open()

        self.click('#to-fail')
        self.assertEqual('Broken', self.content())
        self.assertEqual('Site', self.page.title())
        self.assertEqual('/fail', self.path())

        # an error with status 200
        self.click('#to-soft')
        self.assertEqual('Not allowed', self.content())

    def test_redirect(self):
        # e.g. to the login page when the session has expired
        self.open()
        self.page.click('#to-expired')
        self.page.wait_for_selector('#plain')
        self.assertEqual('/login-page', self.path())

    def test_not_spa_page(self):
        # a link to a page that isn't part of the SPA loads it normally
        self.open()
        self.page.click('#to-plain')
        self.page.wait_for_selector('#plain')
        self.assertEqual('Plain page /plain', self.page.text_content('#plain'))

    def test_new_version(self):
        # new scripts or styles (a deploy): the page loads normally, with the new files
        self.open()
        self.page.evaluate('window.marker = 1')
        self.page.click('#to-new-version')
        self.page.wait_for_selector('#new-version')
        self.assertIsNone(self.page.evaluate('window.marker'))
        self.assertEqual('/new-version', self.path())
        self.assertEqual('New version - Site', self.page.title())

    def test_page_without_content(self):
        # RivenaSpa leaves a page without #content alone: it doesn't load it, and its links load normally
        self.page.goto(self.base + '/no-content')
        self.page.wait_for_timeout(300)
        self.page.click('#to-plain')
        self.page.wait_for_selector('#plain')
        self.assertEqual([], self.loads)

    def test_title_fallback(self):
        self.open()
        self.click('#to-one')
        self.click('#to-no-title')
        self.assertEqual('Site', self.page.title())

    def test_replace(self):
        self.open()
        self.click('#to-one')
        self.loads.clear()
        self.page.evaluate('rivena_spa.replace("/one?page=2")')
        self.assertEqual('/one?page=2', self.page.evaluate('location.pathname + location.search'))
        self.assertEqual([], self.loads)

        # the replaced URL is the history entry: back and forward load it
        self.click('#to-long')
        self.page.go_back()
        self.page.wait_for_timeout(300)
        self.assertEqual(['/long', '/one?page=2'], self.loads)
        self.assertEqual('One - Site', self.page.title())

        # a hash change on the replaced URL doesn't load the page
        self.loads.clear()
        self.page.evaluate('location.hash = "#x"')
        self.page.wait_for_timeout(300)
        self.assertEqual([], self.loads)

    def test_back_and_forward(self):
        self.open()
        self.click('#to-long')
        self.page.evaluate('scrollTo(0, 1500)')
        self.page.evaluate('rivena_spa.navigate("/one")')
        self.page.wait_for_timeout(300)
        self.assertEqual(0, self.page.evaluate('scrollY'))

        self.page.go_back()
        self.page.wait_for_timeout(300)
        self.assertEqual('/long', self.path())
        self.assertTrue(self.content().startswith('Long'))
        # the scroll position is restored
        self.assertEqual(1500, self.page.evaluate('scrollY'))

        self.page.go_back()
        self.page.wait_for_timeout(300)
        self.assertEqual('Home', self.content())

        self.page.go_forward()
        self.page.wait_for_timeout(300)
        self.assertEqual('/long', self.path())

    def test_back_to_rendered_page(self):
        # the page's scripts render its content after it's inserted: the scroll position waits for them
        self.open()
        self.page.evaluate('rivena_spa.navigate("/rendered")')
        self.page.wait_for_timeout(300)
        self.page.evaluate('scrollTo(0, 1500)')
        self.page.evaluate('rivena_spa.navigate("/one")')
        self.page.wait_for_timeout(300)

        self.page.go_back()
        self.page.wait_for_timeout(300)
        self.assertEqual('/rendered', self.path())
        self.assertEqual(1500, self.page.evaluate('scrollY'))

    def test_anchors(self):
        self.open()
        self.click('#to-anchor')
        self.assertEqual('/long#target', self.path())
        self.assertGreater(self.page.evaluate('scrollY'), 2500)

        # an anchor on the same page doesn't load the page again, also when going back
        self.click('#same-anchor')
        self.assertEqual('/long#other', self.path())
        self.page.go_back()
        self.page.wait_for_timeout(300)
        self.assertEqual(['/long'], self.loads)


if __name__ == '__main__':
    unittest.main()
