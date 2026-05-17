const AI_API_URL = "https://aarvella-site.vercel.app/api/ai-stylist";
const WHATSAPP_NUMBER = "919742049990";

const chatButton = document.getElementById("ai-chat-button");
const chatWidget = document.getElementById("ai-chat-widget");
const chatClose = document.getElementById("ai-chat-close");
const chatForm = document.getElementById("ai-chat-form");
const chatInput = document.getElementById("ai-chat-input");
const chatMessages = document.getElementById("ai-chat-messages");

const conversation = [];

chatButton.addEventListener("click", () => {
  chatWidget.hidden = false;
  chatInput.focus();
});

chatClose.addEventListener("click", () => {
  chatWidget.hidden = true;
});

function resizeChatWidget() {
  const widget = document.getElementById("ai-chat-widget");
  if (!widget) return;

  const isMobile = window.matchMedia("(max-width: 768px)").matches;

  if (!isMobile) {
    widget.style.top = "";
    widget.style.height = "";
    widget.style.bottom = "";
    return;
  }

  const viewport = window.visualViewport;
  if (!viewport) return;

  const topPadding = 10;
  const bottomPadding = 70;

  widget.style.top = `${viewport.offsetTop + topPadding}px`;
  widget.style.bottom = "auto";
  widget.style.height = `${viewport.height - topPadding - bottomPadding}px`;
}

window.addEventListener("resize", resizeChatWidget);

window.addEventListener("orientationchange", () => {
  setTimeout(resizeChatWidget, 300);
});

if (window.visualViewport) {
  visualViewport.addEventListener("resize", resizeChatWidget);
  visualViewport.addEventListener("scroll", resizeChatWidget);
}

resizeChatWidget();

function addMessage(type, text) {
  const message = document.createElement("div");
  message.className = `ai-message ${type}`;
  message.textContent = text;
  chatMessages.appendChild(message);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

chatInput.addEventListener("focus", () => {
  updateMobileViewport();

  setTimeout(() => {
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }, 250);
});

chatInput.addEventListener("blur", () => {
  setTimeout(updateMobileViewport, 250);
});

function shouldShowBookingButton(text) {
  const keywords = ["book", "appointment", "preferred", "confirm", "whatsapp"];
  return keywords.some((word) => text.toLowerCase().includes(word));
}

function addWhatsAppButton() {
  const button = document.createElement("button");
  button.textContent = "Continue Booking on WhatsApp";
  button.className = "ai-whatsapp-button";

  button.addEventListener("click", () => {
    const summary = conversation
      .map((msg) => `${msg.role}: ${msg.content}`)
      .join("\n");

    const whatsappText = encodeURIComponent(
      `Hi Aarvella, I would like to book an appointment.\n\nConversation:\n${summary}`
    );

    window.location.href = `https://wa.me/${WHATSAPP_NUMBER}?text=${whatsappText}`;
  });

  chatMessages.appendChild(button);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

chatForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  const userText = chatInput.value.trim();
  if (!userText) return;

  chatInput.value = "";
  addMessage("user", userText);

  conversation.push({
    role: "user",
    content: userText
  });

  addMessage("bot", "Typing...");

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

    const data = await response.json();

    const typingMessage = chatMessages.lastChild;
    typingMessage.remove();

    const reply = data.reply || "Please continue on WhatsApp for booking.";
    addMessage("bot", reply);

    conversation.push({
      role: "assistant",
      content: reply
    });

    if (shouldShowBookingButton(reply)) {
      addWhatsAppButton();
    }
  } catch (error) {
    const typingMessage = chatMessages.lastChild;
    typingMessage.remove();

    addMessage(
      "bot",
      "Sorry, the AI Stylist is unavailable. Please continue booking on WhatsApp."
    );

    addWhatsAppButton();
  }
});
const aiButton = document.getElementById("ai-chat-button");

aiButton.style.opacity = "0";
aiButton.style.pointerEvents = "none";

window.addEventListener("scroll", () => {
  if (window.scrollY > 500) {
    aiButton.style.opacity = "1";
    aiButton.style.pointerEvents = "auto";
  } else {
    aiButton.style.opacity = "0";
    aiButton.style.pointerEvents = "none";
  }
});
