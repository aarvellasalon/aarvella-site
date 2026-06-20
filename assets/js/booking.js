/* ==========================================================
   Aarvella shared booking popup
   File: assets/js/booking.js
========================================================== */

(() => {
	const scriptElement = document.currentScript;

	const partialUrl = scriptElement?.src
		? new URL("../partials/booking-popup.html", scriptElement.src).href
		: "assets/partials/booking-popup.html";

	const whatsappNumber = "919742049990";

	let popup = null;
	let currentStep = 1;
	let selectedService = "";
	let previouslyFocused = null;

	async function mountPopup() {
		const existingPopup = document.getElementById("bookingPopup");

		if (existingPopup) {
			popup = existingPopup;
			initialize();
			return;
		}

		const mount = document.getElementById(
			"booking-popup-placeholder"
		);

		if (!mount) {
			console.warn("Booking popup placeholder not found.");
			return;
		}

		try {
			const response = await fetch(partialUrl, {
				cache: "no-cache"
			});

			if (!response.ok) {
				throw new Error(
					`Booking popup failed to load: HTTP ${response.status}`
				);
			}

			mount.innerHTML = await response.text();

			popup = document.getElementById("bookingPopup");

			if (!popup) {
				throw new Error(
					"Booking popup HTML loaded, but #bookingPopup was not found."
				);
			}

			initialize();
		} catch (error) {
			console.error("Booking popup include error:", error);
		}
	}

	function initialize() {
		if (!popup || popup.dataset.initialized === "true") {
			return;
		}

		popup.dataset.initialized = "true";

		popup.addEventListener("click", handlePopupClick);
		document.addEventListener("click", handleBookingTrigger);
		document.addEventListener("keydown", handleKeydown);

		const phoneInput = popup.querySelector("#bookingPhone");

		if (phoneInput) {
			phoneInput.addEventListener("input", () => {
				phoneInput.removeAttribute("aria-invalid");
				phoneInput.setCustomValidity("");
				setError("detailsError", "");
			});

			phoneInput.addEventListener("blur", () => {
				const rawPhone = phoneInput.value.trim();

				if (!rawPhone) {
					return;
				}

				const validatedPhone =
					validateIndianMobileNumber(rawPhone);

				if (!validatedPhone) {
					phoneInput.setAttribute(
						"aria-invalid",
						"true"
					);

					phoneInput.setCustomValidity(
						"Enter a valid 10-digit Indian mobile number."
					);

					setError(
						"detailsError",
						"Enter a valid Indian mobile number, such as 9876543210, 09876543210, 919876543210 or +919876543210."
					);

					return;
				}

				phoneInput.value =
					validatedPhone.international;

				phoneInput.removeAttribute("aria-invalid");
				phoneInput.setCustomValidity("");
				setError("detailsError", "");
			});
		}

		showStep(1);
	}

	function initializePhoneValidation() {
		const phoneInput = popup.querySelector("#bookingPhone");

		if (!phoneInput) {
			return;
		}

		phoneInput.addEventListener("input", () => {
			phoneInput.removeAttribute("aria-invalid");
			phoneInput.setCustomValidity("");
			setError("detailsError", "");
		});

		phoneInput.addEventListener("blur", () => {
			const value = phoneInput.value.trim();

			if (!value) {
				return;
			}

			const normalizedPhone =
				normalizeIndianMobileNumber(value);

			if (!normalizedPhone) {
				showPhoneError(phoneInput);
				return;
			}

			phoneInput.value =
				normalizedPhone.international;

			phoneInput.removeAttribute("aria-invalid");
			phoneInput.setCustomValidity("");
			setError("detailsError", "");
		});
	}

	function handleBookingTrigger(event) {
		const trigger = event.target.closest(
			".js-book, .card-book-btn, [data-booking-trigger]"
		);

		if (!trigger) {
			return;
		}

		event.preventDefault();

		openBooking(trigger.dataset.book || "");
	}

	function handlePopupClick(event) {
		if (event.target.closest("[data-booking-close]")) {
			closeBooking();
			return;
		}

		const service = event.target.closest(
			".service-option"
		);

		if (service) {
			selectService(service);
			return;
		}

		if (event.target.closest("[data-booking-next]")) {
			nextStep();
			return;
		}

		if (event.target.closest("[data-booking-prev]")) {
			previousStep();
			return;
		}

		if (
			event.target.closest("[data-booking-confirm]")
		) {
			confirmBooking();
			return;
		}

		const dateButton = event.target.closest(
			"[data-date-offset]"
		);

		if (dateButton) {
			setDate(
				Number(dateButton.dataset.dateOffset),
				dateButton
			);

			return;
		}

		const timeButton = event.target.closest(
			"[data-time]"
		);

		if (timeButton) {
			setTime(
				timeButton.dataset.time,
				timeButton
			);
		}
	}

	function openBooking(preselectedService = "") {
		if (!popup) {
			return;
		}

		previouslyFocused = document.activeElement;
		currentStep = 1;
		selectedService = "";

		resetPopup();
		showStep(1);

		if (preselectedService) {
			const matchingService = [
				...popup.querySelectorAll(
					".service-option"
				)
			].find(
				(button) =>
					button.textContent.trim() ===
					preselectedService
			);

			if (matchingService) {
				selectService(matchingService);
			}
		}

		popup.classList.add("active");
		popup.setAttribute("aria-hidden", "false");

		document.body.classList.add("booking-open");

		requestAnimationFrame(() => {
			popup
				.querySelector(".popup-container")
				?.focus();
		});
	}

	function closeBooking() {
		if (!popup) {
			return;
		}

		popup.classList.remove("active");
		popup.setAttribute("aria-hidden", "true");

		document.body.classList.remove("booking-open");

		if (previouslyFocused instanceof HTMLElement) {
			previouslyFocused.focus();
		}
	}

	function showStep(stepNumber) {
		currentStep = stepNumber;

		popup
			.querySelectorAll(".popup-step")
			.forEach((step) => {
				step.classList.toggle(
					"active",
					Number(step.dataset.step) ===
						stepNumber
				);
			});

		const progressBar =
			popup.querySelector("#progressBar");

		if (progressBar) {
			progressBar.style.width =
				`${stepNumber * 25}%`;
		}

		const container =
			popup.querySelector(".popup-container");

		if (container) {
			container.scrollTop = 0;
		}
	}

	function selectService(button) {
		popup
			.querySelectorAll(".service-option")
			.forEach((option) => {
				const isSelected = option === button;

				option.classList.toggle(
					"selected",
					isSelected
				);

				option.setAttribute(
					"aria-checked",
					String(isSelected)
				);
			});

		selectedService = button.textContent.trim();

		setError("bookingError", "");
	}

	function nextStep() {
		if (currentStep === 1 && !selectedService) {
			setError(
				"bookingError",
				"Please select a service."
			);

			return;
		}

		if (currentStep < 4) {
			showStep(currentStep + 1);
		}
	}

	function previousStep() {
		if (currentStep > 1) {
			showStep(currentStep - 1);
		}
	}

	function setDate(offset, activeButton) {
		const dateInput =
			popup.querySelector("#bookingDate");

		if (!dateInput) {
			return;
		}

		const date = new Date();

		date.setHours(12, 0, 0, 0);
		date.setDate(date.getDate() + offset);

		dateInput.value = [
			date.getFullYear(),
			String(date.getMonth() + 1).padStart(
				2,
				"0"
			),
			String(date.getDate()).padStart(2, "0")
		].join("-");

		popup
			.querySelectorAll("[data-date-offset]")
			.forEach((button) => {
				button.classList.toggle(
					"selected",
					button === activeButton
				);
			});
	}

	function setTime(time, activeButton) {
		const timeInput =
			popup.querySelector("#bookingTime");

		if (!timeInput) {
			return;
		}

		timeInput.value = time;

		popup
			.querySelectorAll("[data-time]")
			.forEach((button) => {
				button.classList.toggle(
					"selected",
					button === activeButton
				);
			});
	}

	/***
	 * Accepted formats:
	 *
	 * +91 9876543210
	 * +919876543210
	 * 91 9876543210
	 * 919876543210
	 * 09876543210
	 * 9876543210
	 *
	 * Spaces, hyphens and parentheses are ignored.
	 * Indian mobile numbers must begin with 6, 7, 8 or 9.
	 */
	function normalizeIndianMobileNumber(value) {
		const compact = value
			.trim()
			.replace(/[\s()-]/g, "");

		let nationalNumber = "";

		if (/^\+91[6-9]\d{9}$/.test(compact)) {
			nationalNumber = compact.slice(3);
		} else if (/^91[6-9]\d{9}$/.test(compact)) {
			nationalNumber = compact.slice(2);
		} else if (/^0[6-9]\d{9}$/.test(compact)) {
			nationalNumber = compact.slice(1);
		} else if (/^[6-9]\d{9}$/.test(compact)) {
			nationalNumber = compact;
		} else {
			return null;
		}

		return {
			national: nationalNumber,
			international: `+91${nationalNumber}`,
			whatsapp: `91${nationalNumber}`
		};
	}

	function showPhoneError(phoneInput) {
		const errorMessage =
			"Enter a valid Indian mobile number, for example +91 9876543210, 919876543210, 09876543210, or 9876543210.";

		setError("detailsError", errorMessage);

		phoneInput.setAttribute(
			"aria-invalid",
			"true"
		);

		phoneInput.setCustomValidity(
			"Enter a valid Indian mobile number."
		);
	}

	function validateIndianMobileNumber(rawValue) {
		const compact = rawValue
			.trim()
			.replace(/[\s().-]/g, "");

		/*
			Accepted:
			9876543210
			09876543210
			919876543210
			+919876543210

			The actual 10-digit number must start with 6, 7, 8 or 9.
		*/
		const match = compact.match(
			/^(?:\+91|91|0)?([6-9]\d{9})$/
		);

		if (!match) {
			return null;
		}

		const nationalNumber = match[1];

		return {
			national: nationalNumber,
			international: `+91${nationalNumber}`,
			whatsapp: `91${nationalNumber}`
		};
	}

	function confirmBooking() {
		const nameInput =
			popup.querySelector("#bookingName");

		const phoneInput =
			popup.querySelector("#bookingPhone");

		const name =
			nameInput?.value.trim() || "";

		const rawPhone =
			phoneInput?.value.trim() || "";

		const date =
			popup.querySelector("#bookingDate")?.value ||
			"To be confirmed";

		const time =
			popup.querySelector("#bookingTime")?.value ||
			"To be confirmed";

		if (!name) {
			setError(
				"detailsError",
				"Please enter your full name."
			);

			nameInput?.focus();
			return;
		}

		if (!rawPhone) {
			setError(
				"detailsError",
				"Please enter your mobile number."
			);

			if (phoneInput) {
				phoneInput.setAttribute(
					"aria-invalid",
					"true"
				);

				phoneInput.focus();
			}

			return;
		}

		const validatedPhone =
			validateIndianMobileNumber(rawPhone);

		if (!validatedPhone) {
			setError(
				"detailsError",
				"Enter a valid Indian mobile number, such as 9876543210, 09876543210, 919876543210 or +919876543210."
			);

			if (phoneInput) {
				phoneInput.setAttribute(
					"aria-invalid",
					"true"
				);

				phoneInput.setCustomValidity(
					"Enter a valid 10-digit Indian mobile number."
				);

				phoneInput.focus();
			}

			return;
		}

		if (phoneInput) {
			phoneInput.value =
				validatedPhone.international;

			phoneInput.removeAttribute(
				"aria-invalid"
			);

			phoneInput.setCustomValidity("");
		}

		setError("detailsError", "");

		const message = [
			"Hi Aarvella, I want to book an appointment.",
			"",
			`Service: ${selectedService}`,
			`Date: ${date}`,
			`Time: ${time}`,
			`Name: ${name}`,
			`Phone: ${validatedPhone.international}`
		].join("\n");

		const whatsappButton =
			popup.querySelector("#whatsappBtn");

		if (whatsappButton) {
			whatsappButton.href =
				`https://wa.me/${whatsappNumber}` +
				`?text=${encodeURIComponent(message)}`;
		}

		showStep(4);
	}

	function resetPopup() {
		popup
			.querySelectorAll(".service-option")
			.forEach((button) => {
				button.classList.remove("selected");

				button.setAttribute(
					"aria-checked",
					"false"
				);
			});

		popup
			.querySelectorAll("input")
			.forEach((input) => {
				input.value = "";
				input.removeAttribute("aria-invalid");
				input.setCustomValidity("");
			});

		popup
			.querySelectorAll(
				"[data-date-offset], [data-time]"
			)
			.forEach((button) => {
				button.classList.remove("selected");
			});

		setError("bookingError", "");
		setError("detailsError", "");
	}

	function setError(id, message) {
		const element =
			popup.querySelector(`#${id}`);

		if (element) {
			element.textContent = message;
		}
	}

	function handleKeydown(event) {
		if (
			!popup?.classList.contains("active")
		) {
			return;
		}

		if (event.key === "Escape") {
			closeBooking();
			return;
		}

		if (event.key === "Tab") {
			trapFocus(event);
		}
	}

	function trapFocus(event) {
		const focusable = [
			...popup.querySelectorAll(
				'button:not([disabled]), ' +
				'a[href], ' +
				'input:not([disabled]), ' +
				'[tabindex]:not([tabindex="-1"])'
			)
		].filter(
			(element) =>
				element.offsetParent !== null
		);

		if (!focusable.length) {
			return;
		}

		const first = focusable[0];
		const last =
			focusable[focusable.length - 1];

		if (
			event.shiftKey &&
			document.activeElement === first
		) {
			event.preventDefault();
			last.focus();
		} else if (
			!event.shiftKey &&
			document.activeElement === last
		) {
			event.preventDefault();
			first.focus();
		}
	}

	document.addEventListener(
		"DOMContentLoaded",
		mountPopup
	);

	/* Compatibility with existing inline calls */
	window.openBooking = openBooking;
	window.closeBooking = closeBooking;
})();