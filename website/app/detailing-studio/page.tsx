import type { Metadata } from "next";
import Link from "next/link";
import { CtaBand, Faq, JsonLd, Photo, Price, SectionHead, Visit, WhatsAppButton, CallButton, IconArrow } from "../components/ui";
import { PageHero } from "../components/PageHero";
import Reveal from "../components/Reveal";
import { services } from "@/lib/services";
import { breadcrumbLd, faqLd, graph, pageMetadata, serviceLd, webPageLd } from "@/lib/seo";

const path = "/detailing-studio/";
const title = "Car Detailing Studio in Pune: PPF, Ceramic | Jay Ganesh";
const description = "Our detailing studio at Loni Kalbhor, Pune: paint protection film, ceramic coating, foam wash and interior deep cleaning. Book a free paint inspection.";
const lead = "The Jay Ganesh detailing studio does paint protection film (PPF), ceramic coating, car detailing and new-car protection, all indoors under proper light. It's next to our accessories shop at Kawadipat Toll Naka on the Pune–Solapur road, Loni Kalbhor, for car owners from Hadapsar, Manjri, Kharadi, Wagholi and across Pune.";

export const metadata: Metadata = pageMetadata({ title, description, path });

const faqs = [
  { q: "What does the Jay Ganesh detailing studio do?", a: "Paint protection film (PPF), ceramic coating, car detailing (foam wash, decontamination and interior deep cleaning) and new-car protection packages, all done indoors at Kawadipat Toll Naka, Loni Kalbhor." },
  { q: "Should I choose PPF or ceramic coating?", a: "PPF protects the paint from stone chips and scratches. Ceramic coating adds gloss and makes washing easier but does not stop chips. Many owners fit PPF first, then coat on top." },
  { q: "Is the paint inspection free?", a: "Yes. Bring the car in, or send photos on WhatsApp, and we'll check the paint with you and suggest what it needs before you decide." },
  { q: "Do I need to book a slot?", a: "Yes, for studio work. Message us on WhatsApp with your car model and the service you want, and we'll keep a slot for your car." },
];

export default function StudioHub() {
  return (
    <>
      <JsonLd
        data={graph(
          serviceLd({ name: "Car detailing studio: PPF, ceramic coating and detailing", serviceType: "Car detailing", description: lead, path }),
          webPageLd({ name: title, description, path }),
          faqLd(faqs, path),
          breadcrumbLd([{ name: "Detailing studio", path }]),
        )}
      />
      <PageHero
        crumbs={[{ label: "Detailing studio" }]}
        eyebrow="New at Jay Ganesh"
        title="The detailing studio"
        lead={lead}
        image="detailing-studio"
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
          <p className="pc-note" style={{ marginTop: 18 }}>
            Also at the studio: <Link href="/car-window-film-pune/">sun-control window film</Link>, fitted within India&apos;s legal visible-light limits.
          </p>
        </div>
      </section>

      <section className="band">
        <Reveal className="wrap split split--start split--sticky">
          <SectionHead eyebrow="Questions" title="The studio: common questions" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <section className="band band--tight">
        <Reveal className="wrap"><Visit /></Reveal>
      </section>
      <CtaBand title="Not sure what your car needs? Send us a photo." message="Hi Jay Ganesh, here's a photo of my car. What do you suggest? Car model: " />
    </>
  );
}
