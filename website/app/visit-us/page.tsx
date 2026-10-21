import type { Metadata } from "next";
import { Crumbs, Photo, SectionHead, WhatsAppButton, IconPhone, IconPin } from "../components/ui";
import { site } from "@/lib/site";

export const metadata: Metadata = {
  title: { absolute: "Visit Us: Kawadipat Toll Naka, Loni Kalbhor | Jay Ganesh Car Accessories" },
  description: "Find Jay Ganesh Car Accessories at Kawadipat Toll Naka on the Pune–Solapur road, Loni Kalbhor. Open every day, 10 am to 8 pm. Call or WhatsApp +91 99813 97676.",
  alternates: { canonical: "/visit-us/" },
};

const from = [
  { area: "Hadapsar", how: "Take the Pune–Solapur road east past Manjri; the shop is at Kawadipat Toll Naka." },
  { area: "Manjri", how: "Head east on the Pune–Solapur road towards Loni Kalbhor; we're at the toll naka." },
  { area: "Uruli Kanchan", how: "Drive west on the Pune–Solapur road towards Pune; the shop is at Kawadipat Toll Naka." },
];

export default function VisitUs() {
  return (
    <>
      <section className="page-hero">
        <div className="wrap">
          <div className="page-hero-copy">
            <Crumbs items={[{ label: "Visit us" }]} />
            <p className="label label--yellow">Loni Kalbhor</p>
            <h1>Visit the shop</h1>
            <hr className="rule" />
            <address style={{ fontStyle: "normal" }} className="stack">
              <span className="lead" style={{ color: "var(--ink)" }}>{site.address}</span>
              <span className="label">{site.hours}</span>
            </address>
            <div className="actions">
              <a className="btn btn--primary" href={site.mapsHref} target="_blank" rel="noopener noreferrer" data-track="directions"><IconPin />Get directions</a>
              <a className="btn btn--ghost" href={site.phoneHref} data-track="call"><IconPhone />{site.phoneDisplay}</a>
            </div>
          </div>
          <a className="map-card" href={site.mapsHref} target="_blank" rel="noopener noreferrer" aria-label="Open the shop's location in Google Maps">
            <span className="map-pin" aria-hidden />
            <span className="label">Kawadipat Toll Naka · Pune–Solapur Road</span>
            <span className="text-link">Open in Google Maps →</span>
          </a>
        </div>
      </section>

      <section className="band band--paper">
        <div className="wrap split" style={{ alignItems: "start" }}>
          <SectionHead eyebrow="Getting here" title="On the Pune–Solapur road" lead="We're right at Kawadipat Toll Naka in Loni Kalbhor." />
          <dl className="specs">
            {from.map((f) => (
              <div key={f.area}><dt className="label">From {f.area}</dt><dd>{f.how}</dd></div>
            ))}
            <div><dt className="label">Hours</dt><dd>{site.hours}</dd></div>
            <div><dt className="label">Phone and WhatsApp</dt><dd><a href={site.phoneHref}>{site.phoneDisplay}</a></dd></div>
            <div><dt className="label">Payment</dt><dd>UPI, cards and cash</dd></div>
          </dl>
        </div>
      </section>

      <section className="band">
        <div className="wrap">
          <SectionHead eyebrow="When you arrive" title="The shop and the studio" />
          <div className="grid grid-3">
            <Photo label="Shop front from the highway" m="studioBay" />
            <Photo label="Accessories display inside" />
            <Photo label="Studio entrance" m="hero" />
          </div>
          <div className="actions" style={{ marginTop: 28 }}>
            <WhatsAppButton message="Hi Jay Ganesh, I'd like to visit. When is a good time? Car model: ">Tell us you&apos;re coming</WhatsAppButton>
          </div>
        </div>
      </section>
    </>
  );
}
