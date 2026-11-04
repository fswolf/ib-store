import Notify from "./components/notify.js"
import ApiClient from "./tools/api_client.js";

export default class GlobalUI {
    path;

    menu_button;
    menu_nav;
    menu_nav_link;
    search_input;
    cart_count;

    api = new ApiClient();

	// dropdowns;

    footer_date;

    notify;

	constructor(properties = {}) {
		Object.assign(this, properties);

		this.notify = new Notify();
        this.init();
	}

    init() {
        this.menu_button ??= document.getElementById('menu-toggle');
        this.menu_nav ??= document.getElementById('nav-container');
        this.menu_nav_link ??= document.querySelectorAll('.nav-links a');
        this.search_input ??= document.getElementById('search');

		// this.dropdowns ??= document.querySelectorAll('.dropdown');

	    this.footer_date ??= document.getElementById('year');
	    this.cart_count ??= document.getElementById('cart-count');

        this.active_link();
		this.handle_menu();
		this.handle_search();
		this.handle_cart();

		if (this.footer_date) {
        	this.footer_date.textContent = new Date().getFullYear();
        }
    }

	handle_menu() {
		/* Mobile Toggle */
	    if (this.menu_button && this.menu_nav) {
	        this.menu_button.addEventListener('click', () => {
	            this.menu_nav.classList.toggle('active');
	        });
	    }

	    /* Sub Menus */
	    // this.dropdowns.forEach(dropdown => {
	    //     const toggle = dropdown.querySelector('.dropdown-toggle');
	    //     if (!toggle) return;

	    //     toggle.addEventListener('click', (e) => {
	    //         e.preventDefault();
	    //         dropdown.classList.toggle('open');
	    //     });
	    // });
	}

	handle_search() {
	    if (!this.search_input) return;

	    // prefill on /search/:item
	    const match = this.path.match(/^\/search\/([^/]+)/);
	    if (match) this.search_input.value = decodeURIComponent(match[1]);

	    this.search_input.addEventListener('keydown', (e) => {
	        if (e.key !== 'Enter') return;
	        e.preventDefault();

	        // Apache 404s on encoded slashes (%2F), so strip them
	        const term = this.search_input.value.replace(/\//g, ' ').trim();
	        if (!term) return;

	        window.location.href = `/search/${encodeURIComponent(term)}`;
	    });
	}

	/* Add to Cart buttons anywhere on the page, product cards included (they load after us, so listen on the body) */
	handle_cart() {
	    document.body.addEventListener('click', async (e) => {
	        const button = e.target.closest('.add-cart-btn');
	        if (!button || button.disabled) return;
	        e.preventDefault();

	        const id = Number(button.dataset.id);
	        if (!id) return;

	        // the product page has a qty box, cards do not
	        const qty = Number(document.getElementById('qty')?.value) || 1;

	        button.disabled = true;
	        const result = await this.api.post('/api/cart/add', { id, qty });
	        button.disabled = false;

	        const data = result.data ?? result.raw; // api_client puts a non 2xx body in raw
	        if (!result.success || !data?.success) {
	            window.notify.error(data?.error || result.error || "Could not add to cart.");
	            return;
	        }

	        this.set_cart_count(data.count);
	        window.notify.success(`Added to cart (${data.qty} in cart)`);
	        document.dispatchEvent(new CustomEvent('cart:changed', { detail: data.cart }));

	        // a moment of feedback on the button itself
	        const label = button.textContent;
	        button.textContent = 'Added';
	        setTimeout(() => button.textContent = label, 1200);
	    });
	}

	set_cart_count(count) {
	    if (this.cart_count) this.cart_count.textContent = count;
	}

	active_link() {
	    const path = this.path.replace(/\/$/, '');

	    this.menu_nav_link.forEach(link => {
	        if (link.getAttribute('href') === '#') return;

	        const href = new URL(link.href).pathname.replace(/\/$/, '');

	        if (href === path) {
	            link.classList.add('active');
	        }
	    });
	}
}
