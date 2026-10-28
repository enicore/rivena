<div id="login-page" x-data="loginController">
    <?php // post, so without the script the password isn't in the URL ?>
    <form class="box" method="post" action="<?= htmlspecialchars($action) ?>" @submit.prevent="submit"
          x-show="!twoFactor">
        <h4 class="text-center">Login</h4>
        <input type="hidden" name="csrf" value="<?= w_csrf() ?>">

        <template x-if="error">
            <p class="alert danger p mb" x-text="error"></p>
        </template>

        <div class="input-box">
            <label for="email">Email</label>
            <div class="input-group">
                <input type="email" id="email" name="email" required>
            </div>
        </div>

        <div class="input-box password">
            <label for="password">Password</label>
            <div class="input-group">
                <?= $this->partial('admin/partials/password',
                    ['attributes' => 'id="password" name="password" required']) ?>
            </div>
        </div>

        <label class="flex items-center gap-sm mt">
            <input type="checkbox" name="remember" value="1"> Remember me
        </label>

        <div class="text-center">
            <button type="submit" class="primary width-100 mt" :class="{progress: loading}" :disabled="loading">
                Login
            </button>
        </div>

        <?php if ($passwordResets): ?>
            <p class="text-center mt"><a href="<?= w_route('forgot-password') ?>">Forgot password?</a></p>
        <?php endif ?>
    </form>

    <?php
    // the second step for the users with two-factor authentication: a code of one of their methods, or a security key
    // (the browser signs the challenge, which goes in the code)
    ?>
    <template x-if="twoFactor">
        <form class="box" method="post" action="<?= w_route('login.two-factor') ?>" @submit.prevent="submit"
              data-challenge="<?= w_route('login.two-factor.challenge') ?>">
            <h4 class="text-center">Two-factor authentication</h4>
            <input type="hidden" name="csrf" value="<?= w_csrf() ?>">
            <input type="hidden" name="method" :value="method">

            <template x-if="error">
                <p class="alert danger p mb" x-text="error"></p>
            </template>

            <template x-if="method !== 'webauthn'">
                <div>
                    <div class="input-box">
                        <label for="code" x-text="method === 'recovery' ? 'Enter one of your recovery codes.' :
                            'Enter the code from your authenticator app.'"></label>
                        <div class="input-group">
                            <input type="text" id="code" name="code" required autocomplete="one-time-code"
                                   :inputmode="method === 'recovery' ? 'text' : 'numeric'">
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="primary width-100 mt" :class="{progress: loading}"
                                :disabled="loading">Verify</button>
                    </div>
                </div>
            </template>

            <template x-if="method === 'webauthn'">
                <div>
                    <p>Use your security key or passkey.</p>
                    <input type="hidden" name="code" id="code">
                    <div class="text-center">
                        <button type="button" class="primary width-100 mt" :class="{progress: loading}"
                                :disabled="loading" @click="useKey($el.form)">Use security key</button>
                    </div>
                </div>
            </template>

            <template x-for="other in twoFactor.filter(other => other.id !== method)" :key="other.id">
                <p class="text-center mt mb-0">
                    <a href="#" @click.prevent="useMethod(other.id)" x-text="other.id === 'recovery' ?
                        'Use a recovery code' : `Use your ${other.name.toLowerCase()}`"></a>
                </p>
            </template>
            <p class="text-center mt mb-0"><a href="<?= w_route('login') ?>">Cancel</a></p>
        </form>
    </template>
</div>
