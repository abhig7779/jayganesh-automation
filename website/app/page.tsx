import Link from "next/link";
import PpfStory from "./components/PpfStory";
import QuickQuote from "./components/QuickQuote";
import HeroCompare from "./components/HeroCompare";
import { CtaBand, Photo, Price, SectionHead, Trust, Visit, WhatsAppButton, IconArrow } from "./components/ui";
import { popularCars } from "@/lib/catalog";
import { offers, offerSeason } from "@/lib/offers";
import { site, whatsapp } from "@/lib/site";
import type { MediaKey } from "@/lib/media";
import type { PriceKey } from "@/lib/prices";

const studio: { href: string; name: string; line: string; m: MediaKey; price: PriceKey }[] = [
  { href: "/ppf-pune/", name: "Paint protection film", line: "Stops stone chips and scratches. Self-heals light marks.", m: "ppfApply", price: "ppfFrontKit" },
  { href: "/ceramic-coating-pune/", name: "Ceramic coating", line: "Deep gloss, water beading and easier washing for years.", m: "beadingDark", price: "ceramicFrom" },
  { href: "/car-detailing-pune/", name: "Detailing", line: "Snow foam wash and interior deep cleaning, done by hand.", m: "foam", price: "washFrom" },
];

const accessories = [
  { name: "Seat covers", href: "/seat-covers/" },
  { name: "Headlights", href: "/accessories/#headlights" },
  { name: "Ambient lighting", href: "/accessories/#ambient" },
  { name: "Android stereos", href: "/accessories/#stereo" },
  { name: "360° cameras", href: "/accessories/#camera" },
  { name: "Dashcams", href: "/accessories/#dashcam" },
];

export default function Home() {
  const liveOffer = offers.find((o) => o.active);

  return (
    <>
      {/* Hero */}
      <section className="hero" aria-labelledby="hero-title">
        <div className="wrap hero-grid">
          <div className="hero-copy">
            {liveOffer && (
              <Link href="/offers/" className="hero-offer">
                <span className="hero-offer-dot" aria-hidden />
                {offerSeason} · {liveOffer.title}
                <IconArrow />
              </Link>
            )}
            <h1 id="hero-title">Your car, done right. <em>Close to home.</em></h1>
            <p className="lead">
              PPF, ceramic coating and detailing in our new studio, plus the seat covers, lights and stereos eastern Pune already comes to us for.
            </p>
            <div className="hero-proof">
              <span className="hero-stars" aria-hidden>★★★★★</span>
              <span><strong>{site.rating}</strong> from {site.reviews} Google reviews</span>
              <span className="hero-sep" aria-hidden />
              <span>{site.customers} customers</span>
            </div>
            <QuickQuote />
          </div>
          <div className="hero-visual">
            <HeroCompare />
            <p className="compare-caption label"><span>Drag to compare</span><span>Same car · before and after PPF</span></p>
          </div>
        </div>
      </section>
      <Trust />

      {/* Studio services */}
      <section className="band" aria-labelledby="studio-title">
        <div className="wrap">
          <div className="section-row">
            <SectionHead eyebrow="The detailing studio" title="Protection that lasts longer than the shine" />
            <Link className="text-link" href="/detailing-studio/">All studio services <IconArrow /></Link>
          </div>
          <div className="svc-grid">
            {studio.map((s) => (
              <Link key={s.href} href={s.href} className="svc">
                <Photo label={s.name} m={s.m} ratio="4 / 5" sizes="(min-width: 900px) 33vw, 100vw" />
                <div className="svc-body">
                  <h3>{s.name}</h3>
                  <p>{s.line}</p>
                  <div className="svc-foot"><Price k={s.price} /><span className="svc-go" aria-hidden><IconArrow /></span></div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* 3D scroll story */}
      <PpfStory />

      {/* Accessories */}
      <section className="band band--panel" aria-labelledby="acc-title">
        <div className="wrap split">
          <div className="stack-lg">
            <SectionHead eyebrow="Accessories" title="Upgrades, fitted at our shop" lead="Chosen for your exact model and fitted by our team. Tell us your car and we'll send options." />
            <ul className="chips">
              {accessories.map((a) => <li key={a.name}><Link href={a.href}>{a.name}</Link></li>)}
            </ul>
            <div className="actions">
              <Link className="btn btn--ghost" href="/accessories/">Browse accessories <IconArrow /></Link>
            </div>
          </div>
          <div className="mosaic">
            <Photo label="Interior" m="interior" ratio="1 / 1" sizes="(min-width: 960px) 25vw, 50vw" />
            <Photo label="Headlights" m="headlight" ratio="1 / 1" sizes="(min-width: 960px) 25vw, 50vw" />
            <Photo label="Lights" m="lightsDark" ratio="2 / 1" className="mosaic-wide" sizes="(min-width: 960px) 50vw, 100vw" />
          </div>
        </div>
      </section>

      {/* Shop by car */}
      <section className="band" aria-labelledby="cars-title">
        <div className="wrap">
          <div className="section-row">
            <SectionHead eyebrow="Shop by car" title="What fits your car?" lead="Tap your model. We'll reply with seat covers, lights, audio and PPF options made for it." />
          </div>
          <div className="cars">
            {popularCars.map((car) => (
              <a key={car} href={whatsapp(`Hi Jay Ganesh, what accessories and PPF options do you have for my ${car}?`)} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
                <span className="label">{car.split(" ")[0]}</span>
                <strong>{car.split(" ").slice(1).join(" ")}</strong>
              </a>
            ))}
          </div>
        </div>
      </section>

      {/* Gallery */}
      <section className="band band--panel" aria-labelledby="gallery-title">
        <div className="wrap">
          <div className="section-row">
            <SectionHead eyebrow="From the studio" title="Work you can see" />
            <Link className="text-link" href="/our-work/">See our work <IconArrow /></Link>
          </div>
          <div className="gallery">
            <Photo label="Polishing" m="polish" ratio="3 / 4" sizes="(min-width: 900px) 25vw, 50vw" />
            <Photo label="Foam wash" m="foamDark" ratio="3 / 4" sizes="(min-width: 900px) 25vw, 50vw" />
            <Photo label="Water beading" m="beadingClose" ratio="3 / 4" sizes="(min-width: 900px) 25vw, 50vw" />
            <Photo label="Interior detailing" m="dashClean" ratio="3 / 4" sizes="(min-width: 900px) 25vw, 50vw" />
          </div>
        </div>
      </section>

      {/* Reviews */}
      <section className="band" aria-labelledby="reviews-title">
        <div className="wrap split">
          <SectionHead eyebrow="Reviews" title="Trusted on the highway for years" lead="Families from Loni Kalbhor, Hadapsar, Manjri and Uruli Kanchan keep coming back, and now bring their new cars to the studio too." />
          <div className="rating">
            <div className="rating-score"><strong>{site.rating}</strong><span className="stars" aria-hidden>★★★★★</span></div>
            <p>From {site.reviews} reviews on Google</p>
            <div className="actions">
              <a className="btn btn--ghost" href={site.reviewsHref} target="_blank" rel="noopener noreferrer">Read the reviews</a>
            </div>
          </div>
        </div>
      </section>

      {/* Offer */}
      {liveOffer && (
        <section className="band band--tight" aria-label="Current offer">
          <div className="wrap">
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
          </div>
        </section>
      )}

      <section className="band band--tight" aria-label="Visit the shop">
        <div className="wrap"><Visit /></div>
      </section>

      <CtaBand />
    </>
  );
}
