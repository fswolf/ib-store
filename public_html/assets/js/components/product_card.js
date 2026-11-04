export default class ProductCard {
    constructor(product) {
        this.product = product;
    }

    render() {
        const product = this.product;

        const card = document.createElement("div");
        card.className = "product-card";

        const badge = this.getBadge(product.badge);
        const image = this.getImage(product);

        card.innerHTML = `
            ${badge}
            ${image}

            <h3>
                <a href="/item/${this.escape(product.slug || product.id)}">
                    ${this.escape(product.name)}
                </a>
            </h3>

            <p class="price">$${Number(product.price).toFixed(2)}</p>

            <button class="add-cart-btn" data-id="${Number(product.id)}"${this.inStock(product) ? "" : " disabled"}>${this.inStock(product) ? "Add to Cart" : "Out of Stock"}</button>
        `;

        return card;
    }

    getBadge(badge) {
        switch (Number(badge)) {
            case 1:
                return `<span class="badge new">New</span>`;
            case 2:
                return `<span class="badge sale">Sale</span>`;
            case 3:
                return `<span class="badge disc">Discontinued</span>`;
            default:
                return "";
        }
    }

    getImage(product) {
        if (product.image) {
            return `
                <div class="product-image">
                    <img src="/${this.escape(product.image)}" alt="${this.escape(product.name)}" loading="lazy">
                </div>
            `;
        }

        return `<div class="product-image-holder"></div>`;
    }

    inStock(product) {
        return Number(product.digital) === 1 || Number(product.stock) > 0;
    }

    // names come from the db, admin typed them, but innerHTML still wants them escaped
    escape(text) {
        return String(text ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    }
}