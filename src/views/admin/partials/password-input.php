<?php use Enicore\Rivena\Classes\Config; ?>
<?php // a new password's input, with $attributes (its id, name or x-model...): the strength bar and the reason the
// password can't be used (passwordInput, assets/js/admin/password-input.js), and buttons that generate a password (at
// least 20 characters) and copy it ?>
<div class="password-input" x-data="passwordInput" data-url="<?= w_route('password-strength') ?>"
     data-length="<?= max(20, Config::get('password_min_length')) ?>">
    <?= $this->partial('admin/partials/password', ['attributes' => 'x-ref="input" autocomplete="new-password" ' .
        '@input="empty = !$event.target.value" @input.debounce.400ms="check()" ' . $attributes]) ?>
    <div class="password-strength" x-show="score !== null">
        <div :class="error === null ? 'success' : score > 1 ? 'warning' : 'danger'"
             :style="{width: `${(score + 1) * 20}%`}"></div>
    </div>
    <div class="password-error" x-show="error" x-text="error"></div>
    <div class="flex gap-sm">
        <a href="javascript:void(0)" @click="generate()" class="mr-sm font-sm">Generate password</a>
        <a href="javascript:void(0)" @click="copy()" class="font-sm" x-show="!empty"
           x-text="copied ? 'Copied' : 'Copy password'"></a>
    </div>
</div>
