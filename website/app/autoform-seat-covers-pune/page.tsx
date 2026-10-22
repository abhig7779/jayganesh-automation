import type { Metadata } from "next";
import Link from "next/link";
import "../styles/local.css";
import { CallButton, CtaBand, Faq, IconArrow, JsonLd, PageHero, Photo, SectionHead, Visit, WhatsAppButton } from "../components/ui";
import Reveal from "../components/Reveal";
import { popularCars } from "@/lib/catalog";
import { reels, reelUrl } from "@/lib/reels";
import ReviewsCarousel from "../components/ReviewsCarousel";
import { BrandLogo } from "../components/BrandLogos";
import { getBrandBySlug } from "@/lib/brands";
import { site, whatsapp } from "@/lib/site";
import { areaServedLd, breadcrumbLd, businessRef, faqLd, graph, pageMetadata, webPageLd } from "@/lib/seo";

const path = "/autoform-seat-covers-pune/";
const title = "Autoform Seat Covers in Pune, Authorised Dealer | Jay Ganesh";
const description =
  "Authorised Autoform seat cover dealer in Pune. Covers made for your car model, fitted at our Loni Kalbhor shop on the Pune–Solapur Road. WhatsApp us.";
const lead =
  "Jay Ganesh Car Accessories is an authorised dealer of Autoform seat covers in Pune, at Kawadipat Toll Naka on the Pune–Solapur Road, Loni Kalbhor. Autoform makes its covers for specific car models, so they follow the shape of your seats. Send us your model on WhatsApp; our own team fits the covers at the shop.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const askMessage = (car?: string) =>
  car ? `Hi Jay Ganesh, I'd like Autoform seat cover options and price for my ${car}. Year and variant: ` : "Hi Jay Ganesh, I'd like Autoform seat cover options and price. Car model, year and variant: ";

// Models people search with "autoform seat covers for …" first, then the shop's most-asked list.
const autocompleteFirst = ["Hyundai Creta", "Maruti Grand Vitara"];
const cars = [...autocompleteFirst, ...popularCars.filter((c) => !autocompleteFirst.includes(c))];

const why = [
  { t: "Made for your make and model", d: "Autoform designs its covers for specific car models, so the cut follows your seats, headrests and armrests instead of stretching a universal cover over them." },
  { t: "Consistent, factory-made quality", d: "The covers come made by Autoform for your model, so the set is ready to fit rather than stitched from scratch for each car." },
  { t: "Fitted by our own team", d: "Our installers fit the covers seat by seat at the shop and check the finish with you before you drive out." },
  { t: "Bought from an authorised dealer", d: "We're an authorised Autoform dealer in Pune, so you're buying genuine Autoform covers from the shop that fits them." },
];

const steps = [
  { step: "Send your car model", detail: "Model, year and variant on WhatsApp. A photo of your seats helps." },
  { step: "Get options and prices", detail: "We tell you which Autoform covers are available for your model, with prices." },
  { step: "Fitting at our shop", detail: "Bring the car to Kawadipat Toll Naka and our team fits the covers." },
];

const faqs = [
  { q: "What is the price of Autoform seat covers?", a: "It depends on your car model and the Autoform range you choose. Send your model, year and variant on WhatsApp and we'll share the options and prices for your car." },
  { q: "Do you have Autoform seat covers for my car?", a: "Autoform makes covers for specific car models. Tell us your model, year and variant on WhatsApp and we'll check what's available for it." },
  { q: "Are you an authorised Autoform dealer?", a: `Yes. ${site.name} is an authorised dealer of Autoform seat covers in Pune, at Kawadipat Toll Naka on the Pune–Solapur Road, Loni Kalbhor.` },
  { q: "How long does fitting take?", a: "We'll confirm the fitting time when you book. If the covers for your model have to be ordered in, we'll tell you when they'll be ready, so you only bring the car once." },
  { q: "Should I choose Autoform or custom-made seat covers?", a: "Autoform covers are made for your model by Autoform. Our custom seat covers are cut and fitted for your seats at the shop, in the material you choose. Tell us what you want and we'll show you both." },
  { q: "Where can I buy Autoform seat covers in Pune?", a: `At our shop, ${site.addressShort}, open every day from 10 am to 8 pm. Call or WhatsApp ${site.phoneDisplay} first so we can check covers for your model.` },
];

const reel = reels.find((r) => r.id === "DelJXxghV1r");
const autoform = getBrandBySlug("autoform");

