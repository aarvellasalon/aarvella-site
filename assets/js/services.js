/* ==================================================
   AARVELLA SERVICES PAGE INTERACTIONS
   ================================================== */

document.addEventListener("DOMContentLoaded", () => {
	initMobileNavigation();
	initRevealMotion();
	initCardGlow();
	initMagneticButtons();
	initRippleButtons();
	initHeaderVisibility();
	initBookingFallback();
});

function initMobileNavigation() {
	const hamburger = document.getElementById("hamburger");
	const mobileNav = document.getElementById("mobileNav");
	const overlay = document.getElementById("navOverlay");
	const mobileBookBtn = document.getElementById("mobileBookBtn");

	if (!hamburger || !mobileNav || !overlay) return;

	const openMenu = () => {
		hamburger.classList.add("active");
		mobileNav.classList.add("active");
		overlay.classList.add("active");
		document.body.classList.add("menu-open");
		hamburger.setAttribute("aria-expanded", "true");
		hamburger.setAttribute("aria-label", "Close menu");
	};

	const closeMenu = () => {
		hamburger.classList.remove("active");
		mobileNav.classList.remove("active");
		overlay.classList.remove("active");
		document.body.classList.remove("menu-open");
		hamburger.setAttribute("aria-expanded", "false");
		hamburger.setAttribute("aria-label", "Open menu");
	};

	hamburger.addEventListener("click", (event) => {
		event.stopPropagation();
		mobileNav.classList.contains("active") ? closeMenu() : openMenu();
	});

	overlay.addEventListener("click", closeMenu);

	document.querySelectorAll(".mobile-nav a").forEach((link) => {
		link.addEventListener("click", closeMenu);
	});

	if (mobileBookBtn) {
		mobileBookBtn.addEventListener("click", (event) => {
			event.preventDefault();
			closeMenu();
			window.setTimeout(() => {
				if (typeof window.openBooking === "function") {
					window.openBooking();
				} else {
					openBookingFallback();
				}
			}, 180);
		});
	}

	window.addEventListener("resize", () => {
		if (window.innerWidth > 900) closeMenu();
	});

	document.addEventListener("keydown", (event) => {
		if (event.key === "Escape") closeMenu();
	});
}

function initRevealMotion() {
	const revealItems = [
		...document.querySelectorAll(".reveal-block"),
		...document.querySelectorAll(".service-card")
	];

	if (!revealItems.length) return;

	const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

	document.querySelectorAll(".service-card").forEach((card, index) => {
		card.classList.add("service-reveal", index % 2 === 0 ? "reveal-from-left" : "reveal-from-right");
		card.style.setProperty("--delay", `${Math.min(index * 55, 220)}ms`);
	});

	if (prefersReducedMotion || !("IntersectionObserver" in window)) {
		revealItems.forEach((item) => item.classList.add("is-visible"));
		return;
	}

	const revealQueue = [];
	let queueRunning = false;

	const runQueue = () => {
		if (queueRunning || !revealQueue.length) return;
		queueRunning = true;

		const nextItem = revealQueue.shift();
		window.setTimeout(() => {
			nextItem.classList.add("is-visible");
			queueRunning = false;
			runQueue();
		}, nextItem.classList.contains("service-card") ? 110 : 0);
	};

	const observer = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (!entry.isIntersecting) return;

			const target = entry.target;
			observer.unobserve(target);

			if (!revealQueue.includes(target) && !target.classList.contains("is-visible")) {
				revealQueue.push(target);
			}

			runQueue();
		});
	}, {
		threshold: 0.18,
		rootMargin: "0px 0px -8% 0px"
	});

	revealItems.forEach((item) => observer.observe(item));
}

