import ApiForm from "../../tools/api_form.js";
import ApiClient from "../../tools/api_client.js";
import FileUpload from "../../tools/file_upload.js";

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

        // the image uploads (tab icon, banner) go through file_upload.js. the form's data-action names the api action,
        // its remove button posts to <action>/delete. both reload after so the preview and buttons follow the server
        document.querySelectorAll('form.image-upload[data-action]').forEach((form) => {
            new FileUpload({ form, endpoint: `${this.endpoint}/${form.dataset.action}`, onDone: () => this.reload() });
        });
        document.querySelectorAll('.image-remove').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!confirm(button.dataset.ask)) return;
                const result = await this.api.post(`${this.endpoint}/${button.dataset.action}/delete`, { csrf_token: button.dataset.csrf });
                this.handle(result, () => this.reload());
            });
        });
    }

    // an ApiClient answer the same way product.js reads one: toast, bounce to signin, onDone on success
    handle(result, onDone) {
        const data = result.data ?? result.raw; // api_client puts a non 2xx body in raw
        if (!result.success || !data?.success) {
            if (data?.signin) { window.location.href = "/signin"; return; }
            window.notify.error(data?.error || result.error || "Request failed.");
            return;
        }
        if (data.message) window.notify.success(data.message);
        onDone?.(data);
    }

    // after the toast has had a moment
    reload() {
        setTimeout(() => window.location.reload(), 600);
    }
}
