import ApiForm from "../tools/api_form.js";

// create account form, /api/auth/register signs them in and sends them home
export default class Register {
    constructor(options = {}) {
        this.form = new ApiForm({
            form: options.form,
            endpoint: options.endpoint,
            validate: this.validate,
        });
    }

    validate(data) {
        if (!data.username || !data.email || !data.password || !data.confirm) {
            window.notify.error("Please fill in all fields.");
            return false;
        }

        if (data.password.length < 8) {
            window.notify.error("Password needs at least 8 characters.");
            return false;
        }

        if (data.password !== data.confirm) {
            window.notify.error("Passwords do not match.");
            return false;
        }

        if (!data.csrf_token) {
            window.notify.error("Frontend error, please try again later.");
            return false;
        }

        return true;
    }
}
