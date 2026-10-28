<div id="page-users" x-data="usersController">
    <?php // the search's right edge lines up with the table's buttons (the cells' padding) ?>
    <div class="flex justify-between items-center gap pr-sm mb">
        <h4 class="mb-0">Users</h4>
        <div class="flex items-center gap-sm min-width-0">
            <a href="<?= w_user_path() ?>/users/add" class="button small primary whitespace-nowrap">Add user</a>
            <input type="search" class="small min-width-0" placeholder="Search" :value="table.search"
                   @input.debounce.300ms="table.load({search: $event.target.value, page: 1})">
        </div>
    </div>

    <table class="striped" :class="{loading: table.loading}">
        <thead>
            <tr>
                <th :aria-sort="table.ariaSort('email')">
                    <button type="button" class="sortable" @click="table.sortBy('email')">
                        Email <span x-text="table.arrow('email')" aria-hidden="true"></span>
                    </button>
                </th>
                <th>Roles</th>
                <th :aria-sort="table.ariaSort('created_at')">
                    <button type="button" class="sortable" @click="table.sortBy('created_at')">
                        Registered <span x-text="table.arrow('created_at')" aria-hidden="true"></span>
                    </button>
                </th>
                <th :aria-sort="table.ariaSort('last_login_at')">
                    <button type="button" class="sortable" @click="table.sortBy('last_login_at')">
                        Last login <span x-text="table.arrow('last_login_at')" aria-hidden="true"></span>
                    </button>
                </th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="user in table.rows" :key="user.id">
                <tr>
                    <td x-text="user.email"></td>
                    <td x-text="(user.admin ? 'Admin' : user.roles || 'User') + (user.disabled ? ' (disabled)' : '')">
                    </td>
                    <td x-text="user.created_at"></td>
                    <td x-text="user.last_login_at"></td>
                    <td>
                        <div class="flex justify-end gap-sm">
                            <a :href="`<?= w_user_path() ?>/users/${user.id}/edit`" class="button x-small icon-pencil"
                               title="Edit" aria-label="Edit"></a>
                            <button class="x-small icon-log-in" title="Log in as" aria-label="Log in as"
                                    @click="loginAs(user)" x-show="!user.admin && !user.disabled"></button>
                            <button class="x-small danger icon-trash" title="Delete" aria-label="Delete"
                                    @click="remove(user)" x-show="user.id !== data.currentId"></button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>

    <?= $this->partial('admin/partials/pager') ?>
</div>
