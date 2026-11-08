/**
 * Copyright (C) 2025 Enicore Solutions (https://enicore.com)
 * This software is released under the MIT License.
 *
 * Rivena's page UI: the theme and sidebar toggles, the mobile menu and the dropdown menus, all by class names
 * (.toggle-theme, .toggle-sidebar, .toggle-mobile-menu, .dropdown).
 */

class Rivena {
    constructor() {
        // renews the cookies, so they last a year from the last visit
        ["theme", "sidebar-collapsed"].forEach(name => {
            const value = this.getCookie(name);
            value && this.setCookie(name, value);
        });

        document.addEventListener('DOMContentLoaded', () => {
            this.setupDocumentEvents();
        });
    }

    // returns the cookie's value (an object or array from its JSON), or null if it's missing or broken
    getCookie(name) {
        const value = document.cookie.split("; ").find(cookie => cookie.startsWith(`${name}=`))?.slice(name.length + 1);

        try {
            const text = value === undefined ? null : decodeURIComponent(value);
            return /^[[{]/.test(text) ? JSON.parse(text) : text;
        } catch {
            return null;
        }
    }

    // sets a cookie for a year, an object or array as JSON (an empty value or null deletes it); secure, as site_url is
    // https
    setCookie(name, value) {
        const text = value !== null && typeof value === "object" ? JSON.stringify(value) : String(value ?? "");
        const maxAge = text ? 31536000 : 0;
        document.cookie = `${name}=${encodeURIComponent(text)}; path=/; max-age=${maxAge}; samesite=lax; secure`;
    }

    // handles the clicks and keys of the toggles and dropdowns
    setupDocumentEvents() {
        // the toggles and dropdowns without href (<a role="button" tabindex="0">) work with the keyboard too: Enter and
        // Space click them, Escape closes the menus
        document.addEventListener("keydown", e => {
            const target = e.target instanceof Element ? e.target : null;

            if ((e.key === "Enter" || e.key === " ") && target?.matches('[role="button"]:not(button)')) {
                e.preventDefault();
                target.click();
            } else if (e.key === "Escape") {
                this.hideMenus();
            }
        });

        document.addEventListener("click", e => {
            const target = e.target instanceof Element ? e.target : null;

            if (target?.closest(".toggle-theme")) {
                this.toggleTheme()
            }

            if (target?.closest(".toggle-sidebar")) {
                this.toggleSidebar();
            }

            if (target?.closest(".toggle-mobile-menu")) {
                e.stopPropagation();
                this.toggleMobileMenu();
                return;
            }

            const dropdown = target?.closest(".dropdown");
            if (dropdown) {
                e.stopPropagation();
                // one menu at a time
                document.querySelectorAll('.dropdown.menu-visible').forEach(element => {
                    element !== dropdown && this.setMenuVisible(element, false);
                });
                this.setMenuVisible(dropdown, !dropdown.classList.contains("menu-visible"));
                return;
            }

            this.hideMenus();
        });
    }

    // shows or hides a dropdown's menu (and tells screen readers)
    setMenuVisible(dropdown, visible) {
        dropdown.classList.toggle("menu-visible", visible);
        dropdown.setAttribute("aria-expanded", visible ? "true" : "false");
    }

    // hides the mobile menu and the dropdowns' menus
    hideMenus() {
        if (document.documentElement.classList.contains("mobile-menu-visible")) {
            this.toggleMobileMenu();
        }

        document.querySelectorAll('#navbar .menu-visible, .sidebar-collapsed .menu-visible').forEach(element => {
            this.setMenuVisible(element, false);
        });
    }

    // returns the theme shown: "dark" or "light"
    getTheme() {
        return document.documentElement.classList.contains("light") ? "light" : "dark";
    }

    // shows the theme ("dark" or "light") and saves it, if it isn't shown already
    setTheme(theme) {
        if (theme === this.getTheme()) {
            return;
        }

        const dark = theme === "dark";
        document.documentElement.classList.toggle("dark", dark);
        document.documentElement.classList.toggle("light", !dark);
        this.setCookie("theme", theme);
        document.dispatchEvent(new CustomEvent('color-theme-changed', { detail: { dark } }));
    }

    // switches between the dark and light theme
    toggleTheme() {
        this.setTheme(this.getTheme() === "dark" ? "light" : "dark");
    }

    // shows or hides the mobile menu
    toggleMobileMenu() {
        const visible = document.documentElement.classList.toggle("mobile-menu-visible");
        document.dispatchEvent(new CustomEvent('mobile-menu-visibility-changed', { detail: { visible } }));
    }

    // collapses or expands the sidebar
    toggleSidebar() {
        const expanded = !document.documentElement.classList.toggle("sidebar-collapsed");
        this.setCookie("sidebar-collapsed", expanded ? "" : "1");
        document.dispatchEvent(new CustomEvent('sidebar-visibility-changed', { detail: { expanded } }));
    }
}

const rivena = new Rivena();