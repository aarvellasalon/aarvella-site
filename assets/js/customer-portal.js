(() => {
    "use strict";

    const selectors = {
        greeting: "[data-greeting]",
        profileImages: "[data-profile-image]",
        menuButton: "[data-portal-menu-button]",
        sidebar: "[data-portal-sidebar]",
        overlay: "[data-portal-overlay]",
        profileMenuButton: "[data-profile-menu-trigger]",
        profileDropdown: "[data-profile-dropdown]",
        comingSoon: "[data-coming-soon]",
        toast: "[data-portal-toast]",
        cards: ".portal-card, .portal-panel",
        logoutLinks: ".js-logout",
        navigationLinks: ".portal-nav a, .portal-mobile-nav a",
        liquidButtons: ".btn-gold, .btn-outline"
    };

    let lastFocusedElement = null;
    let toastTimer = null;

    function getGreeting() {
        const hour = new Date().getHours();

        if (hour < 12) return "Good morning,";
        if (hour < 17) return "Good afternoon,";
        return "Good evening,";
    }

    function updateGreeting() {
        const element = document.querySelector(selectors.greeting);
        if (element) element.textContent = getGreeting();
    }

    function initializeProfileImages() {
        document.querySelectorAll(selectors.profileImages).forEach((image) => {
            image.addEventListener(
                "error",
                () => image.classList.add("is-broken"),
                { once: true }
            );
        });
    }

    function initializeMobileSidebar() {
        const button = document.querySelector(selectors.menuButton);
        const sidebar = document.querySelector(selectors.sidebar);
        const overlay = document.querySelector(selectors.overlay);

        if (!button || !sidebar) return;

        const isMobile = () => window.matchMedia("(max-width: 980px)").matches;

        function setMenuState(open) {
            const shouldOpen = Boolean(open && isMobile());

            sidebar.classList.toggle("is-open", shouldOpen);
            overlay?.classList.toggle("is-visible", shouldOpen);
            document.body.classList.toggle("portal-menu-open", shouldOpen);
            button.setAttribute("aria-expanded", String(shouldOpen));
            button.setAttribute(
                "aria-label",
                shouldOpen ? "Close account menu" : "Open account menu"
            );
            overlay?.setAttribute("aria-hidden", String(!shouldOpen));

            if (isMobile()) {
                sidebar.setAttribute("aria-hidden", String(!shouldOpen));
            } else {
                sidebar.removeAttribute("aria-hidden");
            }

            if (shouldOpen) {
                lastFocusedElement = document.activeElement;
                sidebar.scrollTop = 0;
                window.setTimeout(() => {
                    sidebar.querySelector("a, button")?.focus();
                }, 90);
            } else if (lastFocusedElement instanceof HTMLElement) {
                lastFocusedElement.focus({ preventScroll: true });
            }
        }

        button.addEventListener("click", () => {
            setMenuState(!sidebar.classList.contains("is-open"));
        });

        overlay?.addEventListener("click", () => setMenuState(false));

        sidebar.addEventListener("click", (event) => {
            if (isMobile() && event.target.closest("a")) {
                setMenuState(false);
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && sidebar.classList.contains("is-open")) {
                setMenuState(false);
            }
        });

        window.addEventListener("resize", () => {
            if (!isMobile()) setMenuState(false);
        });

        if (isMobile()) {
            sidebar.setAttribute("aria-hidden", "true");
        }
    }

    function initializeProfileMenu() {
        const button = document.querySelector(selectors.profileMenuButton);
        const dropdown = document.querySelector(selectors.profileDropdown);

        if (!button || !dropdown) return;

        function setOpen(open) {
            dropdown.hidden = !open;
            button.setAttribute("aria-expanded", String(open));
            document.body.classList.toggle("portal-profile-open", open);
        }

        button.addEventListener("click", (event) => {
            event.stopPropagation();
            setOpen(dropdown.hidden);
        });

        dropdown.addEventListener("click", (event) => event.stopPropagation());
        document.addEventListener("click", () => setOpen(false));

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && !dropdown.hidden) {
                setOpen(false);
                button.focus();
            }
        });
    }

    function showToast(message) {
        const toast = document.querySelector(selectors.toast);
        if (!toast) return;

        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.hidden = false;

        toastTimer = window.setTimeout(() => {
            toast.hidden = true;
        }, 3200);
    }

    function initializeComingSoonActions() {
        document.querySelectorAll(selectors.comingSoon).forEach((element) => {
            element.addEventListener("click", (event) => {
                event.preventDefault();
                const feature = element.dataset.comingSoon || "This feature";
                showToast(`${feature} will be available in a later portal phase.`);
            });
        });
    }

    function initializeCardSheen() {
        if (!window.matchMedia("(pointer: fine)").matches) return;

        document.querySelectorAll(selectors.cards).forEach((card) => {
            card.addEventListener("pointermove", (event) => {
                const rect = card.getBoundingClientRect();
                const x = ((event.clientX - rect.left) / rect.width) * 100;
                const y = ((event.clientY - rect.top) / rect.height) * 100;

                card.style.setProperty("--sheen-x", `${x}%`);
                card.style.setProperty("--sheen-y", `${y}%`);
                card.style.setProperty("--sheen-opacity", "1");
            });

            card.addEventListener("pointerleave", () => {
                card.style.setProperty("--sheen-opacity", "0");
            });
        });
    }


    function initializeLiquidButtons() {
        const reducedMotion = window.matchMedia(
            "(prefers-reduced-motion: reduce)"
        ).matches;

        document.querySelectorAll(selectors.liquidButtons).forEach((button) => {
            if (!reducedMotion && window.matchMedia("(pointer: fine)").matches) {
                button.addEventListener("pointermove", (event) => {
                    const rect = button.getBoundingClientRect();
                    const offsetX = event.clientX - rect.left - rect.width / 2;
                    const offsetY = event.clientY - rect.top - rect.height / 2;
                    const moveX = Math.max(-4, Math.min(4, offsetX * 0.08));
                    const moveY = Math.max(-3, Math.min(3, offsetY * 0.08));

                    button.style.setProperty(
                        "--magnetic",
                        `translate(${moveX}px, ${moveY}px)`
                    );
                });

                button.addEventListener("pointerleave", () => {
                    button.style.setProperty("--magnetic", "translate(0, 0)");
                });
            }

            button.addEventListener("click", (event) => {
                const container = button.querySelector(
                    ".btn-ripple-container"
                );

                if (!container || reducedMotion) return;

                const rect = button.getBoundingClientRect();
                const ripple = document.createElement("span");
                ripple.className = "btn-ripple";
                ripple.style.setProperty(
                    "--ripple-x",
                    `${event.clientX - rect.left}px`
                );
                ripple.style.setProperty(
                    "--ripple-y",
                    `${event.clientY - rect.top}px`
                );

                container.appendChild(ripple);
                window.setTimeout(() => ripple.remove(), 700);
            });
        });
    }

    function setActiveNavigation() {
        const currentPath = window.location.pathname.replace(/\/+$/, "");

        document.querySelectorAll(selectors.navigationLinks).forEach((link) => {
            if (link.hasAttribute("data-coming-soon")) return;

            const url = new URL(link.href, window.location.origin);
            const linkPath = url.pathname.replace(/\/+$/, "");
            const active = linkPath === currentPath;

            link.classList.toggle("is-active", active);
            if (active) link.setAttribute("aria-current", "page");
            else link.removeAttribute("aria-current");
        });
    }

    function initializeLogoutLinks() {
        document.querySelectorAll(selectors.logoutLinks).forEach((link) => {
            link.addEventListener("click", () => {
                if (link.dataset.loading === "true") return;
                link.dataset.loading = "true";
                link.setAttribute("aria-busy", "true");

                const label = link.querySelector("span");
                if (label) label.textContent = "Logging out…";
            });
        });
    }

    function initializeKeyboardNavigation() {
        document.addEventListener("keydown", (event) => {
            if (event.key === "Tab") {
                document.documentElement.classList.add("keyboard-navigation");
            }
        });

        document.addEventListener("pointerdown", () => {
            document.documentElement.classList.remove("keyboard-navigation");
        });
    }

    function initializePortal() {
        updateGreeting();
        initializeProfileImages();
        initializeMobileSidebar();
        initializeProfileMenu();
        initializeComingSoonActions();
        initializeCardSheen();
        initializeLiquidButtons();
        setActiveNavigation();
        initializeLogoutLinks();
        initializeKeyboardNavigation();
        document.documentElement.classList.add("portal-js-ready");
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializePortal, { once: true });
    } else {
        initializePortal();
    }
})();
