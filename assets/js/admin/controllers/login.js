// The login page's forms, and the password reset pages' (their answer is a message). After the password, a user with
// two-factor authentication gets the second form: a code of one of the user's methods (twoFactor, the first one
// selected), or a security key's signature of the server's challenge (useKey()).
class LoginController {
    loading = false;
    error = '';
    message = '';
    twoFactor = null; // the user's methods: [{id, name}]
    method = '';

    submit(event) {
        this.error = '';
        this.message = '';
        this.loading = true;
        const form = event.target;

        fetch(form.action, {method: 'POST', headers: {'Accept': 'application/json'}, body: new FormData(form)})
            // a wrong password comes with 401 and a JSON error message
            .then(response => response.headers.get('Content-Type')?.includes('json') ? response.json() : Promise.reject())
            .then(response => {
                if (response.success && response.data.twoFactor) {
                    this.twoFactor = response.data.twoFactor;
                    this.useMethod(this.twoFactor[0].id);
                } else if (response.success && response.data.redirect) {
                    window.location.href = response.data.redirect;
                } else if (response.success) {
                    this.message = response.data.message;
                } else if (response.restart) {
                    // the second step expired: the password again
                    this.twoFactor = null;
                    this.error = response.error;
                } else {
                    this.error = response.error;
                }
            })
            .catch(() => this.error = 'Network or server error.')
            .finally(() => this.loading = false);
    }

    // selects the method of the code, with an empty, focused field
    useMethod(id) {
        this.method = id;
        this.error = '';
        this.$nextTick(() => {
            const code = document.getElementById('code');
            code.value = '';
            code.type === 'text' && code.focus();
        });
    }

    // gets the login's challenge, has the browser sign it with a security key, and sends the answer as the code
    useKey(form) {
        if (!webAuthn.supported()) {
            this.error = 'This browser doesn\'t support security keys.';
            return;
        }

        this.error = '';
        this.loading = true;
        const body = new FormData();
        body.append('csrf', form.elements.csrf.value);
        body.append('method', this.method);

        fetch(form.dataset.challenge, {method: 'POST', headers: {'Accept': 'application/json'}, body})
            .then(response => response.json())
            .then(response => {
                if (!response.success) {
                    response.restart && (this.twoFactor = null);
                    return Promise.reject(new Error(response.error));
                }
                return webAuthn.get(response.data.options);
            })
            .then(answer => {
                form.elements.code.value = answer;
                this.submit({target: form});
            })
            .catch(error => {
                this.loading = false;
                this.error = webAuthn.error(error);
            });
    }
}

window.loginController = () => new LoginController();
