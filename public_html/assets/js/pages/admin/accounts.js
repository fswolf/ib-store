import TableSorter from "../../tools/tablesorter.js";
import ApiForm from "../../tools/api_form.js";

// account list with an edit form under it
export default class AdminAccounts {
    constructor({ table = "accounts-table", form = "account-form", endpoint = "/api/admin/accounts" } = {}) {
        this.form = document.getElementById(form);
        this.title = document.getElementById('account-form-title');

        this.table = new TableSorter({
            table,
            endpoint: `${endpoint}/list`,
            rows_select: "accounts-rows",
            info: "accounts-info",
            pager: "accounts-pager",
            search: "accounts-search",
            sort: "id",
            order: "desc",
            search_delay: 300,
            columns: [
                null,
                null,
                null,
                { render: (value) => value ? String(value).slice(0, 10) : "" },
                { render: (value) => Number(value) ? "Yes" : "" },
                { render: (value) => Number(value) ? "Yes" : "" },
                null,
                { render: (value, row) => this.button(row) },
            ],
        });

        if (this.form) {
            this.saver = new ApiForm({
                form,
                endpoint: `${endpoint}/save`,
                onDone: () => {
                    this.form.hidden = true;
                    this.table.refresh();
                },
            });
            document.getElementById('account-cancel')?.addEventListener('click', () => this.form.hidden = true);
        }
    }

    button(row) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn-link';
        b.textContent = 'Edit';
        b.addEventListener('click', () => this.edit(row));
        return b;
    }

    edit(row) {
        if (!this.form) return;
        this.form.hidden = false;
        this.form.elements.id.value = row.id;
        this.form.elements.username.value = row.username ?? "";
        this.form.elements.email.value = row.email ?? "";
        this.form.elements.notes.value = row.notes ?? "";
        this.form.elements.is_admin.checked = Number(row.is_admin) === 1;
        this.form.elements.verified.checked = Number(row.verified) === 1;
        this.title.textContent = `#${row.id}`;
        this.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
