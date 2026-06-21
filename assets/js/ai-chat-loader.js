(() => {
  "use strict";

  const PARTIAL_URL =
    "/assets/partials/ai-chat.html?v=20260621-1";

  const CHAT_SCRIPT_URL =
    "/assets/js/ai-chat.js?v=20260621-3";

  let loadingPromise = null;

  function loadScript(source) {
    return new Promise((resolve, reject) => {
      /*
       * Do not load the same JavaScript file more than once.
       */
      const existingScript = document.querySelector(
        'script[data-ai-chat-script="true"]'
      );

      if (existingScript) {
        if (
          existingScript.dataset.loaded === "true"
        ) {
          resolve();
          return;
        }

        existingScript.addEventListener(
          "load",
          resolve,
          { once: true }
        );

        existingScript.addEventListener(
          "error",
          reject,
          { once: true }
        );

        return;
      }

      const script = document.createElement("script");

      script.src = source;
      script.defer = true;
      script.dataset.aiChatScript = "true";

      script.addEventListener(
        "load",
        () => {
          script.dataset.loaded = "true";
          resolve();
        },
        { once: true }
      );

      script.addEventListener(
        "error",
        () => {
          reject(
            new Error(
              `Unable to load AI chat script: ${source}`
            )
          );
        },
        { once: true }
      );

      document.body.appendChild(script);
    });
  }

  async function loadAIChat() {
    const root =
      document.getElementById("ai-chat-root");

    if (!root) {
      return;
    }

    /*
     * Prevent the partial from being loaded more than once.
     */
    if (root.dataset.aiChatLoaded === "true") {
      return;
    }

    if (loadingPromise) {
      return loadingPromise;
    }

    loadingPromise = (async () => {
      try {
        const response = await fetch(PARTIAL_URL, {
          method: "GET",
          credentials: "same-origin",
          cache: "no-cache"
        });

        if (!response.ok) {
          throw new Error(
            `Unable to load AI chat partial. HTTP ${response.status}`
          );
        }

        const html = await response.text();

        root.innerHTML = html;
        root.dataset.aiChatLoaded = "true";

        /*
         * Load ai-chat.js only after the required HTML
         * elements exist in the document.
         */
        await loadScript(CHAT_SCRIPT_URL);

        document.dispatchEvent(
          new CustomEvent("aiChatReady")
        );
      } catch (error) {
        console.error(
          "AI chat could not be initialized:",
          error
        );

        root.innerHTML = "";
        root.dataset.aiChatLoaded = "false";

        throw error;
      } finally {
        loadingPromise = null;
      }
    })();

    return loadingPromise;
  }

  if (document.readyState === "loading") {
    document.addEventListener(
      "DOMContentLoaded",
      loadAIChat,
      { once: true }
    );
  } else {
    loadAIChat();
  }
})();