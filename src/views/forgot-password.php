<div id="login-page">
    <form class="box" method="post" action="<?= htmlspecialchars($action) ?>" x-data="loginController"
          @submit.prevent="submit">
        <h4 class="text-center">Forgot password</h4>
        <input type="hidden" name="csrf" value="<?= w_csrf() ?>">

        <template x-if="error">
            <p class="alert danger p mb" x-text="error"></p>
        </template>

        <template x-if="message">
            <p class="alert success p mb" x-text="message"></p>
        </template>

        <div class="input-box">
            <label for="email">Email</label>
            <div class="input-group">
                <input type="email" id="email" name="email" required>
            </div>
        </div>

        <div class="text-center">
            <button type="submit" class="primary width-100 mt" :class="{progress: loading}" :disabled="loading">
                Send link
            </button>
        </div>

        <p class="text-center mt"><a href="<?= w_route('login') ?>">Back to login</a></p>
    </form>
</div>
