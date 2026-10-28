<?php use Enicore\Rivena\Classes\Config; use Enicore\Rivena\Models\User; ?>
<nav id="navbar">
    <ul>
        <li class="hide-xs">
            <a href="<?= w_user_path() ?>" class="icon-logo icon-mr">
                <?= htmlspecialchars(Config::get('site_name')) ?>
            </a>
        </li>
        <li class="hide-sm-up">
            <a class="toggle-mobile-menu icon-menu" role="button" tabindex="0" title="Menu"></a>
        </li>
    </ul>
    <ul>
        <li>
            <a class="dropdown icon-user" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"
               title="Account">
                <span class="text hide-xs"><?= htmlspecialchars($_SESSION['auth_email'] ?? '') ?></span>
            </a>
            <ul class="right">
                <?php if (w_route('account')): ?>
                    <li><a href="<?= w_route('account') ?>" class="icon-user-pen">Edit account</a></li>
                <?php endif ?>
                <li><a href="<?= w_route('logout') ?>" class="no-spa icon-logout">Logout</a></li>
            </ul>
        </li>
        <?php if (User::isAdmin() && (w_route('users') || w_route('roles') || w_route('permissions')
            || w_route('events') || w_route('error-log') || w_route('system'))): ?>
            <li>
                <a class="dropdown icon-gear" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"
                   title="Administration"></a>
                <ul class="right spa-page-menu">
                    <?php if (w_route('users')): ?>
                        <li><a href="<?= w_route('users') ?>" class="icon-users">Users</a></li>
                    <?php endif ?>
                    <?php if (w_route('roles')): ?>
                        <li><a href="<?= w_route('roles') ?>" class="icon-sliders">Roles</a></li>
                    <?php endif ?>
                    <?php if (w_route('permissions')): ?>
                        <li><a href="<?= w_route('permissions') ?>" class="icon-lock">Permissions</a></li>
                    <?php endif ?>
                    <?php if (w_route('events')): ?>
                        <li><a href="<?= w_route('events') ?>" class="icon-history">Event Log</a></li>
                    <?php endif ?>
                    <?php if (w_route('error-log')): ?>
                        <li><a href="<?= w_route('error-log') ?>" class="icon-warning">Error Log</a></li>
                    <?php endif ?>
                    <?php if (w_route('system')): ?>
                        <li><a href="<?= w_route('system') ?>" class="icon-server">System Info</a></li>
                    <?php endif ?>
                </ul>
            </li>
        <?php endif ?>
        <li class="show-if-light-theme">
            <a class="toggle-theme icon-moon" role="button" tabindex="0" title="Dark theme"></a>
        </li>
        <li class="show-if-dark-theme">
            <a class="toggle-theme icon-sun" role="button" tabindex="0" title="Light theme"></a>
        </li>
    </ul>
</nav>
