(() => {
	"use strict";

	const profileTrigger = document.querySelector(
		"[data-profile-menu-trigger]"
	);

	const profileDropdown = document.querySelector(
		"[data-profile-dropdown]"
	);

	const toast = document.querySelector(
		"[data-portal-toast]"
	);

	let toastTimer = 0;

	function setGreeting() {
		const greeting = document.querySelector(
			"[data-greeting]"
		);

		if (!greeting) {
			return;
		}

		const hour = new Date().getHours();

		if (hour < 12) {
			greeting.textContent = "Good morning,";
			return;
		}

		if (hour < 17) {
			greeting.textContent = "Good afternoon,";
			return;
		}

		greeting.textContent = "Good evening,";
	}

	function showToast(message) {
		if (!toast) {
			return;
		}

		window.clearTimeout(toastTimer);

		toast.textContent = message;
		toast.hidden = false;

		requestAnimationFrame(() => {
			toast.classList.add("is-visible");
		});

		toastTimer = window.setTimeout(() => {
			toast.classList.remove("is-visible");

			window.setTimeout(() => {
				toast.hidden = true;
			}, 250);
		}, 2600);
	}

	function closeProfileMenu() {
		if (!profileTrigger || !profileDropdown) {
			return;
		}

		profileTrigger.setAttribute(
			"aria-expanded",
			"false"
		);

		profileDropdown.hidden = true;
	}

	function openProfileMenu() {
		if (!profileTrigger || !profileDropdown) {
			return;
		}

		profileTrigger.setAttribute(
			"aria-expanded",
			"true"
		);

		profileDropdown.hidden = false;
	}

	function initializeProfileMenu() {
		if (!profileTrigger || !profileDropdown) {
			return;
		}

		profileTrigger.addEventListener(
			"click",
			(event) => {
				event.stopPropagation();

				if (profileDropdown.hidden) {
					openProfileMenu();
				} else {
					closeProfileMenu();
				}
			}
		);

		profileDropdown.addEventListener(
			"click",
			(event) => {
				event.stopPropagation();
			}
		);

		document.addEventListener(
			"click",
			closeProfileMenu
		);

		document.addEventListener(
			"keydown",
			(event) => {
				if (event.key === "Escape") {
					closeProfileMenu();
					profileTrigger.focus();
				}
			}
		);
	}

	function initializeComingSoonActions() {
		document
			.querySelectorAll("[data-coming-soon]")
			.forEach((element) => {
				element.addEventListener(
					"click",
					(event) => {
						event.preventDefault();

						const feature =
							element.dataset.comingSoon
							|| "This feature";

						showToast(
							`${feature} will be available in the next portal phase.`
						);
					}
				);
			});
	}

	function initializeProfileImageFallback() {
		document
			.querySelectorAll("[data-profile-image]")
			.forEach((image) => {
				image.addEventListener(
					"error",
					() => {
						image.remove();
					},
					{ once: true }
				);
			});
	}

	function initializeLogoutState() {
		document
			.querySelectorAll(".js-logout")
			.forEach((link) => {
				link.addEventListener(
					"click",
					() => {
						link.setAttribute(
							"aria-busy",
							"true"
						);

						const text = link.querySelector(
							"span"
						);

						if (text) {
							text.textContent =
								"Logging out…";
						}
					}
				);
			});
	}

	function initializePortal() {
		setGreeting();
		initializeProfileMenu();
		initializeComingSoonActions();
		initializeProfileImageFallback();
		initializeLogoutState();

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
