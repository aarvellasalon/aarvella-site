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
	WHATSAPP_NUMBER: "919742049990"
};
