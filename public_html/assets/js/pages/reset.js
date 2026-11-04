import ApiForm from "../tools/api_form.js";

// new password form from the email link, the token rides along as a hidden field
export default class Reset {
    constructor(options = {}) {
        this.form = new ApiForm({
            form: options.form,
            endpoint: options.endpoint,
            validate: (data) => {
                if (!data.password || data.password.length < 8) {
                    window.notify.error("Password needs at least 8 characters.");
                    return false;
                }
                if (data.password !== data.confirm) {
                    window.notify.error("Passwords do not match.");
                    return false;
                }
                return true;
            },
        });
    }
}
