import type { Metadata } from "next";
import Link from "next/link";
import { CtaBand, PageHero, Photo, Price, Visit, WhatsAppButton, CallButton, IconArrow } from "../components/ui";
import Reveal from "../components/Reveal";
import { categories } from "@/lib/catalog";

export const metadata: Metadata = {
  title: { absolute: "Car Accessories in Pune: Seat Covers, Lights, Stereos | Jay Ganesh" },
  description: "Seat covers, LED headlights, fog lamps, ambient lighting, Android stereos, 360° cameras and dashcams, fitted at our shop in Loni Kalbhor. WhatsApp your car model.",
  alternates: { canonical: "/accessories/" },
};

const groups = ["Interior", "Lights", "Tech", "Exterior"] as const;

export default function Accessories() {
  return (
    <>
      <PageHero
        crumbs={[{ label: "Accessories" }]}
        eyebrow="Fitted at our shop"
        title="Car accessories in Pune"
        lead="Seat covers, lights, Android stereos, cameras and more, chosen for your car model and fitted by our team in Loni Kalbhor."
        media={<Photo label="The accessories shop floor" m="interior" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <div className="actions">
          <WhatsAppButton message="Hi Jay Ganesh, I'm looking for accessories. Car model: ">WhatsApp your car model</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <nav className="group-nav" aria-label="Accessory groups">
        <div className="wrap">
          <ul className="chips">
            {groups.map((g) => <li key={g}><a href={`#g-${g}`}>{g}</a></li>)}
          </ul>
        </div>
      </nav>

      {groups.map((g, gi) => (
        <section key={g} className={`band${gi % 2 ? " band--panel" : ""}`} aria-labelledby={`g-${g}`}>
          <div className="wrap">
            <Reveal className="section-head">
              <p className="label label--yellow eyebrow">{`0${gi + 1}`}</p>
              <h2 id={`g-${g}`} style={{ scrollMarginTop: "calc(var(--header-h) + 96px)" }}>{g}</h2>
            </Reveal>
            <Reveal className="grid grid-3">
              {categories.filter((c) => c.group === g).map((c) => (
                <article key={c.key} id={c.key} className="cat">
                  <Photo label={`${c.name}, fitted in a customer's car`} m={c.photo} ratio="16 / 10" />
                  <h3>{c.name}</h3>
                  <p>{c.blurb}</p>
                  <Price k={c.price} />
                  {c.href ? (
                    <Link className="btn btn--ghost btn--sm" href={c.href}>See {c.name.toLowerCase()} <IconArrow /></Link>
                  ) : (
                    <WhatsAppButton variant="ghost" size="sm" message={`Hi Jay Ganesh, I'm looking for ${c.name.toLowerCase()}. Car model: `}>Ask for a price</WhatsAppButton>
                  )}
                </article>
              ))}
            </Reveal>
          </div>
        </section>
      ))}

      <section className="band band--tight">
        <Reveal className="wrap"><Visit /></Reveal>
      </section>
      <CtaBand message="Hi Jay Ganesh, I'm looking for accessories. Car model: " />
    </>
  );
}
