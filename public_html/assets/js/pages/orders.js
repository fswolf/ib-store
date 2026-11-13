import ApiClient from "../tools/api_client.js";

// account orders, the list and the single order: a resend button posts its order to /api/account/resend
// and the downloads and keys email goes out again
export default class Orders {
    api = new ApiClient();

    constructor({ endpoint = "/api/account" } = {}) {
        this.endpoint = endpoint;

        document.querySelectorAll('.resend-email').forEach(button => {
            button.addEventListener('click', () => this.resend(button));
        });
    }

    async resend(button) {
        button.disabled = true;

        const result = await this.api.post(`${this.endpoint}/resend`, { uuid: button.dataset.uuid, csrf_token: button.dataset.csrf });
        const data = result.data ?? result.raw; // api_client puts a non 2xx body in raw

        button.disabled = false;

        if (data?.signin) { window.location.href = '/signin'; return; }
        if (!result.success || !data?.success) {
            window.notify.error(data?.error || result.error || "Could not send the email.");
            return;
        }

        window.notify.success(data.message);
    }
}
