const AI_API_URL = "https://aarvella-site.vercel.app/api/ai-stylist";
const WHATSAPP_NUMBER = window.AARVELLA_CONFIG?.WHATSAPP_NUMBER || "919742049990";

const chatButton = document.getElementById("ai-chat-button");
const chatWidget = document.getElementById("ai-chat-widget");
const chatClose = document.getElementById("ai-chat-close");
const chatForm = document.getElementById("ai-chat-form");
const chatInput = document.getElementById("ai-chat-input");
const chatMessages = document.getElementById("ai-chat-messages");

const conversation = [];
const root = document.documentElement;
const mobileOrTablet = window.matchMedia("(max-width: 1024px)");

let viewportFrame = 0;

function scrollMessagesToBottom() {
  if (!chatMessages) return;

  chatMessages.scrollTop = chatMessages.scrollHeight;
}

/*
 * On iPhone and some Android browsers, the on-screen keyboard changes
 * the visual viewport without changing the normal page viewport.
 *
 * These CSS variables allow the chat window to remain inside the
 * actually visible portion of the screen.
 */
function syncVisibleViewport() {
  viewportFrame = 0;

  if (!chatWidget || chatWidget.hidden || !mobileOrTablet.matches) {
    return;
  }

  const viewport = window.visualViewport;

  const visibleHeight = viewport
    ? viewport.height
    : window.innerHeight;

  const visibleTop = viewport
    ? viewport.offsetTop
    : 0;

  root.style.setProperty(
    "--ai-viewport-height",
    `${Math.max(1, Math.round(visibleHeight))}px`
  );

  root.style.setProperty(
    "--ai-viewport-top",
    `${Math.max(0, Math.round(visibleTop))}px`
  );

  const keyboardHeight = Math.max(
    0,
    window.innerHeight - visibleHeight
  );

  document.body.classList.toggle(
    "ai-keyboard-open",
    keyboardHeight > 120
  );

  requestAnimationFrame(scrollMessagesToBottom);
}

function scheduleViewportSync() {
  if (viewportFrame) {
    cancelAnimationFrame(viewportFrame);
  }

  viewportFrame = requestAnimationFrame(syncVisibleViewport);
}

function clearViewportState() {
  root.style.removeProperty("--ai-viewport-height");
  root.style.removeProperty("--ai-viewport-top");

  document.body.classList.remove("ai-keyboard-open");
}

function openChat() {
  if (!chatWidget || !chatInput) return;

  chatWidget.hidden = false;

  document.body.classList.add("ai-chat-open");

  chatButton?.setAttribute("aria-expanded", "true");

  syncVisibleViewport();

  requestAnimationFrame(() => {
    scrollMessagesToBottom();

    try {
      chatInput.focus({
        preventScroll: true
      });
    } catch (error) {
      chatInput.focus();
    }

    /*
     * Run viewport detection again while the mobile keyboard
     * is opening and resizing the visible viewport.
     */
    window.setTimeout(syncVisibleViewport, 60);
    window.setTimeout(syncVisibleViewport, 300);
  });
}

function closeChat() {
  if (!chatWidget) return;

  chatInput?.blur();

  chatWidget.hidden = true;

  document.body.classList.remove("ai-chat-open");

  chatButton?.setAttribute("aria-expanded", "false");

  clearViewportState();
  updateAiButtonVisibility();
}

chatButton?.setAttribute("aria-expanded", "false");
chatButton?.setAttribute("aria-controls", "ai-chat-widget");

chatClose?.setAttribute(
  "aria-label",
  "Close AI Stylist"
);

chatButton?.addEventListener("click", openChat);
chatClose?.addEventListener("click", closeChat);

/*
 * Allow the Escape key to close the chat on desktop
 * and external keyboards.
 */
document.addEventListener("keydown", (event) => {
  if (
    event.key === "Escape" &&
    chatWidget &&
    !chatWidget.hidden
  ) {
    closeChat();
  }
});

/*
 * Track the real visible viewport whenever the mobile
 * keyboard opens, closes, or changes size.
 */
