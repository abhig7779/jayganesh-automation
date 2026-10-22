import Link from "next/link";
import Hero from "./components/Hero";
import QuickQuote from "./components/QuickQuote";
import ModelTiles from "./components/ModelTiles";
import HowItWorks from "./components/HowItWorks";
import AccessoryRail from "./components/AccessoryRail";
import HomeGallery from "./components/HomeGallery";
import ReviewsCarousel from "./components/ReviewsCarousel";
import ReelsShowcase from "./components/ReelsShowcase";
import GoogleRating from "./components/GoogleRating";
import Reveal from "./components/Reveal";
import { CtaBand, Faq, JsonLd, Photo, Price, SectionHead, Visit, WhatsAppButton, IconArrow } from "./components/ui";
import { popularCars } from "@/lib/catalog";
import { offers, offerSeason } from "@/lib/offers";
import { site, whatsapp } from "@/lib/site";
import { breadcrumbLd, faqLd, graph, webPageLd } from "@/lib/seo";
import type { MediaKey } from "@/lib/media";
import type { PriceKey } from "@/lib/prices";

const studio: { href: string; name: string; line: string; m: MediaKey; price: PriceKey }[] = [
  { href: "/ppf-pune/", name: "Paint protection film", line: "Stops stone chips and scratches. Self-heals light marks.", m: "ppfApply", price: "ppfFrontKit" },
  { href: "/ceramic-coating-pune/", name: "Ceramic coating", line: "Deep gloss, water beading and easier washing for years.", m: "beadingDark", price: "ceramicFrom" },
  { href: "/car-detailing-pune/", name: "Detailing", line: "Exterior decontamination and interior deep cleaning, done by hand.", m: "polish", price: "washFrom" },
];

const faqs = [
  { q: "What does Jay Ganesh Car Accessories do?", a: "We sell and fit car accessories (seat covers, lights, Android stereos, speakers, 360° cameras and dashcams) and run a detailing studio for PPF, ceramic coating, car detailing and new-car protection, at Loni Kalbhor, Pune." },
  { q: "Where is the shop, and when is it open?", a: `Jay Ganesh Car Accessories (also searched as Jai Ganesh Car Accessories) is at ${site.addressShort}, Pune. We're open every day, 10 am to 8 pm. Call or WhatsApp ${site.phoneDisplay}.` },
  { q: "Should I choose PPF or ceramic coating?", a: "PPF is a clear film that stops stone chips and scratches. Ceramic coating adds deep gloss and makes washing easier but does not stop chips. Many owners fit PPF first and coat on top." },
  { q: "How do I get a price?", a: "Send your car model and what you need on WhatsApp. Prices depend on the car and the product, so we reply with options and prices for your model." },
  { q: "Is Jay Ganesh Car Accessories well reviewed?", a: `We're rated ${site.rating} on Google from ${site.reviews} reviews, and have served ${site.customers} customers from Loni Kalbhor, Hadapsar, Manjri, Uruli Kanchan and across Pune.` },
];

function Head({ id, eyebrow, title, lead, children }: { id: string; eyebrow?: string; title: React.ReactNode; lead?: string; children?: React.ReactNode }) {
  return (
    <div className="h-head">
      <div className="h-head-text">
        {eyebrow && <p className="label">{eyebrow}</p>}
        <h2 id={id}>{title}</h2>
        {lead && <p className="h-lead">{lead}</p>}
      </div>
      {children && <div className="h-head-aside">{children}</div>}
    </div>
  );
}

