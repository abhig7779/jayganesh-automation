import type { Metadata } from "next";
import Link from "next/link";
import "../styles/local.css";
import ReviewsCarousel from "../components/ReviewsCarousel";
import { CtaBand, Faq, JsonLd, Photo, Price, SectionHead, Visit, WhatsAppButton, CallButton, IconArrow } from "../components/ui";
import { PageHero } from "../components/PageHero";
import Reveal from "../components/Reveal";
import { popularCars } from "@/lib/catalog";
import { site, whatsapp } from "@/lib/site";
import { breadcrumbLd, faqLd, graph, pageMetadata, serviceLd, webPageLd } from "@/lib/seo";
import { BrandLogo } from "../components/BrandLogos";
import { getBrandBySlug } from "@/lib/brands";

const autoform = getBrandBySlug("autoform");

const path = "/seat-covers/";
const title = "Car Seat Covers in Pune, Made to Fit | Jay Ganesh";
const description = "Custom car seat covers made for your model and fitted at our Loni Kalbhor shop, Pune. Innova, Scorpio, Creta, Swift and more. WhatsApp your model.";
const lead = "Custom car seat covers are cut and fitted for your car's exact seats, including headrests and armrests. They're for owners who want a neat, made-to-fit look on any model, from hatchbacks to 7-seaters. Choose the material at our shop in Loni Kalbhor, Pune; most sets are fitted the same day.";

export const metadata: Metadata = pageMetadata({ title, description, path });

// TODO(shop): confirm materials, colours and what's included in a set.
const faqs = [
  { q: "How much do seat covers cost?", a: "It depends on the car, the number of seats and the material. Send your car model and year on WhatsApp and we'll share options with prices." },
  { q: "How long does fitting take?", a: "Most sets are fitted the same day at our shop. For 7- and 8-seaters, keep a few hours." },
  { q: "Will the covers fit my seats with airbags?", a: "Covers for cars with seat airbags need the right stitching on the sides. Tell us your variant and we'll pick covers that suit it." },
  { q: "Can I see the material before ordering?", a: "Yes. Visit the shop to see and feel the materials and colours, or ask us for photos on WhatsApp." },
  { q: "Do you make seat covers for 7-seaters like the Innova and Ertiga?", a: "Yes. We fit covers for 7- and 8-seaters, including the third row. Send your model, year and variant on WhatsApp and we'll share options." },
  { q: "Do you sell Autoform seat covers?", a: "Yes. We're an authorised Autoform seat cover dealer in Pune. Autoform makes covers for specific car models; send us your model on WhatsApp and we'll check what's available and share prices." },
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
          serviceLd({ name: "Custom car seat covers, fitted", serviceType: "Car seat covers", description: lead, path }),
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
          <Reveal><SectionHead eyebrow="Recent fittings" title="From the shop" /></Reveal>
          <Reveal className="grid grid-3">
            <Photo label="Front seats, quilted pattern" m="interior" />
            <Photo label="Third row in a 7-seater" m="dashClean" />
            <Photo label="Stitching close-up" />
          </Reveal>
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