if (window.visualViewport) {
  window.visualViewport.addEventListener(
    "resize",
    scheduleViewportSync
  );

  window.visualViewport.addEventListener(
    "scroll",
    scheduleViewportSync
  );
}

window.addEventListener(
  "resize",
  scheduleViewportSync
);

window.addEventListener(
  "orientationchange",
  () => {
    window.setTimeout(syncVisibleViewport, 150);
  }
);

chatInput?.addEventListener("focus", () => {
  window.setTimeout(syncVisibleViewport, 60);
  window.setTimeout(syncVisibleViewport, 300);
});

chatInput?.addEventListener("blur", () => {
  window.setTimeout(syncVisibleViewport, 100);
});

function addMessage(type, text) {
  if (!chatMessages) return null;

  const message = document.createElement("div");

  message.className = `ai-message ${type}`;
  message.textContent = text;

  chatMessages.appendChild(message);

  scrollMessagesToBottom();

  return message;
}

function shouldShowBookingButton(text) {
  const keywords = [
    "book",
    "appointment",
    "preferred",
    "confirm",
    "whatsapp"
  ];

  return keywords.some((word) =>
    text.toLowerCase().includes(word)
  );
}

function addWhatsAppButton() {
  if (!chatMessages) return;

  /*
   * Prevent multiple WhatsApp buttons from being added.
   */
  const existingButton = chatMessages.querySelector(
    ".ai-whatsapp-button"
  );

  if (existingButton) {
    existingButton.remove();
  }

  const button = document.createElement("button");

  button.type = "button";
  button.textContent = "Continue Booking on WhatsApp";
  button.className = "ai-whatsapp-button";

  button.addEventListener("click", () => {
    const summary = conversation
      .map(
        (message) =>
          `${message.role}: ${message.content}`
      )
      .join("\n");

    const whatsappText = encodeURIComponent(
      `Hi Aarvella, I would like to book an appointment.

Conversation:
${summary}`
    );

    window.location.href =
      `https://wa.me/${WHATSAPP_NUMBER}?text=${whatsappText}`;
  });

  chatMessages.appendChild(button);

  scrollMessagesToBottom();
}

chatForm?.addEventListener(
  "submit",
  async (event) => {
    event.preventDefault();

    const userText = chatInput?.value.trim();

    if (!userText || !chatInput) return;

    chatInput.value = "";

    addMessage("user", userText);

    conversation.push({
      role: "user",
      content: userText
    });

    const typingMessage = addMessage(
      "bot",
      "Typing..."
    );

    try {
      const response = await fetch(AI_API_URL, {
        method: "POST",

        headers: {
          "Content-Type": "application/json"
        },

        body: JSON.stringify({
          messages: conversation.slice(-10)
        })
      });

      if (!response.ok) {
        throw new Error(
          `AI request failed with status ${response.status}`
        );
      }

      const data = await response.json();

      typingMessage?.remove();

      const reply =
        data.reply ||
        "Please continue on WhatsApp for booking.";

      addMessage("bot", reply);

      conversation.push({
        role: "assistant",
        content: reply
      });

      if (shouldShowBookingButton(reply)) {
        addWhatsAppButton();
      }
    } catch (error) {
      typingMessage?.remove();

      console.error(
        "AI Stylist request failed:",
        error
      );

      addMessage(
        "bot",
        "Sorry, the AI Stylist is unavailable. Please continue booking on WhatsApp."
      );

      addWhatsAppButton();
    }
  }
);

/*
 * Show the floating AI button after the user has scrolled
 * down the page. Hide it while the chat window is open.
 */
function updateAiButtonVisibility() {
  if (!chatButton) return;

  const chatIsOpen =
    chatWidget &&
    !chatWidget.hidden;

  const shouldShow =
    window.scrollY > 500 &&
    !chatIsOpen;

  chatButton.style.opacity =
    shouldShow ? "1" : "0";

  chatButton.style.pointerEvents =
    shouldShow ? "auto" : "none";
}

window.addEventListener(
  "scroll",
  updateAiButtonVisibility,
  {
    passive: true
  }
);

updateAiButtonVisibility();