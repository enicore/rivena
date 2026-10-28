<div id="page-user" x-data="userController">
    <h4><?= htmlspecialchars($title) ?></h4>

    <?php $form = $this->partial('admin/partials/user-form', compact('roles', 'account', 'canDisableOrDelete')) ?>
    <?php if ($twoFactor === null): ?>
        <?= $form ?>
    <?php else: ?>
        <?php // the account's tab, and the two-factor authentication's ?>
        <div class="tabs">
            <input type="radio" id="user-tab-account" value="account" x-model="tab">
            <input type="radio" id="user-tab-two-factor" value="two-factor" x-model="tab">
            <nav class="underlined">
                <label for="user-tab-account">Account</label>
                <label for="user-tab-two-factor">Two-factor authentication</label>
            </nav>
            <section>
                <div><?= $form ?></div>
                <div><?= $this->partial('admin/partials/user-two-factor') ?></div>
            </section>
        </div>
    <?php endif ?>
</div>
