// A form with a file in it, posted as multipart. ApiForm goes through ApiClient, which sends json, and a file
// needs the browser to build the body and set the boundary itself, so this is the multipart twin of ApiForm:
// same endings (toast the message, toast the error, bounce to signin), onDone(data, form) on success.
// The submit button is disabled while the upload runs, and reads busy if one is given. reset clears the form after a good upload.
// sendFiles() posts several files one request each, for a picker with multiple or files dropped on the page.
export default class FileUpload {
    constructor({ form, endpoint, onDone = null, reset = true, busy = null, signin_path = "/signin" }) {
        this.form = typeof form === "string" ? document.getElementById(form) : form;
        if (!this.form) return;

        Object.assign(this, { endpoint, onDone, reset, busy, signin_path });
        this.form.addEventListener("submit", (e) => {
            e.preventDefault();
            this.send();
        });
    }

    async send() {
        const button = this.form.querySelector('button[type="submit"]');
        const label = button?.textContent;
        if (button) {
            button.disabled = true;
            if (this.busy) button.textContent = this.busy;
        }

        const data = await this.post(new FormData(this.form));

        if (button) {
            button.disabled = false;
            button.textContent = label;
        }

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

    // each file goes in its own request as field `name`, with the form's other fields, so every request stays under the
    // server's upload limits. onDone runs per file, a failed one toasts with its name and the rest carry on.
    // progress(text) hears "2 of 5" as it goes and "" at the end
    async sendFiles(files, name, progress = null) {
        const list = [...files];
        let added = 0;
        for (const [i, file] of list.entries()) {
            progress?.(`Uploading ${i + 1} of ${list.length}...`);
            const body = new FormData(this.form);
            body.set(name, file);
            const data = await this.post(body);
            if (data?.signin) { window.location.href = this.signin_path; return; }
            if (!data?.success) {
                window.notify.error(`${file.name}: ${data?.error || "Upload failed."}`);
                continue;
            }
            added++;
            this.onDone?.(data, this.form);
        }
        progress?.("");
        if (added) window.notify.success(`${added} of ${list.length} uploaded.`);
        if (this.reset) this.form.reset();
    }

    async post(body) {
        try {
            const res = await fetch(this.endpoint, { method: "POST", body });
            return await res.json();
        } catch (err) {
            return { success: false, error: "Network error." };
        }
    }
}
