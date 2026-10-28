<?php // the Error Log page: the site's errors and warnings (ErrorLog), the last seen first ?>
<div id="page-error-log" x-data="errorLogController">
    <div class="flex justify-between items-center gap mb">
        <h4 class="mb-0">Error Log</h4>
        <input type="search" class="small" placeholder="Search" :value="table.search"
               @input.debounce.300ms="table.load({search: $event.target.value, page: 1})">
    </div>

    <table class="striped" :class="{loading: table.loading}" x-show="table.total">
        <thead>
            <tr>
                <th :aria-sort="table.ariaSort('last_at')">
                    <button type="button" class="sortable" @click="table.sortBy('last_at')">
                        Last seen <span x-text="table.arrow('last_at')" aria-hidden="true"></span>
                    </button>
                </th>
                <th :aria-sort="table.ariaSort('level')">
                    <button type="button" class="sortable" @click="table.sortBy('level')">
                        Level <span x-text="table.arrow('level')" aria-hidden="true"></span>
                    </button>
                </th>
                <th>Message</th>
                <th>Path</th>
                <th :aria-sort="table.ariaSort('count')">
                    <button type="button" class="sortable" @click="table.sortBy('count')">
                        Count <span x-text="table.arrow('count')" aria-hidden="true"></span>
                    </button>
                </th>
            </tr>
        </thead>
        <tbody>
            <template x-for="error in table.rows" :key="error.id">
                <tr>
                    <td class="whitespace-nowrap" x-text="error.time"></td>
                    <td x-text="error.level" :class="{'text-danger': error.level === 'error'}"></td>
                    <td class="break-words" x-text="error.message"></td>
                    <td class="break-words" x-text="error.path"></td>
                    <td x-text="error.count"></td>
                </tr>
            </template>
        </tbody>
    </table>
    <p class="text-center opacity-60" x-show="!table.total">No errors</p>

    <?= $this->partial('admin/partials/pager') ?>
</div>
