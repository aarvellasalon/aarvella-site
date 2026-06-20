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

		const mount = document.getElementById("booking-popup-placeholder");

		if (!mount) {
			console.warn("Booking popup placeholder not found.");
			return;
		}

		try {
			const response = await fetch(partialUrl, { cache: "no-cache" });

			if (!response.ok) {
				throw new Error(`Booking popup failed to load: HTTP ${response.status}`);
			}

			mount.innerHTML = await response.text();
			popup = document.getElementById("bookingPopup");
			initialize();
		} catch (error) {
			console.error("Booking popup include error:", error);
		}
	}

	function initialize() {
		if (!popup || popup.dataset.initialized === "true") return;

		popup.dataset.initialized = "true";
		popup.addEventListener("click", handlePopupClick);
		document.addEventListener("click", handleBookingTrigger);
		document.addEventListener("keydown", handleKeydown);

		showStep(1);
	}

	function handleBookingTrigger(event) {
		const trigger = event.target.closest(
			".js-book, .card-book-btn, [data-booking-trigger]"
		);

		if (!trigger) return;

		event.preventDefault();
		openBooking(trigger.dataset.book || "");
	}

	function handlePopupClick(event) {
		if (event.target.closest("[data-booking-close]")) {
			closeBooking();
			return;
		}

		const service = event.target.closest(".service-option");
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

		if (event.target.closest("[data-booking-confirm]")) {
			confirmBooking();
			return;
		}

		const dateButton = event.target.closest("[data-date-offset]");
		if (dateButton) {
			setDate(Number(dateButton.dataset.dateOffset), dateButton);
			return;
		}

		const timeButton = event.target.closest("[data-time]");
		if (timeButton) {
			setTime(timeButton.dataset.time, timeButton);
		}
	}

	function openBooking(preselectedService = "") {
		if (!popup) return;

		previouslyFocused = document.activeElement;
		currentStep = 1;
		selectedService = "";

		resetPopup();
		showStep(1);

		if (preselectedService) {
			const matchingService = [...popup.querySelectorAll(".service-option")]
				.find((button) => button.textContent.trim() === preselectedService);

			if (matchingService) selectService(matchingService);
		}

		popup.classList.add("active");
		popup.setAttribute("aria-hidden", "false");
		document.body.classList.add("booking-open");

		requestAnimationFrame(() => {
			popup.querySelector(".popup-container")?.focus();
		});
	}

	function closeBooking() {
		if (!popup) return;

		popup.classList.remove("active");
		popup.setAttribute("aria-hidden", "true");
		document.body.classList.remove("booking-open");

		if (previouslyFocused instanceof HTMLElement) {
			previouslyFocused.focus();
		}
	}

	function showStep(stepNumber) {
		currentStep = stepNumber;

		popup.querySelectorAll(".popup-step").forEach((step) => {
			step.classList.toggle(
				"active",
				Number(step.dataset.step) === stepNumber
			);
		});

		const progressBar = popup.querySelector("#progressBar");
		if (progressBar) {
			progressBar.style.width = `${stepNumber * 25}%`;
		}

		const container = popup.querySelector(".popup-container");
		if (container) container.scrollTop = 0;
	}

	function selectService(button) {
		popup.querySelectorAll(".service-option").forEach((option) => {
			const isSelected = option === button;
			option.classList.toggle("selected", isSelected);
			option.setAttribute("aria-checked", String(isSelected));
		});

		selectedService = button.textContent.trim();
		setError("bookingError", "");
	}

	function nextStep() {
		if (currentStep === 1 && !selectedService) {
			setError("bookingError", "Please select a service.");
			return;
		}

		if (currentStep < 4) showStep(currentStep + 1);
	}

	function previousStep() {
		if (currentStep > 1) showStep(currentStep - 1);
	}

	function setDate(offset, activeButton) {
		const dateInput = popup.querySelector("#bookingDate");
		if (!dateInput) return;

		const date = new Date();
		date.setHours(12, 0, 0, 0);
		date.setDate(date.getDate() + offset);

		dateInput.value = [
			date.getFullYear(),
			String(date.getMonth() + 1).padStart(2, "0"),
			String(date.getDate()).padStart(2, "0")
		].join("-");

		popup.querySelectorAll("[data-date-offset]").forEach((button) => {
			button.classList.toggle("selected", button === activeButton);
		});
	}

	function setTime(time, activeButton) {
		const timeInput = popup.querySelector("#bookingTime");
		if (!timeInput) return;

		timeInput.value = time;

		popup.querySelectorAll("[data-time]").forEach((button) => {
			button.classList.toggle("selected", button === activeButton);
		});
	}

	function confirmBooking() {
		const name = popup.querySelector("#bookingName")?.value.trim() || "";
		const phone = popup.querySelector("#bookingPhone")?.value.trim() || "";
		const date = popup.querySelector("#bookingDate")?.value || "To be confirmed";
		const time = popup.querySelector("#bookingTime")?.value || "To be confirmed";

		if (!name || !phone) {
			setError("detailsError", "Please enter your name and phone number.");
			return;
		}

		const message = [
			"Hi Aarvella, I want to book an appointment.",
			"",
			`Service: ${selectedService}`,
			`Date: ${date}`,
			`Time: ${time}`,
			`Name: ${name}`,
			`Phone: ${phone}`
		].join("\n");

		const whatsappButton = popup.querySelector("#whatsappBtn");

		if (whatsappButton) {
			whatsappButton.href =
				`https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
		}

		showStep(4);
	}

	function resetPopup() {
		popup.querySelectorAll(".service-option").forEach((button) => {
			button.classList.remove("selected");
			button.setAttribute("aria-checked", "false");
		});

		popup.querySelectorAll("input").forEach((input) => {
			input.value = "";
		});

		popup.querySelectorAll("[data-date-offset], [data-time]").forEach((button) => {
			button.classList.remove("selected");
		});

		setError("bookingError", "");
		setError("detailsError", "");
	}

	function setError(id, message) {
		const element = popup.querySelector(`#${id}`);
		if (element) element.textContent = message;
	}

	function handleKeydown(event) {
		if (!popup?.classList.contains("active")) return;

		if (event.key === "Escape") {
			closeBooking();
			return;
		}

		if (event.key === "Tab") {
			trapFocus(event);
		}
	}

	function trapFocus(event) {
		const focusable = [...popup.querySelectorAll(
			'button:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'
		)].filter((element) => element.offsetParent !== null);

		if (!focusable.length) return;

		const first = focusable[0];
		const last = focusable[focusable.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	document.addEventListener("DOMContentLoaded", mountPopup);

	/* Compatibility with any existing inline calls */
	window.openBooking = openBooking;
	window.closeBooking = closeBooking;
})();
