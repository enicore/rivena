// The user page: add, edit, disable and delete a user, and the logged-in user's account (data.account); the
// two-factor authentication tab (data.twoFactor): the logged-in user's methods and recovery codes, each change with the
// current password, and an admin turns another user's off.
class UserController extends Controller {
    tab = 'account';
    twoFactorError = '';
    setup = null; // the method being set up, with what its setup shows: {method, name, qr, secret} or {..., options}
    code = ''; // the setup's code
    keyName = ''; // the new security key's name
    codes = null; // the recovery codes shown
    codesNew = false; // the codes are new: save them
    savedEmail = ''; // the user's email as saved (changing it asks for the password)

    init() {
        super.init();
        this.tab = this.data.tab ?? 'account';
        this.savedEmail = this.data.user.email;
    }

    // saves the user; changing the email or password of oneself or of an admin (data.askPassword) asks for the current
    // password in the confirmation dialog first, which shows it when it's wrong
    save() {
        const user = this.data.user;
        const isNew = user.id === null;
        const url = this.data.account ? `${userPath}/account/save` : `${userPath}/users/save`;
        const send = data => this.submit(url, data, () => {
            if (this.data.account) {
                this.savedEmail = user.email;
                user.password = '';
                // the emptied input hides its strength bar
                this.$nextTick(() => document.getElementById('user-password').dispatchEvent(new Event('input')));
                this.showNotification('Account saved successfully.');
            } else {
                this.showNotification(isNew ? 'User account created successfully.' :
                    'User account saved successfully.');
                window.rivena_spa.navigate(`${userPath}/users`);
            }
        });

        if (!this.data.askPassword || (user.email === this.savedEmail && user.password === '')) {
            send(user);
            return;
        }

        this.confirm({title: 'Save changes', message: 'Enter your password to change the email or password.',
            button: 'Save', password: true}, password => send({...user, current_password: password}).then(() => {
            const error = this.errors.current_password;
            delete this.errors.current_password;
            return error ? Promise.reject(new Error(error)) : null;
        }));
    }

    // disables or enables the user, after a confirmation
    toggleDisabled() {
        const disabled = !this.data.user.disabled;

        this.confirm({
            title: disabled ? 'Disable user' : 'Enable user',
            message: disabled ? 'Disable the user? The user is logged out and can\'t log in until enabled again.' :
                'Enable the user? The user can log in again.',
            button: disabled ? 'Disable' : 'Enable',
            danger: disabled,
        }, () => this.post(`${userPath}/users/disable`, {id: this.data.user.id, disabled})
            .then(() => this.data.user.disabled = disabled));
    }

    // deletes the user after a confirmation, then goes back to the users page
    remove() {
        this.confirm({title: 'Delete user', message: `Delete ${this.data.user.email}?`, button: 'Delete', danger: true},
            () => this.post(`${userPath}/users/delete`, {id: this.data.user.id}).then(() => {
                this.showNotification('User account deleted successfully.');
                window.rivena_spa.navigate(`${userPath}/users`);
            }));
    }

    twoFactorOn() {
        return this.data.twoFactor.methods.some(method => method.enabled);
    }

    methodIcon(method) {
        return method.id === 'webauthn' ? 'icon-key' : 'icon-smartphone';
    }

    // asks for the password, then starts the method's setup (its QR code)
    startSetup(method) {
        this.withPassword({title: `Set up: ${method.name}`, message: 'Enter your password to continue.',
            button: 'Continue'}, 'setup', {method: method.id}, page => {
            this.setup = {...page, method: method.id, name: method.name};
            this.code = '';
            this.keyName = '';
            this.codes = null;
            this.errors = {};
        });
    }

    // leaves the setup, without its error
    cancelSetup() {
        this.setup = null;
        this.twoFactorError = '';
    }

    // has the browser register the security key, then saves it (finishSetup() with the browser's answer as the code)
    registerKey() {
        if (!webAuthn.supported()) {
            this.twoFactorError = 'This browser doesn\'t support security keys.';
            return;
        }

        this.busy = true;
        this.twoFactorError = '';
        webAuthn.create(this.setup.options, this.keyName)
            .then(answer => this.finishSetup(answer))
            .catch(error => {
                this.busy = false;
                this.twoFactorError = webAuthn.error(error);
            });
    }

