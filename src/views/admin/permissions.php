<?php // the Permissions page: a row a permission, a column a role (the admins have all, so they aren't listed) ?>
<div id="page-permissions" x-data="permissionsController">
    <h4>Permissions</h4>

    <div class="alert danger" x-show="error" x-text="error"></div>
    <table class="striped">
        <thead>
            <tr>
                <th>Permission</th>
                <th>ID</th>
                <template x-for="role in data.roles" :key="role.id">
                    <th class="text-center" x-text="role.name"></th>
                </template>
            </tr>
        </thead>
        <tbody>
            <template x-for="permission in data.permissions" :key="permission.name">
                <tr>
                    <td x-text="permission.description"></td>
                    <td class="opacity-60" x-text="permission.name"></td>
                    <template x-for="role in data.roles" :key="role.id">
                        <td class="text-center">
                            <input type="checkbox" :value="permission.name" x-model="role.permissions"
                                   :aria-label="role.name">
                        </td>
                    </template>
                </tr>
            </template>
        </tbody>
    </table>

    <button class="primary mt" :class="{progress: busy}" :disabled="busy" @click="save()">Save</button>
</div>
