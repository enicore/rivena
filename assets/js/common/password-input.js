// The eye button of a password input (admin/partials/password) shows and hides the password.
document.addEventListener('click', event => {
    const input = event.target.closest('.password-eye')?.previousElementSibling;
    input && (input.type = input.type === 'password' ? 'text' : 'password');
});

// A new password's input (admin/partials/password-input): the server rates the password as it's typed (its score and
// the reason it can't be used), and the buttons generate a random password and copy it.
window.passwordInput = () => ({
    score: null, // null: nothing typed
    error: null,
    empty: true,
    copied: false,

    // posts the password with the form's email; the CSRF token is the admin layout's, or the form's on the login pages
    check() {
        const input = this.$refs.input;
        const password = input.value;
        const token = document.querySelector('meta[name="csrf"]')?.content ?? input.form.elements.csrf.value;

        if (password === '') {
            this.score = null;
            this.error = null;
            return;
        }

        fetch(this.$root.dataset.url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': token},
            body: JSON.stringify({password, email: input.form.querySelector('input[type="email"]')?.value ?? ''}),
        })
            .then(response => response.json())
            .then(response => {
                // an answer for what's still typed
                if (response.success && input.value === password) {
                    this.score = response.data.score;
                    this.error = response.data.error;
                }
            });
    },

    // fills in a random password, shown; only bytes below the largest multiple of the characters' count, so each
    // character is as likely (no look-alikes like 0 and O)
    generate() {
        const chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!#$%&*+-=?@_';
        let password = '';

        while (password.length < this.$root.dataset.length) {
            const byte = crypto.getRandomValues(new Uint8Array(1))[0];
            byte < 256 - 256 % chars.length && (password += chars[byte % chars.length]);
        }

        this.$refs.input.value = password;
        this.$refs.input.type = 'text';
        this.$refs.input.dispatchEvent(new Event('input'));
    },

    copy() {
        navigator.clipboard.writeText(this.$refs.input.value).then(() => {
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        });
    },
});
