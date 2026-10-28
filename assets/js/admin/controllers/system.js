// The System Info page: the test email.
class SystemController extends Controller {
    sendTestEmail() {
        this.submit(`${userPath}/system/test-email`, {},
            data => this.showNotification(`Test email sent to ${data.email}.`));
    }
}

window.systemController = () => new SystemController();
