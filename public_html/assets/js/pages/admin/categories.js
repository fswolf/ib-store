import ApiForm from "../../tools/api_form.js";
import ApiClient from "../../tools/api_client.js";

// categories: the table rows carry their data, edit copies a row into the form, save reloads
export default class AdminCategories {
    api = new ApiClient();

    constructor({ form = "category-form", endpoint = "/api/admin/categories" } = {}) {
        this.endpoint = endpoint;
        this.form = document.getElementById(form);
        this.title = document.getElementById('category-form-title');
        if (!this.form) return;

        this.saver = new ApiForm({
            form,
            endpoint: `${endpoint}/save`,
            onDone: () => setTimeout(() => window.location.reload(), 600),
        });

        document.getElementById('categories-table')?.addEventListener('click', (e) => {
            const button = e.target.closest('button');
            if (!button) return;
            const tr = button.closest('tr');
            if (button.classList.contains('edit-category')) this.edit(tr.dataset);
            if (button.classList.contains('delete-category')) this.remove(tr.dataset);
        });

        document.getElementById('category-reset')?.addEventListener('click', () => this.clear());
    }

    edit(data) {
        this.form.elements.id.value = data.id;
        this.form.elements.name.value = data.name;
        this.form.elements.route.value = data.route;
        this.form.elements.description.value = data.description;
        this.form.elements.active.checked = data.active === "1";
        this.title.textContent = `Edit Category: ${data.name}`;
        this.form.querySelector('button[type="submit"]').textContent = 'Save Category';
        this.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    clear() {
        this.form.reset();
        this.form.elements.id.value = "";
        this.title.textContent = 'New Category';
    }

    async remove(data) {
        if (!confirm(`Delete the category "${data.name}"?`)) return;
        const csrf_token = this.form.querySelector('button[type="submit"]').dataset.csrf;
        const result = await this.api.post(`${this.endpoint}/delete`, { id: data.id, csrf_token });
        const reply = result.data ?? result.raw; // api_client puts a non 2xx body in raw
        if (!result.success || !reply?.success) {
            if (reply?.signin) { window.location.href = "/signin"; return; }
            window.notify.error(reply?.error || result.error || "Delete failed.");
            return;
        }
        window.notify.success(reply.message);
        setTimeout(() => window.location.reload(), 600);
    }
}
