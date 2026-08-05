/* ==========================================================
   Aarvella shared site config
   File: assets/js/site-config.js

   Single source for values duplicated across otherwise-independent
   scripts (assets/js/booking.js, assets/js/ai-chat.js). Not a module —
   loaded as a plain classic script, before those two, on every page
   that includes either. Sets one global rather than exporting, so it
   works the same way as every other script on this site.
========================================================== */

window.AARVELLA_CONFIG = {
	WHATSAPP_NUMBER: "919142351661"
};

/*
 * Wires up the floating "Book on WhatsApp" button (assets/css/buttons.css
 * .av-btn--floating-whatsapp) present on every page, so its markup never
 * needs to hardcode the number directly. Runs synchronously here (this
 * script is deliberately not deferred) — safe because the button's HTML
 * always sits earlier in the DOM than this <script> tag.
 */
document.querySelectorAll("[data-whatsapp-float]").forEach((link) => {
	const message = encodeURIComponent(
		"Hi Aarvella, I'd like to book an appointment."
	);
	link.href = `https://wa.me/${window.AARVELLA_CONFIG.WHATSAPP_NUMBER}?text=${message}`;
});
