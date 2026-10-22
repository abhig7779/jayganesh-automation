import type { Metadata } from "next";
import Link from "next/link";
import { CtaBand, Faq, JsonLd, PageHero, Photo, Price, SectionHead, Visit, WhatsAppButton, CallButton, IconArrow } from "../components/ui";
import Reveal from "../components/Reveal";
import { categories } from "@/lib/catalog";
import { breadcrumbLd, faqLd, graph, pageMetadata, serviceLd, webPageLd } from "@/lib/seo";

const path = "/accessories/";
const title = "Car Accessories in Pune: Seat Covers, Lights | Jay Ganesh";
const description = "Seat covers, LED headlights, fog lamps, Android stereos, 360° cameras and dashcams, fitted at our Loni Kalbhor shop, Pune. WhatsApp your car model.";
const lead = "Jay Ganesh Car Accessories sells and fits seat covers, LED headlights, fog lamps, ambient lighting, Android stereos, speakers, 360° cameras and dashcams, chosen for your car model. Everything is fitted by our own team at Kawadipat Toll Naka on the Pune–Solapur road, Loni Kalbhor, for car owners across Pune.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const faqs = [
  { q: "Which car accessories do you fit?", a: "Seat covers, floor mats and interior trims, LED and projector headlights, fog lamps, ambient lighting, Android stereos, speakers and subwoofers, 360° cameras, dashcams and exterior accessories." },
  { q: "Do you fit the accessories at your shop?", a: "Yes. Everything we sell is fitted by our own team at the shop in Loni Kalbhor, with wiring tucked away and everything tested before you drive out." },
  { q: "Will an Android stereo fit my car?", a: "Most cars can take a touchscreen stereo with the right frame and wiring for the dashboard. Send your model, year and variant on WhatsApp and we'll confirm what fits." },
  { q: "How do I get a price for an accessory?", a: "WhatsApp +91 99813 97676 with your car model, year and what you're looking for. Prices depend on the car and the product, so we reply with options for your model." },
];

const groups = ["Interior", "Lights", "Tech", "Exterior"] as const;

export default function Accessories() {
  return (
    <>
      <JsonLd
        data={graph(
          serviceLd({ name: "Car accessories, supplied and fitted", serviceType: "Car accessories installation", description: lead, path }),
          webPageLd({ name: title, description, path }),
          faqLd(faqs, path),
          breadcrumbLd([{ name: "Accessories", path }]),
        )}
      />
      <PageHero
        crumbs={[{ label: "Accessories" }]}
        eyebrow="Fitted at our shop"
        title="Car accessories in Pune"
        lead={lead}
        media={<Photo label="The accessories shop floor" m="interior" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <div className="actions">
          <WhatsAppButton message="Hi Jay Ganesh, I'm looking for accessories. Car model: ">WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <nav className="group-nav" aria-label="Accessory groups">
        <div className="wrap">
          <ul className="chips">
            {groups.map((g) => <li key={g}><a href={`#g-${g}`}>{g}</a></li>)}
          </ul>
        </div>
      </nav>

      {groups.map((g, gi) => (
        <section key={g} className={`band${gi % 2 ? " band--panel" : ""}`} aria-labelledby={`g-${g}`}>
          <div className="wrap">
            <Reveal className="section-head">
              <p className="label label--yellow eyebrow">{`0${gi + 1}`}</p>
              <h2 id={`g-${g}`} style={{ scrollMarginTop: "calc(var(--header-h) + 96px)" }}>{g}</h2>
            </Reveal>
            <Reveal className="grid grid-3">
              {categories.filter((c) => c.group === g).map((c) => (
                <article key={c.key} id={c.key} className="cat">
                  <Photo label={`${c.name}, fitted in a customer's car`} m={c.photo} ratio="16 / 10" />
                  <h3>{c.name}</h3>
                  <p>{c.blurb}</p>
                  <Price k={c.price} />
                  {c.href ? (
                    <Link className="btn btn--ghost btn--sm" href={c.href}>See {c.name.toLowerCase()} <IconArrow /></Link>
                  ) : (
                    <WhatsAppButton variant="ghost" size="sm" message={`Hi Jay Ganesh, I'm looking for ${c.name.toLowerCase()}. Car model: `}>Ask for a price</WhatsAppButton>
                  )}
                </article>
              ))}
            </Reveal>
          </div>
        </section>
      ))}

      <section className="band band--panel">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title="Accessories: common questions" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap"><Visit /></Reveal>
      </section>
      <CtaBand message="Hi Jay Ganesh, I'm looking for accessories. Car model: " />
    </>
  );
}
