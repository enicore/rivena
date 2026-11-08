// The users page: the table, and log in as a user and delete, with a confirmation.
class UsersController extends Controller {
    init() {
        super.init();
        this.table = new Table(`${userPath}/users/list`, this.data.table, {query: true});
    }

    // the whole page loads again, as the user
    loginAs(user) {
        this.confirm({title: 'Log in as user', message: `Log in as ${user.email}? You'll be logged out.`,
            button: 'Log in'}, () => this.post(`${userPath}/users/login-as`, {id: user.id})
            .then(() => location.href = userPath));
    }

    remove(user) {
        this.confirm({title: 'Delete user', message: `Delete ${user.email}?`, button: 'Delete', danger: true},
            () => this.post(`${userPath}/users/delete`, {id: user.id}).then(() => {
                this.showNotification('User account deleted successfully.');
                this.table.load();
            }));
    }
}

window.usersController = () => new UsersController();
