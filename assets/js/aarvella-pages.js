document.addEventListener("DOMContentLoaded", () => {

	/* =========================
	   MOBILE NAVIGATION
	========================= */
	const hamburger = document.getElementById("hamburger");
	const mobileNav = document.getElementById("mobileNav");
	const overlay = document.getElementById("navOverlay");
	const mobileBookBtn = document.getElementById("mobileBookBtn");

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

	if (hamburger) {
		hamburger.addEventListener("click", (e) => {
			e.stopPropagation();

			if (mobileNav.classList.contains("active")) {
				closeMenu();
			} else {
				openMenu();
			}
		});
	}

	if (overlay) {
		overlay.addEventListener("click", closeMenu);
	}

	document.querySelectorAll(".mobile-nav a").forEach((link) => {
		link.addEventListener("click", closeMenu);
	});

	window.addEventListener("resize", () => {
		if (window.innerWidth > 980) {
			closeMenu();
		}
	});

	/* =========================
	   REVEAL ANIMATIONS
	========================= */

	const revealElements = document.querySelectorAll(
		".glass-card, .image-split, .team-grid, .blog-grid, .location-wrap, .contact-grid"
	);

	const revealObserver = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					entry.target.classList.add("visible");
				}
			});
		},
		{
			threshold: 0.15
		}
	);

	revealElements.forEach((el, i) => {
		el.classList.add("reveal");
		el.style.transitionDelay = `${Math.min(i * 60, 300)}ms`;

		revealObserver.observe(el);
	});

	/* =========================
	   CARD MOUSE GLOW
	========================= */

	document
		.querySelectorAll(".blog-card, .stylist-card, .glass-card")
		.forEach((card) => {
			card.addEventListener("mousemove", (e) => {
				const rect = card.getBoundingClientRect();
				card.style.setProperty(
					"--mx",
					`${e.clientX - rect.left}px`
				);
				card.style.setProperty(
					"--my",
					`${e.clientY - rect.top}px`
				);
			});
		});

	/* =========================
	   RIPPLE EFFECT
	========================= */

	function spawnRipple(button, e) {
		const container = button.querySelector(".btn-ripple-container");
		if (!container) return;
		const ripple = document.createElement("span");
		ripple.classList.add("btn-ripple");
		const rect = button.getBoundingClientRect();
		ripple.style.setProperty(
			"--ripple-x",
			`${e.clientX - rect.left}px`
		);
		ripple.style.setProperty(
			"--ripple-y",
			`${e.clientY - rect.top}px`
		);
		container.appendChild(ripple);
		setTimeout(() => {
			ripple.remove();
		}, 700);
	}
	/* =========================
	   SMART HEADER VISIBILITY
	========================= */

	const header = document.querySelector(".site-header");

	let lastScroll = 0;

	window.addEventListener("scroll", () => {

		const currentScroll = window.pageYOffset;

		if (currentScroll <= 80) {
			header.classList.remove("nav-hidden");
			header.classList.add("nav-visible");
			return;
		}

		if (currentScroll > lastScroll) {
			header.classList.add("nav-hidden");
		} else {
			header.classList.remove("nav-hidden");
			header.classList.add("nav-visible");
		}

		lastScroll = currentScroll;
	});