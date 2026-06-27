(() => {
	"use strict";

	const BOOKING_URL = "/#booking";

	const selectors = {
		welcomeText: ".portal-welcome p",
		profileImage: ".portal-welcome img",
		bookingButtons: ".js-book",
		logoutLinks: ".portal-logout",
		navigationLinks: ".portal-nav a",
		menuButton: "[data-portal-menu-button]",
		sidebar: "[data-portal-sidebar]",
		overlay: "[data-portal-overlay]",
		cards: ".portal-card"
	};

	let lastFocusedElement = null;

	function getGreeting() {
		const hour = new Date().getHours();

		if (hour < 12) return "Good morning";
		if (hour < 17) return "Good afternoon";
		return "Good evening";
	}

	function updateGreeting() {
		const welcomeText = document.querySelector(selectors.welcomeText);
		if (!welcomeText) return;
		welcomeText.textContent = getGreeting();
	}

	function initializeProfileImageFallback() {
		const profileImage = document.querySelector(selectors.profileImage);
		if (!profileImage) return;

		const fallbackImage = "/assets/images/default-profile.webp";

		profileImage.addEventListener(
			"error",
			() => {
				if (profileImage.src.includes("default-profile.webp")) return;
				profileImage.src = fallbackImage;
			},
			{ once: true }
		);
	}

	function initializeBookingButtons() {
		const buttons = document.querySelectorAll(selectors.bookingButtons);

		buttons.forEach((button) => {
			button.addEventListener("click", (event) => {
				event.preventDefault();

				if (button.dataset.loading === "true") return;

				button.dataset.loading = "true";
				button.setAttribute("aria-busy", "true");

				const originalText = button.textContent.trim();
				button.dataset.originalText = originalText;
				button.textContent = "Opening booking…";

				window.setTimeout(() => {
					window.location.assign(BOOKING_URL);
				}, 180);
			});
		});
	}

	function setActiveNavigation() {
		const currentPath = window.location.pathname.replace(/\/+$/, "");
		const links = document.querySelectorAll(selectors.navigationLinks);

		links.forEach((link) => {
			const linkUrl = new URL(link.href, window.location.origin);
			const linkPath = linkUrl.pathname.replace(/\/+$/, "");

			if (linkPath === currentPath) {
				link.classList.add("is-active");
				link.setAttribute("aria-current", "page");
			} else {
				link.classList.remove("is-active");
				link.removeAttribute("aria-current");
			}
		});
	}

	function initializeMobileNavigation() {
		const menuButton = document.querySelector(selectors.menuButton);
		const sidebar = document.querySelector(selectors.sidebar);
		const overlay = document.querySelector(selectors.overlay);

		if (!menuButton || !sidebar) return;

		function openMenu() {
			lastFocusedElement = document.activeElement;
			sidebar.classList.add("is-open");
			overlay?.classList.add("is-visible");
			document.body.classList.add("portal-menu-open");
			menuButton.setAttribute("aria-expanded", "true");
			sidebar.setAttribute("aria-hidden", "false");

			const firstLink = sidebar.querySelector("a, button");
			window.setTimeout(() => {
				firstLink?.focus();
			}, 100);
		}

		function closeMenu() {
			sidebar.classList.remove("is-open");
			overlay?.classList.remove("is-visible");
			document.body.classList.remove("portal-menu-open");
			menuButton.setAttribute("aria-expanded", "false");
			sidebar.setAttribute("aria-hidden", "true");
			lastFocusedElement?.focus();
		}

		menuButton.addEventListener("click", () => {
			if (sidebar.classList.contains("is-open")) {
				closeMenu();
			} else {
				openMenu();
			}
		});

		overlay?.addEventListener("click", closeMenu);

		sidebar.addEventListener("click", (event) => {
			const link = event.target.closest("a");
			if (link && window.innerWidth <= 980) {
				closeMenu();
			}
		});

		document.addEventListener("keydown", (event) => {
			if (event.key === "Escape" && sidebar.classList.contains("is-open")) {
				closeMenu();
			}
		});
	}

	function initializeLogoutLinks() {
		const logoutLinks = document.querySelectorAll(selectors.logoutLinks);

		logoutLinks.forEach((link) => {
			link.addEventListener("click", () => {
				if (link.dataset.loading === "true") return;
				link.dataset.loading = "true";
				link.setAttribute("aria-busy", "true");
				link.textContent = "Logging out…";
			});
		});
	}

	function initializeKeyboardDetection() {
		document.addEventListener("keydown", (event) => {
			if (event.key === "Tab") {
				document.documentElement.classList.add("keyboard-navigation");
			}
		});

		document.addEventListener("mousedown", () => {
			document.documentElement.classList.remove("keyboard-navigation");
		});
	}

	function handlePageRestore() {
		window.addEventListener("pageshow", (event) => {
			if (!event.persisted) return;

			document
				.querySelectorAll('[data-loading="true"]')
				.forEach((element) => {
					element.dataset.loading = "false";
					element.removeAttribute("aria-busy");

					if (element.dataset.originalText) {
						element.textContent = element.dataset.originalText;
					}
				});
		});
	}

	function initializeCardSheen() {
		const cards = document.querySelectorAll(selectors.cards);
		if (!cards.length) return;

		const supportsFinePointer = window.matchMedia("(pointer: fine)").matches;

		if (!supportsFinePointer) {
			cards.forEach((card) => {
				card.style.setProperty("--glow-opacity", "0.16");
			});
			return;
		}

		cards.forEach((card) => {
			card.addEventListener("mousemove", (event) => {
				const rect = card.getBoundingClientRect();
				const x = ((event.clientX - rect.left) / rect.width) * 100;
				const y = ((event.clientY - rect.top) / rect.height) * 100;

				card.style.setProperty("--mx", `${x}%`);
				card.style.setProperty("--my", `${y}%`);
				card.style.setProperty("--glow-opacity", "1");
			});

			card.addEventListener("mouseleave", () => {
				card.style.setProperty("--glow-opacity", "0");
			});
		});
	}

	function initializePortal() {
		updateGreeting();
		initializeProfileImageFallback();
		initializeBookingButtons();
		setActiveNavigation();
		initializeMobileNavigation();
		initializeLogoutLinks();
		initializeKeyboardDetection();
		handlePageRestore();
		initializeCardSheen();

		document.documentElement.classList.add("portal-js-ready");
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initializePortal, { once: true });
	} else {
		initializePortal();
	}
})();