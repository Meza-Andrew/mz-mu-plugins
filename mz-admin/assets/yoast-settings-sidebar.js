(() => {
    const STYLE_ID = "meza-yoast-settings-sidebar-shadow-style";
    const FORCE_OPEN_CLASS = "meza-yoast-sidebar-force-open";
    const PROCESSED_MARKER = "data-meza-sidebar-limiter-processed";
    const CSS_TEXT = String(window.mezaYoastSettingsSidebarCss || "");

    let rafId = null;
    let documentObserver = null;
    const observedRoots = new WeakSet();

    const normalize = (value) => String(value || "").replace(/\s+/g, " ").trim().toLowerCase();
    const isLimiterLabel = (value) => value === "show more" || value === "show less";
    const isAdvancedLabel = (value) => value === "advanced";

    const isShadowRoot = (value) =>
        value &&
        typeof value.querySelector === "function" &&
        typeof value.appendChild === "function" &&
        value.nodeType === 11;

    const looksLikeYoastSettingsRoot = (root) =>
        isShadowRoot(root) &&
        (
            root.querySelector(".yst-sidebar-navigation") ||
            root.querySelector(".yst-sidebar-navigation__collapsible") ||
            root.querySelector("#button-search") ||
            root.querySelector("#link-yoast-logo")
        );

    const findYoastShadowRoots = () => {
        const roots = [];
        const elements = document.body ? document.body.querySelectorAll("*") : [];
        for (const element of elements) {
            if (!(element instanceof HTMLElement) || !element.shadowRoot || !isShadowRoot(element.shadowRoot)) {
                continue;
            }

            if (looksLikeYoastSettingsRoot(element.shadowRoot)) {
                roots.push(element.shadowRoot);
            }
        }

        return roots;
    };

    const ensureShadowStyle = (root) => {
        if (!isShadowRoot(root) || CSS_TEXT === "") {
            return;
        }

        let style = root.getElementById ? root.getElementById(STYLE_ID) : null;
        if (!(style instanceof HTMLStyleElement)) {
            style = document.createElement("style");
            style.id = STYLE_ID;
            root.appendChild(style);
        }

        if (style.textContent !== CSS_TEXT) {
            style.textContent = CSS_TEXT;
        }
    };

    const getControlledPanel = (root, button) => {
        if (!isShadowRoot(root) || !(button instanceof HTMLElement)) {
            return null;
        }

        const controlsId = button.getAttribute("aria-controls");
        if (!controlsId) {
            return null;
        }

        if (typeof root.getElementById === "function") {
            const panel = root.getElementById(controlsId);
            if (panel instanceof HTMLElement) {
                return panel;
            }
        }

        const panel = root.querySelector(`[id="${controlsId.replace(/"/g, '\\"')}"]`);
        return panel instanceof HTMLElement ? panel : null;
    };

    const revealElement = (element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.hidden = false;
        if (element.getAttribute("aria-hidden") === "true") {
            element.setAttribute("aria-hidden", "false");
        }

        element.classList.remove(
            "rah-animating",
            "rah-animating--up",
            "rah-animating--down",
            "rah-animating--to-height-zero",
            "rah-animating--to-height-auto",
            "rah-animating--to-height-specific",
            "rah-static--height-zero",
            "rah-static--height-specific"
        );
        element.classList.add("rah-static--height-auto");

        element.style.setProperty("display", "block", "important");
        element.style.setProperty("height", "auto", "important");
        element.style.setProperty("max-height", "none", "important");
        element.style.setProperty("overflow", "visible", "important");
        element.style.setProperty("opacity", "1", "important");
        element.style.setProperty("visibility", "visible", "important");
    };

    const isSidebarLimiterButton = (root, button) => {
        if (!(button instanceof HTMLButtonElement) || !isLimiterLabel(normalize(button.textContent))) {
            return false;
        }

        const panel = getControlledPanel(root, button);
        if (!(panel instanceof HTMLElement)) {
            return false;
        }

        return panel.querySelector(".yst-sidebar-navigation__item") !== null;
    };

    const removeLimiterUi = (button) => {
        const wrapper = button.closest(".yst-relative");
        const target = wrapper instanceof HTMLElement ? wrapper : button;

        if (!(target instanceof HTMLElement) || target.hasAttribute(PROCESSED_MARKER)) {
            return;
        }

        target.setAttribute(PROCESSED_MARKER, "true");
        target.remove();
    };

    const removeQuickSearchUi = (root) => {
        const quickSearchButton = root.querySelector("#button-search");
        if (quickSearchButton instanceof HTMLElement) {
            quickSearchButton.remove();
        }
    };

    const removeCollapseAllUi = (root) => {
        Array.from(root.querySelectorAll("button")).forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            if (normalize(button.textContent) === "collapse all") {
                button.remove();
            }
        });
    };

    const isAdvancedSection = (section) => {
        if (!(section instanceof HTMLElement)) {
            return false;
        }

        const button = section.querySelector(":scope > .yst-sidebar-navigation__collapsible-button");
        return button instanceof HTMLElement && isAdvancedLabel(normalize(button.textContent));
    };

    const syncAccordionPresentation = (root) => {
        Array.from(root.querySelectorAll(".yst-sidebar-navigation__collapsible")).forEach((section) => {
            if (!(section instanceof HTMLElement)) {
                return;
            }

            const button = section.querySelector(":scope > .yst-sidebar-navigation__collapsible-button");
            const list = section.querySelector(":scope > .yst-sidebar-navigation__list");
            const isAdvanced = isAdvancedSection(section);

            if (isAdvanced) {
                section.classList.remove(FORCE_OPEN_CLASS);

                if (button instanceof HTMLButtonElement) {
                    button.removeAttribute("aria-disabled");
                    button.tabIndex = 0;
                    button.style.removeProperty("pointer-events");
                }

                return;
            }

            section.classList.add(FORCE_OPEN_CLASS);

            if (button instanceof HTMLButtonElement) {
                button.setAttribute("aria-expanded", "true");
                button.setAttribute("aria-disabled", "true");
                button.tabIndex = -1;
                button.style.setProperty("pointer-events", "none", "important");
            }

            if (list instanceof HTMLElement) {
                revealElement(list);
            }
        });
    };

    const disableAccordionEvents = (root) => {
        if (root.__mezaYoastSidebarHandlersAttached) {
            return;
        }

        const stopToggle = (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const button = target?.closest?.(".yst-sidebar-navigation__collapsible-button");
            const section = button?.closest?.(".yst-sidebar-navigation__collapsible");
            if (!(button instanceof HTMLButtonElement) || isAdvancedSection(section)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") {
                event.stopImmediatePropagation();
            }
        };

        root.addEventListener("mousedown", stopToggle, true);
        root.addEventListener("pointerdown", stopToggle, true);
        root.addEventListener("click", stopToggle, true);
        root.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " " || event.key === "Spacebar") {
                stopToggle(event);
            }
        }, true);

        root.__mezaYoastSidebarHandlersAttached = true;
    };

    const syncSidebar = (root) => {
        if (!isShadowRoot(root)) {
            return;
        }

        ensureShadowStyle(root);

        if (!looksLikeYoastSettingsRoot(root)) {
            return;
        }

        disableAccordionEvents(root);
        syncAccordionPresentation(root);

        Array.from(root.querySelectorAll("button[aria-controls]"))
            .filter((button) => isSidebarLimiterButton(root, button))
            .forEach((button) => {
                const panel = getControlledPanel(root, button);
                revealElement(panel);

                Array.from(panel?.children ?? []).forEach((child) => {
                    revealElement(child);
                });

                removeLimiterUi(button);
            });

        removeQuickSearchUi(root);
        removeCollapseAllUi(root);
    };

    const attachShadowObserver = (root) => {
        if (!isShadowRoot(root) || observedRoots.has(root)) {
            return;
        }

        const observer = new MutationObserver(() => {
            scheduleSync();
        });
        observer.observe(root, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ["aria-expanded", "class", "style", "hidden"],
        });
        root.__mezaYoastSidebarObserver = observer;
        observedRoots.add(root);
    };

    const performSync = () => {
        findYoastShadowRoots().forEach((root) => {
            attachShadowObserver(root);
            syncSidebar(root);
        });
    };

    const scheduleSync = () => {
        if (rafId !== null) {
            return;
        }

        rafId = window.requestAnimationFrame(() => {
            rafId = null;
            performSync();
        });
    };

    const patchAttachShadow = () => {
        if (Element.prototype.__mezaYoastAttachShadowPatched || typeof Element.prototype.attachShadow !== "function") {
            return;
        }

        const originalAttachShadow = Element.prototype.attachShadow;

        Element.prototype.attachShadow = function patchedAttachShadow(init) {
            const root = originalAttachShadow.call(this, init);
            if (isShadowRoot(root)) {
                attachShadowObserver(root);
                scheduleSync();
            }
            return root;
        };

        Element.prototype.__mezaYoastAttachShadowPatched = true;
    };

    const init = () => {
        patchAttachShadow();
        scheduleSync();

        if (!documentObserver) {
            documentObserver = new MutationObserver(() => {
                scheduleSync();
            });
        }

        const target = document.body || document.documentElement;
        if (target instanceof HTMLElement) {
            documentObserver.observe(target, {
                childList: true,
                subtree: true,
            });
        }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
        init();
    }
})();
