import ApiForm from "../tools/api_form.js";

export default class Forgot {
    constructor(options = {}) {
        // no form when mail is off, the page says so instead
        if (!document.getElementById(options.form)) return;

        this.form = new ApiForm({
            form: options.form,
            endpoint: options.endpoint,
            reset: true,
            validate: (data) => {
                if (!data.email) {
                    window.notify.error("Enter your email address.");
                    return false;
                }
                return true;
            },
        });
    }
}
