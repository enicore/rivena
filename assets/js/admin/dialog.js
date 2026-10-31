// The base of the modal dialogs (admin/partials/confirm-dialog.php and content-dialog.php): each is included once in
// the admin layout and opened by a window event from Controller; options is null while the dialog is closed.
class Dialog {
    options = null;
    returnFocus = null;

    // shows the dialog and focuses its first field, or else its first button
    open(options) {
        this.returnFocus = document.activeElement;
        this.options = options;
        this.$nextTick(() => this.$root.querySelector('.dialog input, .dialog-buttons button')?.focus());
    }

    // hides the dialog and gives the focus back to the element that had it
    close() {
        if (this.options) {
            this.options = null;
            this.returnFocus?.focus?.();
        }
    }

    // the dialog's width and height from the options (a CSS size like "600px" or "80%" of the window), set as style
    // properties, which the CSP allows (unlike a style attribute); an empty value keeps the stylesheet's size
    size() {
        return {
            width: this.options?.width ?? '',
            height: this.options?.height ?? '',
        };
    }
}
