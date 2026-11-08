// The Roles page: add and rename a role in a dialog, delete one with a confirmation.
class RolesController extends Controller {
    id = null; // the renamed role's, null for a new one
    name = '';

    // opens the dialog for the role, or for a new one
    edit(role = null) {
        this.id = role?.id ?? null;
        this.name = role?.name ?? '';
        this.error = '';
        this.errors = {};
        this.openDialog('role');
    }

    save() {
        const isNew = this.id === null;

        this.submit(`${userPath}/roles/save`, {id: this.id, name: this.name}, data => {
            this.data.roles = data.roles;
            this.dialog = null;
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
