import type { Metadata } from "next";
import { CtaBand, Faq, IconPhone, IconPin, IconWhatsApp, JsonLd, PageHero, SectionHead } from "../components/ui";
import Reveal from "../components/Reveal";
import LeadForm from "../components/LeadForm";
import { site, whatsapp } from "@/lib/site";

export const metadata: Metadata = {
  title: { absolute: "Contact Jay Ganesh Car Accessories | Call, WhatsApp or Visit, Loni Kalbhor, Pune" },
  description:
    "Contact Jay Ganesh Car Accessories for car accessories, PPF, ceramic coating and detailing in Pune. Call or WhatsApp +91 99813 97676, or visit us at Kawadipat Toll Naka, Loni Kalbhor. Open 7 days.",
  alternates: { canonical: "/contact-us/" },
};

const faqs = [
  { q: "How fast do you reply?", a: `We reply to WhatsApp messages and form enquiries during shop hours (${site.hoursShort.toLowerCase()}). Messages sent late at night are answered the next morning.` },
  { q: "Can I book on WhatsApp?", a: "Yes. Send your car model and the service you want, and we'll confirm a day and time. For PPF, coatings and detailing, booking ahead keeps a studio slot for your car." },
  { q: "Do you give a price before I come?", a: "Yes. Tell us the car model, year and what you're looking for, and we'll reply with options and prices. For paint work we may ask for a photo of the car first." },
];

const jsonLd = [
  {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: [
      { "@type": "ListItem", position: 1, name: "Home", item: `${site.url}/` },
      { "@type": "ListItem", position: 2, name: "Contact us", item: `${site.url}/contact-us/` },
    ],
  },
  {
    "@context": "https://schema.org",
    "@type": "ContactPage",
    name: `Contact ${site.name}`,
    url: `${site.url}/contact-us/`,
    mainEntity: {
      "@type": "AutoPartsStore",
      name: site.name,
      telephone: site.phoneE164,
      address: {
        "@type": "PostalAddress",
        streetAddress: "Kawadipat, Toll Naka, Pune–Solapur Road",
        addressLocality: site.locality,
        addressRegion: site.region,
        addressCountry: "IN",
      },
      contactPoint: {
        "@type": "ContactPoint",
        telephone: site.phoneE164,
        contactType: "customer service",
        areaServed: "IN",
      },
    },
  },
];

function IconInstagram() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="5" />
      <circle cx="12" cy="12" r="4" />
      <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
    </svg>
  );
}
function IconClock() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <circle cx="12" cy="12" r="9" />
      <path d="M12 7v5l3 2" />
    </svg>
  );
}

export default function ContactUs() {
  return (
    <>
      <JsonLd data={jsonLd} />
      <PageHero
        crumbs={[{ label: "Contact us" }]}
        eyebrow="Call · WhatsApp · Visit"
        title="Contact Jay Ganesh Car Accessories"
        lead="Ask for a price, book a studio slot or check if a part fits your car. WhatsApp is the quickest way to reach us."
      />

      <section className="band band--panel contact-band">
        <div className="wrap contact-layout">
          <Reveal className="contact-form-col">
            <LeadForm kind="contact" />
          </Reveal>

          <Reveal as="aside" className="contact-card" delay={80}>
            <p className="label label--yellow eyebrow">Reach the shop</p>
            <ul className="contact-list">
              <li>
                <span className="contact-ico"><IconPhone /></span>
                <div>
                  <p className="label">Call</p>
                  <a className="contact-big" href={site.phoneHref} data-track="call">{site.phoneDisplay}</a>
                </div>
              </li>
              <li>
                <span className="contact-ico contact-ico--wa"><IconWhatsApp /></span>
                <div>
                  <p className="label">WhatsApp</p>
                  <a className="contact-big" href={whatsapp()} target="_blank" rel="noopener noreferrer" data-track="whatsapp">Message us</a>
                </div>
              </li>
              <li>
                <span className="contact-ico"><IconPin /></span>
                <div>
                  <p className="label">Address</p>
                  <address>{site.address}</address>
                  <a className="text-link" href={site.mapsHref} target="_blank" rel="noopener noreferrer" data-track="directions">Open in Google Maps</a>
                </div>
              </li>
              <li>
                <span className="contact-ico"><IconClock /></span>
                <div>
                  <p className="label">Hours</p>
                  <p>{site.hours}</p>
                </div>
              </li>
              <li>
                <span className="contact-ico"><IconInstagram /></span>
                <div>
                  <p className="label">Instagram</p>
                  <a href={site.instagramHref} target="_blank" rel="noopener noreferrer">{site.instagram}</a>
                </div>
              </li>
            </ul>
          </Reveal>
        </div>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Find us" title="At Kawadipat Toll Naka" lead="On the Pune–Solapur road at Loni Kalbhor." /></Reveal>
          <Reveal className="map-embed">
            <iframe
              src="https://www.google.com/maps?q=Jay+Ganesh+Car+Accessories+Loni+Kalbhor&output=embed"
              title="Map showing Jay Ganesh Car Accessories at Kawadipat Toll Naka, Loni Kalbhor"
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
              allowFullScreen
            />
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <Reveal className="wrap split split--start">
          <SectionHead eyebrow="FAQ" title="Before you message" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <CtaBand />
    </>
  );
}
