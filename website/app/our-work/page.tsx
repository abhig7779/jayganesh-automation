import type { Metadata } from "next";
import Link from "next/link";
import { CtaBand, PageHero, Photo, SectionHead } from "../components/ui";
import Reveal from "../components/Reveal";
import { jobs } from "@/lib/work";
import { site } from "@/lib/site";

export const metadata: Metadata = {
  title: { absolute: "Our Work: PPF, Ceramic, Seat Covers and More | Jay Ganesh" },
  description: "Real jobs from our Loni Kalbhor shop and studio: PPF, ceramic coating, seat covers, detailing and accessory fittings.",
  alternates: { canonical: "/our-work/" },
};

export default function OurWork() {
  return (
    <>
      <PageHero
        crumbs={[{ label: "Our work" }]}
        eyebrow="From the shop and studio"
        title="Our work"
        lead="Every job here was done for a customer at our shop. More go up on Instagram every week."
        media={<Photo label="Paint correction on a bonnet" m="polish" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <div className="actions">
          <a className="btn btn--ghost" href={site.instagramHref} target="_blank" rel="noopener noreferrer">Follow on Instagram</a>
        </div>
      </PageHero>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Recent jobs" title="From the shop and studio" /></Reveal>
          <Reveal className="grid grid-3">
            {jobs.map((j) => (
              <Link key={j.slug} href={j.service} className="job card--link">
                <Photo label={`${j.car} · ${j.job}`} m={j.photo} ratio="4 / 3" />
                <div>
                  <p className="label label--yellow">{j.car} · {j.time}</p>
                  <h3>{j.job}</h3>
                </div>
              </Link>
            ))}
          </Reveal>
        </div>
      </section>

      <CtaBand title="Want your car to look like this?" message="Hi Jay Ganesh, I saw your work on the website. Car model: " />
    </>
  );
}
