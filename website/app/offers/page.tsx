import type { Metadata } from "next";
import { CtaBand, Crumbs, Price, SectionHead, Visit, WhatsAppButton } from "../components/ui";
import { offers, offerSeason } from "@/lib/offers";
import { facts } from "@/lib/prices";

export const metadata: Metadata = {
  title: { absolute: `Offers: ${offerSeason} | Jay Ganesh Car Accessories` },
  description: "Current offers on foam wash, PPF, new-car protection and accessories combos at Jay Ganesh, Loni Kalbhor. Book on WhatsApp.",
  alternates: { canonical: "/offers/" },
};

export default function Offers() {
  const active = offers.filter((o) => o.active);
  return (
    <>
      <section className="page-hero">
        <div className="wrap">
          <div className="page-hero-copy">
            <Crumbs items={[{ label: "Offers" }]} />
            <p className="label label--yellow">{offerSeason}</p>
            <h1>Offers this season</h1>
            <hr className="rule" />
            <p className="lead">Book on WhatsApp to hold your slot. Offers are for bookings made during the dates shown.</p>
            {facts.slotsLeft != null && <p className="price-ask" style={{ width: "fit-content" }}>{facts.slotsLeft} slots left before Dhanteras</p>}
          </div>
        </div>
      </section>

      <section className="band">
        <div className="wrap">
          {active.length ? (
            <div className="grid grid-2">
              {active.map((o) => (
                <article key={o.title} className="offer">
                  <span className="label label--yellow offer-until">{o.until}</span>
                  <h3>{o.title}</h3>
                  <p style={{ color: "var(--ink-muted)" }}>
                    {o.detail}
                    {o.title.startsWith("Full-body PPF") && facts.ppfLaunchBonus ? ` Launch bonus: ${facts.ppfLaunchBonus}.` : ""}
                  </p>
                  <Price k={o.price} />
                  <WhatsAppButton message={o.whatsappMessage}>Book this offer</WhatsAppButton>
                </article>
              ))}
            </div>
          ) : (
            <SectionHead title="No offers right now" lead="Follow us on Instagram to hear about the next one, or WhatsApp us for a price today." />
          )}
          <p className="label" style={{ marginTop: 24 }}>Offers can&apos;t be combined unless stated. See our <a href="/terms/">terms</a>.</p>
        </div>
      </section>

      <section className="band band--tight"><div className="wrap"><Visit /></div></section>
      <CtaBand />
    </>
  );
}
