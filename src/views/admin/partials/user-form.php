<?php // the user page's form: email, password, roles, date and time formats, time zone; save, disable, delete ?>
<form class="page-form" @submit.prevent="save()">
    <div class="alert danger" x-show="error" x-text="error"></div>
    <div class="alert warning" x-show="data.user.disabled">This user is disabled and can't log in.</div>
    <div class="input-group" :class="{error: errors.email}">
        <label for="user-email">Email</label>
        <input type="email" id="user-email" x-model="data.user.email" required autocomplete="off">
        <div class="error-message" x-text="errors.email"></div>
    </div>
    <div class="input-group" :class="{error: errors.password}">
        <label for="user-password">Password</label>
        <?= $this->partial('admin/partials/password-input', ['attributes' => 'id="user-password" ' .
            'x-model="data.user.password" :placeholder="data.user.id === null ? \'\' : \'unchanged\'" ' .
            ':required="data.user.id === null"']) ?>
        <div class="error-message" x-text="errors.password"></div>
    </div>
    <?php if ($roles): ?>
        <?php // the User role is shown on, but can't change (every user has it); not for the admins ?>
        <div class="input-group" :class="{error: errors.roles}">
            <label>Roles</label>
            <div class="switch-list">
                <template x-for="role in data.roles" :key="role.id">
                    <div class="flex items-center gap-sm">
                        <input type="checkbox" class="switch" :id="`user-role-${role.id}`" :value="role.id"
                               x-model.number="data.user.roles" :disabled="role.id === 1">
                        <label class="switch" :for="`user-role-${role.id}`"></label>
                        <label :for="`user-role-${role.id}`" x-text="role.name"></label>
                    </div>
                </template>
            </div>
            <div class="error-message" x-text="errors.roles"></div>
        </div>
    <?php endif ?>
    <div class="input-group" :class="{error: errors.date_format}">
        <label for="user-date-format">Date format</label>
        <select id="user-date-format" x-model="data.user.date_format">
            <template x-for="format in data.dateFormats" :key="format.value">
                <option :value="format.value" x-text="format.text"
                        :selected="format.value === data.user.date_format"></option>
            </template>
        </select>
        <div class="error-message" x-text="errors.date_format"></div>
    </div>
    <div class="input-group" :class="{error: errors.time_format}">
        <label for="user-time-format">Time format</label>
        <select id="user-time-format" x-model="data.user.time_format">
            <template x-for="format in data.timeFormats" :key="format.value">
                <option :value="format.value" x-text="format.text"
                        :selected="format.value === data.user.time_format"></option>
            </template>
        </select>
        <div class="error-message" x-text="errors.time_format"></div>
    </div>
    <div class="input-group" :class="{error: errors.timezone}">
        <label for="user-timezone">Time zone</label>
        <select id="user-timezone" x-model="data.user.timezone">
            <template x-for="timezone in data.timezones" :key="timezone.value">
                <option :value="timezone.value" x-text="timezone.text"
                        :selected="timezone.value === data.user.timezone"></option>
            </template>
        </select>
        <div class="error-message" x-text="errors.timezone"></div>
    </div>
    <div class="flex gap-sm mt">
        <button type="submit" class="primary" :class="{progress: busy}" :disabled="busy">Save</button>
        <?php if (!$account): ?>
            <a href="<?= w_user_path() ?>/users" class="button">Cancel</a>
        <?php endif ?>
        <?php // pushed to the right together ?>
        <div class="flex gap-sm ml-auto">
            <?php if ($canDisableOrDelete): ?>
                <button type="button" @click="toggleDisabled()"
                        x-text="data.user.disabled ? 'Enable' : 'Disable'"></button>
                <button type="button" class="danger" @click="remove()">Delete</button>
            <?php endif ?>
        </div>
    </div>
</form>
