import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { CtaBand, Faq, IconArrow, JsonLd, PageHero, Photo, SectionHead, Visit, WhatsAppButton, CallButton } from "./ui";
import Reveal from "./Reveal";
import { popularCars } from "@/lib/catalog";
import { getHub, getProductCategory, howItWorks, hubs, productCategories, type HubSlug } from "@/lib/products";
import type { Topic } from "@/lib/reviews";
import ReviewsCarousel from "./ReviewsCarousel";
import BrandLogos from "./BrandLogos";
import { getBrand, type Brand } from "@/lib/brands";

// Which Google reviews each category page shows.
const topicsFor: Record<string, Topic[]> = {
  headlights: ["lighting"],
  "ambient-lighting": ["ambient", "lighting"],
  "android-stereo": ["audio"],
  "360-camera": ["camera", "audio"],
  dashcams: ["dashcam", "camera"],
};
import { site, whatsapp } from "@/lib/site";
import { breadcrumbLd, faqLd, graph, pageMetadata, serviceLd, webPageLd } from "@/lib/seo";

// Shared template for the accessory product-category pages (headlights,
// ambient lighting, Android stereo, 360° camera, dashcams) and their hubs
// (/car-lights/, /audio-screens/). Content lives in lib/products.ts.

const accessories = { label: "Accessories", href: "/accessories/" };

function crumbsFor(hub?: HubSlug) {
  const h = hub ? getHub(hub) : undefined;
  return h ? [accessories, { label: h.menuLabel, href: h.path }] : [accessories];
}

const askMessage = (item: string, car?: string) =>
  car ? `Hi Jay Ganesh, I'd like ${item} for my ${car}. Year and variant: ` : `Hi Jay Ganesh, I'd like ${item}. Car model and year: `;

export function productCategoryMetadata(slug: string): Metadata {
  const c = getProductCategory(slug);
  if (!c) return {};
  return pageMetadata({ title: c.title, description: c.description, path: c.path });
}

export function hubMetadata(slug: HubSlug): Metadata {
  const h = getHub(slug);
  return pageMetadata({ title: h.title, description: h.description, path: h.path });
}

