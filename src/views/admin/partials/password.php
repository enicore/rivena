<?php // a password input with $attributes (its id, name, x-model...) and the eye button inside it, which shows and
// hides the password (assets/js/admin/password-input.js) ?>
<div class="password-box">
    <input type="password" <?= $attributes ?>>
    <button type="button" class="password-eye" aria-label="Show or hide the password">
        <span class="icon-eye"></span><span class="icon-eye-off"></span>
    </button>
</div>
