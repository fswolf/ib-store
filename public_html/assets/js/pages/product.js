export default class Product {
	constructor() {

		this.initCarousel();
		this.initImages();
		this.initQty();
	}

	initCarousel() {
	    const track = document.querySelector('.related-track');

	    if (!track) return;

	    document.querySelector('.next-btn')?.addEventListener('click', () => {
	        track.scrollBy({
	            left: 300,
	            behavior: 'smooth'
	        });
	    });

	    document.querySelector('.prev-btn')?.addEventListener('click', () => {
	        track.scrollBy({
	            left: -300,
	            behavior: 'smooth'
	        });
	    });
	}

	initImages() {
		document.querySelectorAll('.thumbnail').forEach(thumb => {
		    thumb.addEventListener('click', () => {
		        document.getElementById('main-image').src = thumb.src;
		        document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
		        thumb.classList.add('active');
		    });
		});
	}

	// keep the qty box inside 1..stock, the add to cart click itself is handled in global_ui.js
	initQty() {
		const qty = document.getElementById('qty');
		if (!qty) return;

		qty.addEventListener('change', () => {
			const max = Number(qty.max) || 99;
			qty.value = Math.min(Math.max(1, Number(qty.value) || 1), max);
		});
	}

}