export default function ProductCategoryPage({ slug }: { slug: string }) {
  const c = getProductCategory(slug);
  if (!c) notFound();

  const crumbs = crumbsFor(c.hub);
  const message = askMessage(c.item);
  const related = productCategories.filter((p) => p.slug !== c.slug);

  return (
    <>
      <JsonLd
        data={graph(
          serviceLd({ name: c.h1, serviceType: c.serviceType, description: c.lead, path: c.path }),
          webPageLd({ name: c.title, description: c.description, path: c.path }),
          faqLd(c.faqs, c.path),
          breadcrumbLd([...crumbs.map((x) => ({ name: x.label, path: x.href })), { name: c.menuLabel, path: c.path }]),
        )}
      />

      <PageHero
        crumbs={[...crumbs, { label: c.menuLabel }]}
        eyebrow={c.eyebrow}
        title={c.h1}
        lead={c.lead}
        media={<Photo label={c.photoLabel} m={c.photo} ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <p className="label">Price on WhatsApp · fitted at our shop</p>
        <div className="actions">
          <WhatsAppButton message={message}>WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--panel">
        <Reveal className="wrap split split--start">
          <div className="stack-lg">
            <SectionHead eyebrow="What we fit" title={`${c.menuLabel}: what we fit`} lead="Supplied and fitted by our own team, chosen for your car model." />
            {c.brands.length > 0 && (
              <div className="stack">
                <p className="label">Brands we&apos;ve fitted include</p>
                <BrandLogos brands={c.brands.map(getBrand).filter((b): b is Brand => Boolean(b))} />
                {c.brands.some((b) => !getBrand(b)) && (
                  <ul className="chips">{c.brands.filter((b) => !getBrand(b)).map((b) => <li key={b}><span>{b}</span></li>)}</ul>
                )}
                {c.brandsNote && <p className="pc-note">{c.brandsNote}</p>}
              </div>
            )}
          </div>
          <ul className="points">
            {c.whatWeFit.map((w) => <li key={w.name}><strong>{w.name}</strong><span>{w.detail}</span></li>)}
          </ul>
        </Reveal>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="For your car" title={`${c.menuLabel} by car`} lead="Tap your model to ask for options and prices on WhatsApp." /></Reveal>
          <Reveal className="cars">
            {popularCars.map((car) => (
              <a key={car} href={whatsapp(askMessage(c.item, car))} target="_blank" rel="noopener noreferrer" data-track="whatsapp" aria-label={`${car}: ask about ${c.item} on WhatsApp`}>
                <strong>{car.split(" ").slice(1).join(" ")}</strong>
                <span className="label">{car.split(" ")[0]}</span>
              </a>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow={`Google reviews · ${site.rating}★`} title={c.reviewsTitle} /></Reveal>
          <ReviewsCarousel topics={topicsFor[c.slug] ?? ["lighting"]} />
        </div>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="How it works" title="Three steps" /></Reveal>
          <Reveal as="ol" className="process">{howItWorks(c.item).map((s) => <li key={s.step}><strong>{s.step}</strong><span>{s.detail}</span></li>)}</Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title={`${c.menuLabel}: common questions`} />
          <Faq items={c.faqs} />
        </Reveal>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap stack-lg">
          <div className="stack">
            <p className="label">More accessories</p>
            <ul className="chips">
              {related.map((p) => <li key={p.path}><Link href={p.path}>{p.menuLabel}</Link></li>)}
              <li><Link href="/seat-covers/">Seat covers</Link></li>
              <li><Link href="/accessories/">All accessories</Link></li>
            </ul>
          </div>
          <Visit />
        </Reveal>
      </section>

      <CtaBand message={message} />
    </>
  );
}

export function HubPage({ slug }: { slug: HubSlug }) {
  const h = getHub(slug);
  const children = h.children.map((s) => getProductCategory(s)!);
  const message = `Hi Jay Ganesh, I'm looking for ${h.menuLabel.toLowerCase()}. Car model: `;

  return (
    <>
      <JsonLd
        data={graph(
          webPageLd({ type: "CollectionPage", name: h.title, description: h.description, path: h.path }),
          {
            "@type": "ItemList",
            "@id": `${site.url}${h.path}#list`,
            itemListElement: children.map((c, i) => ({ "@type": "ListItem", position: i + 1, name: c.menuLabel, url: site.url + c.path })),
          },
          breadcrumbLd([{ name: accessories.label, path: accessories.href }, { name: h.menuLabel, path: h.path }]),
        )}
      />

      <PageHero
        crumbs={[accessories, { label: h.menuLabel }]}
        eyebrow={h.eyebrow}
        title={h.h1}
        lead={h.lead}
        media={<Photo label={h.menuLabel} m={h.photo} ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <div className="actions">
          <WhatsAppButton message={message}>WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Choose a type" title={`${h.menuLabel} we fit`} /></Reveal>
          <Reveal className="grid grid-3">
            {children.map((c) => (
              <Link key={c.path} href={c.path} className="cat card--link">
                <Photo label={c.photoLabel} m={c.photo} ratio="16 / 10" />
                <h3>{c.menuLabel}</h3>
                <p>{c.description.split(". ")[0]}.</p>
                <span className="text-link">See {c.menuLabel.toLowerCase()} <IconArrow /></span>
              </Link>
            ))}
            {h.more.map((m) => (
              <article key={m.name} className="cat">
                <Photo label={m.name} m={m.photo} ratio="16 / 10" />
                <h3>{m.name}</h3>
                <p>{m.blurb}</p>
                <WhatsAppButton variant="ghost" size="sm" message={`Hi Jay Ganesh, I'm looking for ${m.name.toLowerCase()}. Car model: `}>Ask for a price</WhatsAppButton>
              </article>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap stack-lg">
          <div className="stack">
            <p className="label">Other accessories</p>
            <ul className="chips">
              {hubs.filter((x) => x.slug !== h.slug).map((x) => <li key={x.path}><Link href={x.path}>{x.menuLabel}</Link></li>)}
              <li><Link href="/dashcams/">Dashcams</Link></li>
              <li><Link href="/seat-covers/">Seat covers</Link></li>
              <li><Link href="/accessories/">All accessories</Link></li>
            </ul>
          </div>
          <Visit />
        </Reveal>
      </section>

      <CtaBand message={message} />
    </>
  );
}
