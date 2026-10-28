// The Roles page: add and rename a role in a dialog, delete one with a confirmation.
class RolesController extends Controller {
    dialog = false;
    id = null; // the renamed role's, null for a new one
    name = '';

    openDialog(role = null) {
        this.dialog = true;
        this.id = role?.id ?? null;
        this.name = role?.name ?? '';
        this.error = '';
        this.errors = {};
        this.$nextTick(() => this.$root.querySelector('#role-name').focus());
    }

    closeDialog() {
        if (!this.busy) {
            this.dialog = false;
        }
    }

    save() {
        const isNew = this.id === null;

        this.submit(`${userPath}/roles/save`, {id: this.id, name: this.name}, data => {
            this.data.roles = data.roles;
            this.dialog = false;
            this.showNotification(isNew ? 'Role added successfully.' : 'Role renamed successfully.');
        });
    }

    remove(role) {
        const users = role.users === 1 ? '1 user loses it' : `${role.users} users lose it`;

        this.confirm({title: 'Delete role', message: `Delete the role ${role.name}? ${users}.`, button: 'Delete',
            danger: true}, () => this.post(`${userPath}/roles/delete`, {id: role.id}).then(data => {
            this.data.roles = data.roles;
            this.showNotification('Role deleted successfully.');
        }));
    }
}

window.rolesController = () => new RolesController();
