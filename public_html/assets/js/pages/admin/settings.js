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

        // the home page banner: the words are a normal form, the image upload is multipart (the browser sets the
        // boundary, so not through ApiForm), remove is a plain post. image changes reload so the preview follows the server
        if (document.getElementById('hero-text-form')) {
            this.heroText = new ApiForm({ form: "hero-text-form", endpoint: `${this.endpoint}/hero/save` });
        }
        document.getElementById('hero-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.upload(e.target);
        });
        document.getElementById('hero-delete')?.addEventListener('click', async (e) => {
            if (!confirm("Remove the banner image? The words stay.")) return;
            const result = await this.api.post(`${this.endpoint}/hero/delete`, { csrf_token: e.target.dataset.csrf });
            this.done(result.data ?? result.raw, result);
        });
    }

    async upload(form) {
        if (!form.elements.image.files[0]) return;
        const button = form.querySelector('button');
        button.disabled = true;

        let data;
        try {
            const res = await fetch(`${this.endpoint}/hero`, { method: "POST", body: new FormData(form) });
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
