<div id="page-events" x-data="eventController">
    <div class="flex justify-between items-center gap mb">
        <h4 class="mb-0">Event Log</h4>
        <input type="search" class="small" placeholder="Search" :value="table.search"
               @input.debounce.300ms="table.load({search: $event.target.value, page: 1})">
    </div>

    <table class="striped" :class="{loading: table.loading}" x-show="table.total">
        <thead>
            <tr>
                <th :aria-sort="table.ariaSort('created_at')">
                    <button type="button" class="sortable" @click="table.sortBy('created_at')">
                        Time <span x-text="table.arrow('created_at')" aria-hidden="true"></span>
                    </button>
                </th>
                <th :aria-sort="table.ariaSort('user_email')">
                    <button type="button" class="sortable" @click="table.sortBy('user_email')">
                        User <span x-text="table.arrow('user_email')" aria-hidden="true"></span>
                    </button>
                </th>
                <th :aria-sort="table.ariaSort('action')">
                    <button type="button" class="sortable" @click="table.sortBy('action')">
                        Action <span x-text="table.arrow('action')" aria-hidden="true"></span>
                    </button>
                </th>
                <th>Details</th>
                <th :aria-sort="table.ariaSort('ip')">
                    <button type="button" class="sortable" @click="table.sortBy('ip')">
                        IP address <span x-text="table.arrow('ip')" aria-hidden="true"></span>
                    </button>
                </th>
                <th>Country</th>
                <th>User agent</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="entry in table.rows" :key="entry.id">
                <tr>
                    <td class="whitespace-nowrap" x-text="entry.time"></td>
                    <td x-text="entry.user"></td>
                    <td x-text="entry.action"></td>
                    <td x-text="entry.message"></td>
                    <td x-text="entry.ip"></td>
                    <td x-text="entry.country"></td>
                    <td x-text="entry.user_agent"></td>
                </tr>
            </template>
        </tbody>
    </table>
    <p class="text-center opacity-60" x-show="!table.total">No records</p>

    <?= $this->partial('admin/partials/pager') ?>
</div>
