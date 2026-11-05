import TableSorter from "../../tools/tablesorter.js";
import ExportCSV from "../../tools/export_csv.js";

// admin order list. the status select swaps the endpoint query and reloads
export default class AdminOrders {
    constructor({ table = "orders-table", endpoint = "/api/admin/orders/list" } = {}) {
        this.endpoint = endpoint;
        this.status = document.getElementById('orders-status');

        this.table = new TableSorter({
            table,
            endpoint: this.url(),
            rows_select: "orders-rows",
            info: "orders-info",
            pager: "orders-pager",
            search: "orders-search",
            sort: "created",
            order: "desc",
            search_delay: 300,
            columns: [
                { render: (value, row) => this.link(row) },
                { render: (value) => value ? String(value).slice(0, 16) : "" },
                { render: (value, row) => value ?? row.email },
                null,
                null,
                { render: (value) => this.badge(value) },
                { render: (value, row) => `$${Number(value).toFixed(2)} ${row.currency}` },
            ],
        });

        this.status?.addEventListener('change', () => {
            this.table.endpoint = this.url();
            this.table.page = 1;
            this.table.load();
            history.replaceState(null, "", this.status.value ? `?status=${this.status.value}` : location.pathname);
        });

        // the export takes the same search and status the table is showing, all pages of it
        this.export = new ExportCSV({
            button: "orders-export",
            endpoint: endpoint.replace(/\/list$/, '/export'),
            data: () => ({ input: document.getElementById('orders-search')?.value ?? '', status: this.status?.value ?? '' }),
            filename: () => `orders-${this.status?.value || 'all'}-${new Date().toISOString().slice(0, 10)}`,
        });

        document.getElementById(table)?.addEventListener('click', (e) => {
            if (e.target.closest('a')) return;
            const tr = e.target.closest('tbody tr');
            const row = tr && this.table.data[tr.rowIndex - 1];
            if (row) window.location.href = `/admin/orders/${row.uuid}`;
        });
    }

    url() {
        const status = this.status?.value;
        return status ? `${this.endpoint}?status=${encodeURIComponent(status)}` : this.endpoint;
    }

    link(row) {
        const a = document.createElement('a');
        a.href = `/admin/orders/${row.uuid}`;
        const code = document.createElement('code');
        code.textContent = String(row.uuid).slice(0, 8);
        a.append(code);
        return a;
    }

    badge(status) {
        const span = document.createElement('span');
        span.className = `order-status ${status}`;
        span.textContent = status ? status[0].toUpperCase() + status.slice(1) : "";
        return span;
    }
}