function initCardGlow() {
	document.querySelectorAll(".service-card").forEach((card) => {
		card.addEventListener("pointermove", (event) => {
			const rect = card.getBoundingClientRect();
			const x = ((event.clientX - rect.left) / rect.width) * 100;
			const y = ((event.clientY - rect.top) / rect.height) * 100;

			card.style.setProperty("--card-glow-x", `${x}%`);
			card.style.setProperty("--card-glow-y", `${y}%`);
		});

		card.addEventListener("pointerleave", () => {
			card.style.setProperty("--card-glow-x", "50%");
			card.style.setProperty("--card-glow-y", "22%");
		});
	});
}

function initMagneticButtons() {
	const canHover = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
	const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

	if (!canHover || prefersReducedMotion) return;

	document.querySelectorAll(".btn-gold, .btn-outline").forEach((button) => {
		button.addEventListener("pointermove", (event) => {
			const rect = button.getBoundingClientRect();
			const x = event.clientX - rect.left - rect.width / 2;
			const y = event.clientY - rect.top - rect.height / 2;

			button.style.setProperty("--magnetic-x", `${x * 0.08}px`);
			button.style.setProperty("--magnetic-y", `${y * 0.12}px`);
		});

		button.addEventListener("pointerleave", () => {
			button.style.setProperty("--magnetic-x", "0px");
			button.style.setProperty("--magnetic-y", "0px");
		});
	});
}

function initRippleButtons() {
	document.querySelectorAll(".btn-gold, .btn-outline").forEach((button) => {
		button.addEventListener("click", (event) => {
			const container = button.querySelector(".btn-ripple-container");
			if (!container) return;

			const rect = button.getBoundingClientRect();
			const ripple = document.createElement("span");
			ripple.className = "btn-ripple";
			ripple.style.setProperty("--ripple-x", `${event.clientX - rect.left}px`);
			ripple.style.setProperty("--ripple-y", `${event.clientY - rect.top}px`);
			container.appendChild(ripple);

			window.setTimeout(() => ripple.remove(), 650);
		});
	});
}

function initHeaderVisibility() {
	const header = document.getElementById("siteHeader") || document.querySelector(".site-header");
	if (!header) return;

	let lastScrollY = window.scrollY;
	let ticking = false;

	const updateHeader = () => {
		const currentY = window.scrollY;
		const scrollingDown = currentY > lastScrollY;
		const pastHero = currentY > window.innerHeight * 0.78;

		if (pastHero && scrollingDown && !document.body.classList.contains("menu-open")) {
			header.classList.add("nav-hidden");
			header.classList.remove("nav-visible");
		} else {
			header.classList.remove("nav-hidden");
			header.classList.add("nav-visible");
		}

		lastScrollY = Math.max(currentY, 0);
		ticking = false;
	};

	window.addEventListener("scroll", () => {
		if (!ticking) {
			window.requestAnimationFrame(updateHeader);
			ticking = true;
		}
	}, { passive: true });
}

function initBookingFallback() {
	document.querySelectorAll(".js-book").forEach((button) => {
		button.addEventListener("click", () => {
			if (typeof window.openBooking === "function") {
				return;
			}

			openBookingFallback();
		});
	});

	const popup = document.getElementById("bookingPopup");
	if (!popup) return;

	popup.querySelector(".popup-overlay")?.addEventListener("click", () => {
		if (typeof window.closeBooking === "function") {
			window.closeBooking();
		} else {
			closeBookingFallback();
		}
	});

	document.addEventListener("keydown", (event) => {
		if (event.key === "Escape" && popup.classList.contains("active")) {
			if (typeof window.closeBooking === "function") {
				window.closeBooking();
			} else {
				closeBookingFallback();
			}
		}
	});
}

function openBookingFallback() {
	const popup = document.getElementById("bookingPopup");
	if (!popup) return;

	popup.classList.add("active");
	popup.setAttribute("aria-hidden", "false");
	document.body.style.overflow = "hidden";
}

function closeBookingFallback() {
	const popup = document.getElementById("bookingPopup");
	if (!popup) return;

	popup.classList.remove("active");
	popup.setAttribute("aria-hidden", "true");
	document.body.style.overflow = "";
}
