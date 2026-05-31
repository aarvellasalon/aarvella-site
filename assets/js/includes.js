document.addEventListener("DOMContentLoaded", async () => {
	const footerPlaceholder = document.getElementById("footer-placeholder");

	if (!footerPlaceholder) {
		console.warn("Footer placeholder not found.");
		return;
	}

	const currentScript = document.currentScript;
	const scriptUrl = currentScript ? new URL(currentScript.src) : null;

	const footerUrl = scriptUrl
		? new URL("../partials/footer.html", scriptUrl).href
		: "assets/partials/footer.html";

	try {
		const response = await fetch(footerUrl, {
			cache: "no-cache"
		});

		if (!response.ok) {
			throw new Error(`Footer could not be loaded. HTTP status: ${response.status}`);
		}

		const footerHTML = await response.text();
		footerPlaceholder.innerHTML = footerHTML;

		console.log("Aarvella footer loaded successfully.");
	} catch (error) {
		console.error("Footer include error:", error);

		footerPlaceholder.innerHTML = `
			<footer class="main-footer">
				<div class="footer-container">
					<div class="footer-col">
						<div class="logo">AARVELLA</div>
						<p class="mission-text">Footer could not be loaded.</p>
					</div>
				</div>
			</footer>
		`;
	}
});