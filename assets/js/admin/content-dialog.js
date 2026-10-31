// The content dialog (admin/partials/content-dialog.php), opened by Controller.showDialog(): shows a copy of the
// <template> that options.content selects, which the page's view has, so the content is written in PHP like the page.
class ContentDialog extends Dialog {
    // shows the dialog with a copy of the template's content
    open(options) {
        super.open(options);
        this.$nextTick(() => {
            const template = document.querySelector(options.content);
            this.$refs.contents.replaceChildren(template.content.cloneNode(true));
        });
    }
}

window.contentDialog = () => new ContentDialog();
