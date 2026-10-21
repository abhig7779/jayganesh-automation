import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { CtaBand, Faq, JsonLd, PageHero, Photo, Price, SectionHead, Visit, WhatsAppButton, CallButton } from "../components/ui";
import Reveal from "../components/Reveal";
import { getService, services } from "@/lib/services";
import { studioLinks } from "@/lib/catalog";
import { facts } from "@/lib/prices";
import { site } from "@/lib/site";

export const dynamicParams = false;

export function generateStaticParams() {
  return services.map((s) => ({ service: s.slug }));
}

export async function generateMetadata({ params }: { params: Promise<{ service: string }> }): Promise<Metadata> {
  const s = getService((await params).service);
  if (!s) return {};
  return { title: { absolute: `${s.title} | ${site.shortName}` }, description: s.description, alternates: { canonical: `/${s.slug}/` } };
}

export default async function ServicePage({ params }: { params: Promise<{ service: string }> }) {
  const s = getService((await params).service);
  if (!s) notFound();

  const warranty =
    s.slug === "ppf-pune" && facts.ppfFilmBrand
      ? `${facts.ppfFilmBrand} film${facts.ppfWarrantyYears ? `, with a ${facts.ppfWarrantyYears}-year warranty card` : ""}.`
      : null;

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@graph": [
            { "@type": "Service", name: s.h1, serviceType: s.menuLabel, description: s.description, areaServed: "Pune", provider: { "@type": "AutoRepair", name: site.name, telephone: site.phoneE164 } },
            { "@type": "FAQPage", mainEntity: s.faqs.map((f) => ({ "@type": "Question", name: f.q, acceptedAnswer: { "@type": "Answer", text: f.a } })) },
            { "@type": "BreadcrumbList", itemListElement: [
              { "@type": "ListItem", position: 1, name: "Home", item: site.url + "/" },
              { "@type": "ListItem", position: 2, name: "Detailing studio", item: site.url + "/detailing-studio/" },
              { "@type": "ListItem", position: 3, name: s.menuLabel, item: `${site.url}/${s.slug}/` },
            ] },
          ],
        }}
      />

      <PageHero
        crumbs={[{ label: "Detailing studio", href: "/detailing-studio/" }, { label: s.menuLabel }]}
        eyebrow={s.eyebrow}
        title={s.h1}
        lead={s.lead}
        media={<Photo label={s.photoSlots[0]} m={s.photos[0]} ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        {warranty && <p className="label">{warranty}</p>}
        <div className="actions">
          <WhatsAppButton message={s.whatsappMessage}>WhatsApp for a price</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--panel">
        <Reveal className="wrap split split--start">
          <div className="stack-lg">
            <SectionHead eyebrow="In plain words" title={`What is ${s.menuLabel.toLowerCase() === "ppf" ? "PPF" : s.menuLabel.toLowerCase()}?`} />
            <div className="prose">{s.whatItIs.map((p) => <p key={p}>{p}</p>)}</div>
          </div>
          <div className="stack">
            <p className="label">Good for</p>
            <ul className="points">{s.goodFor.map((g) => <li key={g}><strong>{g}</strong></li>)}</ul>
          </div>
        </Reveal>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Packages" title={s.packagesTitle} lead="Prices depend on your car's size and paint. Send your model on WhatsApp for an exact price." /></Reveal>
          <Reveal className={`grid ${s.packages.length > 2 ? "grid-3" : "grid-2"}`}>
            {s.packages.map((p) => (
              <article key={p.name} className={`package${p.featured ? " package--featured" : ""}`}>
                <div className="stack">
                  {p.featured && <p className="label label--yellow">Most chosen</p>}
                  <h3>{p.name}</h3>
                </div>
                <ul>{p.includes.map((i) => <li key={i}>{i}</li>)}</ul>
                <div className="spec-row"><span className="label">Time</span><span>{p.time}</span></div>
                <Price k={p.price} />
                <WhatsAppButton variant={p.featured ? "primary" : "ghost"} size="sm" message={`${s.whatsappMessage}(${p.name}) `}>Ask for this</WhatsAppButton>
              </article>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="How we work" title="The process" /></Reveal>
          <Reveal as="ol" className="process">
            {s.process.map((p) => <li key={p.step}><strong>{p.step}</strong><span>{p.detail}</span></li>)}
          </Reveal>
        </div>
      </section>

      {s.compare && (
        <section className="band">
          <Reveal className="wrap">
            <SectionHead eyebrow="Compared" title="PPF, ceramic or wax?" />
            <div className="table-wrap">
              <table className="compare">
                <thead><tr><th scope="col"><span className="sr-only">Feature</span></th>{s.compare.columns.map((c) => <th key={c} scope="col">{c}</th>)}</tr></thead>
                <tbody>
                  {s.compare.rows.map((r, i) => (
                    <tr key={r}><th scope="row">{r}</th>{s.compare!.cells[i].map((c, j) => <td key={j}>{c}</td>)}</tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Reveal>
        </section>
      )}

      <section className={s.compare ? "band band--panel" : "band"}>
        <div className="wrap">
          <Reveal><SectionHead eyebrow="From the studio" title="Recent work" /></Reveal>
          <Reveal className="grid grid-3">
            {s.photoSlots.slice(1).map((p, i) => <Photo key={p} label={p} m={s.photos[i + 1]} />)}
          </Reveal>
        </div>
      </section>

      <section className={s.compare ? "band" : "band band--panel"}>
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title={`${s.menuLabel === "PPF" ? "PPF" : s.menuLabel}: common questions`} />
          <Faq items={s.faqs} />
        </Reveal>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap stack-lg">
          <div className="stack">
            <p className="label">Also in the studio</p>
            <ul className="chips">
              {studioLinks.filter((l) => l.href !== `/${s.slug}/`).map((l) => <li key={l.href}><Link href={l.href}>{l.label}</Link></li>)}
            </ul>
          </div>
          <Visit />
        </Reveal>
      </section>

      <CtaBand message={s.whatsappMessage} />
    </>
  );
}
