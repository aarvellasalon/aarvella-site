document.addEventListener("DOMContentLoaded", async () => {
	const footerPlaceholder = document.getElementById("footer-placeholder");

	if (!footerPlaceholder) return;

	try {
		const response = await fetch("assets/partials/footer.html", {
			cache: "no-cache"
		});

		if (!response.ok) {
			throw new Error(`Footer could not be loaded. HTTP status: ${response.status}`);
		}

		footerPlaceholder.innerHTML = await response.text();
	} catch (error) {
		console.error("Footer include error:", error);
	}
});

document.addEventListener("submit", async (event) => {
	const form = event.target.closest("#newsletterForm");

	if (!form) return;

	event.preventDefault();

	const emailInput = form.querySelector('input[name="email"]');
	const message = document.getElementById("newsletterMessage");

	const email = emailInput.value.trim();

	if (!email) {
		if (message) message.textContent = "Please enter your email address.";
		return;
	}

	try {
		const response = await fetch("/api/newsletter/subscribe.php", {
			method: "POST",
			headers: {
				"Content-Type": "application/json"
			},
			body: JSON.stringify({
				email,
				source: "footer_form"
			})
		});

		const result = await response.json();

		if (message) {
			message.textContent = result.message || "Thank you for subscribing.";
		}

		if (result.success) {
			form.reset();
		}
	} catch (error) {
		console.error("Newsletter error:", error);

		if (message) {
			message.textContent = "Something went wrong. Please try again.";
		}
	}
});