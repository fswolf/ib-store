import ApiForm from "../../tools/api_form.js";
import ApiClient from "../../tools/api_client.js";
import FileUpload from "../../tools/file_upload.js";

// product edit form plus the image uploader (file_upload.js, multipart)
export default class AdminProduct {
    api = new ApiClient();

    constructor({ endpoint = "/api/admin/products" } = {}) {
        this.endpoint = endpoint;
        this.form = document.getElementById('product-form');
        if (!this.form) return;

        this.saver = new ApiForm({
            form: "product-form",
            endpoint: `${endpoint}/save`,
            onDone: (data) => {
                // new product: the api sends us to its edit page so images can be added
                if (!data.redirect) this.form.elements.slug.value = data.slug;
            },
        });

        this.listen();
        this.toggle_digital();
    }

    listen() {
        const digital = document.getElementById('digital');
        digital?.addEventListener('change', () => this.toggle_digital());

        // slug from the name until someone types their own
        const name = this.form.elements.name;
        const slug = this.form.elements.slug;
        let touched = slug.value !== "";
        slug.addEventListener('input', () => touched = slug.value !== "");
        name.addEventListener('input', () => {
            if (!touched) slug.value = name.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        });

        document.getElementById('delete-product')?.addEventListener('click', async () => {
            if (!confirm("Delete this product and its images? Orders keep their copy of the name.")) return;
            const result = await this.api.post(`${this.endpoint}/delete`, { id: this.form.dataset.id, csrf_token: this.csrf() });
            this.handle(result, (data) => window.location.href = data.redirect);
        });

        // each upload adds a card to the list, no reload
        this.uploader = new FileUpload({
            form: "image-form",
            endpoint: `${this.endpoint}/image`,
            onDone: (data) => document.getElementById('image-list').append(this.card(data.image)),
        });

        document.getElementById('image-list')?.addEventListener('click', (e) => {
            const button = e.target.closest('button');
            if (!button) return;
            const card = button.closest('.image-card');
            if (button.classList.contains('image-delete')) this.image('delete', card);
            if (button.classList.contains('image-main')) this.image('main', card);
        });
    }

    toggle_digital() {
        const digital = document.getElementById('digital')?.checked;
        const group = document.getElementById('download-group');
        if (group) group.style.display = digital ? '' : 'none';
        this.form.elements.stock.disabled = !!digital;
    }

    csrf() {
        return this.form.querySelector('button[type="submit"]').dataset.csrf;
    }

    async image(action, card) {
        const result = await this.api.post(`${this.endpoint}/image/${action}`, { id: card.dataset.id, csrf_token: this.csrf() });
        this.handle(result, () => window.location.reload());
    }

    card(image) {
        const div = document.createElement('div');
        div.className = 'image-card';
        div.dataset.id = image.id;
        div.innerHTML = `
            <img src="/${image.image}" alt="">
            <div class="image-actions">
                <button type="button" class="btn-link image-main"${image.sort_order === 1 ? " disabled" : ""}>${image.sort_order === 1 ? "Main image" : "Make main"}</button>
                <button type="button" class="btn-link image-delete">Remove</button>
            </div>`;
        return div;
    }

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
}
