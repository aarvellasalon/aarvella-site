(() => {
  "use strict";

  const BUTTON_SELECTOR = ".av-btn, .btn-gold, .btn-outline";
  const MAGNETIC_SELECTOR = ".av-btn[data-av-magnetic=\"true\"], .btn-gold[data-av-magnetic=\"true\"], .btn-outline[data-av-magnetic=\"true\"]";
  const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)");
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  function resolveButton(target) {
    if (target instanceof Element) return target.closest(BUTTON_SELECTOR);
    if (typeof target === "string") return document.querySelector(target);
    return null;
  }

  function isUnavailable(button) {
    return !button || button.disabled || button.getAttribute("aria-disabled") === "true";
  }

  function createRipple(button, clientX, clientY) {
    if (isUnavailable(button) || reducedMotion.matches) return;

    const rect = button.getBoundingClientRect();
    const x = Number.isFinite(clientX) ? clientX - rect.left : rect.width / 2;
    const y = Number.isFinite(clientY) ? clientY - rect.top : rect.height / 2;
    const host = button.querySelector(".btn-ripple-container") || button;
    const ripple = document.createElement("span");

    ripple.className = "av-btn__ripple";
    ripple.setAttribute("aria-hidden", "true");
    ripple.style.setProperty("--av-ripple-x", `${x}px`);
    ripple.style.setProperty("--av-ripple-y", `${y}px`);
    host.appendChild(ripple);
    ripple.addEventListener("animationend", () => ripple.remove(), { once: true });
  }

  function resetMagnetic(button) {
    if (!button) return;
    button.style.removeProperty("--av-btn-x");
    button.style.removeProperty("--av-btn-y");
  }

  function setLoading(target, loading = true, loadingText = "Please wait") {
    const button = resolveButton(target);
    if (!button) return false;

    const label = button.querySelector(".av-btn__label, .btn-text") || button;

    if (loading) {
      if (button.dataset.avLoading === "true") return true;
      button.dataset.avLoading = "true";
      button.dataset.avOriginalAriaLabel = button.getAttribute("aria-label") || "";
      button.setAttribute("aria-busy", "true");
      button.setAttribute("aria-label", loadingText);

      button.dataset.avWasDisabled = String(Boolean(button.disabled));
      button.dataset.avHadAriaDisabled = button.getAttribute("aria-disabled") || "";
      if ("disabled" in button) button.disabled = true;
      else button.setAttribute("aria-disabled", "true");

      if (!button.querySelector(".av-btn__spinner")) {
        const spinner = document.createElement("span");
        spinner.className = "av-btn__spinner";
        spinner.setAttribute("aria-hidden", "true");
        button.appendChild(spinner);
      }

      if (label && label !== button) label.setAttribute("aria-hidden", "true");
    } else {
      if (button.dataset.avLoading !== "true") return true;
      delete button.dataset.avLoading;
      button.removeAttribute("data-av-loading");
      button.removeAttribute("aria-busy");

      const oldLabel = button.dataset.avOriginalAriaLabel;
      if (oldLabel) button.setAttribute("aria-label", oldLabel);
      else button.removeAttribute("aria-label");
      delete button.dataset.avOriginalAriaLabel;

      if ("disabled" in button) button.disabled = button.dataset.avWasDisabled === "true";
      if (button.dataset.avHadAriaDisabled) button.setAttribute("aria-disabled", button.dataset.avHadAriaDisabled);
      else button.removeAttribute("aria-disabled");
      delete button.dataset.avWasDisabled;
      delete button.dataset.avHadAriaDisabled;

      button.querySelector(".av-btn__spinner")?.remove();
      if (label && label !== button) label.removeAttribute("aria-hidden");
    }

    return true;
  }

  document.addEventListener("pointerdown", event => {
    if (event.button !== 0) return;
    const button = event.target.closest?.(BUTTON_SELECTOR);
    if (!button) return;
    createRipple(button, event.clientX, event.clientY);
  });

  /* Keyboard activation does not emit pointerdown, so add a centred ripple. */
  document.addEventListener("click", event => {
    if (event.detail !== 0) return;
    const button = event.target.closest?.(BUTTON_SELECTOR);
    if (!button) return;
    createRipple(button);
  });

  document.addEventListener("pointermove", event => {
    if (!finePointer.matches || reducedMotion.matches) return;
    const button = event.target.closest?.(MAGNETIC_SELECTOR);
    if (!button || isUnavailable(button)) return;

    const rect = button.getBoundingClientRect();
    const strength = Number(button.dataset.avMagneticStrength || 7);
    const x = ((event.clientX - rect.left) / rect.width - 0.5) * strength;
    const y = ((event.clientY - rect.top) / rect.height - 0.5) * strength;

    button.style.setProperty("--av-btn-x", `${x.toFixed(2)}px`);
    button.style.setProperty("--av-btn-y", `${y.toFixed(2)}px`);
  });

  document.addEventListener("pointerout", event => {
    const button = event.target.closest?.(MAGNETIC_SELECTOR);
    if (!button) return;
    if (event.relatedTarget instanceof Node && button.contains(event.relatedTarget)) return;
    resetMagnetic(button);
  });

  /* Optional declarative loading state for AJAX/form handlers. */
  document.addEventListener("av:button-loading", event => {
    const detail = event.detail || {};
    setLoading(detail.button || event.target, detail.loading !== false, detail.text);
  });

  window.AarvellaButtons = Object.freeze({
    version: "1.0.0",
    createRipple,
    setLoading,
    reset(target) {
      const button = resolveButton(target);
      if (!button) return false;
      resetMagnetic(button);
      return setLoading(button, false);
    }
  });
})();
