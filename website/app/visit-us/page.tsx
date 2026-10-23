import type { Metadata } from "next";
import { breadcrumbLd, graph, pageMetadata, webPageLd } from "@/lib/seo";
import { JsonLd, Photo, SectionHead, WhatsAppButton, IconArrow, IconPhone, IconPin } from "../components/ui";
import { PageHero } from "../components/PageHero";
import Reveal from "../components/Reveal";
import { site } from "@/lib/site";

const path = "/visit-us/";
const title = "Visit Us: Kawadipat Toll Naka, Loni Kalbhor | Jay Ganesh";
const description =
  "Find Jay Ganesh Car Accessories at Kawadipat Toll Naka, Pune–Solapur Road, Loni Kalbhor. Open every day, 10 am to 8 pm. Call or WhatsApp +91 99813 97676.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const from = [
  { area: "Hadapsar", how: "Take the Pune–Solapur road east past Manjri; the shop is at Kawadipat Toll Naka." },
  { area: "Manjri", how: "Head east on the Pune–Solapur road towards Loni Kalbhor; we're at the toll naka." },
  { area: "Uruli Kanchan", how: "Drive west on the Pune–Solapur road towards Pune; the shop is at Kawadipat Toll Naka." },
];

export default function VisitUs() {
  return (
    <>
      <JsonLd data={graph(webPageLd({ name: title, description, path }), breadcrumbLd([{ name: "Visit us", path }]))} />
      <PageHero
        image="detailing-studio"
        crumbs={[{ label: "Visit us" }]}
        eyebrow="Loni Kalbhor"
        title="Visit the shop"
        lead={
          <address className="stack" style={{ fontStyle: "normal" }}>
            <span className="lead" style={{ color: "var(--ink)" }}>{site.address}</span>
            <span className="label">{site.hours}</span>
          </address>
        }
      >
        <div className="actions">
          <a className="btn btn--primary" href={site.mapsHref} target="_blank" rel="noopener noreferrer" data-track="directions"><IconPin />Get directions</a>
          <a className="btn btn--ghost" href={site.phoneHref} data-track="call"><IconPhone /><span className="notranslate" translate="no">{site.phoneDisplay}</span></a>
        </div>
      </PageHero>

      <section className="band band--panel">
        <Reveal className="wrap split split--start">
          <div className="stack-lg">
            <SectionHead eyebrow="Getting here" title="On the Pune–Solapur road" lead="We're right at Kawadipat Toll Naka in Loni Kalbhor." />
            <a className="map-card" href={site.mapsHref} target="_blank" rel="noopener noreferrer" aria-label="Open the shop's location in Google Maps">
              <span className="map-pin" aria-hidden />
              <span className="label">Kawadipat Toll Naka · Pune–Solapur Road</span>
              <span className="text-link">Open in Google Maps <IconArrow /></span>
            </a>
          </div>
          <dl className="specs">
            {from.map((f) => (
              <div key={f.area}><dt className="label">From {f.area}</dt><dd>{f.how}</dd></div>
            ))}
            <div><dt className="label">Hours</dt><dd>{site.hours}</dd></div>
            <div><dt className="label">Phone and WhatsApp</dt><dd><a className="notranslate" translate="no" href={site.phoneHref}>{site.phoneDisplay}</a></dd></div>
            <div><dt className="label">Payment</dt><dd>UPI, cards and cash</dd></div>
          </dl>
        </Reveal>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="When you arrive" title="The shop and the studio" /></Reveal>
          <Reveal className="grid grid-3">
            <Photo label="Shop front from the highway" m="studioBay" />
            <Photo label="Accessories display inside" />
            <Photo label="Studio entrance" m="hero" />
          </Reveal>
          <div className="actions" style={{ marginTop: 36 }}>
            <WhatsAppButton message="Hi Jay Ganesh, I'd like to visit. When is a good time? Car model: ">Tell us you&apos;re coming</WhatsAppButton>
          </div>
        </div>
      </section>
    </>
  );
}
