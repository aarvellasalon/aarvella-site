/*
 * document.currentScript is only valid during this script's own synchronous
 * top-level execution — it's already null by the time an async
 * DOMContentLoaded callback runs, so it must be captured here, not inside
 * the listener below (same pattern as booking.js / ai-chat-loader.js).
 */
const includesScriptUrl = document.currentScript
	? new URL(document.currentScript.src)
	: null;

document.addEventListener("DOMContentLoaded", async () => {
	const footerPlaceholder = document.getElementById("footer-placeholder");

	if (!footerPlaceholder) {
		console.warn("Footer placeholder not found.");
		return;
	}

	const footerUrl = includesScriptUrl
		? new URL("../partials/footer.html", includesScriptUrl).href
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