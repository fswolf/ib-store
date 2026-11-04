import ApiClient from "../tools/api_client.js";

// the cart page. items come from /api/cart/items, every change posts back and re-renders from the reply
export default class Cart {
	api = new ApiClient();

	constructor({ element = "cart-list", endpoint = "/api/cart" } = {}) {
		this.list = document.getElementById(element);
		this.endpoint = endpoint;
		this.checkoutBtn = document.querySelector('.checkout-btn');
		this.totals = {
			subtotal: document.getElementById('cart-subtotal'),
			shipping: document.getElementById('cart-shipping'),
			tax: document.getElementById('cart-tax'),
			total: document.getElementById('cart-total'),
		};

		if (!this.list) return;

		this.listen();
		this.load();
	}

	async load() {
		const result = await this.api.get(`${this.endpoint}/items`);
		const cart = result.success && result.data?.cart;

		if (!cart) {
			window.notify.error("Failed to load the cart.");
			this.list.replaceChildren();
			this.list.removeAttribute('aria-busy');
			return;
		}

		this.render(cart);
	}

	async change(action, id, qty = 1) {
		this.list.setAttribute('aria-busy', 'true');
		const result = await this.api.post(`${this.endpoint}/${action}`, { id, qty });
		const data = result.data ?? result.raw; // api_client puts a non 2xx body in raw

		if (!result.success || !data?.success) {
			window.notify.error(data?.error || result.error || "Cart update failed.");
			this.list.removeAttribute('aria-busy');
			return;
		}

		this.render(data.cart);
		const count = document.getElementById('cart-count');
		if (count) count.textContent = data.count;
	}

	listen() {
		this.list.addEventListener('change', (e) => {
			const input = e.target.closest('input[type="number"]');
			if (!input) return;
			const item = input.closest('.cart-item');
			this.change('update', Number(item.dataset.id), Math.max(0, Number(input.value) || 0)); // 0 removes the line
		});

		this.list.addEventListener('click', (e) => {
			const button = e.target.closest('.remove-btn');
			if (!button) return;
			const item = button.closest('.cart-item');
			this.change('remove', Number(item.dataset.id));
		});

		this.checkoutBtn?.addEventListener('click', (e) => {
			e.preventDefault();
			// signin bounces back here if they are not logged in, see BaseController::requireLogin
			window.location.href = '/cart/checkout';
		});
	}

	render(cart) {
		this.list.removeAttribute('aria-busy');
		this.list.replaceChildren();

		if (!cart.items.length) {
			const p = document.createElement('p');
			p.className = 'no-results';
			p.innerHTML = 'Your cart is empty. <a href="/items">Browse the store.</a>';
			this.list.append(p);
			if (this.checkoutBtn) this.checkoutBtn.disabled = true;
		}
		else {
			cart.items.forEach(item => this.list.append(this.row(item)));
			if (this.checkoutBtn) this.checkoutBtn.disabled = false;
		}

		const t = cart.totals;
		this.totals.subtotal.textContent = `$${t.subtotal}`;
		this.totals.shipping.textContent = t.has_physical ? `$${t.shipping}` : 'None';
		this.totals.tax.textContent = `$${t.tax}`;
		this.totals.total.textContent = `$${t.total} ${t.currency}`;
	}

	row(item) {
		const div = document.createElement('div');
		div.className = 'cart-item';
		div.dataset.id = item.id;

		const image = document.createElement('div');
		image.className = 'cart-item-image';
		if (item.image) {
			const img = document.createElement('img');
			img.src = `/${item.image}`;
			img.alt = item.name;
			image.append(img);
		}
		else {
			const holder = document.createElement('div');
			holder.className = 'product-image-holder';
			image.append(holder);
		}

		const details = document.createElement('div');
		details.className = 'cart-item-details';

		const h3 = document.createElement('h3');
		const link = document.createElement('a');
		link.href = `/item/${encodeURIComponent(item.slug)}`;
		link.textContent = item.name;
		h3.append(link);

		const meta = document.createElement('div');
		meta.className = 'cart-meta';
		meta.textContent = [item.sku ? `SKU: ${item.sku}` : null, item.digital ? 'Digital download' : null].filter(Boolean).join(' • ');

		const price = document.createElement('p');
		price.textContent = `$${Number(item.price).toFixed(2)}`;
		if (item.qty > 1) price.textContent += ` × ${item.qty} = $${item.line_total}`;

		details.append(h3, meta, price);

		const controls = document.createElement('div');
		controls.className = 'cart-item-controls';

		const label = document.createElement('label');
		label.textContent = 'Qty';

		const qty = document.createElement('input');
		qty.type = 'number';
		qty.value = item.qty;
		qty.min = 1;
		qty.max = item.digital ? 1 : Math.max(1, item.stock);
		if (item.digital) qty.disabled = true;

		const remove = document.createElement('button');
		remove.className = 'remove-btn';
		remove.type = 'button';
		remove.textContent = 'Remove';

		controls.append(label, qty, remove);

		div.append(image, details, controls);
		return div;
	}
}
