// The confirmation dialog (admin/partials/confirm-dialog.php), opened by Controller.confirm(); with options.password,
// it asks for the current password, which goes to the action.
class ConfirmDialog {
    options = null;
    error = '';
    busy = false;
    password = '';
    returnFocus = null;

    // shows the dialog and focuses its password field or its button
    open(options) {
        this.returnFocus = document.activeElement;
        this.options = options;
        this.error = '';
        this.password = '';
        this.$nextTick(() => this.$root.querySelector('.dialog input, .dialog-buttons button')?.focus());
    }

    // hides the dialog (not while the action runs) and gives the focus back
    close() {
        if (!this.busy && this.options) {
            this.options = null;
            this.returnFocus?.focus?.();
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
