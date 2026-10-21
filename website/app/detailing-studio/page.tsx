import type { Metadata } from "next";
import Link from "next/link";
import { CtaBand, PageHero, Photo, Price, SectionHead, Visit, WhatsAppButton, CallButton, IconArrow } from "../components/ui";
import Reveal from "../components/Reveal";
import { services } from "@/lib/services";

export const metadata: Metadata = {
  title: { absolute: "Car Detailing Studio in Pune: PPF, Ceramic, Detailing | Jay Ganesh" },
  description: "Our new studio at Loni Kalbhor: paint protection film, ceramic coating, foam wash and interior deep cleaning. Book a free paint inspection on WhatsApp.",
  alternates: { canonical: "/detailing-studio/" },
};

export default function StudioHub() {
  return (
    <>
      <PageHero
        crumbs={[{ label: "Detailing studio" }]}
        eyebrow="New at Jay Ganesh"
        title="The detailing studio"
        lead="PPF, ceramic coating and detailing, done indoors under proper light. Next to the accessories shop you already know, on the Pune–Solapur road."
        media={<Photo label="The studio bay with the ceiling lights on" m="hero" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <div className="actions">
          <WhatsAppButton message="Hi Jay Ganesh, I'd like to book a free paint inspection. Car model: ">Book a free paint inspection</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Services" title="What the studio does" /></Reveal>
          <Reveal className="grid grid-2">
            {services.map((s) => (
              <Link key={s.slug} href={`/${s.slug}/`} className="card card--link">
                <Photo label={s.photoSlots[0]} m={s.photos[0]} ratio="16 / 9" sizes="(min-width: 720px) 50vw, 100vw" />
                <p className="label label--yellow">{s.menuLabel}</p>
                <h3>{s.h1}</h3>
                <p>{s.lead}</p>
                <div className="spec-row"><Price k={s.packages[0].price} /><span className="text-link">Details <IconArrow /></span></div>
              </Link>
            ))}
          </Reveal>
        </div>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap"><Visit /></Reveal>
      </section>
      <CtaBand title="Not sure what your car needs? Send us a photo." message="Hi Jay Ganesh, here's a photo of my car. What do you suggest? Car model: " />
    </>
  );
}