export default function AutoformSeatCovers() {
  return (
    <>
      <JsonLd
        data={graph(
          {
            "@type": "Service",
            "@id": `${site.url}${path}#service`,
            name: "Autoform seat covers, supplied and fitted",
            serviceType: "Car seat covers",
            description: lead,
            url: site.url + path,
            brand: { "@type": "Brand", name: "Autoform" },
            provider: businessRef,
            areaServed: areaServedLd,
          },
          webPageLd({ name: title, description, path }),
          faqLd(faqs, path),
          breadcrumbLd([
            { name: "Accessories", path: "/accessories/" },
            { name: "Seat covers", path: "/seat-covers/" },
            { name: "Autoform seat covers", path },
          ]),
        )}
      />
      <PageHero
        crumbs={[{ label: "Accessories", href: "/accessories/" }, { label: "Seat covers", href: "/seat-covers/" }, { label: "Autoform" }]}
        eyebrow="Authorised Autoform dealer · Pune"
        title="Autoform seat covers in Pune — authorised dealer"
        lead={lead}
        media={<Photo label="Autoform seat covers in a Ford Endeavour" src="/reels/DelJXxghV1r.jpg" alt="Autoform seat covers fitted in a customer's Ford Endeavour at our shop" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <div className="actions">
          <WhatsAppButton message={askMessage()}>Ask for Autoform options</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      {autoform && (
        <section className="band band--tight" aria-label="Authorised Autoform dealer">
          <div className="wrap">
            <div className="dealer-lockup">
              <BrandLogo brand={autoform} size="lg" color />
              <p className="dealer-lockup__text"><strong>Authorised dealer</strong> · Pune</p>
            </div>
          </div>
        </section>
      )}

      <section className="band band--panel">
        <Reveal className="wrap split split--start">
          <SectionHead eyebrow="Why Autoform" title="Covers made for your model" lead="Autoform's covers are designed model by model. We supply them and fit them." />
          <ul className="points">
            {why.map((w) => <li key={w.t}><strong>{w.t}</strong><span>{w.d}</span></li>)}
          </ul>
        </Reveal>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Popular models" title="Autoform seat covers by car" lead="Tap your car to ask for Autoform options and the price on WhatsApp. Not listed? Send us your model anyway." /></Reveal>
          <Reveal className="cars">
            {cars.map((car) => (
              <a key={car} href={whatsapp(askMessage(car))} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
                <strong>{car.split(" ").slice(1).join(" ")}</strong>
                <span className="label">{car.split(" ")[0]}</span>
              </a>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="How it works" title="From WhatsApp to fitted" /></Reveal>
          <Reveal as="ol" className="process">{steps.map((s) => <li key={s.step}><strong>{s.step}</strong><span>{s.detail}</span></li>)}</Reveal>
        </div>
      </section>

      {reel?.video && (
        <section className="band">
          <Reveal className="wrap reel-feature">
            <video src={reel.video} poster={reel.poster} controls playsInline preload="none" width={405} height={720} aria-label={reel.title} />
            <div className="stack-lg">
              <SectionHead eyebrow="From our Instagram" title="Autoform seat covers in a Ford Endeavour" lead="A fitting at our shop, filmed for our Instagram. Follow us there to see more cars we've fitted." />
              <div className="actions">
                <a className="btn btn--ghost" href={reelUrl(reel.id)} target="_blank" rel="noopener noreferrer">Watch on Instagram <IconArrow /></a>
                <Link className="btn btn--ghost" href="/#reels-title">More reels</Link>
              </div>
            </div>
          </Reveal>
        </section>
      )}

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow={`Google reviews · ${site.rating}★`} title="What customers say about our seat covers" /></Reveal>
          <ReviewsCarousel topics={["autoform", "seat-covers"]} />
        </div>
      </section>

      <section className="band">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title="Autoform seat covers: common questions" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap stack-lg">
          <div className="stack">
            <p className="label">Also for your seats</p>
            <ul className="chips">
              <li><Link href="/seat-covers/">Custom seat covers</Link></li>
              <li><Link href="/accessories/">All car accessories</Link></li>
              <li><Link href="/car-detailing-pune/">Interior detailing</Link></li>
            </ul>
          </div>
          <Visit />
        </Reveal>
      </section>
      <CtaBand title="Send us your car model. We'll check Autoform covers for it." message={askMessage()} />
    </>
  );
}
