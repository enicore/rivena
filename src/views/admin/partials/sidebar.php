<nav id="sidebar">
    <ul class="sidebar-menu spa-page-menu">
        <?php if (w_route('dashboard')): ?>
            <li>
                <a href="<?= w_route('dashboard') ?>" class="icon-gauge"><span class="text">Dashboard</span></a>
            </li>
        <?php endif ?>
    </ul>
    <ul class="hide-sm-down">
        <li>
            <a class="toggle-sidebar" role="button" tabindex="0" title="Collapse/expand sidebar">
                <span class="icon-chevron-left show-if-sidebar-expanded"></span>
                <span class="icon-chevron-right show-if-sidebar-collapsed"></span>
            </a>
        </li>
    </ul>
</nav>
