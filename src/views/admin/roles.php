<?php // the Roles page: the roles (the built-in User and Admin aren't listed), add and rename in a dialog, delete ?>
<div id="page-roles" x-data="rolesController" @keydown.escape.window="closeDialog()">
    <div class="flex justify-between items-center gap pr-sm mb">
        <h4 class="mb-0">Roles</h4>
        <button class="small primary" @click="openDialog()">Add role</button>
    </div>

    <table class="striped" x-show="data.roles.length">
        <thead>
            <tr>
                <th>Name</th>
                <th>Users</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="role in data.roles" :key="role.id">
                <tr>
                    <td x-text="role.name"></td>
                    <td x-text="role.users"></td>
                    <td>
                        <div class="flex justify-end gap-sm">
                            <button class="x-small icon-pencil" title="Rename" aria-label="Rename"
                                    @click="openDialog(role)"></button>
                            <button class="x-small danger icon-trash" title="Delete" aria-label="Delete"
                                    @click="remove(role)"></button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
    <p class="opacity-60" x-show="!data.roles.length">No roles yet.</p>

    <div class="modal-mask" :class="{visible: dialog, message: error}" @click.self="closeDialog()"
         x-effect="document.body.classList.toggle('modal-visible', dialog)">
        <form class="dialog" role="dialog" aria-modal="true" aria-labelledby="role-dialog-title" x-show="dialog"
              @submit.prevent="save()" @keydown.tab="trapFocus($event)">
            <div class="dialog-title" id="role-dialog-title" x-text="id ? 'Rename role' : 'Add role'"></div>
            <div class="dialog-message alert danger" x-text="error"></div>
            <div class="dialog-contents">
                <div class="input-group mb-0" :class="{error: errors.name}">
                    <label for="role-name">Name</label>
                    <input type="text" id="role-name" x-model="name" maxlength="100" required autocomplete="off">
                    <div class="error-message" x-text="errors.name"></div>
                </div>
            </div>
            <div class="dialog-buttons">
                <button type="submit" class="primary" :class="{progress: busy}" :disabled="busy"
                        x-text="id ? 'Save' : 'Add'"></button>
                <button type="button" @click="closeDialog()">Cancel</button>
            </div>
        </form>
    </div>
</div>
