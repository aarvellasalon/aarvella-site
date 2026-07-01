(() => {
  "use strict";

  const SELECTORS = {
    mount: "[data-navigation-partial]",
    root: "[data-av-navigation]",
    menuTrigger: "[data-av-menu-trigger]",
    menuPanel: "[data-av-menu-panel]",
    mobileToggle: "[data-av-mobile-toggle]",
    mobileClose: "[data-av-mobile-close]",
    mobileDrawer: "[data-av-mobile-drawer]",
    mobileOverlay: "[data-av-mobile-overlay]",
    accordion: "[data-av-accordion]",
    book: "[data-av-book]"
  };

  const focusableSelector = [
    "a[href]",
    "button:not([disabled])",
    "input:not([disabled])",
    "select:not([disabled])",
    "textarea:not([disabled])",
    "[tabindex]:not([tabindex='-1'])"
  ].join(",");

  async function loadPartial() {
    const mount = document.querySelector(SELECTORS.mount);
    if (!mount || mount.querySelector(SELECTORS.root)) return;

    const source = mount.dataset.navigationPartial || "/assets/partials/navigation.html";

    try {
      const response = await fetch(source, { credentials: "same-origin" });
      if (!response.ok) throw new Error(`Navigation partial returned ${response.status}`);
      mount.innerHTML = await response.text();
      document.dispatchEvent(new CustomEvent("aarvella:navigation:loaded"));
    } catch (error) {
      console.error("Aarvella navigation could not be loaded:", error);
      mount.innerHTML = '<a href="/index.html" style="position:fixed;top:16px;left:16px;z-index:9000;color:#d7b65d;text-decoration:none;font:600 16px Inter,sans-serif;letter-spacing:.18em">AARVELLA</a>';
    }
  }

  function initialiseNavigation(root) {
    if (!root || root.dataset.avInitialised === "true") return;
    root.dataset.avInitialised = "true";

    const menuTriggers = [...root.querySelectorAll(SELECTORS.menuTrigger)];
    const menuPanels = [...root.querySelectorAll(SELECTORS.menuPanel)];
    const mobileToggle = root.querySelector(SELECTORS.mobileToggle);
    const mobileClose = root.querySelector(SELECTORS.mobileClose);
    const mobileDrawer = root.querySelector(SELECTORS.mobileDrawer);
    const mobileOverlay = root.querySelector(SELECTORS.mobileOverlay);
    const accordionButtons = [...root.querySelectorAll(SELECTORS.accordion)];
    let lastFocusedElement = null;
    let lastScrollY = window.scrollY;
    let ticking = false;

    function panelFor(name) {
      return menuPanels.find(panel => panel.dataset.avMenuPanel === name);
    }

    function closeDesktopMenus(exceptName = "") {
      menuTriggers.forEach(trigger => {
        const name = trigger.dataset.avMenuTrigger;
        if (name === exceptName) return;
        trigger.setAttribute("aria-expanded", "false");
        const panel = panelFor(name);
        if (!panel) return;
        panel.classList.remove("is-open");
        window.setTimeout(() => {
          if (!panel.classList.contains("is-open")) panel.hidden = true;
        }, 260);
      });
    }

    function openDesktopMenu(trigger) {
      const name = trigger.dataset.avMenuTrigger;
      const panel = panelFor(name);
      if (!panel) return;
      const isOpen = trigger.getAttribute("aria-expanded") === "true";
      closeDesktopMenus(isOpen ? "" : name);
      if (isOpen) {
        trigger.setAttribute("aria-expanded", "false");
        panel.classList.remove("is-open");
        window.setTimeout(() => { if (!panel.classList.contains("is-open")) panel.hidden = true; }, 260);
        return;
      }
      panel.hidden = false;
      requestAnimationFrame(() => panel.classList.add("is-open"));
      trigger.setAttribute("aria-expanded", "true");
    }

    function setMobileMenu(open) {
      if (!mobileDrawer || !mobileToggle || !mobileOverlay) return;
      if (open) {
        lastFocusedElement = document.activeElement;
        closeDesktopMenus();
        mobileDrawer.classList.add("is-open");
        mobileOverlay.classList.add("is-open");
        root.classList.add("av-menu-active");
        document.body.classList.add("av-menu-open");
        mobileDrawer.setAttribute("aria-hidden", "false");
        mobileToggle.setAttribute("aria-expanded", "true");
        mobileToggle.setAttribute("aria-label", "Close menu");
        window.setTimeout(() => mobileClose?.focus(), 80);
      } else {
        mobileDrawer.classList.remove("is-open");
        mobileOverlay.classList.remove("is-open");
        root.classList.remove("av-menu-active");
        document.body.classList.remove("av-menu-open");
        mobileDrawer.setAttribute("aria-hidden", "true");
        mobileToggle.setAttribute("aria-expanded", "false");
        mobileToggle.setAttribute("aria-label", "Open menu");
        if (lastFocusedElement instanceof HTMLElement) lastFocusedElement.focus({ preventScroll: true });
      }
    }

    function trapFocus(event) {
      if (event.key !== "Tab" || !mobileDrawer?.classList.contains("is-open")) return;
      const items = [...mobileDrawer.querySelectorAll(focusableSelector)].filter(item => item.offsetParent !== null);
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }

    function openBooking() {
      setMobileMenu(false);
      closeDesktopMenus();

      if (typeof window.openBooking === "function") {
        window.setTimeout(() => window.openBooking(), 120);
        return;
      }

      if (window.AarvellaBooking && typeof window.AarvellaBooking.open === "function") {
        window.setTimeout(() => window.AarvellaBooking.open(), 120);
        return;
      }

      const bookingEvent = new CustomEvent("aarvella:booking:open", {
        bubbles: true,
        cancelable: true
      });
      if (!document.dispatchEvent(bookingEvent)) return;

      const popup = document.querySelector("#bookingPopup, .booking-popup, [data-booking-popup]");
      if (popup) {
        window.setTimeout(() => {
          popup.classList.add("active", "is-open");
          popup.setAttribute("aria-hidden", "false");
          document.body.classList.add("menu-open");
        }, 120);
      }
    }

    function markCurrentPage() {
      const current = (window.location.pathname.split("/").pop() || "index.html").toLowerCase();
      root.querySelectorAll("[data-av-route]").forEach(link => {
        const route = (link.dataset.avRoute || "").toLowerCase();
        const active = route === current;
        link.classList.toggle("is-active", active);
        if (active) link.setAttribute("aria-current", "page");
      });

      const servicePages = new Set([
        "services.html", "mens-hair.html", "womens-hair.html", "hair-coloring.html",
        "hair-texture.html", "skin-care.html", "beauty-essentials.html",
        "hand-foot-spa.html", "makeup.html", "bridal.html"
      ]);
      if (servicePages.has(current)) {
        const trigger = root.querySelector('[data-av-menu-trigger="services"]');
        trigger?.classList.add("is-active");
      }
    }

    function handleScroll() {
      const currentY = Math.max(window.scrollY, 0);
      root.classList.toggle("av-scrolled", currentY > 18);

      const desktop = window.matchMedia("(min-width: 1181px)").matches;
      const anyMenuOpen = menuTriggers.some(trigger => trigger.getAttribute("aria-expanded") === "true");
      if (desktop && !anyMenuOpen && !root.classList.contains("av-menu-active")) {
        if (currentY > lastScrollY && currentY > 170) root.classList.add("av-nav-hidden");
        else if (currentY < lastScrollY - 4 || currentY < 90) root.classList.remove("av-nav-hidden");
      } else {
        root.classList.remove("av-nav-hidden");
      }
      lastScrollY = currentY;
      ticking = false;
    }

    menuTriggers.forEach(trigger => trigger.addEventListener("click", event => {
      event.stopPropagation();
      openDesktopMenu(trigger);
    }));

    root.addEventListener("click", event => {
      const bookButton = event.target.closest(SELECTORS.book);
      if (bookButton) openBooking();

      const mobileLink = event.target.closest(".av-mobile-drawer a[href]");
      if (mobileLink) setMobileMenu(false);
    });

    mobileToggle?.addEventListener("click", () => setMobileMenu(mobileToggle.getAttribute("aria-expanded") !== "true"));
    mobileClose?.addEventListener("click", () => setMobileMenu(false));
    mobileOverlay?.addEventListener("click", () => setMobileMenu(false));

    accordionButtons.forEach(button => button.addEventListener("click", () => {
      const panel = document.getElementById(button.getAttribute("aria-controls"));
      const open = button.getAttribute("aria-expanded") === "true";
      button.setAttribute("aria-expanded", String(!open));
      if (panel) panel.hidden = open;
    }));

    document.addEventListener("click", event => {
      if (!root.contains(event.target)) closeDesktopMenus();
      else if (!event.target.closest(SELECTORS.menuTrigger) && !event.target.closest(SELECTORS.menuPanel)) closeDesktopMenus();
    });

    document.addEventListener("keydown", event => {
      trapFocus(event);
      if (event.key === "Escape") {
        closeDesktopMenus();
        setMobileMenu(false);
      }
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth > 1180) setMobileMenu(false);
      closeDesktopMenus();
    }, { passive: true });

    window.addEventListener("scroll", () => {
      if (!ticking) {
        requestAnimationFrame(handleScroll);
        ticking = true;
      }
    }, { passive: true });

    document.addEventListener("mousemove", event => {
      if (event.clientY <= 16) root.classList.remove("av-nav-hidden");
    }, { passive: true });

    markCurrentPage();
    handleScroll();
  }

  async function boot() {
    await loadPartial();
    initialiseNavigation(document.querySelector(SELECTORS.root));
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }

  document.addEventListener("aarvella:navigation:loaded", () => {
    initialiseNavigation(document.querySelector(SELECTORS.root));
  });
})();
