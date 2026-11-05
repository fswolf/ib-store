import ApiForm from "../../tools/api_form.js";
import ApiClient from "../../tools/api_client.js";

export default class AdminSettings {
    api = new ApiClient();

    constructor({ form = "settings-form", endpoint = "/api/admin/settings/save" } = {}) {
        this.endpoint = endpoint.replace(/\/save$/, '');
        this.form = new ApiForm({
            form,
            endpoint,
            onDone: (data, el) => {
                // the store name sits in the menu too
                const logo = document.querySelector('#store-name a');
                if (logo) logo.textContent = el.elements.store_name.value;
            },
        });

        // the mail test button, only there when mail is on
        if (document.getElementById('testmail-form')) {
            this.testmail = new ApiForm({ form: "testmail-form", endpoint: `${this.endpoint}/testmail` });
        }

        // the banner words are a normal form
        if (document.getElementById('hero-text-form')) {
            this.heroText = new ApiForm({ form: "hero-text-form", endpoint: `${this.endpoint}/hero/save` });
        }

        // the image uploads (tab icon, banner): multipart, so not through ApiForm (the browser sets the boundary).
        // the form's data-action names the api action, its remove button posts to <action>/delete. both reload after
        document.querySelectorAll('form.image-upload[data-action]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.upload(form, form.dataset.action);
            });
        });
        document.querySelectorAll('.image-remove').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!confirm(button.dataset.ask)) return;
                const result = await this.api.post(`${this.endpoint}/${button.dataset.action}/delete`, { csrf_token: button.dataset.csrf });
                this.done(result.data ?? result.raw, result);
            });
        });
    }

    async upload(form, action) {
        if (!form.elements.image.files[0]) return;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;

        let data;
        try {
            const res = await fetch(`${this.endpoint}/${action}`, { method: "POST", body: new FormData(form) });
            data = await res.json();
        } catch (err) {
            data = { success: false, error: "Network error" };
        }

        button.disabled = false;
        this.done(data);
    }

    // an image answer: toast it, reload on success so the preview and buttons follow the server
    done(data, result = null) {
        if (!data?.success) {
            if (data?.signin) { window.location.href = "/signin"; return; }
            window.notify.error(data?.error || result?.error || "Request failed.");
            return;
        }
        window.notify.success(data.message);
        setTimeout(() => window.location.reload(), 600);
    }
}
