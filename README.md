# Enicore Rivena

A PHP framework for building admin-focused websites. It uses Slim, Eloquent and Alpine.js, with users, logins and an 
admin area built in. Needs PHP 8.4 and, for development, `sass` on the web server.

## New project

```shell
composer require enicore/rivena
vendor/bin/rivena init      # files, composer.json, database, site address, first admin, .env
vendor/bin/rivena migrate   # Rivena's new migrations, then the app's (sql/migrations in both)
vendor/bin/rivena build     # builds the styles and scripts (dev does it on each request)
```

Point the web server at `public/` and open `/usr`. Init asks for the site URL and the database first and fails without
them, before creating anything (an existing .env gets what it can). It never replaces existing files; run it again to
add what's missing.

```
.env                  dev/prod differences: database, SMTP, site_url, app_env, app_key, admins (not in git)
public/index.php      the routes
src/config.php        the rest of the configuration
src/Controllers/      the controllers (HomeController: the home page)
src/views/            the views; one named like Rivena's replaces it (layout.php: the public pages' layout,
                      admin/partials/sidebar.php: the menu)
src/styles/app.scss   the styles -> public/css/app.css
src/scripts/          the scripts -> public/js/app.js (with Rivena's scripts and Alpine)
```

With `app_env=dev`: styles and scripts are rebuilt on the next request after a change (errors in the Error Log), pages
reload when `src/` or `public/` changes, error pages show the details, no error emails. The built files are committed.

The app's tables: `sql/migrations/*.sql`, each run once by `rivena migrate` in name order (recorded as `app/<file>`);
the first one creates them. Migrations only add (tables, columns with a default), so the running code keeps working:
migrate, then deploy; drop what's unused in a later release.

## Routes

```php
$app = new App(dirname(__DIR__));
$app->get('/', [HomeController::class, 'page']);

$app->removeRoutes('system');   // a Rivena page out, with its actions and menu link

// logged-in users
$app->group(Config::get('user_path'), function (RouteCollectorProxy $group) {
    $group->get('', [DashboardController::class, 'page'])->setName('dashboard');
    $group->get('/reports', [ReportsController::class, 'page'])->setName('reports');
})->add(CsrfMiddleware::class)->add(AuthMiddleware::class)->add(SessionMiddleware::class);

// admins only
$app->group(Config::get('user_path'), function (RouteCollectorProxy $group) {
    $group->get('/settings', [SettingsController::class, 'page'])->setName('settings');
})->add(AdminMiddleware::class)->add(CsrfMiddleware::class)->add(AuthMiddleware::class)
    ->add(SessionMiddleware::class);

$app->run();
```

Rivena's routes are at `user_path` (`/usr`): `login`, `logout`, `account`, and for admins `users`, `roles`,
`permissions`, `events`, `error-log`, `system`; actions are named after their page (`users.save`). To replace one,
remove it and add yours with the same name. To restrict one, with its actions:

```php
$app->getRouteGroup('account')->add(new PermissionMiddleware('account.edit'));
```

Options of `new App()`: `services` (PHP-DI definitions, e.g. `AuthController::class => DI\autowire(MyAuth::class)`),
`config_path`, `env_path`, `csp`.

## Adding a page

```php
// src/Controllers/ReportsController.php (namespace App\Controllers); Controller for pages outside the admin area
class ReportsController extends AdminController
{
    public const array PERMISSIONS = ['reports.view' => 'View reports'];   // shown on the Permissions page

    public function page(Request $request, Response $response): Response
    {
        return $this->sendPage($request, $response, 'admin/reports', ['users' => User::count()], 'Reports');
    }

    public function save(Request $request, Response $response): Response
    {
        try {
            $name = $request->getString('name', maxLength: 100, allowEmpty: false);
            $age = $request->getInt('age', default: 0);
            $active = $request->getBool('active', default: false);   // unchecked checkboxes send nothing
        } catch (InputException $e) {
            return $response->sendError($e->getMessage(), 400);
        }

        if (...) {
            return $response->sendFieldErrors(['name' => 'Another report has this name.']);
        }

        $this->logEvent('report.saved', $name);
        return $response->sendSuccess(['id' => $id]);
    }
}
```

```html
<!-- src/views/admin/reports.php -->
<div x-data="reportsController"><h4>Reports</h4>Users: <span x-text="data.users"></span></div>
```

```js
// src/scripts/reports.js; the page's data is in this.data; class names must differ from Rivena's
class ReportsController extends Controller {
    save() {
        this.submit(`${userPath}/reports/save`, {name: this.name}, data => this.showNotification('Saved.'));
    }
}

window.reportsController = () => new ReportsController();
```

Styles: `src/styles/_reports.scss` with `@use "reports";` in `app.scss`. Menu link in
`src/views/admin/partials/sidebar.php`: `<a href="<?= w_route('reports') ?>">`.

