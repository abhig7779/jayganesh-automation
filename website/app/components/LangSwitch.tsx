"use client";

import { useEffect, useState } from "react";
import { track } from "./Analytics";

// Hindi via the Google Translate website element, loaded ON DEMAND only.
// - Nothing third-party loads until someone taps the switch, or a page loads
//   with the googtrans=/en/hi cookie (the choice made on an earlier page).
// - Hindi: set the cookie, then inject element.js; Google translates in place.
// - English: clear the cookie (host and .domain variants) and reload.
// Google's banner, tooltip and highlights are hidden in app/styles/chrome.css
// ("Google Translate"). Text that must stay as it is carries translate="no"
// and class "notranslate" (brand names, phone numbers, logos).

const SCRIPT_ID = "jg-translate-script";
const CONTAINER_ID = "jg-translate";
const CHANGE = "jg-lang-change";

type Lang = "en" | "hi";
type TranslateWindow = {
  jgTranslateInit?: () => void;
  google?: { translate?: { TranslateElement: new (opts: Record<string, unknown>, id: string) => unknown } };
};

function currentLang(): Lang {
  return /(?:^|;\s*)googtrans=\/en\/hi(?:;|$)/.test(document.cookie) ? "hi" : "en";
}

/** The registrable host for a domain cookie, or null on localhost / an IP. */
function cookieDomain(): string | null {
  const h = location.hostname;
  if (h === "localhost" || h.endsWith(".localhost") || /^[\d.]+$/.test(h) || h.includes(":")) return null;
  return h.replace(/^www\./, "");
}

function setCookie(value: string) {
  const d = cookieDomain();
  document.cookie = `googtrans=${value}; path=/; SameSite=Lax`;
  if (d) document.cookie = `googtrans=${value}; path=/; domain=.${d}; SameSite=Lax`;
}

function clearCookie() {
  const gone = "expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  const h = location.hostname;
  document.cookie = `googtrans=; ${gone}`;
  for (const d of new Set([h, `.${h}`, cookieDomain(), cookieDomain() && `.${cookieDomain()}`])) {
    if (d) document.cookie = `googtrans=; ${gone}; domain=${d}`;
  }
}

/**
 * Google wraps translated text in <font> elements, so React can later try to
 * remove or insert next to a text node that is no longer where it left it
 * (the well-known "removeChild ... not a child" crash). Only while Hindi is on,
 * make those two DOM calls tolerant of that one case.
 */
function patchDomForTranslate() {
  const w = window as unknown as { __jgDomPatched?: boolean };
  if (w.__jgDomPatched || typeof Node !== "function") return;
  w.__jgDomPatched = true;
  const remove = Node.prototype.removeChild;
  Node.prototype.removeChild = function <T extends Node>(this: Node, child: T): T {
    if (child.parentNode !== this) {
      child.parentNode?.removeChild(child);
      return child;
    }
    return remove.call(this, child) as T;
  };
  const insert = Node.prototype.insertBefore;
  Node.prototype.insertBefore = function <T extends Node>(this: Node, node: T, ref: Node | null): T {
    if (ref && ref.parentNode !== this) return insert.call(this, node, null) as T;
    return insert.call(this, node, ref) as T;
  };
}

function loadTranslate() {
  if (document.getElementById(SCRIPT_ID)) return;
  patchDomForTranslate();
  document.documentElement.lang = "hi";

  // Google renders its (unused) dropdown here; it is kept out of layout.
  if (!document.getElementById(CONTAINER_ID)) {
    const box = document.createElement("div");
    box.id = CONTAINER_ID;
    box.hidden = true;
    box.setAttribute("aria-hidden", "true");
    box.style.cssText = "display:none!important;position:absolute;width:0;height:0;overflow:hidden";
    document.body.appendChild(box);
  }

  const w = window as unknown as TranslateWindow;
  w.jgTranslateInit = () => {
    const TE = w.google?.translate?.TranslateElement;
    if (!TE) return;
    new TE({ pageLanguage: "en", includedLanguages: "hi,en", autoDisplay: false }, CONTAINER_ID);
  };

  const s = document.createElement("script");
  s.id = SCRIPT_ID;
  s.src = "https://translate.google.com/translate_a/element.js?cb=jgTranslateInit";
  s.async = true;
  document.body.appendChild(s);
}

/** Called once from the Header: keeps Hindi on across pages if it was chosen. */
export function bootTranslate() {
  if (currentLang() === "hi") loadTranslate();
}

function useLang() {
  const [lang, setLang] = useState<Lang>("en");
  useEffect(() => {
    setLang(currentLang());
    const on = () => setLang(currentLang());
    window.addEventListener(CHANGE, on);
    return () => window.removeEventListener(CHANGE, on);
  }, []);

  const toggle = () => {
    if (currentLang() === "hi") {
      track("language_switch", { to: "en" });
      clearCookie();
      // Google's own copy of the page state goes with a fresh load.
      location.reload();
      return;
    }
    track("language_switch", { to: "hi" });
    setCookie("/en/hi");
    loadTranslate();
    window.dispatchEvent(new Event(CHANGE));
  };

  return { lang, toggle };
}

/** Round chip next to the theme toggle (desktop header). */
export function LangToggle({ className }: { className?: string }) {
  const { lang, toggle } = useLang();
  const label = lang === "hi" ? "Switch to English" : "Translate to Hindi";
  return (
    <button
      type="button"
      className={["theme-toggle", "lang-toggle", "notranslate", className].filter(Boolean).join(" ")}
      translate="no"
      onClick={toggle}
      aria-label={label}
      title={label}
    >
      <span aria-hidden="true" lang={lang === "hi" ? "en" : "hi"}>{lang === "hi" ? "EN" : "हिं"}</span>
    </button>
  );
}

/** Full-width row for the mobile menu. */
export function LangMenuItem() {
  const { lang, toggle } = useLang();
  const label = lang === "hi" ? "Switch to English" : "Translate to Hindi";
  return (
    <button type="button" className="mobile-lang notranslate" translate="no" onClick={toggle} aria-label={label}>
      <span lang={lang === "hi" ? "en" : "hi"}>{lang === "hi" ? "English" : "हिंदी"}</span>
      <span className="mobile-lang-chip" aria-hidden="true">{lang === "hi" ? "EN" : "हिं"}</span>
    </button>
  );
}