    // saves the method being set up when its code (or a security key's answer) is right; the first method shows the
    // new recovery codes
    finishSetup(code = this.code) {
        this.busy = true;
        this.errors = {};
        this.twoFactorError = '';

        this.post(`${userPath}/account/two-factor/enable`, {code})
            .then(result => {
                this.data.twoFactor = result.twoFactor;
                this.setup = null;
                this.codes = result.codes;
                this.codesNew = !!result.codes;
                this.showNotification('Two-factor authentication method added.');
            })
            .catch(error => {
                if (error.errors && this.setup?.method === 'totp') {
                    this.errors = error.errors;
                } else {
                    this.twoFactorError = error.errors ? 'The security key couldn\'t be registered.' : error.message;
                }
            })
            .finally(() => this.busy = false);
    }

    // removes the method, or one of its keys, after the password
    disableMethod(method, key = null) {
        const last = this.data.twoFactor.methods.filter(method => method.enabled).length === 1 &&
            (!key || method.keys.length === 1);

        this.withPassword({title: `Remove: ${key?.name ?? method.name}`, message: 'Enter your password to remove ' +
            (key ? 'the key.' : 'the method.') + (last ? ' It\'s your last one: two-factor authentication turns ' +
            'off, with the recovery codes.' : ''), button: 'Remove', danger: true}, 'disable',
            {method: method.id, key: key?.id ?? ''}, result => {
            this.data.twoFactor = result.twoFactor;
            this.codes = null;
            this.showNotification('Two-factor authentication method removed.');
        });
    }

    // removes all the methods and the recovery codes, after the password
    turnOff() {
        this.withPassword({title: 'Turn off two-factor authentication', message: 'Enter your password to turn off ' +
            'two-factor authentication. Your methods and recovery codes are removed.', button: 'Turn off',
            danger: true}, 'turn-off', {}, result => {
            this.data.twoFactor = result.twoFactor;
            this.codes = null;
            this.showNotification('Two-factor authentication turned off.');
        });
    }

    // shows the recovery codes (after the password) or hides them
    toggleCodes() {
        if (this.codes) {
            this.codes = null;
            return;
        }

        this.withPassword({title: 'Recovery codes', message: 'Enter your password to see the recovery codes.',
            button: 'Show codes'}, 'recovery-codes', {}, result => {
            this.data.twoFactor = result.twoFactor;
            this.codes = result.codes;
            this.codesNew = false;
        });
    }

    // replaces the recovery codes, after the password
    renewCodes() {
        this.withPassword({title: 'Generate new codes', message: 'Enter your password to generate new recovery ' +
            'codes. The old ones stop working.', button: 'Generate', danger: true}, 'recovery-codes', {renew: true},
            result => {
                this.data.twoFactor = result.twoFactor;
                this.codes = result.codes;
                this.codesNew = true;
            });
    }

    copyCodes() {
        navigator.clipboard.writeText(this.codes.join('\n'))
            .then(() => this.showNotification('Recovery codes copied.'));
    }

    // turns another user's two-factor authentication off (an admin), with the admin's password
    resetTwoFactor() {
        this.confirm({title: 'Turn off two-factor authentication', message: `Turn off two-factor authentication ` +
            `for ${this.data.user.email}? The user can log in with the password only, and set it up again. Enter ` +
            `your password to continue.`, button: 'Turn off', danger: true, password: true},
            password => this.post(`${userPath}/users/two-factor-reset`, {id: this.data.user.id, password})
                .then(result => this.data.twoFactor = result.twoFactor));
    }

    // posts to the account's two-factor action with the password from the confirmation dialog; its errors stay in the
    // dialog
    withPassword(options, action, data, done) {
        this.twoFactorError = '';
        this.confirm({...options, password: true}, password =>
            this.post(`${userPath}/account/two-factor/${action}`, {...data, password}).then(done));
    }
}

window.userController = () => new UserController();