- `sendPage()` answers the whole page, or JSON when RivenaSpa loads it. Also `sendView()` (no layout),
  `sendErrorPage()`, `notFoundRoute()`.
- Responses: `sendSuccess()`, `sendError()`, `sendFieldErrors()` (shown at the fields by `submit()`), `sendJson()`,
  `sendHtml()`, `sendText()`, `sendRedirect()`.
- JS `Controller`: `submit(url, data, done)`, `post(url, data)`, `confirm(options, action)` (the confirmation dialog;
  `password: true` asks for the password), `showNotification()`.
- Views: `<?= $this->partial('admin/partials/pager') ?>`, `w_route('users')`, `w_csrf()`, `w_url()`/`w_script()`/
  `w_style()`/`w_image()` (versioned asset URLs). Comments in views are PHP comments.

**Public pages.** A `Controller` (like `HomeController`), in the `layout` view: `src/views/layout.php` (from init), the
site's design. The login pages have their own layout (`auth-layout`, with noindex). Public pages have no session, so
visitors get no cookie. A form needs one: `SessionMiddleware` on its page and `CsrfMiddleware` too on its action, and
`<meta name="csrf" content="<?= w_csrf() ?>">` in the layout (`postJson()` sends it). `Auth::check()` tells whether a
user is logged in (also with the remember cookie), then `Auth::id()`; it starts a session for every visitor of the page.

**Tables.** `dataTable()` in an admin controller returns a page of a query with the client's page, sort and search:

```php
return $response->sendSuccess($this->dataTable(User::query(), $request->all(), sortable: ['email', 'created_at'],
    searchable: ['email'], map: fn(User $user) => ['id' => $user->public_id, 'email' => $user->email]));
```

In JS: `this.table = new Table(`${userPath}/users/list`, this.data.table, {query: true})`, with the
`admin/partials/pager` view. A sortable header:

```html
<th :aria-sort="table.ariaSort('email')">
    <button type="button" class="sortable" @click="table.sortBy('email')">
        Email <span x-text="table.arrow('email')" aria-hidden="true"></span>
    </button>
</th>
```

## Users

- Admins are the user IDs in `admins` (`.env`). They have every permission and can't be disabled or deleted.
- Roles are managed on the Roles page; every user has the built-in User role. `User::can('reports.view')` checks the
  logged-in user, `PermissionMiddleware` a route, `User::isAdmin()` the admin status. Only users with the `login`
  permission can log in (the User role has it).
- `password_reset` permission: the login page offers "Forgot password" (needs SMTP).
- Two-factor authentication: users set it up on their account page (`two_factor_methods`: `totp`, `webauthn`);
  `two_factor_required` makes it mandatory. WebAuthn keys work only on `site_url`'s host.
- Public IDs: models with `HasPublicId` (like `User`) have a random `public_id` for URLs and JSON;
  `User::findByPublicId($id)`.

## Configuration

`src/config.php` sets `$config['key']` (`Config` includes it); `.env` overrides the values (taking their type; arrays
are comma-separated). Read with `Config::get('key', $default)`.

- Required: `app_key`, `site_name`, `site_url` (the https:// address: links in emails, WebAuthn), `db_host`,
  `db_port`, `db_database`, `db_username`, `db_password` (not with `use_database` false)
- `use_database` (true): false for a site without a database: no Eloquent, none of Rivena's pages (`user_path`), errors
  only in PHP's log
- `app_env` (`dev`), `admins`, `user_path` (`/usr`)
- Mail: `smtp_host`, `smtp_port` (587), `smtp_username`, `smtp_password`, `smtp_timeout` (10), `mail_from`
- `error_notification_email` (at most one email an hour), `event_log_retention_months` (6)
- `password_min_length` (12), `password_max_length` (60), `password_min_score` (3, zxcvbn)
- `two_factor_methods` (`['totp', 'webauthn']`), `two_factor_required` (false), `two_factor_email_notifications` (true)
- `table_rows_per_page` (25), `image_max_megapixels` (25)

## RivenaCSS

```scss
@use "../../vendor/enicore/rivena/assets/scss/rivena" with ($icons: ('logo': '<svg ...>'));

.box {
    width: 50%;
    @include rivena.size(xs) { width: 100%; }   // xs, sm, md, lg, xl, sm-down, sm-up...
}

:root { --line-height: 1.6; }                  // the settings are CSS variables
```

`$icons` become `icon-<name>` classes; `$font-path: false` uses the system font. Hide by size with `hide-xs`,
`hide-sm-down`... The color classes are in `rivena_colors.css`. The frontend is also on npm (`@enicore/rivena`).

## License

Enicore Rivena is licensed under the MIT License. See LICENSE for more information.
