/* ==========================================================
   Aarvella Gift Cards page
   File: assets/js/gift-cards.js

   Talks directly to the Aarvella CRM Booking/Gift-Card API
   (os.aarvella.com) from the browser, reusing the exact same
   phone-OTP -> Sanctum bearer token pattern as assets/js/booking.js
   (same API_BASE/BRANCH_ID constants, duplicated locally rather
   than shared, matching that file's own precedent).
========================================================== */

(() => {
	/* The CRM's gift-card purchase endpoint currently verifies payment
	   through a stub gateway that declines every attempt until the salon
	   deliberately enables it server-side (see StubPaymentGateway in the
	   CRM repo). This flag is a second, independent switch on the website
	   side: while false, visitors see a "launching soon" notice with a
	   WhatsApp fallback instead of the interactive purchase form at all —
	   mirroring BOOKING_TEMPORARILY_DISABLED in booking.js. Both this flag
	   and the CRM's stub_auto_approve need to be turned on (and a real
	   payment gateway wired in) before this goes live for real customers.
	*/
	const GIFT_CARDS_ONLINE_ENABLED = false;

	const WHATSAPP_NUMBER = window.AARVELLA_CONFIG?.WHATSAPP_NUMBER || "919142351661";
	const API_BASE = "https://os.aarvella.com/api/v1";
	const BRANCH_ID = 1;
	const MIN_AMOUNT = 500;
	const MAX_AMOUNT = 25000;

	const form = document.getElementById("giftForm");
	if (!form) return;

	const notice = document.getElementById("giftNotice");
	const successPanel = document.getElementById("giftSuccess");
	const amountTiles = document.getElementById("amountTiles");
	const customAmountInput = document.getElementById("customAmount");
	const amountError = document.getElementById("amountError");
	const recipientToggle = form.querySelector(".recipient-toggle");
	const recipientFields = document.getElementById("recipientFields");
	const recipientName = document.getElementById("recipientName");
	const recipientMessage = document.getElementById("recipientMessage");
	const purchaserName = document.getElementById("purchaserName");
	const purchaserPhone = document.getElementById("purchaserPhone");
	const phoneError = document.getElementById("giftPhoneError");
	const sendOtpBtn = document.getElementById("giftSendOtpBtn");
	const otpCode = document.getElementById("otpCode");
	const otpSentTo = document.getElementById("giftOtpSentTo");
	const otpError = document.getElementById("giftOtpError");
	const otpVerifiedChip = document.getElementById("otpVerifiedChip");
	const resendOtpBtn = document.getElementById("giftResendOtpBtn");
	const payBtn = document.getElementById("payBtn");
	const payError = document.getElementById("payError");
	const mockAmount = document.getElementById("mockAmount");
	const mockRecipient = document.getElementById("mockRecipient");

	const state = {
		amount: 1000,
		recipient: "self",
		phone: "",
		token: null,
		verified: false
	};

	let resendHandle = null;

	/* ---------------------------------------------------------
	   CRM API client — same shape as booking.js's Api object
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
		requestOtp: (phone) => apiFetch("/auth/otp/request", { method: "POST", body: JSON.stringify({ phone }) }),
		verifyOtp: (phone, code) => apiFetch("/auth/otp/verify", { method: "POST", body: JSON.stringify({ phone, code }) }),
		purchaseGiftCard: ({ token, idempotencyKey, amount }) =>
			apiFetch("/gift-cards", {
				method: "POST",
				headers: { Authorization: `Bearer ${token}`, "Idempotency-Key": idempotencyKey },
				body: JSON.stringify({
					branch_id: BRANCH_ID,
					amount,
					payment: {}
				})
			})
	};

	/* ---------------------------------------------------------
	   Utilities (duplicated from booking.js's own local copies)
	--------------------------------------------------------- */

	function validateIndianMobileNumber(rawValue) {
		const compact = rawValue.trim().replace(/[\s().-]/g, "");
		const match = compact.match(/^(?:\+91|91|0)?([6-9]\d{9})$/);

		if (!match) return null;

		const nationalNumber = match[1];
		return { national: nationalNumber, international: `+91${nationalNumber}` };
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

	function formatInr(amount) {
		return `₹${Number(amount).toLocaleString("en-IN")}`;
	}

	function buildSalonWhatsappUrl(intro) {
		return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(intro)}`;
	}

	function setError(element, message) {
		if (element) element.textContent = message || "";
	}

	/* ---------------------------------------------------------
	   Live card preview
	--------------------------------------------------------- */

	function updatePreview() {
		if (mockAmount) mockAmount.textContent = formatInr(state.amount);

		if (mockRecipient) {
			const name = recipientName?.value.trim();
			mockRecipient.textContent = state.recipient === "gift" && name ? name : "You";
		}
	}

	/* ---------------------------------------------------------
	   Amount selection
	--------------------------------------------------------- */

	amountTiles?.addEventListener("click", (event) => {
		const tile = event.target.closest(".amount-tile");
		if (!tile) return;

		state.amount = Number(tile.dataset.amount);
		if (customAmountInput) customAmountInput.value = "";

		amountTiles.querySelectorAll(".amount-tile").forEach((el) => el.classList.toggle("selected", el === tile));
		setError(amountError, "");
		updatePreview();
	});

	customAmountInput?.addEventListener("input", () => {
		const value = Number(customAmountInput.value);

		if (customAmountInput.value.trim() === "") return;

		amountTiles?.querySelectorAll(".amount-tile").forEach((el) => el.classList.remove("selected"));

		if (!value || value < MIN_AMOUNT || value > MAX_AMOUNT) {
			setError(amountError, `Enter an amount between ${formatInr(MIN_AMOUNT)} and ${formatInr(MAX_AMOUNT)}.`);
			return;
		}

		setError(amountError, "");
		state.amount = value;
		updatePreview();
	});

	/* ---------------------------------------------------------
	   Recipient toggle
	--------------------------------------------------------- */

	recipientToggle?.addEventListener("click", (event) => {
		const button = event.target.closest("button[data-recipient]");
		if (!button) return;

		state.recipient = button.dataset.recipient;

		recipientToggle.querySelectorAll("button").forEach((el) => el.classList.toggle("selected", el === button));

		if (recipientFields) recipientFields.hidden = state.recipient !== "gift";

		updatePreview();
	});

	recipientName?.addEventListener("input", updatePreview);

	/* ---------------------------------------------------------
	   Phone + OTP
	--------------------------------------------------------- */

	purchaserPhone?.addEventListener("input", () => {
		purchaserPhone.value = purchaserPhone.value.replace(/\D/g, "").slice(0, 10);
		setError(phoneError, "");

		if (state.verified) {
			state.verified = false;
			state.token = null;
			updatePayButton();
		}
	});

	function goToStep(name) {
		form.querySelectorAll(".gift-step").forEach((step) => {
			step.classList.toggle("active", step.dataset.step === name);
		});
	}

	form.querySelectorAll("[data-gift-goto]").forEach((button) => {
		button.addEventListener("click", () => goToStep(button.dataset.giftGoto));
	});

	function updatePayButton() {
		if (!payBtn) return;

		const label = payBtn.querySelector(".btn-text");

		if (state.verified) {
			payBtn.disabled = false;
			if (label) label.textContent = `Pay ${formatInr(state.amount)}`;
		} else {
			payBtn.disabled = true;
			if (label) label.textContent = "Verify your number to continue";
		}
	}

	async function handleSendOtp() {
		const raw = purchaserPhone?.value.trim() || "";
		const validated = validateIndianMobileNumber(raw);

		if (!validated) {
			setError(phoneError, "Enter a valid 10-digit Indian mobile number.");
			purchaserPhone?.focus();
			return;
		}

		if (state.amount < MIN_AMOUNT || state.amount > MAX_AMOUNT) {
			setError(amountError, `Enter an amount between ${formatInr(MIN_AMOUNT)} and ${formatInr(MAX_AMOUNT)}.`);
			return;
		}

		state.phone = validated.national;
		setError(phoneError, "");

		const label = sendOtpBtn?.querySelector(".btn-text");
		if (sendOtpBtn) sendOtpBtn.disabled = true;
		if (label) label.textContent = "Sending…";

		try {
			await Api.requestOtp(state.phone);

			if (otpSentTo) otpSentTo.textContent = `Code sent to +91 ${state.phone.slice(0, 5)} ${state.phone.slice(5)}`;
			if (otpCode) otpCode.value = "";
			if (otpVerifiedChip) otpVerifiedChip.hidden = true;
			setError(otpError, "");

			goToStep("otp");
			otpCode?.focus();
			startResendTimer();
		} catch (error) {
			setError(phoneError, error.status === 422 ? (error.message || "Please check the number and try again.") : "Couldn't send the code — please try again in a moment.");
		} finally {
			if (sendOtpBtn) sendOtpBtn.disabled = false;
			if (label) label.textContent = "Send code";
		}
	}

	sendOtpBtn?.addEventListener("click", handleSendOtp);

	otpCode?.addEventListener("input", () => {
		otpCode.value = otpCode.value.replace(/\D/g, "").slice(0, 6);

		if (otpCode.value.length === 6) {
			handleVerifyOtp(otpCode.value);
		}
	});

	async function handleVerifyOtp(code) {
		setError(otpError, "Verifying…");

		try {
			const res = await Api.verifyOtp(state.phone, code);
			const token = res?.token || res?.data?.token || res?.access_token || res?.data?.access_token;

			if (!token) {
				throw Object.assign(new Error("missing token"), { status: 0 });
			}

			state.token = token;
			state.verified = true;
			setError(otpError, "");
			stopResendTimer();

			if (otpVerifiedChip) otpVerifiedChip.hidden = false;
			updatePayButton();

			window.setTimeout(() => goToStep("form"), 500);
		} catch (error) {
			otpCode.value = "";
			otpCode.focus();
			setError(otpError, error.status === 422 || error.status === 401 ? "That code didn't match — try again." : "Couldn't verify right now — please try again.");
		}
	}

	resendOtpBtn?.addEventListener("click", async () => {
		if (!resendOtpBtn || resendOtpBtn.disabled) return;

		try {
			await Api.requestOtp(state.phone);
			resendOtpBtn.innerHTML = '<span class="btn-text">Resend in <span id="giftResendTimer">45</span>s</span>';
			startResendTimer();
		} catch (error) {
			setError(otpError, "Couldn't resend the code — please try again shortly.");
		}
	});

	function startResendTimer() {
		let seconds = 45;
		if (!resendOtpBtn) return;

		resendOtpBtn.disabled = true;
		stopResendTimer();

		resendHandle = setInterval(() => {
			seconds -= 1;
			const timerEl = document.getElementById("giftResendTimer");
			if (timerEl) timerEl.textContent = seconds;

			if (seconds <= 0) {
				stopResendTimer();
				resendOtpBtn.innerHTML = '<span class="btn-text">Resend code</span>';
				resendOtpBtn.disabled = false;
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
	   Purchase
	--------------------------------------------------------- */

	function buildGiftMessage(code) {
		const lines = [`I've sent you an Aarvella gift card worth ${formatInr(state.amount)}!`];
		lines.push(`Code: ${code}`);
		lines.push("Show this at Aarvella Unisex Salon (Dehradun) reception to redeem.");

		const message = recipientMessage?.value.trim();
		if (message) lines.push(`\n"${message}"`);

		return lines.join("\n");
	}

	function showSuccess(code) {
		form.hidden = true;
		if (successPanel) successPanel.hidden = false;

		const amountLine = document.getElementById("successAmountLine");
		const codeEl = document.getElementById("successCode");
		if (amountLine) amountLine.textContent = `${formatInr(state.amount)} gift card`;
		if (codeEl) codeEl.textContent = code;

		const copyBtn = document.getElementById("copyCodeBtn");
		copyBtn?.addEventListener("click", () => {
			navigator.clipboard?.writeText(code);
			const label = copyBtn.querySelector(".btn-text");
			if (label) {
				label.textContent = "Copied!";
				window.setTimeout(() => { label.textContent = "Copy Code"; }, 1800);
			}
		});

		const shareBtn = document.getElementById("shareWhatsappBtn");
		if (shareBtn) {
			shareBtn.href = `https://wa.me/?text=${encodeURIComponent(buildGiftMessage(code))}`;
		}
	}

	form.addEventListener("submit", async (event) => {
		event.preventDefault();

		if (!state.verified || !state.token) {
			setError(payError, "Please verify your phone number first.");
			return;
		}

		const label = payBtn.querySelector(".btn-text");
		payBtn.disabled = true;
		if (label) label.textContent = "Processing…";
		setError(payError, "");

		try {
			const res = await Api.purchaseGiftCard({
				token: state.token,
				idempotencyKey: makeIdempotencyKey(),
				amount: state.amount
			});

			const code = res?.data?.code || res?.code;

			if (!code) {
				throw Object.assign(new Error("missing code"), { status: 0 });
			}

			showSuccess(code);
		} catch (error) {
			if (error.status === 402) {
				/* Payment gateway declined (expected default state while the
				   real gateway isn't live yet) — graceful WhatsApp hand-off
				   rather than a dead end, same pattern as booking.js. */
				setError(payError, error.message || "Online payment isn't available yet — please buy your gift card on WhatsApp instead.");
				const ctaBtn = document.getElementById("giftNoticeWhatsappBtn");
				if (ctaBtn) {
					ctaBtn.href = buildSalonWhatsappUrl(`Hi Aarvella, I'd like to buy a ${formatInr(state.amount)} gift card.`);
				}
			} else if (error.status === 422) {
				setError(payError, error.message || "Please check your details and try again.");
			} else {
				setError(payError, "Something went wrong — please try again, or message us on WhatsApp.");
			}
		} finally {
			payBtn.disabled = !state.verified;
			updatePayButton();
		}
	});

	/* ---------------------------------------------------------
	   WhatsApp fallback links + init
	--------------------------------------------------------- */

	function wireWhatsappCtas() {
		const intro = "Hi Aarvella, I'd like to buy a gift card.";

		["giftNoticeWhatsappBtn", "ctaWhatsappBtn"].forEach((id) => {
			const link = document.getElementById(id);
			if (link) link.href = buildSalonWhatsappUrl(intro);
		});
	}

	function init() {
		wireWhatsappCtas();
		updatePreview();
		updatePayButton();

		if (!GIFT_CARDS_ONLINE_ENABLED) {
			if (notice) notice.hidden = false;
			form.hidden = true;
		}
	}

	init();
})();