export default function Home() {
  const liveOffer = offers.find((o) => o.active);

  return (
    <>
      <JsonLd
        data={graph(
          webPageLd({ name: "Car accessories and PPF detailing studio in Loni Kalbhor, Pune", description: `Car accessories and car decor shop, and PPF, ceramic coating and detailing studio at ${site.addressShort}, Pune.`, path: "/" }),
          faqLd(faqs, "/"),
          breadcrumbLd([]),
        )}
      />
      <Hero />

      {/* Trust strip */}
      <div className="trust-x" role="group" aria-label="Why customers choose us">
        <div className="wrap">
          <ul>
            <li><GoogleRating variant="compact" /></li>
            <li>{site.customers} customers</li>
            <li>{site.hoursShort}</li>
          </ul>
        </div>
      </div>

      {/* Studio services */}
      <section className="h-sec" id="studio" aria-labelledby="studio-title">
        <div className="wrap">
          <Reveal>
            <Head id="studio-title" eyebrow="Studio services" title="The detailing studio" lead="PPF, ceramic coating and detailing, done by hand in our new studio at the toll naka.">
              <Link className="h-link" href="/detailing-studio/">All studio services <IconArrow /></Link>
            </Head>
            <div className="svc-x-grid">
              {studio.map((s) => (
                <Link key={s.href} href={s.href} className="svc-x">
                  <Photo label={s.name} m={s.m} ratio="3 / 4" sizes="(min-width: 900px) 33vw, 100vw" />
                  <div className="svc-x-body">
                    <h3>{s.name}</h3>
                    <p>{s.line}</p>
                    <div className="svc-x-foot">
                      <Price k={s.price} />
                      <span className="svc-x-go" aria-hidden><IconArrow /></span>
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </Reveal>
        </div>
      </section>

      {/* Google reviews */}
      <section className="h-sec" aria-labelledby="reviews-title">
        <div className="wrap">
          <Reveal>
            <Head id="reviews-title" eyebrow="Reviews" title={<>Rated {site.rating} on Google</>} lead="Families from Loni Kalbhor, Hadapsar, Manjri and Uruli Kanchan keep coming back, and now bring their new cars to the studio too.">
              <GoogleRating variant="inline" />
            </Head>
            <ReviewsCarousel />
          </Reveal>
        </div>
      </section>

      {/* Instagram reels */}
      <section className="h-sec h-sec--deep" aria-labelledby="reels-title">
        <div className="wrap">
          <Reveal>
            <Head id="reels-title" eyebrow="On Instagram" title="Watch our work" lead="Real cars, filmed in our studio: PPF, coatings and interiors." />
            <ReelsShowcase />
          </Reveal>
        </div>
      </section>

      {/* How it works */}
      <section className="h-sec h-sec--panel" aria-labelledby="how-title">
        <div className="wrap">
          <Reveal>
            <Head id="how-title" eyebrow="How it works" title="From inspection to handover" lead="The same careful process for every car that comes into the studio." />
            <HowItWorks />
          </Reveal>
        </div>
      </section>

      {/* Accessories */}
      <section className="h-sec" aria-labelledby="acc-title">
        <div className="wrap">
          <Reveal>
            <Head id="acc-title" eyebrow="Accessories" title="Upgrades, fitted at our shop" lead="Car accessories and car decor, chosen for your exact model and fitted by our team. Tell us your car and we'll send options.">
              <Link className="h-link" href="/accessories/">Browse accessories <IconArrow /></Link>
            </Head>
            <AccessoryRail />
          </Reveal>
        </div>
      </section>

      {/* Shop by car */}
      <section className="h-sec h-sec--panel" aria-labelledby="cars-title">
        <div className="wrap">
          <Reveal>
            <Head id="cars-title" eyebrow="Shop by car" title="What fits your car?" lead="Tap your model. We'll reply on WhatsApp with seat covers, lights, audio and PPF options made for it." />
            <ModelTiles />
          </Reveal>
        </div>
      </section>

      {/* Our work */}
      <section className="h-sec" aria-labelledby="gallery-title">
        <div className="wrap">
          <Reveal>
            <Head id="gallery-title" eyebrow="From the studio" title="Work you can see">
              <Link className="h-link" href="/our-work/">See our work <IconArrow /></Link>
            </Head>
            <HomeGallery />
          </Reveal>
        </div>
      </section>

      {/* Quick quote */}
      <section className="h-sec h-sec--panel" aria-labelledby="qq-title">
        <div className="wrap">
          <Reveal>
            <div className="qq-x">
              <div className="qq-x-text">
                <p className="label">Quick quote</p>
                <h2 id="qq-title">Get your price in 30 seconds</h2>
                <p className="h-lead">Pick what you need, type your car, and the message opens in WhatsApp, ready to send.</p>
                <p className="label">Reply on WhatsApp · {site.hoursShort}</p>
              </div>
              <QuickQuote />
            </div>
          </Reveal>
        </div>
      </section>

      {/* Offer */}
      {liveOffer && (
        <section className="h-sec h-sec--tight" aria-label="Current offer">
          <div className="wrap">
            <Reveal>
              <div className="offer-banner">
                <div className="stack">
                  <p className="label label--yellow">{offerSeason} · {liveOffer.until}</p>
                  <h3>{liveOffer.title}</h3>
                  <Price k={liveOffer.price} />
                </div>
                <div className="actions">
                  <Link className="btn btn--ghost" href="/offers/">All offers</Link>
                  <WhatsAppButton message={liveOffer.whatsappMessage}>Book this offer</WhatsAppButton>
                </div>
              </div>
            </Reveal>
          </div>
        </section>
      )}

      <section className="h-sec" aria-label="Common questions">
        <div className="wrap">
          <Reveal className="split split--start split--sticky">
            <SectionHead eyebrow="Questions" title="Common questions" />
            <Faq items={faqs} />
          </Reveal>
        </div>
      </section>

      <section className="h-sec h-sec--tight" aria-label="Visit the shop">
        <div className="wrap"><Visit /></div>
      </section>

      <CtaBand />
    </>
  );
}
