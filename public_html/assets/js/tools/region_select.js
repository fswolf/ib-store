// country + province pair on an address form. the country select carries the province lists in
// data-provinces (address_fields.html) as {country: {abbreviation: name}}: a country with a list gets a
// select, any other a text box
export default class RegionSelect {
    constructor({ country, province }) {
        this.country = document.getElementById(country);
        this.province = document.getElementById(province);

        if (!this.country || !this.province) {
            console.warn(`RegionSelect: '${country}' or '${province}' not found.`);
            return;
        }

        this.lists = JSON.parse(this.country.dataset.provinces || "{}");
        this.country.addEventListener("change", () => this.update());
    }

    // swap the province field for the picked country, what was in it is kept when it still fits
    update() {
        const list = this.lists[this.country.value];
        const old = this.province;
        const field = document.createElement(list ? "select" : "input");

        field.id = old.id;
        field.name = old.name;
        field.required = old.required;
        field.autocomplete = "address-level1";

        if (list) {
            const typed = old.value.trim().toLowerCase();
            field.add(new Option("Pick one", ""));
            Object.values(list).forEach(name => field.add(new Option(name, name)));
            // a typed name or abbreviation (texas, TX) picks its option
            const match = Object.entries(list).find(([code, name]) => code.toLowerCase() === typed || name.toLowerCase() === typed);
            field.value = match ? match[1] : "";
        } else {
            field.type = "text";
            if (old.tagName === "INPUT") field.value = old.value;
        }

        old.replaceWith(field);
        this.province = field;
    }
}
