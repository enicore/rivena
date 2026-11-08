// The pages' base: userPath, postJson(), trapFocus() and Controller, the class the page controllers extend.

// the admin area's path, e.g. "/adm", for the request URLs
const userPath = document.querySelector('meta[name="user-path"]')?.content ?? '';

// keeps Tab inside the dialog (@keydown.tab="trapFocus($event)" on it, or the element given): its fields, links and
// enabled buttons
function trapFocus(event, dialog = event.currentTarget) {
    const selector = 'input, select, textarea, a[href], button:not([disabled])';
    const elements = [...dialog.querySelectorAll(selector)];

    if (!elements.length) {
        return;
    }

    const index = elements.indexOf(document.activeElement);
    const next = event.shiftKey ? (index <= 0 ? elements.length - 1 : index - 1) : (index + 1) % elements.length;
    event.preventDefault();
    elements[next].focus();
}

// posts the data as JSON (with the CSRF token) and returns the response's data; errors reject with an Error whose
// errors are the form fields' errors, if any; retries once with a new CSRF token (a remember-me login after the session
// expired starts a new session)
function postJson(url, data = {}) {
    const token = document.querySelector('meta[name="csrf"]')?.content ?? '';

    return fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': token},
        body: JSON.stringify(data),
    })
        // a PHP error page isn't JSON
        .then(response => response.json().catch(() => Promise.reject(new Error(`Server error (${response.status}).`))),
            () => Promise.reject(new Error('Network error.')))
        .then(response => {
            if (response.csrf && response.csrf !== token) {
                document.querySelector('meta[name="csrf"]').content = response.csrf;
                return postJson(url, data);
            }
            if (response.redirect) {
                location.href = response.redirect;
            }
            return response.success ? response.data : Promise.reject(Object.assign(new Error(response.error),
                {errors: response.errors}));
        });
}

class Controller {
    data = {}
    error = ''; // the form's error
    errors = {}; // the errors of the form's fields: {email: 'Another user has this email.'}
    busy = false;
    dialog = null; // the open dialog's name (its x-ref), see openDialog()
    dialogReturnFocus = null;
    dialogKeys = null; // the keydown listener, removed in destroy()

    // Alpine calls it; the page's data (sendPage()) goes to this.data
    init() {
        if (window.spa_page_data !== undefined) {
            this.data = window.spa_page_data;
            delete window.spa_page_data;
        }

        // the open dialog closes with Escape and keeps Tab inside it
        this.dialogKeys = event => {
            if (this.dialog && event.key === 'Escape') {
                this.closeDialog();
            } else if (this.dialog && event.key === 'Tab') {
                trapFocus(event, this.$refs[this.dialog]);
            }
        };
        document.addEventListener('keydown', this.dialogKeys);

        // a closed dialog gives the focus back to the element that had it
        this.$watch('dialog', (dialog, previous) => {
            document.body.classList.toggle('modal-visible', !!dialog);
            !dialog && previous && this.dialogReturnFocus?.focus?.();
        });
    }

    // Alpine calls it when the page is replaced (RivenaSpa)
    destroy() {
        document.removeEventListener('keydown', this.dialogKeys);
    }

    // opens the page's dialog with the x-ref name (a form bound to the page's data, with Rivena's dialog markup) and
    // focuses its first field:
    // <div class="modal-mask" :class="{visible: dialog === 'role'}" @click.self="closeDialog()">
    //     <form class="dialog" x-ref="role" x-show="dialog === 'role'" ...>
    openDialog(name) {
        this.dialogReturnFocus = document.activeElement;
        this.dialog = name;
        this.$nextTick(() => {
            this.$refs[name]?.querySelector('input, select, textarea, .dialog-buttons button')?.focus();
        });
    }

    // closes the open dialog (Escape, a click outside it, Cancel), unless a form is being sent; after saving, the page
    // sets dialog = null
    closeDialog() {
        if (!this.busy) {
            this.dialog = null;
        }
    }

    // posts the data, see postJson()
    post(url, data = {}) {
        return postJson(url, data);
    }

    // posts a form's data (busy meanwhile); calls done() on success, or puts the errors in errors[field], or in error
    // if the form has no such field
    submit(url, data, done) {
        this.busy = true;
        this.error = '';
        this.errors = {};

        return this.post(url, data)
            .then(done)
            .catch(error => {
                for (const [field, message] of Object.entries(error.errors ?? {'': error.message})) {
                    if (field in data) {
                        this.errors[field] = message;
                    } else {
                        this.error = message;
                    }
                }
            })
            .finally(() => this.busy = false);
    }

    // shows the confirmation dialog; options: {title, message, button, danger, password, width, height}, action returns
    // a promise; with password true, the dialog asks for the current password and gives it to action; width and height
    // as in showDialog()
    confirm(options, action) {
        window.dispatchEvent(new CustomEvent('confirm', {detail: {button: 'OK', ...options, action}}));
    }

    // shows the content dialog; options: {title, content, button, width, height}, content selects a <template> of the
    // page whose copy the dialog shows, width and height are CSS sizes like "600px" or "80%" (of the window)
    showDialog(options) {
        window.dispatchEvent(new CustomEvent('content-dialog', {detail: {button: 'Close', ...options}}));
    }

    // shows a message at the corner for 5 seconds; type is a color class like "danger" ("success" by default); the
    // first one adds #notifications to the page, which RivenaSpa keeps (it replaces only the content)
    showNotification(message, type) {
        const notifications = document.querySelector('#notifications') ??
            document.body.appendChild(Object.assign(document.createElement('div'), {id: 'notifications'}));
        const el = document.createElement('div');
        el.className = `card ${type ?? 'success'}`;
        el.textContent = message;
        notifications.prepend(el);
        const timeout = setTimeout(() => el.remove(), 5000);
        el.addEventListener('click', () => { clearTimeout(timeout); el.remove(); }, {once: true});
    }
}

window.controller = () => new Controller();
