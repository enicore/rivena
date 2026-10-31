<?php // the content dialog (Controller.showDialog()), with a copy of a template of the page; it's in the layout,
      // which RivenaSpa doesn't replace: another page (e.g. after Back) hides it ?>
<div x-data="contentDialog" @content-dialog.window="open($event.detail)" @keydown.escape.window="close()"
     @spa-before-page-load.document="options = null"
     x-effect="document.body.classList.toggle('modal-visible', !!options)">
    <div class="modal-mask" :class="{visible: options}" @click.self="close()">
        <template x-if="options">
            <div class="dialog content-dialog" role="dialog" aria-modal="true" aria-labelledby="content-dialog-title"
                 :style="size()" @keydown.tab="trapFocus($event)">
                <div class="dialog-title" id="content-dialog-title" x-text="options.title"></div>
                <div class="dialog-contents" x-ref="contents"></div>
                <div class="dialog-buttons">
                    <button @click="close()" x-text="options.button"></button>
                </div>
            </div>
        </template>
    </div>
</div>
