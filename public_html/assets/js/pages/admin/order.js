import ApiForm from "../../tools/api_form.js";

// one order: status change and admin notes
export default class AdminOrder {
    constructor({ endpoint = "/api/admin/orders" } = {}) {
        const status = document.getElementById('status-form');
        const notes = document.getElementById('notes-form');

        if (status) {
            this.status = new ApiForm({
                form: "status-form",
                endpoint: `${endpoint}/status`,
                beforeSend: (data) => data.id = status.dataset.id,
                onDone: (data) => {
                    const badge = document.getElementById('order-status');
                    if (!badge) return;
                    badge.className = `order-status ${data.status}`;
                    badge.textContent = data.status[0].toUpperCase() + data.status.slice(1);
                },
            });
        }

        if (notes) {
            this.notes = new ApiForm({
                form: "notes-form",
                endpoint: `${endpoint}/notes`,
                beforeSend: (data) => data.id = notes.dataset.id,
            });
        }
    }
}
