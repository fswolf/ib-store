import ApiForm from "../../tools/api_form.js";
import ApiClient from "../../tools/api_client.js";
import FileUpload from "../../tools/file_upload.js";
import MarkdownEditor from "../../tools/markdown_editor.js";

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
        this.markdown();
    }

    listen() {
        // digital and its two delivery boxes decide which sections show
        ['digital', 'use_files', 'use_keys'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.toggle_digital()));

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

        // the key pool: Add Keys sends the pasted keys and / or the picked file, Remove takes the unused ones out.
        // both answer with the new counts, shown in place
        this.keys = new FileUpload({ form: "keys-form", endpoint: `${this.endpoint}/keys`, busy: "Adding...", onDone: (data) => this.key_counts(data) });
        document.getElementById('keys-form')?.addEventListener('submit', () => document.getElementById('keys-result').textContent = ""); // an error only toasts, so no stale line beside it
        document.getElementById('clear-keys')?.addEventListener('click', async () => {
            if (!confirm("Remove the unused keys? Keys already on orders stay with them.")) return;
            const result = await this.api.post(`${this.endpoint}/keys/clear`, { id: this.form.dataset.id, csrf_token: this.csrf() });
            this.handle(result, (data) => this.key_counts(data));
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

    // the description editor. its preview comes from the store's own renderer and wears the product page's description
    // style, so it shows what the page will
    markdown() {
        this.editor = new MarkdownEditor({ element: this.form.elements.description, preview: (text, el) => this.preview(text, el) });
    }

    // side by side asks on every keystroke, so wait for a pause in the typing. an answer that comes back after a
    // newer request went out is dropped, so a slow one never puts older text over newer
    preview(text, el) {
        clearTimeout(this.preview_wait);
        this.preview_wait = setTimeout(async () => {
            const asked = this.preview_asked = (this.preview_asked ?? 0) + 1;
            const result = await this.api.post(`${this.endpoint}/preview`, { text, csrf_token: this.csrf() });
            const data = result.data ?? result.raw;
            if (data?.success && asked === this.preview_asked) el.innerHTML = data.html;
        }, 250);
    }

    // the delivery boxes show under digital, the download field and the key pool under their box
    toggle_digital() {
        const digital = !!document.getElementById('digital')?.checked;
        const show = (id, on) => { const el = document.getElementById(id); if (el) el.style.display = on ? '' : 'none'; };
        show('delivery-group', digital);
        show('download-group', digital && document.getElementById('use_files')?.checked);
        show('keys-section', digital && document.getElementById('use_keys')?.checked);
        // a download has no stock and no condition
        this.form.elements.stock.disabled = !!digital;
        this.form.elements.con.disabled = !!digital;
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

    // the keys section after an add or a remove: the count, what happened (stays after the toast is gone), the remove button
    key_counts({ unused, total, message }) {
        document.getElementById('keys-count').innerHTML = total ? `<strong>${unused.toLocaleString()}</strong> unused of ${total.toLocaleString()} added.` : "No keys yet.";
        document.getElementById('keys-result').textContent = message ?? "";
        document.getElementById('clear-keys').hidden = !unused;
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
