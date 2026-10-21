import type { Metadata } from "next";
import { CtaBand, Faq, JsonLd, PageHero, Photo, Price, SectionHead, Visit, WhatsAppButton, CallButton } from "../components/ui";
import Reveal from "../components/Reveal";
import { popularCars } from "@/lib/catalog";
import { whatsapp } from "@/lib/site";

export const metadata: Metadata = {
  title: { absolute: "Car Seat Covers in Pune, Made for Your Car Model | Jay Ganesh" },
  description: "Custom car seat covers made for your model and fitted at our Loni Kalbhor shop. Innova, Scorpio, Fortuner, Creta, Swift and more. WhatsApp your car model.",
  alternates: { canonical: "/seat-covers/" },
};

// TODO(shop): confirm materials, colours and what's included in a set.
const faqs = [
  { q: "How much do seat covers cost?", a: "It depends on the car, the number of seats and the material. Send your car model and year on WhatsApp and we'll share options with prices." },
  { q: "How long does fitting take?", a: "Most sets are fitted the same day at our shop. For 7- and 8-seaters, keep a few hours." },
  { q: "Will the covers fit my seats with airbags?", a: "Covers for cars with seat airbags need the right stitching on the sides. Tell us your variant and we'll pick covers that suit it." },
  { q: "Can I see the material before ordering?", a: "Yes. Visit the shop to see and feel the materials and colours, or ask us for photos on WhatsApp." },
];

const steps = [
  { step: "Send your car model", detail: "Model, year and variant on WhatsApp, with a photo of the seats if you can." },
  { step: "Choose the material and colour", detail: "We share options and prices, or you see them at the shop." },
  { step: "Fitting at our shop", detail: "Fitted seat by seat, with headrests and armrests covered." },
];

export default function SeatCovers() {
  return (
    <>
      <JsonLd data={{ "@context": "https://schema.org", "@type": "FAQPage", mainEntity: faqs.map((f) => ({ "@type": "Question", name: f.q, acceptedAnswer: { "@type": "Answer", text: f.a } })) }} />
      <PageHero
        crumbs={[{ label: "Accessories", href: "/accessories/" }, { label: "Seat covers" }]}
        eyebrow="Accessories · Interior"
        title="Car seat covers, made for your model"
        lead="Seat covers chosen for your car's exact seats, fitted at our shop. One of the most-asked-for jobs at Jay Ganesh."
        media={<Photo label="Fitted seat covers in an MUV, second row" m="interior" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <Price k="seatCoverFrom" />
        <div className="actions">
          <WhatsAppButton message="Hi Jay Ganesh, I want seat covers. Car model and year: ">WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

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
