"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { categories, companyLinks, primaryNav, studioLinks } from "@/lib/catalog";
import { site, whatsapp } from "@/lib/site";
import { IconArrow, IconChevron, IconPhone, IconPin, IconWhatsApp, WhatsAppButton } from "./ui";
import ThemeToggle from "./ThemeToggle";
import { LangMenuItem, LangToggle, bootTranslate } from "./LangSwitch";

const groups = ["Interior", "Lights", "Tech", "Exterior"] as const;

function categoryHref(c: (typeof categories)[number]) {
  return c.href ?? "/accessories/#" + c.key;
}

export default function Header() {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [dock, setDock] = useState(false);

  // Transparent over the top of the page; a dark glass bar once scrolled.
  // The phone contact dock only slides in after the hero, so the first screen
  // has a single WhatsApp call to action.
  useEffect(() => {
    const onScroll = () => {
      setScrolled(window.scrollY > 24);
      // The home hero is a tall pinned section: wait until its end is near.
      const hero = document.querySelector<HTMLElement>(".hero-v");
      const at = hero && hero.offsetHeight > window.innerHeight * 1.5
        ? hero.offsetTop + hero.offsetHeight - window.innerHeight * 0.9
        : window.innerHeight * 0.6;
      const on = window.scrollY > at;
      setDock(on);
      document.documentElement.dataset.dock = on ? "on" : "off";
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Hindi chosen on an earlier page: load Google Translate again (only then).
  useEffect(() => bootTranslate(), []);

  // Close the mobile menu on navigation, and lock page scroll while it's open.
  useEffect(() => setOpen(false), [pathname]);
  useEffect(() => {
    document.documentElement.style.overflow = open ? "hidden" : "";
    if (!open) return () => { document.documentElement.style.overflow = ""; };
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("keydown", onKey);
    return () => {
      document.documentElement.style.overflow = "";
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const isActive = (href: string) =>
    pathname === href ||
    (href === "/detailing-studio/" && studioLinks.some((l) => pathname === l.href)) ||
    (href === "/accessories/" && pathname === "/seat-covers/") ||
    (href === "/contact-us/" && pathname === "/visit-us/");

  return (
    <>
      <a className="skip-link" href="#main">Skip to content</a>

      <div className="topbar">
        <div className="wrap">
          <a href={site.mapsHref} target="_blank" rel="noopener noreferrer"><IconPin />{site.addressShort}</a>
          <span className="topbar-right">
            <span>{site.hoursShort}</span>
            <span className="topbar-dot" aria-hidden />
            <a href={site.reviewsHref} target="_blank" rel="noopener noreferrer">
              <span className="topbar-star" aria-hidden>★</span> {site.rating} on Google · {site.reviews} reviews
            </a>
          </span>
        </div>
      </div>

      <header className={["header", scrolled && "is-scrolled", open && "is-open"].filter(Boolean).join(" ")}>
        <div className="wrap">
          {/* No aria-label: the visible name is the link text (WCAG 2.5.3). */}
          <Link className="brand" href="/">
            {/* Small WebPs, not the 690px logo.png: it is shown ~58px wide (64px
                file at 1x, 128px at 2x). A plain <img> so it isn't preloaded
                ahead of the hero photo. */}
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/logo-128.webp" srcSet="/logo-64.webp 1x, /logo-128.webp 2x" alt="" width={58} height={40} decoding="async" />
            <span className="brand-name notranslate" translate="no">
              <strong>Jay Ganesh</strong>
              <span className="label">Detailing &amp; Accessories</span>
            </span>
          </Link>

          <nav className="nav" aria-label="Main">
            {primaryNav.map((item) => (
              <div className="nav-item" key={item.href}>
                <Link className="nav-link" href={item.href} aria-current={isActive(item.href) ? "page" : undefined}>
                  {item.label}
                  {item.menu && <IconChevron className="chev" />}
                </Link>

                {item.menu === "studio" && (
                  <div className="mega">
                    <div className="wrap">
                      <div style={{ gridColumn: "span 2" }}>
                        <h4 className="label label--yellow">Detailing studio</h4>
                        <ul>
                          {studioLinks.map((l) => (
                            <li key={l.href}><Link href={l.href}><strong>{l.label}</strong><span>{l.note}</span></Link></li>
                          ))}
                        </ul>
                      </div>
                      <div style={{ gridColumn: "span 2" }}>
                        <h4 className="label label--yellow">Not sure what you need?</h4>
                        <p className="mega-note">
                          PPF stops chips. Ceramic adds gloss. Detailing cleans. Send a photo of your car and we'll suggest the right one.
                        </p>
                      </div>
                      <div className="mega-card">
                        <p className="label">Free paint inspection</p>
                        <p>Bring your car in and we'll check the paint with you under studio lights.</p>
                        <WhatsAppButton size="sm" message="Hi Jay Ganesh, I'd like to book a free paint inspection. Car model: ">Book on WhatsApp</WhatsAppButton>
                      </div>
                    </div>
                  </div>
                )}

                {item.menu === "accessories" && (
                  <div className="mega">
                    <div className="wrap">
                      {groups.map((g) => (
                        <div key={g}>
                          <h4 className="label label--yellow">{g}</h4>
                          <ul>
                            {categories.filter((c) => c.group === g).map((c) => (
                              <li key={c.key}><Link href={categoryHref(c)}><strong>{c.name}</strong><span>{c.blurb}</span></Link></li>
                            ))}
                          </ul>
                        </div>
                      ))}
                      <div className="mega-card">
                        <p className="label">Fitted at our shop</p>
                        <p>Tell us your car model and what you're looking for. We'll send options and prices.</p>
                        <Link className="text-link" href="/accessories/">All accessories <IconArrow /></Link>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            ))}
          </nav>

          <div className="header-actions">
            <LangToggle className="lang-toggle--head" />
            <ThemeToggle />
            <a className="btn btn--ghost btn--sm btn--call" href={site.phoneHref}><IconPhone />Call</a>
            <WhatsAppButton size="sm" className="btn--wa-head">WhatsApp</WhatsAppButton>
            <button
              className="menu-toggle"
              type="button"
              aria-expanded={open}
              aria-controls="mobile-menu"
              aria-label={open ? "Close menu" : "Open menu"}
              onClick={() => setOpen((v) => !v)}
            >
              <span />
            </button>
          </div>
        </div>
      </header>

      {open && (
        <nav className="mobile-menu" id="mobile-menu" aria-label="Main">
          <details open>
            <summary>Detailing Studio</summary>
            <ul>
              {studioLinks.map((l) => (
                <li key={l.href}><Link href={l.href}><strong>{l.label}</strong>{l.note}</Link></li>
              ))}
              <li><Link href="/detailing-studio/"><strong>All studio services</strong></Link></li>
            </ul>
          </details>
          <details>
            <summary>Accessories</summary>
            <ul>
              {categories.map((c) => (
                <li key={c.key}><Link href={categoryHref(c)}><strong>{c.name}</strong>{c.blurb}</Link></li>
              ))}
            </ul>
          </details>
          {primaryNav.filter((i) => !i.menu).map((i) => (
            <Link key={i.href} href={i.href}>{i.label}<IconArrow /></Link>
          ))}
          <Link href="/visit-us/">Visit Us<IconArrow /></Link>
          <details>
            <summary>Work with us</summary>
            <ul>
              {companyLinks.filter((l) => l.href === "/partnerships/" || l.href === "/become-a-vendor/").map((l) => (
                <li key={l.href}><Link href={l.href}><strong>{l.label}</strong></Link></li>
              ))}
            </ul>
          </details>
          <LangMenuItem />
          <div className="mobile-contact">
            <div className="actions">
              <WhatsAppButton block>WhatsApp us</WhatsAppButton>
              <a className="btn btn--ghost btn--block" href={site.phoneHref} data-track="call"><IconPhone />Call <span className="notranslate" translate="no">{site.phoneDisplay}</span></a>
            </div>
            <p className="label">{site.hoursShort}</p>
            <p className="label">{site.addressShort}</p>
            <a className="mobile-rating" href={site.reviewsHref} target="_blank" rel="noopener noreferrer">
              <span aria-hidden>★</span> {site.rating} on Google · {site.reviews} reviews
            </a>
          </div>
        </nav>
      )}

      <div className={["action-bar", dock && "is-visible"].filter(Boolean).join(" ")} aria-label="Contact the shop">
        <a href={whatsapp()} target="_blank" rel="noopener noreferrer" data-track="whatsapp"><IconWhatsApp />WhatsApp</a>
        <a href={site.phoneHref} data-track="call"><IconPhone />Call</a>
        <a href={site.mapsHref} target="_blank" rel="noopener noreferrer" data-track="directions"><IconPin />Map</a>
      </div>
    </>
  );
}
