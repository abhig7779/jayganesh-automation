import type { Metadata } from "next";
import { CtaBand, PageHero, Price, SectionHead, Visit, WhatsAppButton } from "../components/ui";
import Reveal from "../components/Reveal";
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
      <PageHero
        crumbs={[{ label: "Offers" }]}
        eyebrow={offerSeason}
        title="Offers this season"
        lead="Book on WhatsApp to hold your slot. Offers are for bookings made during the dates shown."
      >
        {facts.slotsLeft != null && <p className="price-ask offer-slots">{facts.slotsLeft} slots left before Dhanteras</p>}
      </PageHero>

      <section className="band band--panel">
        <div className="wrap">
          {active.length ? (
            <Reveal className="grid grid-2">
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
            </Reveal>
          ) : (
            <SectionHead title="No offers right now" lead="Follow us on Instagram to hear about the next one, or WhatsApp us for a price today." />
          )}
          <p className="offers-note">Offers can&apos;t be combined unless stated. See our <a href="/terms/">terms</a>.</p>
        </div>
      </section>

      <section className="band band--tight"><Reveal className="wrap"><Visit /></Reveal></section>
      <CtaBand />
    </>
  );
}
