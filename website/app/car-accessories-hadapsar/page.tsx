import type { Metadata } from "next";
import Link from "next/link";
import "../styles/local.css";
import { CallButton, CtaBand, Faq, IconArrow, IconPin, JsonLd, SectionHead, Visit, WhatsAppButton } from "../components/ui";
import { PageHero } from "../components/PageHero";
import Reveal from "../components/Reveal";
import GoogleRating from "../components/GoogleRating";
import { popularCars } from "@/lib/catalog";
import { reviewsData, shortName } from "@/lib/reviews";
import { site, whatsapp } from "@/lib/site";
import { breadcrumbLd, businessRef, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";

const area = "Hadapsar";
const path = "/car-accessories-hadapsar/";
const title = "Car Accessories near Hadapsar, Pune | Jay Ganesh";
const description =
  "Car accessories near Hadapsar: seat covers, Autoform, lights, stereos, dashcams and a PPF studio at Kawadipat Toll Naka, Pune–Solapur Road. Open 7 days.";
const lead =
  "Jay Ganesh Car Accessories is on the Pune–Solapur Road, east of Hadapsar, at Kawadipat Toll Naka in Loni Kalbhor. Hadapsar drivers come to us for seat covers, lights, Android stereos and dashcams, and for PPF and ceramic coating in our studio. Our own team fits everything. WhatsApp your car model for a price.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const directionsHref = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent("Hadapsar, Pune")}&destination=${encodeURIComponent("Jay Ganesh Car Accessories, Loni Kalbhor")}`;
const ask = (car?: string) => `Hi Jay Ganesh, I'm from Hadapsar. ${car ? `I'd like accessories for my ${car}. ` : ""}Car model and what I need: `;

const offers = [
  { href: "/seat-covers/", label: "Interior", title: "Seat covers", text: "Custom covers made for your model, fitted seat by seat at the shop." },
  { href: "/autoform-seat-covers-pune/", label: "Authorised dealer", title: "Autoform seat covers", text: "Autoform's model-specific covers, supplied and fitted by our team." },
  { href: "/accessories/#headlights", label: "Lights", title: "LED headlights and fog lamps", text: "Brighter lights for the drive home on the highway, wired neatly." },
  { href: "/accessories/#stereo", label: "Tech", title: "Android stereos and speakers", text: "Touchscreen stereos and speakers, fitted for your dashboard." },
  { href: "/accessories/#dashcam", label: "Tech", title: "Dashcams and 360° cameras", text: "Installed, wired and tested before you drive out." },
  { href: "/ppf-pune/", label: "Studio", title: "PPF and ceramic coating", text: "Paint protection film and coatings, done indoors in our studio." },
];

const faqs = [
  { q: "Where is your shop if I'm coming from Hadapsar?", a: `We're at ${site.addressShort}. From Hadapsar, head east on the Pune–Solapur Road towards Loni Kalbhor; the shop is at the Kawadipat toll naka. Use the Google Maps link on this page for directions.` },
  { q: "Is there a Jay Ganesh or Jai Ganesh car accessories shop in Hadapsar itself?", a: `Our only shop is at Kawadipat Toll Naka, Loni Kalbhor, east of Hadapsar. People spell our name both ways, Jay Ganesh and Jai Ganesh. Other shops with similar names in Hadapsar are separate businesses and not connected to us. To be sure you've reached us, call or WhatsApp ${site.phoneDisplay}.` },
  { q: "Can I get a price before I drive over from Hadapsar?", a: "Yes. Send your car model, year and what you need on WhatsApp and we'll reply with options and prices, so you know before you leave home." },
  { q: "Do I need an appointment?", a: "You can walk in for accessories. For PPF, ceramic coating and detailing, message us on WhatsApp first so we can keep a studio slot for your car." },
  { q: "Are you open on weekends?", a: `Yes. We're open every day, 10 am to 8 pm, including Saturday and Sunday.` },
];

const review = reviewsData.reviews.find((r) => r.id === "sadanand-chotve");

export default function CarAccessoriesHadapsar() {
  return (
    <>
      <JsonLd
        data={graph(
          {
            "@type": "Service",
            "@id": `${site.url}${path}#service`,
            name: `Car accessories and detailing for ${area}`,
            serviceType: "Car accessories installation",
            description: lead,
            url: site.url + path,
            provider: businessRef,
            areaServed: { "@type": "Place", name: `${area}, Pune`, containedInPlace: { "@type": "City", name: "Pune" } },
          },
          webPageLd({ name: title, description, path }),
          faqLd(faqs, path),
          breadcrumbLd([{ name: "Car accessories near Hadapsar", path }]),
        )}
      />
      <PageHero
        crumbs={[{ label: "Car accessories near Hadapsar" }]}
        eyebrow="Hadapsar · Pune–Solapur Road"
        title="Car accessories near Hadapsar"
        lead={lead}
        image="detailing-studio"
      >
        <div className="actions">
          <WhatsAppButton message={ask()}>WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="What Hadapsar customers come for" title="Accessories and studio work, one stop" lead="Fit accessories and book paint protection on the same visit." /></Reveal>
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

      {review && (
        <section className="band">
          <div className="wrap">
            <Reveal><SectionHead eyebrow="Google review" title="From a customer near Hadapsar" /></Reveal>
            <Reveal>
              <figure className="review-quote">
                <blockquote cite={site.reviewsHref}><p>&ldquo;{review.text.trim()}&rdquo;</p></blockquote>
                <figcaption><strong>{shortName(review.name)}</strong> · {review.stars}★ on Google</figcaption>
                <GoogleRating variant="inline" />
              </figure>
            </Reveal>
          </div>
        </section>
      )}

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Popular models" title="What fits your car?" lead="Tap your model and tell us what you need. We reply on WhatsApp with options and prices." /></Reveal>
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
          <Reveal><SectionHead eyebrow="Directions" title="From Hadapsar to Kawadipat Toll Naka" lead="Head east on the Pune–Solapur Road towards Loni Kalbhor. The shop is at the toll naka." /></Reveal>
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
            <a className="btn btn--ghost" href={directionsHref} target="_blank" rel="noopener noreferrer" data-track="directions"><IconPin />Directions from Hadapsar</a>
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title="Coming from Hadapsar" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight"><Reveal className="wrap"><Visit /></Reveal></section>
      <CtaBand message={ask()} />
    </>
  );
}
