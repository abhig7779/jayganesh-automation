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
import { ppf } from "@/lib/services/ppf";
import { getProductCategory, howItWorks } from "@/lib/products";
import { site } from "@/lib/site";
import { areaServedLd, breadcrumbLd, businessRef, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";

// Authorised-dealer brand content, all from lib/dealer-brands.ts:
// - <DealerBrandsSection> : logo grid with a line per brand, linking to its page
//   (/ppf-pune/, /car-lights/headlights/; `compact` strip on /car-lights/).
// - <DealerBrandPage>     : /garware-ppf-pune/, /dhc-headlights-pune/ …
// - <DealerHubPage>       : /ppf-brands-pune/, /headlight-brands-pune/
// Styles: app/styles/dealer.css.

const shortTitle = (t: string) => `${t} | ${site.shortName}`;

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

function brandCopy(b: DealerBrand) {
  const info = dealerCategories[b.category];
  const product = dealerProductName(b);
  const isPpf = b.category === "ppf";
  const path = dealerBrandPath(b);
  const h1 = `${product} in Pune – authorised dealer`;
  const title = isPpf ? `${b.name} PPF in Pune, Authorised Dealer | Jay Ganesh` : `${b.name} Headlights in Pune, Authorised Dealer | Jay Ganesh`;
  const aka = b.alsoSearchedAs?.length ? ` (also searched as ${b.alsoSearchedAs.join(", ")})` : "";
  const description = isPpf
    ? `Authorised ${b.name} PPF dealer in Pune. ${b.name} paint protection film fitted at our studio on the Pune–Solapur Road, Loni Kalbhor, near Hadapsar. WhatsApp your car model.`
    : `Authorised ${b.name} headlight dealer in Pune. ${b.name} LED and projector lights fitted at our shop on the Pune–Solapur Road, Loni Kalbhor, near Hadapsar. WhatsApp your model.`;
  const lead = isPpf
    ? `${site.name} is an authorised ${b.name} dealer in Pune. We fit ${b.name} paint protection film${aka} at our own studio at ${site.addressShort}, on the Hadapsar side of Pune. Send us your car model on WhatsApp for the ${b.name} options and a price.`
    : `${site.name} is an authorised ${b.name} dealer in Pune. We supply and fit ${b.name} headlights${aka} at our shop at ${site.addressShort}, on the Hadapsar side of Pune. Send us your car model on WhatsApp for the ${b.name} options that fit it and a price.`;
  const message = isPpf
    ? `Hi Jay Ganesh, I'd like a price for ${b.name} PPF for my car. Car model: `
    : `Hi Jay Ganesh, I'd like ${b.name} headlights for my car. Car model and year: `;

  const faqs = isPpf
    ? [
        { q: `Are you an authorised ${b.name} PPF dealer in Pune?`, a: `Yes. ${site.name} is an authorised ${b.name} dealer, at ${site.addressShort}, Pune. We fit the film at our own studio.` },
        { q: `What is the price of ${b.name} PPF?`, a: `It depends on your car model and how much of the car you want covered: a front kit, high-impact areas or the full body. WhatsApp us your car model and we'll send the ${b.name} price for your car.` },
        { q: `What warranty comes with ${b.name} PPF?`, a: `PPF comes with a warranty card. The terms depend on the ${b.name} film you choose, so ask us on WhatsApp and we'll share the details for your film before you book.` },
        { q: `How long does ${b.name} PPF fitting take?`, a: "It depends on how much of the car is covered. We'll confirm the time when you book, and the car stays in our studio while the film is fitted and settles." },
        { q: `Where can I get ${b.name} PPF fitted in Pune?`, a: `At our studio at ${site.addressShort}, on the Pune–Solapur Road. We're open every day, 10 am to 8 pm. WhatsApp or call ${site.phoneDisplay} to book a slot.` },
      ]
    : [
        { q: `Are you an authorised ${b.name} dealer in Pune?`, a: `Yes. ${site.name} is an authorised ${b.name} dealer, at ${site.addressShort}, Pune. Our own team fits the lights at the shop.` },
        { q: `What is the price of ${b.name} headlights?`, a: `It depends on your car model and the ${b.name} light you choose. WhatsApp us your car model, year and variant and we'll reply with the options and prices.` },
        { q: `Will ${b.name} headlights fit my car?`, a: "The right fitting depends on the model, year and headlamp type. Send a photo of your headlights with the model on WhatsApp and we'll confirm what fits." },
        { q: `Do you fit ${b.name} lights at the shop?`, a: "Yes. Our team fits and wires the lights at Loni Kalbhor, aims the beam, and tests everything with you before you drive out." },
        { q: `Where can I buy ${b.name} headlights in Pune?`, a: `At our shop at ${site.addressShort}, on the Pune–Solapur Road, open every day from 10 am to 8 pm. WhatsApp or call ${site.phoneDisplay} first so we can check stock for your model.` },
      ];

  const crumbs = [...info.parents, { label: info.hubLabel, href: info.hubPath }];
  return { info, product, isPpf, path, h1, title, description, lead, message, faqs, crumbs };
}

export function dealerBrandMetadata(slug: string): Metadata {
  const b = getDealerBrand(slug);
  if (!b) return {};
  const c = brandCopy(b);
  return pageMetadata({ title: c.title, description: c.description, path: c.path });
}

export function DealerBrandPage({ slug }: { slug: string }) {
  const b = getDealerBrand(slug);
  if (!b) notFound();
  const { info, product, isPpf, path, h1, title, description, lead, message, faqs, crumbs } = brandCopy(b);
  const headlights = getProductCategory("headlights");

  const whatWeDo = isPpf
    ? ppf.packages.map((p) => ({ name: `${b.name} PPF: ${p.name.toLowerCase()}`, detail: p.includes.join(", ") + "." }))
    : (headlights?.whatWeFit ?? []).map((w) => ({ name: w.name, detail: w.detail }));
  const steps = isPpf ? ppf.process : howItWorks(`${b.name} headlights`);
  const others = dealerBrandsIn(b.category).filter((x) => x.slug !== b.slug);

  return (
    <>
      <JsonLd
        data={graph(
          {
            "@type": "Service",
            "@id": `${site.url}${path}#service`,
            name: `${product}, supplied and fitted`,
            serviceType: info.serviceType,
            description: lead,
            url: site.url + path,
            brand: { "@type": "Brand", name: b.name, ...(b.url ? { url: b.url } : {}) },
            provider: businessRef,
            areaServed: areaServedLd,
          },
          webPageLd({ name: title, description, path }),
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
      >
        <div className="actions">
          <WhatsAppButton message={message}>WhatsApp for a {b.name} quote</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--tight" aria-label={`Authorised ${b.name} dealer`}>
        <div className="wrap">
          <div className="dealer-lockup">
            <DealerBrandMark brand={b} />
            <p className="dealer-lockup__text"><strong>Authorised dealer</strong> · Pune</p>
          </div>
        </div>
      </section>

      <section className="band band--panel">
        <Reveal className="wrap split split--start">
          <div className="stack-lg">
            <SectionHead
              eyebrow="What we do"
              title={isPpf ? `${b.name} PPF, fitted at our studio` : `${b.name} lights, fitted at our shop`}
              lead={isPpf ? `We supply ${b.name} film and fit it ourselves, panel by panel. Choose how much of the car you want covered.` : `We supply ${b.name} lights chosen for your car model and fit them ourselves.`}
            />
            {b.about && <p className="dealer-about">{b.about}{b.url && <> <a href={b.url} target="_blank" rel="noopener noreferrer">{b.name} website</a>.</>}</p>}
            <div className="actions">
              <WhatsAppButton message={message}>Ask for {b.name} options</WhatsAppButton>
            </div>
          </div>
          <ul className="points">
            {whatWeDo.map((w) => <li key={w.name}><strong>{w.name}</strong><span>{w.detail}</span></li>)}
          </ul>
        </Reveal>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="How it works" title={isPpf ? "From inspection to handover" : "Three steps"} /></Reveal>
          <Reveal as="ol" className="process">{steps.map((s) => <li key={s.step}><strong>{s.step}</strong><span>{s.detail}</span></li>)}</Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow={`Google reviews · ${site.rating}★`} title={isPpf ? "What customers say about our PPF work" : "What customers say about their lights"} /></Reveal>
          <ReviewsCarousel topics={info.topics} />
        </div>
      </section>

      <section className="band">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title={`${product}: common questions`} />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight band--panel">
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
      ? `Authorised dealer for ${list} PPF in Pune. Fitted at our studio on the Pune–Solapur Road, Loni Kalbhor. WhatsApp your car model.`
      : `Authorised dealer for ${list} headlights in Pune. Fitted at our Loni Kalbhor shop on the Pune–Solapur Road. WhatsApp your model.`,
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
