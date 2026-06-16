/* const hamburger = document.querySelector('.hamburger');
const mobileNav = document.querySelector('.mobile-nav');
const bookingPopup = document.querySelector('.booking-popup');
const closePopupButtons = document.querySelectorAll('.close-popup, .popup-overlay');
const bookingButtons = document.querySelectorAll('.js-book');

if (hamburger && mobileNav) {
    hamburger.addEventListener('click', () => {
        mobileNav.classList.toggle('open');
    });
}

bookingButtons.forEach((button) => {
    button.addEventListener('click', () => {
        if (bookingPopup) {
            bookingPopup.classList.add('active');
        }
    });
});

closePopupButtons.forEach((element) => {
    element.addEventListener('click', () => {
        if (bookingPopup) {
            bookingPopup.classList.remove('active');
        }
    });
}); */
document.addEventListener("DOMContentLoaded", () => {
	const hamburger = document.getElementById("hamburger");
	const mobileNav = document.getElementById("mobileNav");
	const overlay = document.getElementById("navOverlay");
	const mobileBookBtn = document.getElementById("mobileBookBtn");

	if (!hamburger || !mobileNav || !overlay) return;

	function openMenu() {
		hamburger.classList.add("active");
		mobileNav.classList.add("active");
		overlay.classList.add("active");
		document.body.classList.add("menu-open");
		document.body.style.overflow = "hidden";
	}

	function closeMenu() {
		hamburger.classList.remove("active");
		mobileNav.classList.remove("active");
		overlay.classList.remove("active");
		document.body.classList.remove("menu-open");
		document.body.style.overflow = "";
	}

	hamburger.addEventListener("click", e => {
		e.stopPropagation();
		if (mobileNav.classList.contains("active")) {
			closeMenu();
		} else {
			openMenu();
		}
	});

	overlay.addEventListener("click", closeMenu);

	document.querySelectorAll(".mobile-nav a").forEach(link => {
		link.addEventListener("click", closeMenu);
	});

	if (mobileBookBtn) {
		mobileBookBtn.addEventListener("click", e => {
			e.preventDefault();
			closeMenu();
			setTimeout(() => {
				openBooking();
			}, 180);
		});
	}

	window.addEventListener("resize", () => {
		if (window.innerWidth > 768) {
			closeMenu();
		}
	});
});

/* ==================================================
   SERVICES CARD SCROLL REVEAL
   Alternating left/right entry, one card at a time.
   ================================================== */

document.addEventListener("DOMContentLoaded", () => {
	const serviceCards = Array.from(document.querySelectorAll("#services .service-card"));

	if (!serviceCards.length) return;

	const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

	serviceCards.forEach((card, index) => {
		card.classList.add("service-reveal");
		card.classList.add(index % 2 === 0 ? "reveal-from-left" : "reveal-from-right");
		card.style.setProperty("--card-index", index);

		card.addEventListener("pointermove", (event) => {
			const rect = card.getBoundingClientRect();
			const x = ((event.clientX - rect.left) / rect.width) * 100;
			const y = ((event.clientY - rect.top) / rect.height) * 100;

			card.style.setProperty("--card-glow-x", `${x}%`);
			card.style.setProperty("--card-glow-y", `${y}%`);
		});

		card.addEventListener("pointerleave", () => {
			card.style.setProperty("--card-glow-x", "50%");
			card.style.setProperty("--card-glow-y", "18%");
		});
	});

	if (prefersReducedMotion || !("IntersectionObserver" in window)) {
		serviceCards.forEach((card) => card.classList.add("is-visible"));
		return;
	}

	const revealQueue = [];
	let queueRunning = false;

	function runRevealQueue() {
		if (queueRunning || !revealQueue.length) return;

		queueRunning = true;
		const nextCard = revealQueue.shift();

		window.setTimeout(() => {
			nextCard.classList.add("is-visible");
			queueRunning = false;
			runRevealQueue();
		}, 130);
	}

	const cardObserver = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (!entry.isIntersecting) return;

			const card = entry.target;
			cardObserver.unobserve(card);

			if (!revealQueue.includes(card) && !card.classList.contains("is-visible")) {
				revealQueue.push(card);
			}

			runRevealQueue();
		});
	}, {
		root: null,
		threshold: 0.22,
		rootMargin: "0px 0px -8% 0px"
	});

	serviceCards.forEach((card) => cardObserver.observe(card));
});
