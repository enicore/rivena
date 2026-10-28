<div id="login-page">
    <form class="box" method="post" action="<?= htmlspecialchars($action) ?>" x-data="loginController"
          @submit.prevent="submit">
        <h4 class="text-center">Reset password</h4>
        <input type="hidden" name="csrf" value="<?= w_csrf() ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <?php if ($email === null): ?>
            <p class="alert danger p mb">This link is invalid or expired.</p>
            <p class="text-center"><a href="<?= w_route('forgot-password') ?>">Get a new link</a></p>
        <?php else: ?>
            <template x-if="error">
                <p class="alert danger p mb" x-text="error"></p>
            </template>

            <template x-if="message">
                <p class="alert success p mb" x-text="message"></p>
            </template>

            <div x-show="!message">
                <?php // the account's email, for the password managers and the password's strength ?>
                <input type="email" value="<?= htmlspecialchars($email) ?>" autocomplete="username" hidden>
                <div class="input-box password">
                    <label for="password">New password</label>
                    <div class="input-group">
                        <?= $this->partial('admin/partials/password-input',
                            ['attributes' => 'id="password" name="password" required']) ?>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="primary width-100 mt" :class="{progress: loading}"
                            :disabled="loading">
                        Save
                    </button>
                </div>
            </div>

            <p class="text-center mt"><a href="<?= w_route('login') ?>">Back to login</a></p>
        <?php endif ?>
    </form>
</div>
