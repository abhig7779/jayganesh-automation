import type { Metadata } from "next";
import { CallButton, CtaBand, Faq, IconPhone, IconPin, IconWhatsApp, JsonLd, SectionHead, WhatsAppButton } from "../components/ui";
import GoogleRating from "../components/GoogleRating";
import { PageHero } from "../components/PageHero";
import Reveal from "../components/Reveal";
import LeadForm from "../components/LeadForm";
import { site, whatsapp } from "@/lib/site";
import { breadcrumbLd, businessRef, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";

const path = "/contact-us/";
const title = "Contact Jay Ganesh Car Accessories, Loni Kalbhor";
const description =
  "Call or WhatsApp +91 99813 97676 for car accessories, PPF, ceramic coating and detailing, or visit Kawadipat Toll Naka, Loni Kalbhor, Pune. Open 7 days.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const faqs = [
  { q: "How fast do you reply?", a: `We reply to WhatsApp messages and form enquiries during shop hours (${site.hoursShort.toLowerCase()}). Messages sent late at night are answered the next morning.` },
  { q: "Can I book on WhatsApp?", a: "Yes. Send your car model and the service you want, and we'll confirm a day and time. For PPF, coatings and detailing, booking ahead keeps a studio slot for your car." },
  { q: "Do you give a price before I come?", a: "Yes. Tell us the car model, year and what you're looking for, and we'll reply with options and prices. For paint work we may ask for a photo of the car first." },
];

const jsonLd = graph(
  { ...webPageLd({ type: "ContactPage", name: `Contact ${site.name}`, description, path }), mainEntity: businessRef },
  faqLd(faqs, path),
  breadcrumbLd([{ name: "Contact us", path }]),
);

function IconInstagram() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="5" />
      <circle cx="12" cy="12" r="4" />
      <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
    </svg>
  );
}
function IconYouTube() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12 31 31 0 0 0 1 16.8a3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8ZM9.7 15.1V8.9L15.5 12l-5.8 3.1Z" />
    </svg>
  );
}
function IconFacebook() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M13.5 22v-8.2h2.8l.4-3.2h-3.2V8.5c0-.9.3-1.6 1.6-1.6h1.7V4.1c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.4H7.3v3.2h2.8V22h3.4Z" />
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
        image="accessories"
        crumbs={[{ label: "Contact us" }]}
        eyebrow="Call · WhatsApp · Visit"
        title="Contact Jay Ganesh Car Accessories"
        lead="Ask for a price, book a studio slot or check if a part fits your car. WhatsApp is the quickest way to reach us."
      >
        <div className="actions">
          <WhatsAppButton message="Hi Jay Ganesh, I'd like to know more.">WhatsApp us</WhatsAppButton>
          <CallButton />
          <a className="btn btn--ghost" href={site.mapsHref} target="_blank" rel="noopener noreferrer" data-track="directions"><IconPin /> Get directions</a>
        </div>
      </PageHero>

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
                  <p className="contact-note">Right at the Kawadipat toll naka on the Pune–Solapur road.</p>
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
                  <p className="label">Follow us</p>
                  <a href={site.instagramHref} target="_blank" rel="noopener noreferrer">{site.instagram}</a>
                  <span className="contact-social">
                    <a href={site.youtubeHref} target="_blank" rel="noopener noreferrer" aria-label="YouTube"><IconYouTube /> YouTube</a>
                    <a href={site.facebookHref} target="_blank" rel="noopener noreferrer" aria-label="Facebook"><IconFacebook /> Facebook</a>
                  </span>
                </div>
              </li>
            </ul>
            <div className="contact-rating"><GoogleRating variant="inline" /></div>
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
