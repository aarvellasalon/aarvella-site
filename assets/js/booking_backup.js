document.addEventListener("DOMContentLoaded", () => {
	let currentStep = 1;
	let selectedService = "";

	const bookingPopup = document.getElementById("bookingPopup");
	const progressBar = document.getElementById("progressBar");
	const popupOverlay = document.querySelector(".popup-overlay");

	if (!bookingPopup) return;

	function showStep(stepNumber) {
		document.querySelectorAll(".popup-step").forEach((step) => {
			step.classList.remove("active");
		});

		const targetStep = document.querySelector(
			`.popup-step[data-step="${stepNumber}"]`
		);

		if (targetStep) {
			targetStep.classList.add("active");
		}

		if (progressBar) {
			progressBar.style.width = `${stepNumber * 25}%`;
		}
	}

	window.openBooking = function () {
		currentStep = 1;
		selectedService = "";

		document.querySelectorAll(".service-options div").forEach((option) => {
			option.classList.remove("selected");
		});

		showStep(1);

		bookingPopup.classList.add("active");
		document.body.classList.add("booking-open");
		document.body.style.overflow = "hidden";
	};

	window.closeBooking = function () {
		bookingPopup.classList.remove("active");
		document.body.classList.remove("booking-open");
		document.body.style.overflow = "";
	};

	window.selectService = function (el) {
		document.querySelectorAll(".service-options div").forEach((option) => {
			option.classList.remove("selected");
		});

		el.classList.add("selected");
		selectedService = el.textContent.trim();
	};

	window.nextStep = function () {
		if (currentStep === 1 && !selectedService) {
			showBookingError("Please select a service");
			return;
		}

		if (currentStep >= 4) return;

		currentStep++;
		showStep(currentStep);
	};

	window.prevStep = function () {
		if (currentStep <= 1) return;

		currentStep--;
		showStep(currentStep);
	};

	window.setDate = function (offset) {
		const dateInput = document.getElementById("bookingDate");
		if (!dateInput) return;

		const date = new Date();
		date.setDate(date.getDate() + offset);

		dateInput.value = date.toISOString().split("T")[0];
	};

	window.setTime = function (time) {
		const timeInput = document.getElementById("bookingTime");
		if (!timeInput) return;

		timeInput.value = time;
	};

	window.confirmBooking = function () {
		const name = document.getElementById("name")?.value.trim();
		const phone = document.getElementById("phone")?.value.trim();
		const date = document.getElementById("bookingDate")?.value || "";
		const time = document.getElementById("bookingTime")?.value || "";

		if (!name || !phone) {
			alert("Please fill all details");
			return;
		}

		currentStep = 4;
		showStep(4);

		const message = `
Hi Aarvella, I want to book an appointment.

Service: ${selectedService}
Date: ${date}
Time: ${time}
Name: ${name}
Phone: ${phone}
		`;

		const whatsappBtn = document.getElementById("whatsappBtn");

		if (whatsappBtn) {
			whatsappBtn.href =
				"https://wa.me/919742049990?text=" + encodeURIComponent(message);
		}
	};

	function showBookingError(message) {
		const stepOne = document.querySelector('.popup-step[data-step="1"]');
		if (!stepOne) return;

		const existingError = stepOne.querySelector(".booking-error");
		if (existingError) existingError.remove();

		const error = document.createElement("div");
		error.className = "booking-error";
		error.textContent = message;
		error.style.color = "#ff6b6b";
		error.style.marginTop = "12px";
		error.style.fontSize = "0.9rem";

		stepOne.appendChild(error);

		setTimeout(() => {
			error.remove();
		}, 2200);
	}

	function spawnRipple(button, e) {
		const container = button.querySelector(".btn-ripple-container");
		if (!container) return;

		const ripple = document.createElement("span");
		ripple.classList.add("btn-ripple");

		const rect = button.getBoundingClientRect();

		ripple.style.setProperty("--ripple-x", `${e.clientX - rect.left}px`);
		ripple.style.setProperty("--ripple-y", `${e.clientY - rect.top}px`);

		container.appendChild(ripple);

		setTimeout(() => {
			ripple.remove();
		}, 700);
	}

	document.querySelectorAll(".js-book, .card-book-btn").forEach((button) => {
		button.addEventListener("click", (e) => {
			e.preventDefault();

			spawnRipple(button, e);

			setTimeout(() => {
				openBooking();
			}, 120);
		});
	});

	if (popupOverlay) {
		popupOverlay.addEventListener("click", closeBooking);
	}

	document.addEventListener("keydown", (e) => {
		if (e.key === "Escape" && bookingPopup.classList.contains("active")) {
			closeBooking();
		}
	});
});