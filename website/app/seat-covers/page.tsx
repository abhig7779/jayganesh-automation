import type { Metadata } from "next";
import Link from "next/link";
import "../styles/local.css";
import ReviewsCarousel from "../components/ReviewsCarousel";
import { CtaBand, Faq, JsonLd, Photo, Price, SectionHead, Visit, WhatsAppButton, CallButton, IconArrow } from "../components/ui";
import { PageHero } from "../components/PageHero";
import Reveal from "../components/Reveal";
import { popularCars } from "@/lib/catalog";
import { site, whatsapp } from "@/lib/site";
import { areaServedLd, breadcrumbLd, businessRef, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";
import { autoformRanges, customDesigns, customMaterials, designDisclaimer } from "@/lib/seat-covers";
import { BrandLogo } from "../components/BrandLogos";
import { getBrandBySlug } from "@/lib/brands";

const autoform = getBrandBySlug("autoform");

const path = "/seat-covers/";
const title = "Car Seat Covers in Pune, Made to Fit | Jay Ganesh";
const description = "Custom car seat covers made in-house for your model, in Napa leatherette, Nova or Velour, plus Autoform covers. Fitted at Loni Kalbhor, Pune. WhatsApp us.";
const lead = "We make custom car seat covers in-house, cut and stitched for your car's exact seats, including headrests and armrests, from hatchbacks to 7-seaters. Choose the material and design at our shop in Loni Kalbhor, Pune. We're also an authorised Autoform dealer, if you'd rather have Autoform covers made for your model.";
const customMessage = "Hi Jay Ganesh, I'd like custom seat covers made for my car. Car model and year: ";
const materialList = customMaterials.map((m) => m.name).join(", ").replace(/, ([^,]*)$/, " or $1");

export const metadata: Metadata = pageMetadata({ title, description, path });

// TODO(shop): confirm colours and what's included in a set.
const faqs = [
  { q: "Do you make seat covers yourselves?", a: "Yes. We make custom seat covers to order for your car model, then fit them at our shop. WhatsApp your car model, year and variant and we'll share materials, designs and prices." },
  { q: "Which materials and designs can I choose?", a: `Materials: ${materialList}. Designs: Bentley-style or Porsche-style quilting. Visit the shop to see them, or ask for photos on WhatsApp.` },
  { q: "Are Bentley-style and Porsche-style covers connected to those car brands?", a: `No. ${designDisclaimer}` },
  { q: "How much do seat covers cost?", a: "It depends on the car, the number of seats and the material. Send your car model and year on WhatsApp and we'll share options with prices." },
  { q: "How long does fitting take?", a: "Most sets are fitted the same day at our shop. For 7- and 8-seaters, keep a few hours." },
  { q: "Will the covers fit my seats with airbags?", a: "Covers for cars with seat airbags need the right stitching on the sides. Tell us your variant and we'll pick covers that suit it." },
  { q: "Can I see the material before ordering?", a: "Yes. Visit the shop to see and feel the materials and colours, or ask us for photos on WhatsApp." },
  { q: "Do you make seat covers for 7-seaters like the Innova and Ertiga?", a: "Yes. We fit covers for 7- and 8-seaters, including the third row. Send your model, year and variant on WhatsApp and we'll share options." },
  { q: "Do you sell Autoform seat covers?", a: `Yes. We're an authorised Autoform seat cover dealer in Pune, with Autoform's ${autoformRanges.join(", ").replace(/, ([^,]*)$/, " and $1")} ranges. Autoform makes covers for specific car models; send us your model on WhatsApp and we'll check what's available and share prices.` },
  { q: "Where can I buy car seat covers in Pune?", a: "At our shop at Kawadipat Toll Naka on the Pune–Solapur road, Loni Kalbhor, open 7 days, 10 am to 8 pm. You can see the materials in person, and most sets are fitted the same day." },
];

const steps = [
  { step: "Send your car model", detail: "Model, year and variant on WhatsApp, with a photo of the seats if you can." },
  { step: "Choose the material and colour", detail: "We share options and prices, or you see them at the shop." },
  { step: "Fitting at our shop", detail: "Fitted seat by seat, with headrests and armrests covered." },
];

export default function SeatCovers() {
  return (
    <>
      <JsonLd
        data={graph(
          {
            "@type": "Service",
            "@id": `${site.url}${path}#service`,
            name: "Car seat covers: custom-made in-house, and Autoform",
            serviceType: "Car seat covers",
            description: lead,
            url: site.url + path,
            provider: businessRef,
            areaServed: areaServedLd,
            hasOfferCatalog: {
              "@type": "OfferCatalog",
              name: "Car seat covers",
              itemListElement: [
                {
                  "@type": "OfferCatalog",
                  name: "Custom seat covers, made in-house",
                  itemListElement: [...customMaterials, ...customDesigns].map((x) => ({
                    "@type": "Offer",
                    itemOffered: { "@type": "Product", name: `Custom seat covers: ${x.name}`, description: x.detail },
                  })),
                },
                {
                  "@type": "OfferCatalog",
                  name: "Autoform seat covers (authorised dealer)",
                  url: `${site.url}/autoform-seat-covers-pune/`,
                  itemListElement: autoformRanges.map((r) => ({
                    "@type": "Offer",
                    itemOffered: { "@type": "Product", name: `Autoform ${r}`, brand: { "@type": "Brand", name: "Autoform" } },
                  })),
                },
              ],
            },
          },
          webPageLd({ name: title, description, path }),
          faqLd(faqs, path),
          breadcrumbLd([{ name: "Accessories", path: "/accessories/" }, { name: "Seat covers", path }]),
        )}
      />
      <PageHero
        crumbs={[{ label: "Accessories", href: "/accessories/" }, { label: "Seat covers" }]}
        eyebrow="Accessories · Interior"
        title="Car seat covers, made for your model"
        lead={lead}
        image="seat-covers"
      >
        <Price k="seatCoverFrom" />
        <div className="actions">
          <WhatsAppButton message="Hi Jay Ganesh, I want seat covers. Car model and year: ">WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--panel" aria-labelledby="custom-title">
        <div className="wrap stack-lg">
          <Reveal className="split split--start">
            <div className="stack-lg">
              <div className="section-head">
                <p className="label label--yellow eyebrow">Made in-house</p>
                <h2 id="custom-title">Custom seat covers, made in-house</h2>
                <p className="lead">We make seat covers to order for your car model, then fit them at our shop. Choose a material and a design, and WhatsApp your car model for options and prices.</p>
              </div>
              <div className="actions">
                <WhatsAppButton message={customMessage}>WhatsApp your car model</WhatsAppButton>
              </div>
            </div>
            <div className="stack">
              <p className="label">Choose a material</p>
              <ul className="points">
                {customMaterials.map((m) => <li key={m.name}><strong>{m.name}</strong><span>{m.detail}</span></li>)}
              </ul>
              <p className="label">Choose a design</p>
              <ul className="points">
                {customDesigns.map((d) => <li key={d.name}><strong>{d.name}</strong><span>{d.detail}</span></li>)}
              </ul>
              <p className="pc-note">{designDisclaimer}</p>
            </div>
          </Reveal>
          <Reveal className="grid grid-2">
            <Photo label="Custom-fitted seat covers in a car" m="interior" sizes="(min-width: 720px) 50vw, 100vw" />
            <Photo label="Custom seat covers in an MUV, all three rows" m="workMuvSeats" sizes="(min-width: 720px) 50vw, 100vw" />
          </Reveal>
        </div>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap">
          <div className="callout">
            <div className="stack">
              {autoform && <BrandLogo brand={autoform} color />}
              <p className="label label--yellow eyebrow">Authorised Autoform dealer</p>
              <h2>Want Autoform seat covers?</h2>
              <p>We&apos;re an authorised dealer of Autoform seat covers in Pune. Autoform makes covers for specific car models, and our team fits them at the shop.</p>
            </div>
            <Link className="btn btn--ghost" href="/autoform-seat-covers-pune/">Autoform seat covers <IconArrow /></Link>
          </div>
        </Reveal>
      </section>

      <section className="band band--tight" aria-labelledby="autoform-ranges-title">
        <Reveal className="wrap stack">
          <p className="label label--yellow eyebrow">Autoform ranges</p>
          <h2 id="autoform-ranges-title">Autoform ranges we offer</h2>
          <ul className="chips notranslate" translate="no">
            {autoformRanges.map((r) => <li key={r}><span>Autoform {r}</span></li>)}
          </ul>
          <p className="pc-note">Which ranges, colours and designs Autoform makes depends on your car model. WhatsApp your model, year and variant and we&apos;ll tell you what&apos;s available for it.</p>
        </Reveal>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Popular with our customers" title="Seat covers by car" lead="Tap your model to ask for options and prices on WhatsApp." /></Reveal>
          <Reveal className="cars">
            {popularCars.map((car) => (
              <a key={car} href={whatsapp(`Hi Jay Ganesh, I want seat covers for my ${car}. Year: `)} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
                <strong>{car.split(" ").slice(1).join(" ")}</strong>
                <span className="label">{car.split(" ")[0]}</span>
              </a>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="How it works" title="Three steps" /></Reveal>
          <Reveal as="ol" className="process">{steps.map((s) => <li key={s.step}><strong>{s.step}</strong><span>{s.detail}</span></li>)}</Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow={`Google reviews · ${site.rating}★`} title="What customers say about our seat covers" /></Reveal>
          <ReviewsCarousel topics={["seat-covers", "autoform"]} />
        </div>
      </section>

      <section className="band">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title="Seat covers: common questions" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight"><Reveal className="wrap"><Visit /></Reveal></section>
      <CtaBand message="Hi Jay Ganesh, I want seat covers. Car model and year: " />
    </>
  );
}
