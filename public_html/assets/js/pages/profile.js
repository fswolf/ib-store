import ApiForm from "../tools/api_form.js";

// account settings, four forms each posting to their own /api/account/* endpoint
export default class Profile {
    constructor({ endpoint = "/api/account" } = {}) {
        this.profile = new ApiForm({
            form: "profile-form",
            endpoint: `${endpoint}/profile`,
            onDone: (data, form) => {
                // the menu shows the username, keep it in step
                const name = document.querySelector('.dropdown-user-name');
                if (name) name.textContent = form.elements.username.value;
            },
        });

        this.password = new ApiForm({
            form: "password-form",
            endpoint: `${endpoint}/password`,
            reset: true,
            validate: (data) => {
                if (data.password.length < 8) {
                    window.notify.error("New password needs at least 8 characters.");
                    return false;
                }
                if (data.password !== data.confirm) {
                    window.notify.error("Passwords do not match.");
                    return false;
                }
                return true;
            },
        });

        this.billing = new ApiForm({ form: "billing-form", endpoint: `${endpoint}/address` });
        this.shipping = new ApiForm({ form: "shipping-form", endpoint: `${endpoint}/address` });
    }
}
