import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import "../styles/dealer.css";
import { CallButton, CtaBand, Faq, IconArrow, JsonLd, SectionHead, Visit, WhatsAppButton } from "./ui";
import { PageHero } from "./PageHero";
import Reveal from "./Reveal";
import ReviewsCarousel from "./ReviewsCarousel";
import { BrandLogo } from "./BrandLogos";
import { getBrandBySlug } from "@/lib/brands";
import {
  dealerBrandPath,
  dealerBrandsIn,
  dealerCategories,
  dealerProductName,
  getDealerBrand,
  type DealerBrand,
  type DealerCategory,
} from "@/lib/dealer-brands";
import { heroAlt } from "@/lib/heroes";
import { site } from "@/lib/site";
import { areaServedLd, breadcrumbLd, businessRef, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";

// Authorised-dealer brand content, all from lib/dealer-brands.ts:
// - <DealerBrandsSection> : logo grid with a line per brand, linking to its page
//   (/ppf-pune/, /car-lights/headlights/; `compact` strip on /car-lights/).
// - <DealerBrandPage>     : /garware-ppf-pune/, /dhc-headlights-pune/ …
// - <DealerHubPage>       : /ppf-brands-pune/, /headlight-brands-pune/
// - <DealerLogoRows>      : homepage "Authorised dealer" logo rows
// Styles: app/styles/dealer.css.

/** Official logo in full colour, or a neutral text wordmark when no official file was found. */
export function DealerBrandMark({ brand }: { brand: DealerBrand }) {
  const logo = brand.logo ? getBrandBySlug(brand.logo) : undefined;
  if (logo) return <BrandLogo brand={logo} color />;
  return <span className="dealer-wordmark">{brand.name}</span>;
}

export function DealerBrandsSection({
  category,
  compact = false,
  current,
  panel = false,
}: {
  category: DealerCategory;
  /** Logo strip only (no notes), e.g. on the /car-lights/ hub. */
  compact?: boolean;
  /** Slug of the brand page this sits on, left out of the list. */
  current?: string;
  panel?: boolean;
}) {
  const info = dealerCategories[category];
  const list = dealerBrandsIn(category).filter((b) => b.slug !== current);
  const title = category === "ppf" ? "PPF brands we're authorised to sell and fit" : "Headlight brands we're authorised to sell and fit";

  return (
    <section className={["band", panel && "band--panel", compact && "band--tight"].filter(Boolean).join(" ")} aria-labelledby={`dealer-${category}-title`}>
      <div className="wrap">
        <Reveal>
          <div className="section-head">
            <p className="label label--yellow eyebrow">Authorised dealer</p>
            <h2 id={`dealer-${category}-title`}>{title}</h2>
            {!compact && (
              <p className="lead">
                {site.name} is an authorised dealer for these {info.product === "PPF" ? "PPF" : "headlight"} brands. Tap a brand for its page, or WhatsApp your car model for options and a price.
              </p>
            )}
          </div>
        </Reveal>
        {compact ? (
          <Reveal as="ul" className="dealer-strip">
            {list.map((b) => (
              <li key={b.slug}>
                <Link href={dealerBrandPath(b)} aria-label={`${dealerProductName(b)} in Pune`}>
                  <DealerBrandMark brand={b} />
                </Link>
              </li>
            ))}
          </Reveal>
        ) : (
          <Reveal as="ul" className="dealer-grid">
            {list.map((b) => (
              <li key={b.slug}>
                <Link href={dealerBrandPath(b)} className="dealer-card card--link">
                  <span className="dealer-card__mark"><DealerBrandMark brand={b} /></span>
                  <strong>{b.name}</strong>
                  <span className="dealer-card__note">{b.note}</span>
                  <span className="text-link">{dealerProductName(b)} in Pune <IconArrow /></span>
                </Link>
              </li>
            ))}
          </Reveal>
        )}
        <p className="dealer-legal">
          <Link href={info.hubPath}>All {info.hubLabel.toLowerCase()}</Link> · Brand names and logos belong to their owners.
        </p>
      </div>
    </section>
  );
}

/* ---------- Brand page ---------- */
// Each page leads with the brand's own data (intro, named range, three brand
// FAQs from lib/dealer-brands.ts), so most of the main copy is unique to the
// brand. The shared template is kept short: a hero line, two generic FAQs,
// the reviews carousel and the links.

function brandCopy(b: DealerBrand) {
  const info = dealerCategories[b.category];
  const product = dealerProductName(b);
  const isPpf = b.category === "ppf";
  const path = dealerBrandPath(b);
  const h1 = `${product} in Pune – authorised dealer`;
  const aka = b.alsoSearchedAs?.length ? ` (also searched as ${b.alsoSearchedAs.join(", ")})` : "";
  const lead = isPpf
    ? `${site.name} is an authorised ${b.name} dealer in Pune. We fit ${b.name} paint protection film${aka} at our own studio at ${site.addressShort}. WhatsApp your car model for ${b.name} options and a price.`
    : `${site.name} is an authorised ${b.name} dealer in Pune. We supply and fit ${b.name} lights${aka} at our shop at ${site.addressShort}. WhatsApp your car model for the ${b.name} options that fit and a price.`;
  const message = isPpf
    ? `Hi Jay Ganesh, I'd like a price for ${b.name} PPF for my car. Car model: `
    : `Hi Jay Ganesh, I'd like ${b.name} headlights for my car. Car model and year: `;

  // Two generic questions, after the brand's own three.
  const generic = isPpf
    ? [
        { q: `Are you an authorised ${b.name} PPF dealer in Pune?`, a: `Yes. ${site.name} is an authorised ${b.name} dealer at ${site.addressShort}, Pune, open every day from 10 am to 8 pm. We fit the film ourselves at our studio.` },
        { q: `What is the price of ${b.name} PPF?`, a: `It depends on the ${b.name} film, your car model and how much you cover: a front kit, high-impact areas or the full body. WhatsApp us your car model and we'll send the ${b.name} price for your car.` },
      ]
    : [
        { q: `Are you an authorised ${b.name} dealer in Pune?`, a: `Yes. ${site.name} is an authorised ${b.name} dealer at ${site.addressShort}, Pune, open every day from 10 am to 8 pm. Our own team fits, wires and aims the lights.` },
        { q: `What is the price of ${b.name} headlights?`, a: `It depends on your car model and the ${b.name} light you choose. WhatsApp us your car model, year and variant and we'll reply with the options and prices.` },
      ];
  const faqs = [...b.faqs, ...generic];

  const crumbs = [...info.parents, { label: info.hubLabel, href: info.hubPath }];
  const photoAlt = heroAlt[info.hero];
  // Lower-case the photo caption's first letter ("A technician …"), but not an acronym ("LED headlights …").
  const photoAltLc = /^[A-Z][a-z ]/.test(photoAlt) ? photoAlt.charAt(0).toLowerCase() + photoAlt.slice(1) : photoAlt;
  const heroAltText = `${product} in Pune at ${site.shortName}: ${photoAltLc}`;
  return { info, product, isPpf, path, h1, lead, message, faqs, crumbs, heroAltText };
}

export function dealerBrandMetadata(slug: string): Metadata {
  const b = getDealerBrand(slug);
  if (!b) return {};
  return pageMetadata({ title: b.title, description: b.description, path: dealerBrandPath(b) });
}

export function DealerBrandPage({ slug }: { slug: string }) {
  const b = getDealerBrand(slug);
  if (!b) notFound();
  const { info, product, isPpf, path, h1, lead, message, faqs, crumbs, heroAltText } = brandCopy(b);
  const siblings = dealerBrandsIn(b.category);
  const others = siblings.filter((x) => x.slug !== b.slug);
  // A short, different set of reviews on each brand page, so the carousel isn't a large identical block across them.
  const reviewOffset = siblings.findIndex((x) => x.slug === b.slug) * 4;
  const rangeTitle = isPpf ? `${b.name} PPF range we fit` : `${b.name} lights we fit`;

  return (
    <>
      <JsonLd
        data={graph(
          {
            "@type": "Service",
            "@id": `${site.url}${path}#service`,
            name: `${product}, supplied and fitted`,
            serviceType: info.serviceType,
            description: b.intro,
            url: site.url + path,
            brand: { "@type": "Brand", name: b.name, ...(b.alsoSearchedAs?.length ? { alternateName: b.alsoSearchedAs } : {}), ...(b.url ? { url: b.url } : {}) },
            provider: businessRef,
            areaServed: areaServedLd,
            ...(b.range.length
              ? {
                  hasOfferCatalog: {
                    "@type": "OfferCatalog",
                    name: rangeTitle,
                    itemListElement: b.range.map((r) => ({ "@type": "Offer", itemOffered: { "@type": "Product", name: r.name, brand: { "@type": "Brand", name: b.name } } })),
                  },
                }
              : {}),
          },
          webPageLd({ name: b.title, description: b.description, path }),
          faqLd(faqs, path),
          breadcrumbLd([...crumbs.map((c) => ({ name: c.label, path: c.href })), { name: product, path }]),
        )}
      />

      <PageHero
        crumbs={[...crumbs, { label: b.name }]}
        eyebrow={`Authorised ${b.name} dealer · Pune`}
        title={h1}
        lead={lead}
        image={info.hero}
        imageAlt={heroAltText}
      >
        <div className="actions">
          <WhatsAppButton message={message}>WhatsApp for a {b.name} quote</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--panel" aria-labelledby="brand-range-title">
        <Reveal className="wrap split split--start">
          <div className="stack-lg">
            <div className="dealer-lockup">
              <DealerBrandMark brand={b} />
              <p className="dealer-lockup__text"><strong>Authorised dealer</strong> · Pune</p>
            </div>
            <div className="section-head">
              <p className="label label--yellow eyebrow">About {b.name}</p>
              <h2 id="brand-range-title">{rangeTitle}</h2>
              <p className="lead">{b.intro}</p>
            </div>
            {b.url && (
              <p className="dealer-about">
                Product names and details are from {b.name}&apos;s own {b.url.includes("instagram.com") ? "Instagram" : "website"}:{" "}
                <a href={b.url} target="_blank" rel="noopener noreferrer">{b.url.replace(/^https?:\/\/(www\.)?/, "").replace(/\/$/, "")}</a>.
              </p>
            )}
            <div className="actions">
              <WhatsAppButton message={message}>Ask which {b.name} {isPpf ? "film" : "light"} suits my car</WhatsAppButton>
            </div>
          </div>
          {b.range.length > 0 ? (
            <div className="stack">
              <ul className="points">
                {b.range.map((r) => <li key={r.name}><strong>{r.name}</strong><span>{r.detail}</span></li>)}
              </ul>
              {b.rangeNote && <p className="dealer-about">{b.rangeNote}</p>}
            </div>
          ) : (
            <ul className="points">
              <li><strong>{b.name} range</strong><span>{b.rangeNote}</span></li>
              <li><strong>{isPpf ? "Coverage" : "Fitting"}</strong><span>{isPpf ? "Front kit, high-impact areas or the full body, fitted panel by panel at our studio." : "Fitted, wired and beam-aimed by our own team, then tested with you before you drive out."}</span></li>
            </ul>
          )}
        </Reveal>
      </section>

      <section className="band">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title={`${product}: common questions`} />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow={`Google reviews · ${site.rating}★`} title={isPpf ? "What customers say about our PPF work" : "What customers say about their lights"} /></Reveal>
          <ReviewsCarousel topics={info.topics} limit={6} offset={reviewOffset} />
        </div>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap stack-lg">
          <div className="stack">
            <p className="label">Other {isPpf ? "PPF" : "headlight"} brands we&apos;re authorised for</p>
            <ul className="chips">
              {others.map((x) => <li key={x.slug}><Link href={dealerBrandPath(x)}>{dealerProductName(x)}</Link></li>)}
            </ul>
          </div>
          <div className="stack">
            <p className="label">More</p>
            <ul className="chips">
              <li><Link href={info.servicePath}>{isPpf ? "PPF in Pune" : "Headlights"}</Link></li>
              <li><Link href={info.hubPath}>All {info.hubLabel.toLowerCase()}</Link></li>
              {isPpf ? <li><Link href="/ceramic-coating-pune/">Ceramic coating</Link></li> : <li><Link href="/car-lights/">Car lights</Link></li>}
            </ul>
          </div>
          <Visit />
        </Reveal>
      </section>

      <CtaBand title={`Send us your car model. We'll reply with a ${b.name} price.`} message={message} />
    </>
  );
}

/* ---------- Homepage dealer strip ---------- */

/** Compact "Authorised dealer" logo rows for the homepage: Autoform, then the PPF and headlight brands, each linking to its page. */
export function DealerLogoRows() {
  const autoform = getBrandBySlug("autoform");
  const rows: { key: string; label: string; hub?: { href: string; label: string }; items: { href: string; label: string; mark: React.ReactNode }[] }[] = [
    {
      key: "seat-covers",
      label: "Seat covers",
      items: [{ href: "/autoform-seat-covers-pune/", label: "Autoform seat covers in Pune", mark: autoform ? <BrandLogo brand={autoform} color /> : <span className="dealer-wordmark">Autoform</span> }],
    },
    ...(["ppf", "headlights"] as const).map((cat) => ({
      key: cat,
      label: cat === "ppf" ? "PPF" : "Headlights",
      hub: { href: dealerCategories[cat].hubPath, label: `All ${dealerCategories[cat].hubLabel.toLowerCase()}` },
      items: dealerBrandsIn(cat).map((b) => ({ href: dealerBrandPath(b), label: `${dealerProductName(b)} in Pune`, mark: <DealerBrandMark brand={b} /> })),
    })),
  ];

  return (
    <div className="dealer-rows">
      {rows.map((r) => (
        <div key={r.key} className="dealer-row">
          <p className="label dealer-row__label">{r.label}</p>
          <ul className="dealer-strip">
            {r.items.map((i) => (
              <li key={i.href}>
                <Link href={i.href} aria-label={i.label} title={i.label}>{i.mark}</Link>
              </li>
            ))}
          </ul>
          {r.hub && <Link className="text-link dealer-row__hub" href={r.hub.href}>{r.hub.label} <IconArrow /></Link>}
        </div>
      ))}
      <p className="dealer-legal">Brand names and logos belong to their owners.</p>
    </div>
  );
}

/* ---------- Hub page ---------- */

function hubCopy(category: DealerCategory) {
  const info = dealerCategories[category];
  const names = dealerBrandsIn(category).map((b) => b.name);
  const list = `${names.slice(0, -1).join(", ")} and ${names[names.length - 1]}`;
  const isPpf = category === "ppf";
  return {
    info,
    path: info.hubPath,
    title: isPpf ? "PPF Brands in Pune, Authorised Dealer | Jay Ganesh" : "Headlight Brands in Pune, Authorised Dealer | Jay Ganesh",
    h1: isPpf ? "PPF brands in Pune – authorised dealer" : "Headlight brands in Pune – authorised dealer",
    description: isPpf
      ? `${list} PPF in Pune from one authorised dealer, fitted at our studio. WhatsApp your car model.`
      : `${list} headlights in Pune from one authorised dealer, fitted at our shop. WhatsApp your car.`,
    lead: isPpf
      ? `${site.name} is an authorised dealer for ${list} paint protection film. Every film is fitted by our own team at our studio at ${site.addressShort}, on the Hadapsar side of Pune. Pick a brand, or WhatsApp your car model and we'll suggest options.`
      : `${site.name} is an authorised dealer for ${list} headlights and car lighting. Our own team fits them at our shop at ${site.addressShort}, on the Hadapsar side of Pune. Pick a brand, or WhatsApp your car model and we'll suggest what fits.`,
    message: isPpf ? "Hi Jay Ganesh, I'd like PPF brand options and prices for my car. Car model: " : "Hi Jay Ganesh, I'd like headlight brand options and prices for my car. Car model and year: ",
    faqs: isPpf
      ? [
          { q: "Which PPF brands are you an authorised dealer for?", a: `${list}. We fit all of them at our own studio in Loni Kalbhor, Pune.` },
          { q: "Which PPF brand should I choose?", a: "It depends on your car, your budget and the finish you want. WhatsApp us your car model and we'll explain the options side by side." },
          { q: "What does PPF cost?", a: "It depends on your car model, the brand and how much of the car you want covered. WhatsApp us your model for a price." },
        ]
      : [
          { q: "Which headlight brands are you an authorised dealer for?", a: `${list}. Our own team fits them at our shop in Loni Kalbhor, Pune.` },
          { q: "Which headlight brand should I choose?", a: "It depends on your car's headlamps, how you drive and your budget. WhatsApp us your car model and a photo of the headlights and we'll suggest options." },
          { q: "What does a headlight upgrade cost?", a: "It depends on your car model, the brand and the type of light. WhatsApp us your model for a price." },
        ],
  };
}

export function dealerHubMetadata(category: DealerCategory): Metadata {
  const c = hubCopy(category);
  return pageMetadata({ title: c.title, description: c.description, path: c.path });
}

export function DealerHubPage({ category }: { category: DealerCategory }) {
  const { info, path, title, h1, description, lead, message, faqs } = hubCopy(category);
  const list = dealerBrandsIn(category);
  const isPpf = category === "ppf";

  return (
    <>
      <JsonLd
        data={graph(
          webPageLd({ type: "CollectionPage", name: title, description, path }),
          {
            "@type": "ItemList",
            "@id": `${site.url}${path}#list`,
            itemListElement: list.map((b, i) => ({ "@type": "ListItem", position: i + 1, name: dealerProductName(b), url: site.url + dealerBrandPath(b) })),
          },
          faqLd(faqs, path),
          breadcrumbLd([...info.parents.map((c) => ({ name: c.label, path: c.href })), { name: info.hubLabel, path }]),
        )}
      />

      <PageHero
        crumbs={[...info.parents, { label: info.hubLabel }]}
        eyebrow="Authorised dealer · Pune"
        title={h1}
        lead={lead}
        image={info.hero}
      >
        <div className="actions">
          <WhatsAppButton message={message}>WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <DealerBrandsSection category={category} panel />

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow={`Google reviews · ${site.rating}★`} title={isPpf ? "What customers say about our PPF work" : "What customers say about their lights"} /></Reveal>
          <ReviewsCarousel topics={info.topics} />
        </div>
      </section>

      <section className="band band--panel">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title={`${info.hubLabel}: common questions`} />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap stack-lg">
          <div className="stack">
            <p className="label">More</p>
            <ul className="chips">
              <li><Link href={info.servicePath}>{isPpf ? "PPF in Pune" : "Headlights"}</Link></li>
              <li><Link href={isPpf ? dealerCategories.headlights.hubPath : dealerCategories.ppf.hubPath}>{isPpf ? "Headlight brands" : "PPF brands"}</Link></li>
              {isPpf ? <li><Link href="/detailing-studio/">Detailing studio</Link></li> : <li><Link href="/car-lights/">Car lights</Link></li>}
            </ul>
          </div>
          <Visit />
        </Reveal>
      </section>

      <CtaBand message={message} />
    </>
  );
}
