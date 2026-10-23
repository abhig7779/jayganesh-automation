// Kept out of ThemeToggle.tsx ("use client"): a server component (layout.tsx)
// importing a value from a client module gets a client reference, not the
// string, so the inline <head> script used to be a stub that threw.

export const THEME_KEY = "jg-theme";

// Runs in <head> before the page paints: a saved choice wins, otherwise the
// phone or computer's own light/dark setting decides. Also marks the page as
// running JS (data-js), so CSS can lay out JS-driven sections (the pinned
// "How it works") before first paint.
export const themeScript = `document.documentElement.setAttribute("data-js","");(function(){try{var t=localStorage.getItem("${THEME_KEY}");if(t!=="light"&&t!=="dark"){t=matchMedia("(prefers-color-scheme: light)").matches?"light":"dark"}document.documentElement.dataset.theme=t;document.documentElement.style.colorScheme=t}catch(e){document.documentElement.dataset.theme="dark"}})();`;
