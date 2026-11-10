import ApiClient from "../tools/api_client.js";
import RegionSelect from "../tools/region_select.js";

// checkout page. billing and shipping are two forms, place order posts them both as one json body
export default class Shipping {
	api = new ApiClient();

	constructor({ endpoint = "/api/cart/checkout" } = {}) {
		this.endpoint = endpoint;
		this.billing = document.getElementById('billing-form');
		this.shipping = document.getElementById('shipping-form');   // missing when the cart is digital only
		this.checkbox = document.getElementById('same-address');
		this.notes = document.getElementById('order-notes');
		this.checkoutBtn = document.getElementById('place-order');

		if (!this.billing || !this.checkoutBtn) {
			console.log("form error");
			return;
		}

		new RegionSelect({ country: "billing-country", province: "billing-province" });
		if (this.shipping) new RegionSelect({ country: "shipping-country", province: "shipping-province" });

		this.toggle_fields();
		this.listen_events();
	}

	listen_events() {
		this.checkbox?.addEventListener('change', () => {
			this.toggle_fields();
		});

		// enter in a field should not submit the forms the browser way
		[this.billing, this.shipping].forEach(form => form?.addEventListener('submit', (e) => e.preventDefault()));

		this.checkoutBtn.addEventListener('click', (e) => {
			e.preventDefault();
			this.place_order();
		});
	}

	toggle_fields() {
		if (!this.shipping || !this.checkbox) return;
		this.shipping.style.display = this.checkbox.checked ? 'none' : 'block';
	}

	async place_order() {
		const same = !this.shipping || this.checkbox?.checked;

		if (!this.billing.reportValidity()) return;
		if (!same && !this.shipping.reportValidity()) return;

		const body = {
			csrf_token: this.checkoutBtn.dataset.csrf,
			billing: Object.fromEntries(new FormData(this.billing)),
			shipping: same ? null : Object.fromEntries(new FormData(this.shipping)),
			same_address: same ? 1 : 0,
			notes: this.notes?.value ?? "",
		};

		this.checkoutBtn.disabled = true;
		this.checkoutBtn.textContent = 'Placing order...';

		const result = await this.api.post(this.endpoint, body);
		const data = result.data ?? result.raw; // api_client puts a non 2xx body in raw

		if (!result.success || !data?.success) {
			if (data?.signin) { window.location.href = '/signin'; return; }
			window.notify.error(data?.error || result.error || "Could not place the order.");
			this.checkoutBtn.disabled = false;
			this.checkoutBtn.textContent = 'Place Order';
			return;
		}

		window.location.href = data.redirect;
	}
}
