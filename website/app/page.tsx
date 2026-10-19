import Image from "next/image";
import SceneLoader from "./components/SceneLoader";
import Footer from "./components/Footer";
import { site } from "@/lib/site";

const services = [
  { name: "Seat covers", note: "Made for your car model" },
  { name: "PPF & ceramic", note: "New studio", isNew: true },
  { name: "Detailing", note: "New studio", isNew: true },
  { name: "Lights", note: "Headlights, fog, ambient" },
  { name: "Audio & screens", note: "Android stereos, speakers" },
  { name: "Dashcams", note: "Fitted and wired" },
];

export default function Home() {
  return (
    <>
      <SceneLoader />
      <main className="home">
        <section className="panel hero" aria-labelledby="hero-title">
          <Image className="hero-logo" src="/logo.png" alt={site.name} width={690} height={480} priority />
          <p className="eyebrow">Loni Kalbhor · Pune–Solapur Road</p>
          <h1 id="hero-title">
            New website <span className="yellow">coming soon</span>
          </h1>
          <div className="rule" aria-hidden />
          <p>
            Car accessories, seat covers, lighting, audio, and our new PPF and detailing studio. Until the site is ready,
            message us on WhatsApp.
          </p>
          <div className="actions">
            <a className="btn btn-primary" href={site.whatsappHref} target="_blank" rel="noopener noreferrer">
              WhatsApp us
            </a>
            <a className="btn btn-ghost" href={site.phoneHref}>
              Call {site.phoneDisplay}
            </a>
          </div>
          <div className="scroll-cue" aria-hidden>
            <span /> Scroll
          </div>
        </section>

        <section className="panel services" aria-labelledby="services-title">
          <p className="eyebrow">What we fit</p>
          <h2 id="services-title">Everything for your car, under one roof</h2>
          <ul className="service-list">
            {services.map((s) => (
              <li key={s.name} className={s.isNew ? "new" : undefined}>
                {s.name}
                <small>{s.note}</small>
              </li>
            ))}
          </ul>
        </section>

        <section className="panel visit" aria-labelledby="visit-title">
          <p className="eyebrow">Visit the shop</p>
          <h2 id="visit-title">Rated {site.rating} on Google</h2>
          <div className="visit-grid">
            <div className="visit-card">
              <h3>Reviews</h3>
              <p className="big">{site.rating} ★</p>
              <p>from {site.reviews} Google reviews</p>
            </div>
            <div className="visit-card">
              <h3>Address</h3>
              <p>{site.address}</p>
            </div>
            <div className="visit-card">
              <h3>Hours</h3>
              <p>{site.hours}</p>
            </div>
          </div>
          <div className="actions">
            <a className="btn btn-primary" href={site.mapsHref} target="_blank" rel="noopener noreferrer">
              Get directions
            </a>
            <a className="btn btn-ghost" href={site.instagramHref} target="_blank" rel="noopener noreferrer">
              Instagram {site.instagram}
            </a>
          </div>
        </section>
        <Footer />
      </main>
    </>
  );
}
