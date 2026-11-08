// WebAuthn in the browser, for the security key two-factor method: the server's options (binary values in base64url)
// for the browser's API, and its answer as JSON for the server.
const webAuthn = {
    supported() {
        return !!window.PublicKeyCredential;
    },

    // registers a new key; returns the answer with the key's name
    create(options, name) {
        const publicKey = {...options.publicKey, challenge: this.toBytes(options.publicKey.challenge),
            user: {...options.publicKey.user, id: this.toBytes(options.publicKey.user.id)},
            excludeCredentials: (options.publicKey.excludeCredentials ?? []).map(key => ({...key,
                id: this.toBytes(key.id)}))};

        return navigator.credentials.create({publicKey}).then(credential => JSON.stringify({name,
            clientDataJSON: this.toText(credential.response.clientDataJSON),
            attestationObject: this.toText(credential.response.attestationObject)}));
    },

    // signs the login's challenge with one of the user's keys; returns the answer
    get(options) {
        const publicKey = {...options.publicKey, challenge: this.toBytes(options.publicKey.challenge),
            allowCredentials: (options.publicKey.allowCredentials ?? []).map(key => ({...key,
                id: this.toBytes(key.id)}))};

        return navigator.credentials.get({publicKey}).then(credential => JSON.stringify({
            id: this.toText(credential.rawId),
            clientDataJSON: this.toText(credential.response.clientDataJSON),
            authenticatorData: this.toText(credential.response.authenticatorData),
            signature: this.toText(credential.response.signature)}));
    },

    // the browser's errors: cancelled or timed out, or the key is registered already
    error(error) {
        return error.name === 'InvalidStateError' ? 'This key is set up already.' :
            error.name === 'NotAllowedError' ? 'The security key wasn\'t used (cancelled or timed out).' :
                error.message;
    },

    toBytes(text) {
        return Uint8Array.from(atob(text.replace(/-/g, '+').replace(/_/g, '/')), char => char.charCodeAt(0));
    },

    toText(buffer) {
        return btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_')
            .replace(/=+$/, '');
    },
};
