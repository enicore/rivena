// The Permissions page: the roles' permissions, saved together.
class PermissionsController extends Controller {
    save() {
        this.submit(`${userPath}/permissions/save`, {roles: this.data.roles}, data => {
            this.data.roles = data.roles;
            this.showNotification('Permissions saved successfully.');
        });
    }
}

window.permissionsController = () => new PermissionsController();
