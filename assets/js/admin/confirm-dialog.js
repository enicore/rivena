// The confirmation dialog (admin/partials/confirm-dialog.php), opened by Controller.confirm(); with options.password,
// it asks for the current password, which goes to the action.
class ConfirmDialog extends Dialog {
    error = '';
    busy = false;
    password = '';

    // shows the dialog without the previous error and password
    open(options) {
        this.error = '';
        this.password = '';
        super.open(options);
    }

    // hides the dialog, but not while the action runs
    close() {
        if (!this.busy) {
            super.close();
        }
    }

    // runs the action: closes the dialog when it's done, or shows its error
    run() {
        this.busy = true;
        this.error = '';

        Promise.resolve(this.options.action(this.password))
            .then(() => { this.busy = false; this.close(); })
            .catch(error => { this.busy = false; this.error = error.message ?? error; });
    }
}

window.confirmDialog = () => new ConfirmDialog();
