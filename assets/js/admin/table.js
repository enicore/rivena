// A table whose rows come from the server, a page at a time (AdminController::dataTable()). In a controller's init():
// this.table = new Table(`${userPath}/users/list`, this.data.table, {query: true}) ({query: true} keeps the page,
// sort and search in the URL). The admin/partials/pager view shows its pages.
class Table {
    loading = false;
    error = '';
    request = 0;

    constructor(url, data, {query = false} = {}) {
        this.url = url;
        this.query = query;
        Object.assign(this, data);
    }

    // the number of pages
    get pages() {
        return Math.max(1, Math.ceil(this.total / this.perPage));
    }

    // the first and last row shown
    get from() {
        return (this.page - 1) * this.perPage + 1;
    }

    get to() {
        return Math.min(this.page * this.perPage, this.total);
    }

    // the rows shown, e.g. "26–50 of 120"
    get shown() {
        if (this.pages === 1) {
            return `${this.total} of ${this.total}`;
        }
        return this.from === this.to ? `${this.from} of ${this.total}` : `${this.from}–${this.to} of ${this.total}`;
    }

    // loads a page; changes are the page, sort, dir or search to change
    load(changes = {}) {
        const request = ++this.request;
        const params = {page: this.page, sort: this.sort, dir: this.dir, search: this.search, ...changes};
        this.loading = true;

        return postJson(this.url, params)
            .then(data => {
                // the search sends a request per change
                if (request === this.request) {
                    Object.assign(this, data);
                    this.error = '';
                    this.query && this.updateUrl();
                }
            })
            .catch(error => this.error = error.message)
            .finally(() => request === this.request && (this.loading = false));
    }

    // puts the page, sort and search in the URL
    updateUrl() {
        const url = new URL(location.href);
        const params = {
            page: this.page > 1 ? this.page : null,
            sort: this.sort,
            dir: this.dir,
            search: this.search || null,
        };

        for (const [name, value] of Object.entries(params)) {
            value === null ? url.searchParams.delete(name) : url.searchParams.set(name, value);
        }

        window.rivena_spa?.replace(url.href);
    }

    // sorts by the column, or reverses the order if it's sorted by it
    sortBy(column) {
        this.load({sort: column, dir: this.sort === column && this.dir === 'asc' ? 'desc' : 'asc', page: 1});
    }

    // the sort arrow for the column's header
    arrow(column) {
        return this.sort === column ? (this.dir === 'asc' ? '▲' : '▼') : '';
    }

    // the header's aria-sort, for screen readers (false: no attribute)
    ariaSort(column) {
        return this.sort === column && (this.dir === 'asc' ? 'ascending' : 'descending');
    }
}
