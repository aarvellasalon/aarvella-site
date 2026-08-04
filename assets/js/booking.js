/* ==========================================================
   Aarvella shared booking popup
   File: assets/js/booking.js

   Talks directly to the Aarvella CRM Booking API
   (os.aarvella.com) from the browser. Availability and
   appointment creation fall back to a WhatsApp hand-off if
   the CRM call fails, so a CRM outage never dead-ends a
   visitor mid-booking.
========================================================== */

(() => {
	const scriptElement = document.currentScript;

	const partialUrl = scriptElement?.src
		? new URL("../partials/booking-popup.html", scriptElement.src).href
		: "assets/partials/booking-popup.html";

	/* The salon is not open yet, so the site must not create real
	   appointments. Set to false to restore the normal booking flow when
	   ready — no other changes are needed. While true, opening the popup
	   shows a "not taking bookings yet" notice (see the "notice" step in
	   assets/partials/booking-popup.html) with a WhatsApp contact link,
	   instead of the service/stylist/OTP/confirm flow. */
	const BOOKING_TEMPORARILY_DISABLED = true;

	const WHATSAPP_NUMBER = window.AARVELLA_CONFIG?.WHATSAPP_NUMBER || "919142351661";
	const API_BASE = "https://os.aarvella.com/api/v1";
	const BRANCH_ID = 1;
	const BRANCH_UTC_OFFSET = "+05:30";
	const FALLBACK_SLOT_TIMES = ["10:00", "13:00", "16:00", "19:00"];

	let popup = null;
	let previouslyFocused = null;
	let cataloguePromise = null;
	const stylistsCache = new Map();
	let resendHandle = null;

	const state = {
		step: 1,
		catalogue: null,
		category: null,
		service: null,
		stylist: null,
		dates: null,
		date: null,
		time: null,
		slotStylistId: null,
		phone: "",
		token: null,
		idempotencyKey: null,
		pendingPreselect: ""
	};

	/* ---------------------------------------------------------
	   CRM API client
	--------------------------------------------------------- */

	async function apiFetch(path, options = {}) {
		let response;

		try {
			response = await fetch(`${API_BASE}${path}`, {
				...options,
				headers: {
					"Content-Type": "application/json",
					"Accept": "application/json",
					...(options.headers || {})
				}
			});
		} catch (networkError) {
			const error = new Error("network");
			error.isNetwork = true;
			throw error;
		}

		let body = null;

		try {
			body = await response.json();
		} catch (parseError) {
			body = null;
		}

		if (!response.ok) {
			const error = new Error(body?.message || `Request failed (${response.status})`);
			error.status = response.status;
			error.errors = body?.errors || null;
			throw error;
		}

		return body;
	}

	const Api = {
		getServices: () => apiFetch(`/branches/${BRANCH_ID}/services`),
		getStylists: (serviceId) => apiFetch(`/branches/${BRANCH_ID}/stylists?service_id=${encodeURIComponent(serviceId)}`),
		getAvailability: ({ serviceId, stylistId, dateStr }) => {
			const payload = { branch_id: BRANCH_ID, service_id: serviceId, date: dateStr };

			if (stylistId) {
				payload.stylist_id = stylistId;
			}

			return apiFetch("/availability", { method: "POST", body: JSON.stringify(payload) });
		},
		requestOtp: (phone) => apiFetch("/auth/otp/request", { method: "POST", body: JSON.stringify({ phone }) }),
		verifyOtp: (phone, code) => apiFetch("/auth/otp/verify", { method: "POST", body: JSON.stringify({ phone, code }) }),
		createAppointment: ({ token, idempotencyKey, serviceId, stylistId, startsAtIso }) =>
			apiFetch("/appointments", {
				method: "POST",
				headers: { Authorization: `Bearer ${token}`, "Idempotency-Key": idempotencyKey },
				body: JSON.stringify({
					branch_id: BRANCH_ID,
					service_id: serviceId,
					stylist_id: stylistId,
					starts_at: startsAtIso,
					source: "website"
				})
			})
	};

	/* Availability response shape is unverified against production
	   (the endpoint wasn't deployed yet when this was written) —
	   normalize defensively across the most likely key names. */
	function normalizeSlots(raw) {
		const list = Array.isArray(raw?.data) ? raw.data : Array.isArray(raw) ? raw : [];

		return list
			.map((entry) => {
				const stamp = entry.starts_at || entry.start || entry.time || entry.slot || null;
				const match = typeof stamp === "string" ? stamp.match(/(\d{2}:\d{2})/) : null;
				const time = match ? match[1] : (typeof stamp === "string" && /^\d{2}:\d{2}$/.test(stamp) ? stamp : null);

				if (!time) {
					return null;
				}

				return {
					time,
					stylistId: entry.stylist_id ?? entry.stylist?.id ?? null,
					available: entry.available !== false
				};
			})
			.filter(Boolean);
	}

	/* ---------------------------------------------------------
	   Utilities
	--------------------------------------------------------- */

	function escapeHtml(value) {
		return String(value).replace(/[&<>"']/g, (char) => ({
			"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
		}[char]));
	}

	function formatPrice(price) {
		const amount = Number(price);
		return Number.isNaN(amount) ? String(price) : `₹${amount.toLocaleString("en-IN")}`;
	}

	function to12h(time) {
		const [hour, minute] = time.split(":").map(Number);
		const suffix = hour >= 12 ? "PM" : "AM";
		const hour12 = hour % 12 === 0 ? 12 : hour % 12;
		return `${hour12}:${String(minute).padStart(2, "0")} ${suffix}`;
	}

	function fmtDateISO(date) {
		return [
			date.getFullYear(),
			String(date.getMonth() + 1).padStart(2, "0"),
			String(date.getDate()).padStart(2, "0")
		].join("-");
	}

	function sameDay(a, b) {
		return a instanceof Date && b instanceof Date && a.toDateString() === b.toDateString();
	}

	function makeIdempotencyKey() {
		if (window.crypto?.randomUUID) {
			return crypto.randomUUID();
		}

		return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (char) => {
			const random = (Math.random() * 16) | 0;
			const value = char === "x" ? random : (random & 0x3) | 0x8;
			return value.toString(16);
		});
	}

	/***
	 * Accepted formats: 9876543210, 09876543210, 919876543210, +919876543210
	 * Indian mobile numbers must begin with 6, 7, 8 or 9.
	 */
	function validateIndianMobileNumber(rawValue) {
		const compact = rawValue.trim().replace(/[\s().-]/g, "");
		const match = compact.match(/^(?:\+91|91|0)?([6-9]\d{9})$/);

		if (!match) {
			return null;
		}

		const nationalNumber = match[1];

		return {
			national: nationalNumber,
			international: `+91${nationalNumber}`
		};
	}

	function buildWhatsappUrl(intro) {
		const lines = [intro];

		if (state.service) {
			lines.push(`Service: ${state.service.display_name || state.service.name}`);
		}

		if (state.stylist) {
			lines.push(`Stylist: ${state.stylist.id === "any" ? "Any available" : state.stylist.name}`);
		}

		if (state.date && state.time) {
			lines.push(`When: ${state.date.toDateString()} ${to12h(state.time)}`);
		}

		if (state.phone) {
			lines.push(`Phone: +91${state.phone}`);
		}

		return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(lines.join("\n"))}`;
	}

	/* ---------------------------------------------------------
	   Mount / init
	--------------------------------------------------------- */

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

			if (!popup) {
				throw new Error("Booking popup HTML loaded, but #bookingPopup was not found.");
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

		popup.querySelector("#serviceSearch")?.addEventListener("input", () => {
			renderServiceList();
		});

		const phoneInput = popup.querySelector("#bookingPhone");

		phoneInput?.addEventListener("input", () => {
			phoneInput.value = phoneInput.value.replace(/\D/g, "").slice(0, 10);
			phoneInput.removeAttribute("aria-invalid");
			setError("phoneError", "");
		});

		popup.querySelector("#sendOtpBtn")?.addEventListener("click", handleSendOtp);
		popup.querySelector("#resendOtpBtn")?.addEventListener("click", handleResendOtp);

		popup.querySelector("#changeNumberBtn")?.addEventListener("click", () => {
			stopResendTimer();
			goTo(3);
		});

		popup.querySelector("#confirmBtn")?.addEventListener("click", handleConfirm);
		popup.querySelector("#icsBtn")?.addEventListener("click", handleAddToCalendar);

		attachEdgeAutoScroll(popup.querySelector("#categoryRow"), "x");
		attachEdgeAutoScroll(popup.querySelector("#stylistRow"), "x");
		attachEdgeAutoScroll(popup.querySelector("#dateStrip"), "x");
		attachEdgeAutoScroll(popup.querySelector("#serviceList"), "y");
	}

	/* Slow, animated auto-scroll when the cursor hovers near the start/end
	   edge of a scrollable strip or list, instead of requiring a visible
	   scrollbar or manual drag. Speed ramps up the closer the cursor is to
	   the edge, capped at a deliberately gentle pace. */
	function attachEdgeAutoScroll(container, axis) {
		if (!container) return;

		const EDGE_ZONE = 46;
		const MAX_SPEED = 2.2;
		let speed = 0;
		let rafHandle = null;

		function extent() {
			return axis === "x"
				? container.scrollWidth - container.clientWidth
				: container.scrollHeight - container.clientHeight;
		}

		function position() {
			return axis === "x" ? container.scrollLeft : container.scrollTop;
		}

		function step() {
			if (speed === 0) {
				rafHandle = null;
				return;
			}

			if (axis === "x") container.scrollLeft += speed;
			else container.scrollTop += speed;

			rafHandle = requestAnimationFrame(step);
		}

		function startIfNeeded() {
			if (rafHandle === null) {
				rafHandle = requestAnimationFrame(step);
			}
		}

		container.addEventListener("pointermove", (event) => {
			const rect = container.getBoundingClientRect();

			const distFromEnd = axis === "x" ? rect.right - event.clientX : rect.bottom - event.clientY;
			const distFromStart = axis === "x" ? event.clientX - rect.left : event.clientY - rect.top;

			const maxScroll = extent();
			const pos = position();

			if (distFromEnd < EDGE_ZONE && pos < maxScroll - 1) {
				speed = Math.max(MAX_SPEED * (1 - distFromEnd / EDGE_ZONE), 0.5);
				startIfNeeded();
			} else if (distFromStart < EDGE_ZONE && pos > 1) {
				speed = -Math.max(MAX_SPEED * (1 - distFromStart / EDGE_ZONE), 0.5);
				startIfNeeded();
			} else {
				speed = 0;
			}
		});

		container.addEventListener("pointerleave", () => {
			speed = 0;
		});
	}

	/* ---------------------------------------------------------
	   Triggers / open / close
	--------------------------------------------------------- */

	function handleBookingTrigger(event) {
		const trigger = event.target.closest(".js-book, .card-book-btn, [data-booking-trigger]");

		if (!trigger) {
			return;
		}

		event.preventDefault();
		openBooking(trigger.dataset.book || "");
	}

	function openBooking(preselectedService = "") {
		if (!popup) {
			return;
		}

		previouslyFocused = document.activeElement;
		resetPopup();

		if (BOOKING_TEMPORARILY_DISABLED) {
			showBookingNotice();
		} else {
			state.pendingPreselect = preselectedService;
			goTo(1);
			renderServiceStep();
		}

		popup.classList.add("active");
		popup.setAttribute("aria-hidden", "false");
		document.body.classList.add("booking-open");

		requestAnimationFrame(() => {
			popup.querySelector(".popup-container")?.focus();
		});
	}

	function showBookingNotice() {
		popup.querySelectorAll(".popup-step").forEach((section) => {
			section.classList.toggle("active", section.dataset.step === "notice");
		});

		const progressBar = popup.querySelector("#progressBar");
		if (progressBar) progressBar.style.width = "0%";

		const summary = popup.querySelector("#miniSummary");
		if (summary) summary.innerHTML = "";

		const fallback = popup.querySelector("#popupFallback");
		if (fallback) fallback.style.display = "none";

		const whatsappBtn = popup.querySelector("#noticeWhatsappBtn");
		if (whatsappBtn) {
			whatsappBtn.href = buildWhatsappUrl("Hi Aarvella, I'd like to book an appointment once you're open.");
		}
	}

	function closeBooking() {
		if (!popup) {
			return;
		}

		stopResendTimer();
		popup.classList.remove("active");
		popup.setAttribute("aria-hidden", "true");
		document.body.classList.remove("booking-open");

		if (previouslyFocused instanceof HTMLElement) {
			previouslyFocused.focus();
		}
	}

	function resetPopup() {
		state.category = null;
		state.service = null;
		state.stylist = null;
		state.dates = null;
		state.date = null;
		state.time = null;
		state.slotStylistId = null;
		state.phone = "";
		state.token = null;
		state.idempotencyKey = null;

		stopResendTimer();

		const searchInput = popup.querySelector("#serviceSearch");
		if (searchInput) searchInput.value = "";

		const phoneInput = popup.querySelector("#bookingPhone");
		if (phoneInput) {
			phoneInput.value = "";
			phoneInput.removeAttribute("aria-invalid");
		}

		["categoryRow", "serviceList", "stylistRow", "dateStrip", "slotGrid", "otpRow", "reviewCard", "successCard"].forEach((id) => {
			const el = popup.querySelector(`#${id}`);
			if (el) el.innerHTML = "";
		});

		["bookingError", "slotError", "phoneError", "otpError", "confirmError"].forEach((id) => setError(id, ""));

		const primaryBtn = popup.querySelector('.popup-step[data-step="1"] [data-booking-next]');
		if (primaryBtn) primaryBtn.disabled = true;

		const step2NextBtn = popup.querySelector('.popup-step[data-step="2"] [data-booking-next]');
		if (step2NextBtn) step2NextBtn.disabled = true;
	}

	/* ---------------------------------------------------------
	   Step navigation
	--------------------------------------------------------- */

	function goTo(step) {
		state.step = step;

		popup.querySelectorAll(".popup-step").forEach((section) => {
			section.classList.toggle("active", Number(section.dataset.step) === step);
		});

		const progressBar = popup.querySelector("#progressBar");
		if (progressBar) {
			progressBar.style.width = `${Math.min(step, 5) / 5 * 100}%`;
		}

		const container = popup.querySelector(".popup-container");
		if (container) container.scrollTop = 0;

		const fallback = popup.querySelector("#popupFallback");
		if (fallback) fallback.style.display = step >= 6 ? "none" : "block";

		updateMiniSummary();
		updateFallbackLink();

		if (step === 2) {
			ensureDateStrip();
			ensureStylists();
			loadSlots();
		}

		if (step === 5) {
			renderReview();
		}
	}

	function nextStep() {
		if (state.step === 1) {
			if (!state.service) {
				setError("bookingError", "Please select a service.");
				return;
			}

			goTo(2);
			return;
		}

		if (state.step === 2) {
			if (!state.time) {
				setError("slotError", "Please choose a time.");
				return;
			}

			goTo(3);
		}
	}

	function previousStep() {
		if (state.step > 1 && state.step <= 5) {
			goTo(state.step - 1);
		}
	}

	function updateMiniSummary() {
		const summary = popup.querySelector("#miniSummary");
		if (!summary) return;

		const chips = [];

		if (state.service) {
			chips.push(state.service.display_name || state.service.name);
		}

		if (state.time) {
			chips.push(to12h(state.time));
		}

		summary.innerHTML = chips.map((chip) => `<span class="mini-chip show">${escapeHtml(chip)}</span>`).join("");
	}

	function updateFallbackLink() {
		const link = popup.querySelector("#waFallbackLink");
		if (link) link.href = buildWhatsappUrl("Hi Aarvella, I want to book an appointment.");
	}

	function setError(id, message) {
		const element = popup.querySelector(`#${id}`);
		if (element) element.textContent = message;
	}

	/* ---------------------------------------------------------
	   Step 1: service catalogue
	--------------------------------------------------------- */

	function ensureCatalogue() {
		if (!cataloguePromise) {
			cataloguePromise = Api.getServices()
				.then((res) => {
					const services = Array.isArray(res?.data) ? res.data : [];
					const categoriesMap = new Map();

					services.forEach((service) => {
						const category = service.category;
						if (category && !categoriesMap.has(category.id)) {
							categoriesMap.set(category.id, category.name);
						}
					});

					return {
						services,
						/* Sorted by category id rather than left in API/services
						   response order, so category order is deterministic
						   and matches the order used elsewhere on the site. */
						categories: [...categoriesMap.entries()]
							.map(([id, name]) => ({ id, name }))
							.sort((a, b) => a.id - b.id)
					};
				})
				.catch((error) => {
					cataloguePromise = null;
					throw error;
				});
		}

		return cataloguePromise;
	}

	function renderServiceStep() {
		const serviceList = popup.querySelector("#serviceList");
		if (serviceList) serviceList.innerHTML = '<p class="popup-hint">Loading services…</p>';

		ensureCatalogue()
			.then((catalogue) => {
				state.catalogue = catalogue;

				if (state.pendingPreselect) {
					applyPreselect(catalogue, state.pendingPreselect);
					state.pendingPreselect = "";
				}

				if (!state.category && catalogue.categories.length) {
					state.category = catalogue.categories[0];
				}

				renderCategoryRow();
				renderServiceList();

				if (state.service) {
					const primaryBtn = popup.querySelector('.popup-step[data-step="1"] [data-booking-next]');
					if (primaryBtn) primaryBtn.disabled = false;
				}
			})
			.catch(() => {
				if (serviceList) serviceList.innerHTML = "";
				setError("bookingError", "We couldn't load the service menu — please try again in a moment, or message us on WhatsApp below.");
			});
	}

	function applyPreselect(catalogue, term) {
		const needle = term.toLowerCase();

		const matchedService = catalogue.services.find((service) =>
			(service.display_name || service.name).toLowerCase().includes(needle)
		);

		if (matchedService) {
			state.service = matchedService;
			state.category = catalogue.categories.find((category) => category.id === matchedService.category?.id) || null;
			return;
		}

		const matchedCategory = catalogue.categories.find((category) => category.name.toLowerCase().includes(needle));
		if (matchedCategory) {
			state.category = matchedCategory;
		}
	}

	function renderCategoryRow() {
		const row = popup.querySelector("#categoryRow");
		if (!row || !state.catalogue) return;

		row.innerHTML = state.catalogue.categories
			.map((category) => `
				<button type="button" class="category-chip${category.id === state.category?.id ? " selected" : ""}" data-category-id="${category.id}" role="tab" aria-selected="${category.id === state.category?.id}">
					${escapeHtml(category.name)}
				</button>
			`)
			.join("");
	}

	function selectCategory(categoryId) {
		const category = state.catalogue?.categories.find((c) => c.id === categoryId);
		if (!category) return;

		state.category = category;

		const searchInput = popup.querySelector("#serviceSearch");
		if (searchInput) searchInput.value = "";

		renderCategoryRow();
		renderServiceList();
	}

	function renderServiceList() {
		const listEl = popup.querySelector("#serviceList");
		const categoryRow = popup.querySelector("#categoryRow");
		if (!listEl || !state.catalogue) return;

		const searchTerm = popup.querySelector("#serviceSearch")?.value.trim().toLowerCase() || "";
		let services = state.catalogue.services;

		if (searchTerm) {
			services = services.filter((service) => (service.display_name || service.name).toLowerCase().includes(searchTerm));
		} else if (state.category) {
			services = services.filter((service) => service.category?.id === state.category.id);
		}

		if (categoryRow) categoryRow.style.display = searchTerm ? "none" : "flex";

		if (!services.length) {
			listEl.innerHTML = '<p class="popup-hint">No services found.</p>';
			return;
		}

		listEl.innerHTML = services
			.map((service) => `
				<button type="button" class="service-row${state.service?.id === service.id ? " selected" : ""}" data-service-id="${service.id}" role="option" aria-selected="${state.service?.id === service.id}">
					<span class="service-row-name">${escapeHtml(service.display_name || service.name)}</span>
					<span class="service-row-meta">${service.duration_minutes}m · ${formatPrice(service.price)}</span>
				</button>
			`)
			.join("");
	}

	function selectServiceRow(serviceId) {
		const service = state.catalogue?.services.find((s) => s.id === serviceId);
		if (!service) return;

		state.service = service;
		state.stylist = null;
		state.time = null;
		state.slotStylistId = null;

		renderServiceList();
		setError("bookingError", "");

		const primaryBtn = popup.querySelector('.popup-step[data-step="1"] [data-booking-next]');
		if (primaryBtn) primaryBtn.disabled = false;
	}

	/* ---------------------------------------------------------
	   Step 2: stylist, date, slot
	--------------------------------------------------------- */

	function ensureStylists() {
		const anyOption = { id: "any", name: "Any Available" };

		if (!state.stylist) state.stylist = anyOption;

		renderStylistRow([anyOption]);

		if (!state.service) return;

		if (stylistsCache.has(state.service.id)) {
			renderStylistRow([anyOption, ...stylistsCache.get(state.service.id)]);
			return;
		}

		Api.getStylists(state.service.id)
			.then((res) => {
				const list = Array.isArray(res?.data) ? res.data : [];
				stylistsCache.set(state.service.id, list);
				renderStylistRow([anyOption, ...list]);
			})
			.catch(() => { /* non-fatal — "Any Available" still works */ });
	}

	function renderStylistRow(list) {
		const row = popup.querySelector("#stylistRow");
		if (!row) return;

		row.innerHTML = list
			.map((stylist) => {
				const label = stylist.id === "any" ? "Any" : stylist.name.split(" ")[0];
				const initials = stylist.id === "any" ? "★" : stylist.name.split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase();
				const selected = state.stylist?.id === stylist.id;

				return `
					<button type="button" class="stylist-chip${selected ? " selected" : ""}" data-stylist-id="${stylist.id}">
						<span class="avatar">${initials}</span>
						<span>${escapeHtml(label)}</span>
					</button>
				`;
			})
			.join("");
	}

	function selectStylistChip(stylistId) {
		if (stylistId === "any") {
			state.stylist = { id: "any", name: "Any Available" };
		} else {
			const list = stylistsCache.get(state.service.id) || [];
			state.stylist = list.find((s) => String(s.id) === String(stylistId)) || { id: stylistId, name: "Selected pro" };
		}

		state.time = null;
		state.slotStylistId = null;

		popup.querySelectorAll(".stylist-chip").forEach((chip) => {
			chip.classList.toggle("selected", chip.dataset.stylistId === String(stylistId));
		});

		const step2NextBtn = popup.querySelector('.popup-step[data-step="2"] [data-booking-next]');
		if (step2NextBtn) step2NextBtn.disabled = true;

		loadSlots();
	}

	function ensureDateStrip() {
		if (!state.dates) {
			const base = new Date();
			base.setHours(0, 0, 0, 0);

			state.dates = Array.from({ length: 7 }, (_, i) => {
				const d = new Date(base);
				d.setDate(d.getDate() + i);
				return d;
			});

			state.date = state.dates[0];
		}

		renderDateStrip();
	}

	function renderDateStrip() {
		const strip = popup.querySelector("#dateStrip");
		if (!strip || !state.dates) return;

		strip.innerHTML = state.dates
			.map((date, index) => `
				<button type="button" class="date-chip${sameDay(date, state.date) ? " selected" : ""}" data-date-index="${index}">
					<span class="dow">${index === 0 ? "Today" : date.toLocaleDateString("en-IN", { weekday: "short" })}</span>
					<span class="dom">${date.getDate()}</span>
				</button>
			`)
			.join("");
	}

	function selectDateChip(index) {
		const date = state.dates?.[index];
		if (!date) return;

		state.date = date;
		state.time = null;
		state.slotStylistId = null;

		renderDateStrip();

		const step2NextBtn = popup.querySelector('.popup-step[data-step="2"] [data-booking-next]');
		if (step2NextBtn) step2NextBtn.disabled = true;

		loadSlots();
	}

	/* When no specific stylist is chosen ("Any Available"), the CRM returns
	   one slot per stylist who is free at that time, so the same time can
	   appear several times over. Collapse those down to one entry per time —
	   preferring an available one if only some of the duplicates are booked. */
	function dedupeSlotsByTime(slots) {
		const byTime = new Map();

		slots.forEach((slot) => {
			const existing = byTime.get(slot.time);
			if (!existing || (existing.available === false && slot.available !== false)) {
				byTime.set(slot.time, slot);
			}
		});

		return [...byTime.values()].sort((a, b) => a.time.localeCompare(b.time));
	}

	async function loadSlots() {
		const grid = popup.querySelector("#slotGrid");
		const hint = popup.querySelector("#slotHint");
		if (!grid || !state.service || !state.date) return;

		setError("slotError", "");
		if (hint) hint.textContent = "";

		grid.innerHTML = Array.from({ length: 6 }, () => '<div class="slot-skeleton"></div>').join("");

		try {
			const raw = await Api.getAvailability({
				serviceId: state.service.id,
				stylistId: state.stylist && state.stylist.id !== "any" ? state.stylist.id : null,
				dateStr: fmtDateISO(state.date)
			});

			const slots = dedupeSlotsByTime(normalizeSlots(raw));

			if (slots.length) {
				renderSlots(slots);
			} else {
				renderSlots(FALLBACK_SLOT_TIMES.map((time) => ({ time, stylistId: null, available: true })));
				if (hint) hint.textContent = "Showing typical hours — we'll confirm your exact slot.";
			}
		} catch (error) {
			renderSlots(FALLBACK_SLOT_TIMES.map((time) => ({ time, stylistId: null, available: true })));
			if (hint) hint.textContent = "Live slot checking is coming online shortly — pick a time and we'll confirm it with you.";
		}
	}

	function renderSlots(slots) {
		const grid = popup.querySelector("#slotGrid");
		if (!grid) return;

		grid.innerHTML = slots
			.map((slot) => `
				<button type="button" class="slot-btn${state.time === slot.time ? " selected" : ""}" data-time="${slot.time}" data-stylist-id="${slot.stylistId ?? ""}" ${slot.available === false ? "disabled" : ""}>
					${to12h(slot.time)}
				</button>
			`)
			.join("");
	}

	function selectSlotBtn(button) {
		state.time = button.dataset.time;
		state.slotStylistId = button.dataset.stylistId || null;

		popup.querySelectorAll(".slot-btn").forEach((btn) => btn.classList.toggle("selected", btn === button));

		const step2NextBtn = popup.querySelector('.popup-step[data-step="2"] [data-booking-next]');
		if (step2NextBtn) step2NextBtn.disabled = false;

		setError("slotError", "");
	}

	/* ---------------------------------------------------------
	   Step 3 + 4: phone + OTP
	--------------------------------------------------------- */

	async function handleSendOtp() {
		const phoneInput = popup.querySelector("#bookingPhone");
		const sendBtn = popup.querySelector("#sendOtpBtn");
		const raw = phoneInput?.value.trim() || "";
		const validated = validateIndianMobileNumber(raw);

		if (!validated) {
			setError("phoneError", "Enter a valid 10-digit Indian mobile number.");
			phoneInput?.setAttribute("aria-invalid", "true");
			phoneInput?.focus();
			return;
		}

		state.phone = validated.national;
		setError("phoneError", "");

		if (sendBtn) {
			sendBtn.disabled = true;
			sendBtn.querySelector(".btn-text").textContent = "Sending…";
		}

		try {
			await Api.requestOtp(state.phone);

			const sentTo = popup.querySelector("#otpSentTo");
			if (sentTo) sentTo.textContent = `Code sent to +91 ${state.phone.slice(0, 5)} ${state.phone.slice(5)}`;

			buildOtpBoxes();
			goTo(4);
			startResendTimer();
		} catch (error) {
			setError("phoneError", error.status === 422 ? (error.message || "Please check the number and try again.") : "Couldn't send the code — please try again in a moment.");
		} finally {
			if (sendBtn) {
				sendBtn.disabled = false;
				sendBtn.querySelector(".btn-text").textContent = "Send code";
			}
		}
	}

	function buildOtpBoxes() {
		const row = popup.querySelector("#otpRow");
		if (!row) return;

		row.innerHTML = "";

		for (let i = 0; i < 6; i++) {
			const input = document.createElement("input");
			input.className = "otp-box";
			input.inputMode = "numeric";
			input.maxLength = 1;
			input.autocomplete = i === 0 ? "one-time-code" : "off";

			input.addEventListener("input", (event) => {
				event.target.value = event.target.value.replace(/\D/g, "").slice(0, 1);
				if (event.target.value && event.target.nextElementSibling) {
					event.target.nextElementSibling.focus();
				}
				checkOtpFilled();
			});

			input.addEventListener("keydown", (event) => {
				if (event.key === "Backspace" && !event.target.value && event.target.previousElementSibling) {
					event.target.previousElementSibling.focus();
				}
			});

			input.addEventListener("paste", (event) => {
				event.preventDefault();
				const digits = (event.clipboardData.getData("text") || "").replace(/\D/g, "").slice(0, 6).split("");
				const boxes = [...row.children];
				digits.forEach((digit, index) => { if (boxes[index]) boxes[index].value = digit; });
				boxes[digits.length - 1]?.focus();
				checkOtpFilled();
			});

			row.appendChild(input);
		}

		row.children[0]?.focus();
	}

	function checkOtpFilled() {
		const row = popup.querySelector("#otpRow");
		const code = [...row.children].map((box) => box.value).join("");

		if (code.length === 6) {
			handleVerifyOtp(code);
		}
	}

	async function handleVerifyOtp(code) {
		setError("otpError", "Verifying…");

		try {
			const res = await Api.verifyOtp(state.phone, code);

			/* Exact response key is unverified against production — the
			   docs describe a Sanctum token but no real verify call could
			   be completed from this session. Check the common shapes. */
			const token = res?.token || res?.data?.token || res?.access_token || res?.data?.access_token;

			if (!token) {
				throw Object.assign(new Error("missing token"), { status: 0 });
			}

			state.token = token;
			setError("otpError", "");
			stopResendTimer();
			goTo(5);
		} catch (error) {
			popup.querySelectorAll(".otp-box").forEach((box) => { box.value = ""; });
			popup.querySelector("#otpRow")?.children[0]?.focus();
			setError("otpError", error.status === 422 || error.status === 401 ? "That code didn't match — try again." : "Couldn't verify right now — please try again.");
		}
	}

	async function handleResendOtp() {
		const resendBtn = popup.querySelector("#resendOtpBtn");
		if (!resendBtn || resendBtn.disabled) return;

		try {
			await Api.requestOtp(state.phone);
			resendBtn.innerHTML = 'Resend in <span id="resendTimer">45</span>s';
			startResendTimer();
		} catch (error) {
			setError("otpError", "Couldn't resend the code — please try again shortly.");
		}
	}

	function startResendTimer() {
		let seconds = 45;
		const resendBtn = popup.querySelector("#resendOtpBtn");
		if (!resendBtn) return;

		resendBtn.disabled = true;
		stopResendTimer();

		resendHandle = setInterval(() => {
			seconds -= 1;
			const timerEl = popup.querySelector("#resendTimer");
			if (timerEl) timerEl.textContent = seconds;

			if (seconds <= 0) {
				stopResendTimer();
				resendBtn.innerHTML = "Resend code";
				resendBtn.disabled = false;
			}
		}, 1000);
	}

	function stopResendTimer() {
		if (resendHandle) {
			clearInterval(resendHandle);
			resendHandle = null;
		}
	}

	/* ---------------------------------------------------------
	   Step 5: review + confirm
	--------------------------------------------------------- */

	function reviewRow(key, value) {
		return `<div class="review-row"><span class="k">${key}</span><span class="v">${value}</span></div>`;
	}

	function stylistLabel() {
		return state.stylist && state.stylist.id !== "any" ? state.stylist.name : "Any available pro";
	}

	function renderReview() {
		const card = popup.querySelector("#reviewCard");
		if (!card || !state.service || !state.date || !state.time) return;

		state.idempotencyKey = makeIdempotencyKey();

		const dateLabel = state.date.toLocaleDateString("en-IN", { weekday: "short", day: "numeric", month: "short" });

		card.innerHTML = [
			reviewRow("Service", escapeHtml(state.service.display_name || state.service.name)),
			reviewRow("Stylist", escapeHtml(stylistLabel())),
			reviewRow("When", `${dateLabel} · ${to12h(state.time)}`),
			reviewRow("Phone", `+91 ${state.phone.slice(0, 5)} •••${state.phone.slice(8)}`),
			reviewRow("Est. price", formatPrice(state.service.price))
		].join("");

		setError("confirmError", "");
	}

	async function handleConfirm() {
		const confirmBtn = popup.querySelector("#confirmBtn");
		if (confirmBtn) {
			confirmBtn.disabled = true;
			confirmBtn.querySelector(".btn-text").textContent = "Booking…";
		}

		const cachedStylists = stylistsCache.get(state.service.id) || [];
		const resolvedStylistId = (state.stylist && state.stylist.id !== "any")
			? state.stylist.id
			: (state.slotStylistId || cachedStylists[0]?.id);

		if (!resolvedStylistId) {
			setError("confirmError", "We couldn't assign a stylist automatically — please pick one on the previous step.");
			if (confirmBtn) {
				confirmBtn.disabled = false;
				confirmBtn.querySelector(".btn-text").textContent = "Confirm & book";
			}
			return;
		}

		const startsAtIso = `${fmtDateISO(state.date)}T${state.time}:00${BRANCH_UTC_OFFSET}`;

		try {
			await Api.createAppointment({
				token: state.token,
				idempotencyKey: state.idempotencyKey,
				serviceId: state.service.id,
				stylistId: resolvedStylistId,
				startsAtIso
			});

			showDone(true);
		} catch (error) {
			if (error.status === 409) {
				state.time = null;
				state.slotStylistId = null;
				goTo(2);
				setError("slotError", "That slot was just taken — please choose another time.");
				loadSlots();
			} else if (error.status === 401) {
				state.token = null;
				goTo(3);
				setError("phoneError", "Your verification expired — please verify again.");
			} else if (error.status === 422) {
				setError("confirmError", error.message || "Please check your details and try again.");
			} else {
				/* Endpoint likely not deployed yet, or a network hiccup —
				   degrade to the WhatsApp hand-off instead of dead-ending. */
				showDone(false);
			}
		} finally {
			if (confirmBtn) {
				confirmBtn.disabled = false;
				confirmBtn.querySelector(".btn-text").textContent = "Confirm & book";
			}
		}
	}

	function showDone(confirmed) {
		const card = popup.querySelector("#successCard");
		const dateLabel = state.date.toLocaleDateString("en-IN", { weekday: "short", day: "numeric", month: "short" });

		if (card) {
			card.innerHTML = [
				reviewRow("Service", escapeHtml(state.service.display_name || state.service.name)),
				reviewRow("Stylist", escapeHtml(stylistLabel())),
				reviewRow("When", `${dateLabel} · ${to12h(state.time)}`)
			].join("");
		}

		const eyebrow = popup.querySelector("#successEyebrow");
		const heading = popup.querySelector("#successHeading");
		const message = popup.querySelector("#successMessage");
		const icsBtn = popup.querySelector("#icsBtn");
		const whatsappBtn = popup.querySelector("#whatsappBtn");

		if (confirmed) {
			if (eyebrow) eyebrow.textContent = "Booked";
			if (heading) heading.textContent = "You're Booked ✨";
			if (message) message.textContent = "We'll see you then. A confirmation has been saved to your booking.";
			if (icsBtn) icsBtn.style.display = "";
			if (whatsappBtn) {
				whatsappBtn.querySelector(".btn-text").textContent = "Message us on WhatsApp";
				whatsappBtn.href = buildWhatsappUrl("Hi Aarvella, just confirming my appointment:");
			}
		} else {
			if (eyebrow) eyebrow.textContent = "Request received";
			if (heading) heading.textContent = "Almost there";
			if (message) message.textContent = "We couldn't lock this in online just now — tap below and we'll confirm your slot on WhatsApp right away.";
			if (icsBtn) icsBtn.style.display = "none";
			if (whatsappBtn) {
				whatsappBtn.querySelector(".btn-text").textContent = "Confirm on WhatsApp";
				whatsappBtn.href = buildWhatsappUrl("Hi Aarvella, I want to book an appointment.");
			}
		}

		goTo(6);
	}

	function handleAddToCalendar() {
		if (!state.date || !state.time || !state.service) return;

		const [hour, minute] = state.time.split(":").map(Number);
		const start = new Date(state.date);
		start.setHours(hour, minute, 0, 0);

		const durationMinutes = state.service.duration_minutes || 45;
		const end = new Date(start.getTime() + durationMinutes * 60000);
		const stamp = (date) => date.toISOString().replace(/[-:]/g, "").split(".")[0] + "Z";

		const body = [
			"BEGIN:VCALENDAR",
			"VERSION:2.0",
			"BEGIN:VEVENT",
			`SUMMARY:Aarvella — ${state.service.display_name || state.service.name}`,
			`DTSTART:${stamp(start)}`,
			`DTEND:${stamp(end)}`,
			"END:VEVENT",
			"END:VCALENDAR"
		].join("\n");

		const link = document.createElement("a");
		link.href = `data:text/calendar;charset=utf8,${encodeURIComponent(body)}`;
		link.download = "aarvella-appointment.ics";
		link.click();
	}

	/* ---------------------------------------------------------
	   Delegated click handling
	--------------------------------------------------------- */

	function handlePopupClick(event) {
		if (event.target.closest("[data-booking-close]")) {
			closeBooking();
			return;
		}

		const categoryChip = event.target.closest(".category-chip");
		if (categoryChip) {
			selectCategory(Number(categoryChip.dataset.categoryId));
			return;
		}

		const serviceRow = event.target.closest(".service-row");
		if (serviceRow) {
			selectServiceRow(Number(serviceRow.dataset.serviceId));
			return;
		}

		const stylistChip = event.target.closest(".stylist-chip");
		if (stylistChip) {
			selectStylistChip(stylistChip.dataset.stylistId);
			return;
		}

		const dateChip = event.target.closest(".date-chip");
		if (dateChip) {
			selectDateChip(Number(dateChip.dataset.dateIndex));
			return;
		}

		const slotBtn = event.target.closest(".slot-btn");
		if (slotBtn && !slotBtn.disabled) {
			selectSlotBtn(slotBtn);
			return;
		}

		const gotoEl = event.target.closest("[data-booking-goto]");
		if (gotoEl) {
			goTo(Number(gotoEl.dataset.bookingGoto));
			return;
		}

		if (event.target.closest("[data-booking-next]")) {
			nextStep();
			return;
		}

		if (event.target.closest("[data-booking-prev]")) {
			previousStep();
		}
	}

	/* ---------------------------------------------------------
	   Keyboard / focus trap
	--------------------------------------------------------- */

	function handleKeydown(event) {
		if (!popup?.classList.contains("active")) {
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
		const focusable = [...popup.querySelectorAll(
			'button:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'
		)].filter((element) => element.offsetParent !== null);

		if (!focusable.length) {
			return;
		}

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

	/* Compatibility with existing inline calls */
	window.openBooking = openBooking;
	window.closeBooking = closeBooking;
})();
