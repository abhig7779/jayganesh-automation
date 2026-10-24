import type { Metadata } from "next";
import Link from "next/link";
import "../styles/local.css";
import { CallButton, CtaBand, Faq, JsonLd, SectionHead, Visit, WhatsAppButton } from "../components/ui";
import { PageHero } from "../components/PageHero";
import Reveal from "../components/Reveal";
import { SoldBrandsSection } from "../components/DealerBrands";
import { windowFilmBrands } from "@/lib/sold-brands";
import { site } from "@/lib/site";
import { breadcrumbLd, faqLd, graph, pageMetadata, serviceLd, webPageLd } from "@/lib/seo";

// Sun-control (window) film for cars, fitted at the studio. The film brands are
// "brands we sell and fit" (lib/sold-brands.ts), NOT authorised-dealer brands.
// FACTS RULE: no prices, heat-rejection figures or warranty years. The legal
// note states only the visible-light limits of the Central Motor Vehicles
// Rules (Rule 100) and sends people to their RTO for anything beyond that.

const path = "/car-window-film-pune/";
const title = "Car Window Film & Sun Control Film in Pune | Jay Ganesh";
const description =
  "Sun-control window film for cars, fitted at our Loni Kalbhor studio in Pune within legal light limits. Garware, 3M, LLumar and DHC. WhatsApp your car model.";

const verified = windowFilmBrands.filter((b) => b.logo).map((b) => b.name);
const brandList = `${verified.slice(0, -1).join(", ")} and ${verified[verified.length - 1]}`;

const lead = `${site.name} fits sun-control window film on cars at our studio at ${site.addressShort}, Pune. Sun-control film is made to cut heat and glare in the cabin, and we only fit films that keep your glass within India's legal visible-light limits. Brands we sell and fit include ${brandList}. WhatsApp your car model for options.`;

const message = "Hi Jay Ganesh, I'd like sun-control window film for my car. Car model and year: ";

export const metadata: Metadata = pageMetadata({ title, description, path });

const fit = [
  { name: "Side windows", detail: "Sun-control film that keeps each side window at 50% visible light or more." },
  { name: "Front and rear windscreens", detail: "Only films that keep the windscreens at 70% visible light or more." },
  { name: "Chosen for your car", detail: "We suggest a film and brand for your model, your budget and how you use the car." },
  { name: "Fitted at our studio", detail: "Fitted indoors by our own team, then checked with you before you drive out." },
];

const faqs = [
  {
    q: "Is sun film legal on cars in India?",
    a: "Film is only allowed if the glass still lets enough light through. Under the Central Motor Vehicles Rules, the front and rear windscreens must let at least 70% of visible light through, and the side windows at least 50%. We only fit films that keep your car within these limits. If you're unsure about the rules where you drive, check with your RTO.",
  },
  {
    q: "Which window film brands do you fit?",
    a: `Brands we sell and fit include ${brandList}. What's in stock changes, so WhatsApp your car model and we'll share the options for it.`,
  },
  {
    q: "What does window film cost?",
    a: "It depends on your car model and the film you choose. WhatsApp us your car model and we'll reply with options and prices.",
  },
  {
    q: "How long does fitting take?",
    a: "It depends on the car and how many windows you want done. We'll confirm the time when you book, so you know when to bring the car in.",
  },
];

export default function CarWindowFilm() {
  return (
    <>
      <JsonLd
        data={graph(
          serviceLd({ name: "Car window film (sun-control film), fitted", serviceType: "Automotive window film installation", description: lead, path }),
          webPageLd({ name: title, description, path }),
          faqLd(faqs, path),
          breadcrumbLd([
            { name: "Detailing studio", path: "/detailing-studio/" },
            { name: "Window film", path },
          ]),
        )}
      />

      <PageHero
        crumbs={[{ label: "Detailing studio", href: "/detailing-studio/" }, { label: "Window film" }]}
        eyebrow="Detailing studio · Window film"
        title="Car window film in Pune, within legal limits"
        lead={lead}
        image="detailing-studio"
      >
        <p className="label">Price depends on car model and film · WhatsApp us</p>
        <div className="actions">
          <WhatsAppButton message={message}>WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--tight" aria-labelledby="film-legal-title">
        <Reveal className="wrap">
          <div className="callout">
            <div className="stack">
              <p className="label label--yellow eyebrow">The legal limit</p>
              <h2 id="film-legal-title">How dark can car window film be in India?</h2>
              <p>
                Under the Central Motor Vehicles Rules, the glass of a car must let through at least 70% of visible light on the
                front and rear windscreens, and at least 50% on the side windows. The limit applies to the glass with the film on it.
                We only fit films that keep your car within these limits.
              </p>
              <p>This is general information, not legal advice. Rules and how they&apos;re enforced can change; if you&apos;re unsure, check with your RTO.</p>
            </div>
          </div>
        </Reveal>
      </section>

      <SoldBrandsSection
        category="window-film"
        panel
        title="Window film brands we sell and fit"
        lead="Sun-control film from these brands, fitted at our studio. WhatsApp your car model and we'll suggest a film that suits it and stays within the legal limit."
      />

      <section className="band">
        <Reveal className="wrap split split--start">
          <SectionHead eyebrow="What we fit" title="Window film: what we fit" lead="Supplied and fitted by our own team, chosen for your car model." />
          <ul className="points">
            {fit.map((f) => <li key={f.name}><strong>{f.name}</strong><span>{f.detail}</span></li>)}
          </ul>
        </Reveal>
      </section>

      <section className="band band--panel">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title="Window film: common questions" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap stack-lg">
          <div className="stack">
            <p className="label">More from the studio</p>
            <ul className="chips">
              <li><Link href="/ppf-pune/">PPF</Link></li>
              <li><Link href="/ceramic-coating-pune/">Ceramic coating</Link></li>
              <li><Link href="/car-detailing-pune/">Car detailing</Link></li>
              <li><Link href="/detailing-studio/">All studio services</Link></li>
            </ul>
          </div>
          <Visit />
        </Reveal>
      </section>

      <CtaBand title="Send us your car model. We'll suggest a film and a price." message={message} />
    </>
  );
}
