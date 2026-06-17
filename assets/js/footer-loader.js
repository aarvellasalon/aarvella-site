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