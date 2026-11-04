import FormHandler from "../tools/form_handler.js";

// FormHandler with the usual endings filled in: the api answers {success, error, message, redirect},
// errors go to a toast, a message goes to a toast, a redirect is followed. onDone(data, form) runs on success
export default class ApiForm extends FormHandler {
    constructor({ form, endpoint, method = "POST", validate = null, beforeSend = null, onDone = null, reset = false }) {
        super({
            form,
            endpoint,
            method,
            validate,
            beforeSend,
            onSuccess: (result, el) => this.done(result, el),
            onError: (result) => this.fail(result),
        });

        this.onDone = onDone;
        this.reset = reset;
    }

    done(result, el) {
        const data = typeof result.data === "string" ? JSON.parse(result.data) : result.data;

        if (!data?.success) {
            this.fail({ ...result, raw: data });
            return;
        }

        if (data.message) window.notify.success(data.message);
        if (this.reset) el.reset();
        this.onDone?.(data, el);
        if (data.redirect) window.location.href = data.redirect;
    }

    // a non 2xx reply: the api body is in result.raw (api_client.js). not signed in bounces to /signin
    fail(result) {
        const data = result.raw && typeof result.raw === "object" ? result.raw : null;
        if (data?.signin) { window.location.href = "/signin"; return; }
        window.notify.error(data?.error || result.error || "Network error.");
    }
}
