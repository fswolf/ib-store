import TableSorter from "../../tools/tablesorter.js";

// admin product list, click a row to edit
export default class AdminProducts {
    constructor({ table = "products-table", endpoint = "/api/admin/products/list" } = {}) {
        this.table = new TableSorter({
            table,
            endpoint,
            rows_select: "products-rows",
            info: "products-info",
            pager: "products-pager",
            search: "products-search",
            sort: "updated_at",
            order: "desc",
            search_delay: 300,
            columns: [
                { render: (value) => this.thumb(value) },
                { render: (value, row) => this.link(row) },
                null,
                null,
                { render: (value) => `$${Number(value).toFixed(2)}` },
                { render: (value, row) => Number(row.digital) ? "digital" : value },
                { render: (value) => Number(value) ? "Yes" : "No" },
                { render: (value) => value ? String(value).slice(0, 10) : "" },
            ],
        });

        document.getElementById(table)?.addEventListener('click', (e) => {
            if (e.target.closest('a')) return;
            const tr = e.target.closest('tbody tr');
            const row = tr && this.table.data[tr.rowIndex - 1];
            if (row) window.location.href = `/admin/products/${row.id}`;
        });
    }

    thumb(image) {
        if (!image) return "";
        const img = document.createElement('img');
        img.src = `/${image}`;
        img.className = 'table-thumb';
        img.alt = "";
        return img;
    }

    link(row) {
        const a = document.createElement('a');
        a.href = `/admin/products/${row.id}`;
        a.textContent = row.name;
        return a;
    }
}
