<?php // it's in the layout, which RivenaSpa doesn't replace: another page (e.g. after Back) hides it, also while busy ?>
<div x-data="confirmDialog" @confirm.window="open($event.detail)" @keydown.escape.window="close()"
     @spa-before-page-load.document="options = null"
     x-effect="document.body.classList.toggle('modal-visible', !!options)">
    <div class="modal-mask" :class="{visible: options, message: error}" @click.self="close()">
        <template x-if="options">
            <div class="dialog confirm-dialog" role="alertdialog" aria-modal="true" @keydown.tab="trapFocus($event)">
                <div class="dialog-title" x-text="options.title"></div>
                <div class="dialog-message alert danger" x-text="error"></div>
                <div class="dialog-contents">
                    <div x-text="options.message"></div>
                    <template x-if="options.password">
                        <div class="mt">
                            <?= $this->partial('admin/partials/password', ['attributes' => 'x-model="password" ' .
                                'placeholder="Current password" autocomplete="current-password" ' .
                                'aria-label="Current password" @keydown.enter.prevent="busy || run()"']) ?>
                        </div>
                    </template>
                </div>
                <div class="dialog-buttons">
                    <button :class="[options.danger ? 'danger' : 'primary', {progress: busy}]" :disabled="busy"
                            @click="run()" x-text="options.button"></button>
                    <button @click="close()">Cancel</button>
                </div>
            </div>
        </template>
    </div>
</div>
