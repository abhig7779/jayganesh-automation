import type { Metadata } from "next";
import Link from "next/link";
import "../styles/local.css";
import { CallButton, CtaBand, Faq, IconArrow, IconPin, JsonLd, PageHero, Photo, SectionHead, Visit, WhatsAppButton } from "../components/ui";
import Reveal from "../components/Reveal";
import GoogleRating from "../components/GoogleRating";
import ReviewsCarousel from "../components/ReviewsCarousel";
import { popularCars } from "@/lib/catalog";
import { site, whatsapp } from "@/lib/site";
import { breadcrumbLd, businessRef, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";

const area = "Kharadi";
const path = "/car-accessories-kharadi/";
const title = "Car Accessories near Kharadi, Pune | Jay Ganesh";
const description =
  "Car accessories, new-car PPF and detailing for Kharadi car owners, at Kawadipat Toll Naka, Loni Kalbhor. Reachable via Manjri. Get a price on WhatsApp.";
const lead =
  "Jay Ganesh Car Accessories is at Kawadipat Toll Naka on the Pune–Solapur Road, Loni Kalbhor, reachable from Kharadi via Manjri. Kharadi car owners come to us to protect a new car with PPF or ceramic coating, and to fit seat covers, dashcams and stereos. Send your model on WhatsApp first and we'll reply with options and prices.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const directionsHref = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent("Kharadi, Pune")}&destination=${encodeURIComponent("Jay Ganesh Car Accessories, Loni Kalbhor")}`;
const ask = (car?: string) => `Hi Jay Ganesh, I'm from Kharadi. ${car ? `My car is a ${car}. ` : ""}I'm looking for: `;

const offers = [
  { href: "/new-car-protection/", label: "New car", title: "New-car protection", text: "Just booked a car? Plan PPF, coating and accessories before or right after delivery." },
  { href: "/ppf-pune/", label: "Studio", title: "Paint protection film", text: "A clear film that takes stone chips and scratches, fitted in our indoor studio." },
  { href: "/ceramic-coating-pune/", label: "Studio", title: "Ceramic coating", text: "Deep gloss and easier washing, applied on paint we prepare first." },
  { href: "/accessories/#dashcam", label: "Tech", title: "Dashcams and 360° cameras", text: "A dashcam for the daily drive and a 360° camera for tight parking, wired neatly." },
  { href: "/autoform-seat-covers-pune/", label: "Interior", title: "Autoform and custom seat covers", text: "We're an authorised Autoform dealer, and we also make covers to fit your seats." },
  { href: "/car-detailing-pune/", label: "Studio", title: "Car detailing", text: "Foam wash, polishing and an interior deep clean, done by hand." },
];

const faqs = [
  { q: "Do you fit accessories for customers from Kharadi?", a: "Yes. Customers drive in from Kharadi, Wagholi and across eastern Pune. Everything is fitted by our own team at our shop at Kawadipat Toll Naka, Loni Kalbhor." },
  { q: "How do I reach the shop from Kharadi?", a: "Go via Manjri to the Pune–Solapur Road, then head east to Kawadipat Toll Naka at Loni Kalbhor. Use the Google Maps link on this page; it picks the best route for the time of day." },
  { q: "Can I book PPF or coating for a car I'm about to take delivery of?", a: "Yes. Tell us the model and your delivery date on WhatsApp and we'll keep a studio slot, so the car comes to us soon after delivery. See our new-car protection page for what we do." },
  { q: "Can you quote on WhatsApp so I don't make a wasted trip?", a: `Yes. Send your car model, year and what you need to ${site.phoneDisplay}. We reply with options and prices for your model, and confirm a day and time if you want to book.` },
  { q: "When are you open?", a: "Every day, 10 am to 8 pm, so you can come in on a weekend or after work." },
];

export default function CarAccessoriesKharadi() {
  return (
    <>
      <JsonLd
        data={graph(
          {
            "@type": "Service",
            "@id": `${site.url}${path}#service`,
            name: `Car accessories, PPF and detailing for ${area}`,
            serviceType: "Car accessories installation",
            description: lead,
            url: site.url + path,
            provider: businessRef,
            areaServed: { "@type": "Place", name: `${area}, Pune`, containedInPlace: { "@type": "City", name: "Pune" } },
          },
          webPageLd({ name: title, description, path }),
          faqLd(faqs, path),
          breadcrumbLd([{ name: "Car accessories near Kharadi", path }]),
        )}
      />
      <PageHero
        crumbs={[{ label: "Car accessories near Kharadi" }]}
        eyebrow="Kharadi · via Manjri"
        title="Car accessories near Kharadi"
        lead={lead}
        media={<Photo label="Paint protection film being laid on a bonnet" m="ppfApply" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <div className="actions">
          <WhatsAppButton message={ask()}>WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="What Kharadi customers come for" title="New cars, protected and fitted out" lead="Plan it all on WhatsApp, then bring the car once." /></Reveal>
          <Reveal className="grid grid-3">
            {offers.map((o) => (
              <Link key={o.href} href={o.href} className="card card--link">
                <p className="label label--yellow">{o.label}</p>
                <h3>{o.title}</h3>
                <p>{o.text}</p>
                <span className="text-link">See more <IconArrow /></span>
              </Link>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal>
            <div className="h-head">
              <div className="h-head-text">
                <p className="label">Reviews</p>
                <h2>Rated {site.rating} on Google</h2>
                <p className="h-lead">From {site.reviews} reviews by customers across Pune.</p>
              </div>
              <div className="h-head-aside"><GoogleRating variant="inline" /></div>
            </div>
            <ReviewsCarousel />
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Popular models" title="Tell us your car" lead="Tap your model and say what you're after. We'll reply on WhatsApp with options and prices." /></Reveal>
          <Reveal className="cars">
            {popularCars.map((car) => (
              <a key={car} href={whatsapp(ask(car))} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
                <strong>{car.split(" ").slice(1).join(" ")}</strong>
                <span className="label">{car.split(" ")[0]}</span>
              </a>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Directions" title="From Kharadi, via Manjri" lead="Join the Pune–Solapur Road and drive east to the toll naka at Loni Kalbhor." /></Reveal>
          <Reveal className="map-embed">
            <iframe
              src="https://www.google.com/maps?q=Jay+Ganesh+Car+Accessories+Loni+Kalbhor&output=embed"
              title="Map showing Jay Ganesh Car Accessories at Kawadipat Toll Naka, Loni Kalbhor"
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
              allowFullScreen
            />
          </Reveal>
          <Reveal className="actions map-actions" delay={60}>
            <a className="btn btn--ghost" href={directionsHref} target="_blank" rel="noopener noreferrer" data-track="directions"><IconPin />Directions from Kharadi</a>
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title="Coming from Kharadi" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight"><Reveal className="wrap"><Visit /></Reveal></section>
      <CtaBand message={ask()} />
    </>
  );
}
