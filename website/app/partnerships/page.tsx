import type { Metadata } from "next";
import { CtaBand, Faq, JsonLd, PageHero, SectionHead, WhatsAppButton, CallButton } from "../components/ui";
import Reveal from "../components/Reveal";
import LeadForm from "../components/LeadForm";
import { site } from "@/lib/site";
import { breadcrumbLd, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";

const path = "/partnerships/";
const title = "Partnerships: Dealerships & Fleets, Pune | Jay Ganesh";
const description =
  "PPF, ceramic coating and accessories for car dealerships, fleets, insurers, workshops and creators in Pune, from our Loni Kalbhor studio.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const whoFor = [
  { label: "Dealerships", t: "Car dealerships and showrooms", d: "PPF, ceramic coating and accessories fitted before new-car delivery, so your buyer drives away with everything done." },
  { label: "Fleets", t: "Fleets and corporate cars", d: "Seat covers, dashcams, cameras and regular detailing for company cars and cab fleets, kept to one standard." },
  { label: "Insurance & workshops", t: "Insurers and workshops", d: "Film, coating and accessory work for cars that come through your workshop or claims desk." },
  { label: "Creators", t: "Influencers and content creators", d: "Car builds, studio sessions and before-and-after content filmed at our studio." },
];

const gets = [
  { t: "One point of contact", d: "One person at the shop who knows your account and answers your calls." },
  { t: "Priority studio slots", d: "We plan your cars into the studio calendar so delivery dates don't slip." },
  { t: "Consistent fitting quality", d: "The same team and the same checks on every car, whether it's the first or the fiftieth." },
  { t: "Proper invoices", d: "Clear invoices for every job, ready for your accounts team." },
];

const steps = [
  { t: "Tell us about your business", d: "Fill in the form below or message us on WhatsApp with what you need." },
  { t: "We talk it through", d: "We call you, understand your cars and timelines, and suggest how we'd handle them." },
  { t: "Start with the first cars", d: "Send the first cars in. We agree on the routine and keep it running." },
];

const faqs = [
  { q: "Do you work with dealerships outside Loni Kalbhor?", a: "Yes. Tell us where you are and how many cars you expect, and we'll work out the best way to handle them." },
  { q: "Can you work to our delivery dates?", a: "That's the point of a partnership. Share your delivery schedule and we'll plan studio slots around it." },
  { q: "How is pricing agreed?", a: "It depends on the cars, the services and the volume. We'll discuss it with you directly after the first call." },
];

const jsonLd = graph(
  webPageLd({ name: `Partner with ${site.name}`, description, path }),
  faqLd(faqs, path),
  breadcrumbLd([{ name: "Partnerships", path }]),
);

export default function Partnerships() {
  return (
    <>
      <JsonLd data={jsonLd} />
      <PageHero
        crumbs={[{ label: "Partnerships" }]}
        eyebrow="For businesses"
        title="Partner with Jay Ganesh"
        lead="For dealerships, fleets, insurers, workshops and creators who need car protection and accessories done right, every time."
      >
        <div className="actions">
          <a className="btn btn--primary" href="#partner-form">Start a conversation</a>
          <WhatsAppButton variant="ghost" message="Hi Jay Ganesh, I'd like to talk about a partnership. Company: ">WhatsApp us</WhatsAppButton>
        </div>
      </PageHero>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Who it's for" title="Businesses that look after a lot of cars" /></Reveal>
          <Reveal className="grid grid-2">
            {whoFor.map((w) => (
              <div key={w.t} className="card">
                <p className="label label--yellow">{w.label}</p>
                <h3>{w.t}</h3>
                <p>{w.d}</p>
              </div>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band">
        <Reveal className="wrap split split--start">
          <SectionHead
            eyebrow="What partners get"
            title="Less chasing, more cars done"
            lead={`The same shop ${site.customers} customers already trust, rated ${site.rating} on Google.`}
          />
          <ul className="points">
            {gets.map((g) => <li key={g.t}><strong>{g.t}</strong><span>{g.d}</span></li>)}
          </ul>
        </Reveal>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="How it works" title="Three steps to get started" /></Reveal>
          <Reveal as="ol" className="process">
            {steps.map((s) => <li key={s.t}><strong>{s.t}</strong><span>{s.d}</span></li>)}
          </Reveal>
        </div>
      </section>

      <section className="band" id="partner-form">
        <div className="wrap form-layout">
          <Reveal className="stack-lg">
            <SectionHead eyebrow="Get in touch" title="Tell us what you need" lead="We'll call you back within working hours to talk it through." />
            <div className="actions">
              <CallButton />
            </div>
            <Faq items={faqs} />
          </Reveal>
          <Reveal delay={80}>
            <LeadForm kind="partnership" />
          </Reveal>
        </div>
      </section>

      <CtaBand title="Prefer to talk first? Call or WhatsApp the shop." message="Hi Jay Ganesh, I'd like to talk about a partnership. Company: " />
    </>
  );
}
