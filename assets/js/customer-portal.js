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
		overlay: "[data-portal-overlay]"
	};

	let lastFocusedElement = null;

	/**
	 * Return an appropriate greeting based on the user's
	 * local device time.
	 */
	function getGreeting() {
		const hour = new Date().getHours();

		if (hour < 12) {
			return "Good morning";
		}

		if (hour < 17) {
			return "Good afternoon";
		}

		return "Good evening";
	}

	/**
	 * Update the welcome label without changing the
	 * customer's name rendered by PHP.
	 */
	function updateGreeting() {
		const welcomeText = document.querySelector(
			selectors.welcomeText
		);

		if (!welcomeText) {
			return;
		}

		welcomeText.textContent = getGreeting();
	}

	/**
	 * Replace a broken Google/Auth0 profile image with
	 * a local Aarvella fallback image.
	 */
	function initializeProfileImageFallback() {
		const profileImage = document.querySelector(
			selectors.profileImage
		);

		if (!profileImage) {
			return;
		}

		const fallbackImage =
			"/assets/images/default-profile.webp";

		profileImage.addEventListener(
			"error",
			() => {
				if (
					profileImage.src.includes(
						"default-profile.webp"
					)
				) {
					return;
				}

				profileImage.src = fallbackImage;
			},
			{ once: true }
		);
	}

	/**
	 * Send customers to the existing Aarvella booking
	 * section. This prevents a non-functional button on
	 * portal pages where the booking popup is not loaded.
	 */
	function initializeBookingButtons() {
		const buttons = document.querySelectorAll(
			selectors.bookingButtons
		);

		buttons.forEach((button) => {
			button.addEventListener("click", (event) => {
				event.preventDefault();

				if (
					button.dataset.loading === "true"
				) {
					return;
				}

				button.dataset.loading = "true";
				button.setAttribute(
					"aria-busy",
					"true"
				);

				const originalText =
					button.textContent.trim();

				button.dataset.originalText =
					originalText;

				button.textContent =
					"Opening booking…";

				window.setTimeout(() => {
					window.location.assign(
						BOOKING_URL
					);
				}, 180);
			});
		});
	}

	/**
	 * Mark the current portal navigation item.
	 */
	function setActiveNavigation() {
		const currentPath =
			window.location.pathname.replace(
				/\/+$/,
				""
			);

		const links = document.querySelectorAll(
			selectors.navigationLinks
		);

		links.forEach((link) => {
			const linkUrl = new URL(
				link.href,
				window.location.origin
			);

			const linkPath =
				linkUrl.pathname.replace(
					/\/+$/,
					""
				);

			if (linkPath === currentPath) {
				link.classList.add("is-active");

				link.setAttribute(
					"aria-current",
					"page"
				);
			} else {
				link.classList.remove("is-active");
				link.removeAttribute("aria-current");
			}
		});
	}

	/**
	 * Optional mobile portal navigation support.
	 *
	 * This becomes active only when the related data
	 * attributes exist in the dashboard HTML.
	 */
	function initializeMobileNavigation() {
		const menuButton = document.querySelector(
			selectors.menuButton
		);

		const sidebar = document.querySelector(
			selectors.sidebar
		);

		const overlay = document.querySelector(
			selectors.overlay
		);

		if (!menuButton || !sidebar) {
			return;
		}

		function openMenu() {
			lastFocusedElement =
				document.activeElement;

			sidebar.classList.add("is-open");
			overlay?.classList.add("is-visible");

			document.body.classList.add(
				"portal-menu-open"
			);

			menuButton.setAttribute(
				"aria-expanded",
				"true"
			);

			sidebar.setAttribute(
				"aria-hidden",
				"false"
			);

			const firstLink =
				sidebar.querySelector("a, button");

			window.setTimeout(() => {
				firstLink?.focus();
			}, 100);
		}

		function closeMenu() {
			sidebar.classList.remove("is-open");
			overlay?.classList.remove("is-visible");

			document.body.classList.remove(
				"portal-menu-open"
			);

			menuButton.setAttribute(
				"aria-expanded",
				"false"
			);

			sidebar.setAttribute(
				"aria-hidden",
				"true"
			);

			lastFocusedElement?.focus();
		}

		menuButton.addEventListener(
			"click",
			() => {
				if (
					sidebar.classList.contains(
						"is-open"
					)
				) {
					closeMenu();
				} else {
					openMenu();
				}
			}
		);

		overlay?.addEventListener(
			"click",
			closeMenu
		);

		sidebar.addEventListener(
			"click",
			(event) => {
				const link =
					event.target.closest("a");

				if (link) {
					closeMenu();
				}
			}
		);

		document.addEventListener(
			"keydown",
			(event) => {
				if (
					event.key === "Escape" &&
					sidebar.classList.contains(
						"is-open"
					)
				) {
					closeMenu();
				}
			}
		);
	}

	/**
	 * Prevent repeated logout clicks while the Auth0
	 * logout redirect is starting.
	 */
	function initializeLogoutLinks() {
		const logoutLinks =
			document.querySelectorAll(
				selectors.logoutLinks
			);

		logoutLinks.forEach((link) => {
			link.addEventListener("click", () => {
				if (
					link.dataset.loading === "true"
				) {
					return;
				}

				link.dataset.loading = "true";

				link.setAttribute(
					"aria-busy",
					"true"
				);

				link.textContent = "Logging out…";
			});
		});
	}

	/**
	 * Add keyboard-visible focus behaviour for users
	 * navigating through the portal without a mouse.
	 */
	function initializeKeyboardDetection() {
		document.addEventListener(
			"keydown",
			(event) => {
				if (event.key === "Tab") {
					document.documentElement.classList.add(
						"keyboard-navigation"
					);
				}
			}
		);

		document.addEventListener(
			"mousedown",
			() => {
				document.documentElement.classList.remove(
					"keyboard-navigation"
				);
			}
		);
	}

	/**
	 * Restore buttons if the user returns using the
	 * browser's back-forward cache.
	 */
	function handlePageRestore() {
		window.addEventListener(
			"pageshow",
			(event) => {
				if (!event.persisted) {
					return;
				}

				document
					.querySelectorAll(
						'[data-loading="true"]'
					)
					.forEach((element) => {
						element.dataset.loading =
							"false";

						element.removeAttribute(
							"aria-busy"
						);

						if (
							element.dataset
								.originalText
						) {
							element.textContent =
								element.dataset
									.originalText;
						}
					});
			}
		);
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

		document.documentElement.classList.add(
			"portal-js-ready"
		);
	}

	if (document.readyState === "loading") {
		document.addEventListener(
			"DOMContentLoaded",
			initializePortal,
			{ once: true }
		);
	} else {
		initializePortal();
	}
})();