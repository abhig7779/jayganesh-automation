import type { Metadata } from "next";
import { breadcrumbLd, graph, pageMetadata, webPageLd } from "@/lib/seo";
import { JsonLd, CtaBand, PageHero, Price, SectionHead, Visit, WhatsAppButton } from "../components/ui";
import Reveal from "../components/Reveal";
import { offers, offerSeason } from "@/lib/offers";
import { facts } from "@/lib/prices";

const path = "/offers/";
const title = `Offers: ${offerSeason}, Loni Kalbhor | Jay Ganesh`;
const description =
  "Current offers on foam wash, full-body PPF, new-car protection and accessories at Jay Ganesh, Loni Kalbhor, Pune. Book your slot on WhatsApp.";

export const metadata: Metadata = pageMetadata({ title, description, path });

export default function Offers() {
  const active = offers.filter((o) => o.active);
  return (
    <>
      <JsonLd data={graph(webPageLd({ name: title, description, path }), breadcrumbLd([{ name: "Offers", path }]))} />
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
                  <h2>{o.title}</h2>
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
