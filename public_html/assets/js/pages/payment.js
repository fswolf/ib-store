import ApiClient from "../tools/api_client.js";

// the payment page. a gateway button starts the payment and follows the redirect; coming back from the
// gateway (or clicking check) asks the store for the order's state and reloads when it is paid
export default class Payment {
	api = new ApiClient();

	constructor({ endpoint = "/api/payment" } = {}) {
		this.endpoint = endpoint;
		this.methods = document.querySelector('.payment-methods');
		if (!this.methods) return;

		this.order = this.methods.dataset.order;
		this.csrf_token = this.methods.dataset.csrf;

		this.listen();

		// back from the gateway: see if the webhook beat us here
		this.status = document.getElementById('payment-status');
		if (this.status) this.check(true);
	}

	listen() {
		this.methods.addEventListener('click', (e) => {
			const button = e.target.closest('.pay-btn');
			if (button) return this.start(button);
			if (e.target.closest('#check-payment')) return this.check(false);
		});
	}

	async start(button) {
		const gateway = button.dataset.gateway;
		button.disabled = true;

		const result = await this.api.post(`${this.endpoint}/${gateway}/start`, { order: this.order, csrf_token: this.csrf_token });
		const data = result.data ?? result.raw;

		if (!result.success || !data?.success) {
			if (data?.signin) { window.location.href = '/signin'; return; }
			window.notify.error(data?.error || result.error || "Could not start the payment.");
			button.disabled = false;
			return;
		}

		window.location.href = data.redirect;
	}

	async check(quiet) {
		const status = this.status;
		if (!status) return;
		const gateway = status.dataset.gateway;
		const result = await this.api.post(`${this.endpoint}/${gateway}/status`, { order: this.order });
		const data = result.data ?? result.raw;

		if (!result.success || !data?.success) {
			if (!quiet) window.notify.error(data?.error || result.error || "Could not check the payment.");
			return;
		}

		if (data.order !== 'pending') {
			window.notify.success("Payment received, thank you.");
			setTimeout(() => window.location.reload(), 800);
			return;
		}

		// the gateway's own word for it, in plain words where we know them
		const words = {
			btcpay: { New: "waiting for the payment", Processing: "payment seen, waiting for it to confirm", Expired: "the invoice expired, start a new one", Invalid: "the invoice was marked invalid, start a new one" },
			stripe: { open: "the card page is still open, finish it there or start again", expired: "that card session expired, start again" },
			paypal: { CREATED: "waiting for you to approve it on PayPal", APPROVED: "approved, taking the payment", VOIDED: "that PayPal order was voided, start again" },
		};
		const labels = { btcpay: 'Bitcoin', stripe: 'Card', paypal: 'PayPal' };
		const text = words[gateway]?.[data.state] ?? (data.open ? "still open, finish it there or start again" : data.state ?? 'nothing started yet');
		status.firstChild.textContent = `${labels[gateway] ?? gateway}: ${text}. `;
		if (!quiet) window.notify.info(text);
	}
}
