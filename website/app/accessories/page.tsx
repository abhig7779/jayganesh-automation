import type { Metadata } from "next";
import Link from "next/link";
import { CtaBand, Crumbs, Photo, Price, SectionHead, Visit, WhatsAppButton, CallButton, IconArrow } from "../components/ui";
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
      <section className="page-hero">
        <div className="wrap">
          <div className="page-hero-copy">
            <Crumbs items={[{ label: "Accessories" }]} />
            <p className="label label--yellow">Fitted at our shop</p>
            <h1>Car accessories in Pune</h1>
            <hr className="rule" />
            <p className="lead">Seat covers, lights, Android stereos, cameras and more, chosen for your car model and fitted by our team in Loni Kalbhor.</p>
            <div className="actions">
              <WhatsAppButton message="Hi Jay Ganesh, I'm looking for accessories. Car model: ">WhatsApp your car model</WhatsAppButton>
              <CallButton />
            </div>
          </div>
          <Photo label="The accessories shop floor" m="interior" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />
        </div>
      </section>

      {groups.map((g, gi) => (
        <section key={g} className={`band${gi % 2 ? " band--panel" : ""}`} aria-labelledby={`g-${g}`}>
          <div className="wrap">
            <SectionHead eyebrow={`0${gi + 1}`} title={g} />
            <div className="grid grid-3">
              {categories.filter((c) => c.group === g).map((c) => (
                <article key={c.key} id={c.key} className="cat">
                  <Photo label={`${c.name}, fitted in a customer's car`} m={c.photo} ratio="16 / 10" />
                  <h3>{c.name}</h3>
                  <p style={{ color: "var(--ink-muted)" }}>{c.blurb}</p>
                  <Price k={c.price} />
                  {c.href ? (
                    <Link className="btn btn--ghost btn--sm" href={c.href} style={{ width: "fit-content" }}>See {c.name.toLowerCase()} <IconArrow /></Link>
                  ) : (
                    <WhatsAppButton variant="ghost" size="sm" message={`Hi Jay Ganesh, I'm looking for ${c.name.toLowerCase()}. Car model: `}>Ask for a price</WhatsAppButton>
                  )}
                </article>
              ))}
            </div>
          </div>
        </section>
      ))}

      <section className="band band--tight">
        <div className="wrap"><Visit /></div>
      </section>
      <CtaBand message="Hi Jay Ganesh, I'm looking for accessories. Car model: " />
    </>
  );
}
