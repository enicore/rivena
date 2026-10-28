<?php
// The user page's two-factor authentication tab: the logged-in user's methods (set up, remove), the recovery codes
// (show, copy, renew) and a method's setup; another user's is only on or off, which an admin turns off.
?>
<div class="two-factor">
    <div class="alert danger mb" x-show="twoFactorError" x-text="twoFactorError"></div>

    <template x-if="data.own && !setup">
        <div>
            <div class="alert warning mb" x-show="data.twoFactor.required && !twoFactorOn()">
                Your account needs two-factor authentication. Set up a method to continue.
            </div>
            <p x-show="!twoFactorOn()">
                Two-factor authentication protects your account with a second step after the password: a code that
                only you have.
            </p>
            <div class="alert success mb flex items-center justify-between gap" x-show="twoFactorOn()">
                <span>Two-factor authentication is on.</span>
                <button type="button" class="small" x-show="!data.twoFactor.required" @click="turnOff()">
                    Turn off
                </button>
            </div>

            <h6 x-show="twoFactorOn()">Your methods</h6>
            <?php // a method with keys (security keys) lists them, each removed on its own; the last one takes the method ?>
            <template x-for="method in data.twoFactor.methods.filter(method => method.enabled)" :key="method.id">
                <div class="two-factor-method">
                    <span :class="methodIcon(method)"></span>
                    <div>
                        <div class="name" x-text="method.name"></div>
                        <div class="opacity-60" x-text="method.description"></div>
                        <template x-for="key in method.keys ?? []" :key="key.id">
                            <div class="key">
                                <span x-text="key.name"></span>
                                <a href="#" @click.prevent="disableMethod(method, key)">Remove</a>
                            </div>
                        </template>
                        <div class="actions">
                            <a href="#" @click.prevent="startSetup(method)" x-show="method.keys">Add a key</a>
                            <a href="#" @click.prevent="disableMethod(method)" x-show="!method.keys">Remove</a>
                        </div>
                    </div>
                </div>
            </template>

            <div class="two-factor-method" x-show="twoFactorOn()">
                <span class="icon-lock"></span>
                <div>
                    <div class="name">
                        Recovery codes
                        <span class="opacity-60" x-text="`(${data.twoFactor.recoveryCodes} left)`"></span>
                    </div>
                    <div class="opacity-60">Single-use codes for when you can't use your other methods.</div>
                    <div class="actions">
                        <a href="#" @click.prevent="toggleCodes()" x-text="codes ? 'Hide codes' : 'Show codes'"></a>
                        <a href="#" @click.prevent="copyCodes()" x-show="codes?.length">Copy codes</a>
                        <a href="#" @click.prevent="renewCodes()">Generate new codes</a>
                    </div>
                    <template x-if="codes">
                        <div>
                            <div class="alert warning mt-sm" x-show="codesNew">
                                Save these codes somewhere safe. Each one works once, when you can't use your other
                                methods.
                            </div>
                            <div class="recovery-codes">
                                <template x-for="code in codes" :key="code">
                                    <span x-text="code"></span>
                                </template>
                                <span class="opacity-60" x-show="!codes.length">No codes left.</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <template x-if="data.twoFactor.methods.some(method => !method.enabled)">
                <h6 x-text="twoFactorOn() ? 'Add a method' : 'Methods'"></h6>
            </template>
            <template x-for="method in data.twoFactor.methods.filter(method => !method.enabled)" :key="method.id">
                <div class="two-factor-method">
                    <span :class="methodIcon(method)"></span>
                    <div>
                        <div class="name" x-text="method.name"></div>
                        <div class="opacity-60" x-text="method.description"></div>
                    </div>
                    <button type="button" class="small" @click="startSetup(method)">Set up</button>
                </div>
            </template>
        </div>
    </template>

    <?php // TOTP's setup: the app scans the QR code (or gets the key), then the user enters its code ?>
    <template x-if="setup?.method === 'totp'">
        <form class="page-form" @submit.prevent="finishSetup()">
            <h6 x-text="`Set up: ${setup.name}`"></h6>
            <ol>
                <li>Open your authenticator app and scan the QR code, or enter the key.</li>
                <li>Enter the 6-digit code the app shows.</li>
            </ol>
            <div class="two-factor-qr" x-html="setup.qr"></div>
            <p class="text-center font-mono" x-text="setup.secret"></p>
            <div class="input-group" :class="{error: errors.code}">
                <label for="two-factor-code">Code</label>
                <input type="text" id="two-factor-code" x-model="code" required inputmode="numeric"
                       autocomplete="one-time-code"
                       x-init="$el.focus()">
                <div class="error-message" x-text="errors.code"></div>
            </div>
            <div class="flex gap-sm mt">
                <button type="submit" class="primary" :class="{progress: busy}" :disabled="busy">Finish</button>
                <button type="button" @click="cancelSetup()">Cancel</button>
            </div>
        </form>
    </template>

    <?php // a security key's setup: its name, then the browser registers it ?>
    <template x-if="setup?.method === 'webauthn'">
        <form class="page-form" @submit.prevent="registerKey()">
            <h6 x-text="`Set up: ${setup.name}`"></h6>
            <p>Name the key, then follow your browser's steps: touch your security key, or use your phone's or
                computer's fingerprint, face or screen lock.</p>
            <div class="input-group">
                <label for="two-factor-key-name">Name</label>
                <input type="text" id="two-factor-key-name" x-model="keyName" maxlength="50"
                       placeholder="e.g. YubiKey, iPhone" x-init="$el.focus()">
            </div>
            <div class="flex gap-sm mt">
                <button type="submit" class="primary" :class="{progress: busy}" :disabled="busy">Register key</button>
                <button type="button" @click="cancelSetup()">Cancel</button>
            </div>
        </form>
    </template>

    <?php // another user's: an admin can only turn it off ?>
    <template x-if="!data.own">
        <div>
            <p x-show="twoFactorOn()">
                Two-factor authentication is on:
                <span x-text="data.twoFactor.methods.filter(method => method.enabled).map(method => method.name)
                    .join(', ')"></span>.
            </p>
            <p x-show="!twoFactorOn()">Two-factor authentication is off.</p>
            <button type="button" class="danger" x-show="twoFactorOn()" @click="resetTwoFactor()">
                Turn off two-factor authentication
            </button>
        </div>
    </template>
</div>
