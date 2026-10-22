import type { Metadata } from "next";
import { CtaBand, Faq, JsonLd, PageHero, SectionHead, WhatsAppButton } from "../components/ui";
import Reveal from "../components/Reveal";
import LeadForm from "../components/LeadForm";
import { site } from "@/lib/site";
import { breadcrumbLd, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";

const path = "/become-a-vendor/";
const title = "Become a Vendor or Supplier, Pune | Jay Ganesh";
const description =
  "Supply car accessories, PPF film, coatings, seat covers, lighting or audio to Jay Ganesh Car Accessories, Loni Kalbhor, Pune. Share your products.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const lookFor = [
  { t: "Genuine branded products", d: "Real, traceable products from the brand or its authorised channel. No copies." },
  { t: "Reliable supply", d: "Stock we can count on, so we can promise customers a fitting date and keep it." },
  { t: "Warranty support", d: "Clear warranty terms and help when a customer needs a claim." },
  { t: "Fair trade pricing", d: "Pricing that lets us give our customers an honest price." },
];

const categories = [
  { t: "PPF & films", d: "Paint protection film and related films for the studio." },
  { t: "Coatings & care", d: "Ceramic and graphene coatings, polishes and detailing products." },
  { t: "Seat covers & interiors", d: "Seat covers, mats, steering covers and interior trims." },
  { t: "Lighting", d: "LED and projector headlights, fog lamps and ambient lighting." },
  { t: "Audio & electronics", d: "Android stereos, speakers, subwoofers, 360° cameras and dashcams." },
  { t: "Something else", d: "If car owners in Pune would want it, tell us about it." },
];

const steps = [
  { t: "Send your details", d: "Use the form below with your company, categories and the brands you supply." },
  { t: "We review and call", d: "If it fits what our customers ask for, we'll call to discuss products, terms and samples." },
  { t: "Trial and onboarding", d: "We start with a trial order, check fit and quality on real cars, then add you as a regular supplier." },
];

const faqs = [
  { q: "Do you buy from new brands?", a: "Yes, if the product is genuine, the quality holds up on real cars and you can support the warranty." },
  { q: "How long does it take to hear back?", a: "We read every submission. If your products fit what we stock, we'll call you. It can take a little longer during busy weeks." },
  { q: "Should I send samples?", a: "Please don't send anything before we talk. We'll tell you what to send and where after the first call." },
];

const jsonLd = graph(
  webPageLd({ name: `Become a vendor for ${site.name}`, description, path }),
  faqLd(faqs, path),
  breadcrumbLd([{ name: "Become a vendor", path }]),
);

export default function BecomeAVendor() {
  return (
    <>
      <JsonLd data={jsonLd} />
      <PageHero
        crumbs={[{ label: "Become a vendor" }]}
        eyebrow="For suppliers"
        title="Become a vendor or supplier"
        lead="We fit accessories and run a PPF and detailing studio for car owners across eastern Pune. If you supply products they'd trust, we'd like to hear from you."
      >
        <div className="actions">
          <a className="btn btn--primary" href="#vendor-form">Share your products</a>
          <WhatsAppButton variant="ghost" message="Hi Jay Ganesh, I'm a supplier and would like to introduce our products. Company: ">WhatsApp us</WhatsAppButton>
        </div>
      </PageHero>

      <section className="band band--panel">
        <Reveal className="wrap split split--start">
          <SectionHead
            eyebrow="What we look for"
            title="Products we can stand behind"
            lead={`Our name is on every fitting. ${site.customers} customers and a ${site.rating} Google rating depend on the parts we choose.`}
          />
          <ul className="points">
            {lookFor.map((l) => <li key={l.t}><strong>{l.t}</strong><span>{l.d}</span></li>)}
          </ul>
        </Reveal>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Categories" title="What we stock" lead="The accessories shop and the studio use products across these categories." /></Reveal>
          <Reveal className="grid grid-3">
            {categories.map((c) => (
              <div key={c.t} className="card">
                <h3>{c.t}</h3>
                <p>{c.d}</p>
              </div>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Onboarding" title="How it works" /></Reveal>
          <Reveal as="ol" className="process">
            {steps.map((s) => <li key={s.t}><strong>{s.t}</strong><span>{s.d}</span></li>)}
          </Reveal>
        </div>
      </section>

      <section className="band" id="vendor-form">
        <div className="wrap form-layout">
          <Reveal className="stack-lg">
            <SectionHead eyebrow="Get in touch" title="Introduce your products" lead="A few details are enough to start. We'll call if it's a fit." />
            <Faq items={faqs} />
          </Reveal>
          <Reveal delay={80}>
            <LeadForm kind="vendor" />
          </Reveal>
        </div>
      </section>

      <CtaBand title="Have a question first? Call or WhatsApp the shop." message="Hi Jay Ganesh, I'm a supplier and have a question. Company: " />
    </>
  );
}
