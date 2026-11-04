import ApiClient from "../tools/api_client.js";

export default class Contact {
    api = new ApiClient();

    constructor({ element = "contact-info", endpoint = "/api/store/info" } = {}) {
        this.element = document.getElementById(element);
        this.meta = document.getElementById("contact-meta");
        this.endpoint = endpoint;

        if (this.element) this.load();
    }

    async load() {
        const result = await this.api.get(this.endpoint);
        const info = result.success && result.data?.info;

        if (!info) {
            window.notify.error("Failed to load contact details.");
            this.element.replaceChildren();
            this.element.removeAttribute("aria-busy");
            return;
        }

        this.render(info);
    }

    render(info) {
        const rows = [
            ["Email", info.email, `mailto:${info.email}`],
            ["Phone", info.phone, `tel:${String(info.phone ?? "").replace(/[^\d+]/g, "")}`],
            ["Address", this.address(info)],
            ["Website", info.website, this.url(info.website)],
        ];

        this.element.replaceChildren(...rows
            .filter(([, value]) => value)
            .map(([label, value, href]) => this.row(label, value, href))
        );
        this.element.removeAttribute("aria-busy");

        if (this.meta) {
            const items = [
                info.currency && `Prices in ${info.currency}`,
                info.timezone && `Time zone: ${info.timezone}`,
            ].filter(Boolean);

            this.meta.replaceChildren(...items.map(text => {
                const li = document.createElement("li");
                li.textContent = text;
                return li;
            }));
        }
    }

    row(label, value, href = null) {
        const div = document.createElement("div");
        div.className = "contact-row";

        const dt = document.createElement("dt");
        dt.textContent = label;

        const dd = document.createElement("dd");
        if (href) {
            const a = document.createElement("a");
            a.href = href;
            a.textContent = value;
            if (href.startsWith("http")) { a.target = "_blank"; a.rel = "noopener"; }
            dd.append(a);
        } else if (Array.isArray(value)) {
            value.forEach((line, i) => { if (i) dd.append(document.createElement("br")); dd.append(line); });
        } else {
            dd.textContent = value;
        }

        div.append(dt, dd);
        return div;
    }

    address(info) {
        const cityLine = [info.city, info.state_province].filter(Boolean).join(", ")
            + (info.postal_code ? ` ${info.postal_code}` : "");
        const lines = [info.address_line1, info.address_line2, cityLine.trim(), info.country].filter(Boolean);
        return lines.length ? lines : null;
    }

    url(site) {
        if (!site) return null;
        return /^https?:\/\//i.test(site) ? site : `https://${site}`;
    }
}
