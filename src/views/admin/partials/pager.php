<div class="grid grid-cols-3 items-center gap" x-show="table.total">
    <span class="font-sm opacity-60" x-text="table.shown"></span>
    <div class="flex justify-center items-center gap-sm" x-show="table.pages > 1">
        <button class="small" title="First page" aria-label="First page" :disabled="table.page <= 1"
                @click="table.load({page: 1})">&lt;&lt;</button>
        <button class="small" title="Previous page" aria-label="Previous page" :disabled="table.page <= 1"
                @click="table.load({page: table.page - 1})">&lt;</button>
        <span class="font-sm opacity-60" x-text="`Page ${table.page} of ${table.pages}`"></span>
        <button class="small" title="Next page" aria-label="Next page" :disabled="table.page >= table.pages"
                @click="table.load({page: table.page + 1})">&gt;</button>
        <button class="small" title="Last page" aria-label="Last page" :disabled="table.page >= table.pages"
                @click="table.load({page: table.pages})">&gt;&gt;</button>
    </div>
</div>
<div class="alert danger mt" x-show="table.error" x-text="table.error"></div>
