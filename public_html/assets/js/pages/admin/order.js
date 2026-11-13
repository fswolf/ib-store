import ApiForm from "../../tools/api_form.js";
import ApiClient from "../../tools/api_client.js";

// one order: status change, admin notes and the resend email button
export default class AdminOrder {
    api = new ApiClient();

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

        const resend = document.getElementById('resend-email');
        resend?.addEventListener('click', () => this.resend(`${endpoint}/resend`, resend));
    }

    async resend(endpoint, button) {
        button.disabled = true;

        const result = await this.api.post(endpoint, { id: button.dataset.id, csrf_token: button.dataset.csrf });
        const reply = result.data ?? result.raw; // api_client puts a non 2xx body in raw

        button.disabled = false;

        if (!result.success || !reply?.success) {
            if (reply?.signin) { window.location.href = "/signin"; return; }
            window.notify.error(reply?.error || result.error || "Could not send the email.");
            return;
        }
        window.notify.success(reply.message);
    }
}
