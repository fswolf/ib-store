// A form with a file in it, posted as multipart. ApiForm goes through ApiClient, which sends json, and a file
// needs the browser to build the body and set the boundary itself, so this is the multipart twin of ApiForm:
// same endings (toast the message, toast the error, bounce to signin), onDone(data, form) on success.
// The submit button is disabled while the upload runs. reset clears the form after a good upload.
export default class FileUpload {
    constructor({ form, endpoint, onDone = null, reset = true, signin_path = "/signin" }) {
        this.form = typeof form === "string" ? document.getElementById(form) : form;
        if (!this.form) return;

        Object.assign(this, { endpoint, onDone, reset, signin_path });
        this.form.addEventListener("submit", (e) => {
            e.preventDefault();
            this.send();
        });
    }

    async send() {
        const button = this.form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;

        let data;
        try {
            const res = await fetch(this.endpoint, { method: "POST", body: new FormData(this.form) });
            data = await res.json();
        } catch (err) {
            data = { success: false, error: "Network error." };
        }

        if (button) button.disabled = false;

        if (!data?.success) {
            if (data?.signin) { window.location.href = this.signin_path; return; }
            window.notify.error(data?.error || "Upload failed.");
            return;
        }

        if (data.message) window.notify.success(data.message);
        if (this.reset) this.form.reset();
        this.onDone?.(data, this.form);
        if (data.redirect) window.location.href = data.redirect;
    }
}
