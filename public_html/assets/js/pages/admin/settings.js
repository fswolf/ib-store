import ApiForm from "../../tools/api_form.js";

export default class AdminSettings {
    constructor({ form = "settings-form", endpoint = "/api/admin/settings/save" } = {}) {
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
            this.testmail = new ApiForm({ form: "testmail-form", endpoint: endpoint.replace(/\/save$/, '/testmail') });
        }
    }
}
