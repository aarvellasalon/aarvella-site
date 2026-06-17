document.addEventListener("submit", async function (event) {
	const form = event.target.closest("#newsletterForm");

	if (!form) return;

	event.preventDefault();

	const emailInput = form.querySelector('input[name="email"]');
	const submitButton = form.querySelector('button[type="submit"]');
	const message = document.getElementById("newsletterMessage");

	if (!emailInput) return;

	const email = emailInput.value.trim();

	if (!email) {
		if (message) {
			message.textContent = "Please enter your email address.";
			message.className = "newsletter-message error";
		}
		return;
	}

	const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	if (!emailPattern.test(email)) {
		if (message) {
			message.textContent = "Please enter a valid email address.";
			message.className = "newsletter-message error";
		}
		return;
	}

	try {
		if (submitButton) {
			submitButton.disabled = true;
			submitButton.textContent = "Joining...";
		}

		const response = await fetch("/api/newsletter/subscribe.php", {
			method: "POST",
			headers: {
				"Content-Type": "application/json"
			},
			body: JSON.stringify({
				email: email,
				source: "footer_form"
			})
		});

		const result = await response.json();

		if (message) {
			message.textContent = result.message || "Thank you for joining Aarvella Gold List.";
			message.className = result.success
				? "newsletter-message success"
				: "newsletter-message error";
		}

		if (result.success) {
			form.reset();
		}
	} catch (error) {
		console.error("Newsletter subscription error:", error);

		if (message) {
			message.textContent = "Something went wrong. Please try again.";
			message.className = "newsletter-message error";
		}
	} finally {
		if (submitButton) {
			submitButton.disabled = false;
			submitButton.textContent = "Join";
		}
	}
});