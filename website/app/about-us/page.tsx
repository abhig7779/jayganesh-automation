import type { Metadata } from "next";
import Link from "next/link";
import { CallButton, CtaBand, Faq, IconArrow, JsonLd, PageHero, Photo, SectionHead, Trust, Visit, WhatsAppButton } from "../components/ui";
import Reveal from "../components/Reveal";
import { site } from "@/lib/site";

export const metadata: Metadata = {
  title: { absolute: "About Jay Ganesh Car Accessories | Car Accessories & PPF Studio, Loni Kalbhor, Pune" },
  description:
    "Jay Ganesh Car Accessories is the car accessories shop on the Pune–Solapur road at Loni Kalbhor, now with a PPF, ceramic coating and detailing studio. Rated 4.9 on Google, open 7 days.",
  alternates: { canonical: "/about-us/" },
};

const whatWeDo = [
  { href: "/accessories/", label: "Accessories", title: "Accessories, fitted for your car", text: "Seat covers, lights, Android stereos, speakers, 360° cameras and dashcams, chosen for your model and fitted at the shop." },
  { href: "/detailing-studio/", label: "Studio", title: "The detailing studio", text: "An indoor studio at Kawadipat Toll Naka for paint protection, coatings and deep cleaning, done under proper light." },
  { href: "/ppf-pune/", label: "PPF", title: "Paint protection film", text: "A clear film that takes the stone chips and scratches so your paint doesn't." },
  { href: "/ceramic-coating-pune/", label: "Ceramic", title: "Ceramic coating", text: "Deep gloss, and a car that's far easier to wash." },
  { href: "/seat-covers/", label: "Interior", title: "Seat covers", text: "Made and fitted for your exact car model, so they sit right." },
];

const reasons = [
  { t: "Fitted for your exact model", d: "Tell us the car and the year. We pick parts that fit it, not a one-size-fits-all box." },
  { t: "Honest advice and pricing", d: "We tell you what your car needs and what it doesn't, and give you the price before we start." },
  { t: "Neat installation", d: "Wiring tucked away, trims put back properly, and everything tested before you drive out." },
  { t: "Open 7 days", d: `${site.hours}. Come in on a weekend or after work.` },
];

const areas = ["Loni Kalbhor", "Hadapsar", "Manjri", "Uruli Kanchan", "Kharadi", "Wagholi", "Fursungi"];

const faqs = [
  { q: "Where is Jay Ganesh Car Accessories?", a: `We're at ${site.address}, right at the toll naka on the Pune–Solapur road.` },
  { q: "Do you have a separate detailing studio?", a: "Yes. The PPF, ceramic coating and detailing work happens in our studio at Kawadipat Toll Naka, next to the accessories shop." },
  { q: "Do I need an appointment?", a: "You can walk in for accessories. For PPF, coatings and detailing, message us on WhatsApp first so we can keep a slot for your car." },
  { q: "Can you tell me the price before I come?", a: "Yes. Send your car model and what you're looking for on WhatsApp and we'll reply with options and prices." },
  { q: "Which areas do your customers come from?", a: `Mostly eastern Pune: ${areas.join(", ")}, and travellers on the Pune–Solapur highway.` },
];

const jsonLd = [
  {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: [
      { "@type": "ListItem", position: 1, name: "Home", item: `${site.url}/` },
      { "@type": "ListItem", position: 2, name: "About us", item: `${site.url}/about-us/` },
    ],
  },
  {
    "@context": "https://schema.org",
    "@type": "AboutPage",
    name: `About ${site.name}`,
    url: `${site.url}/about-us/`,
    about: { "@id": `${site.url}/#business` },
  },
  {
    "@context": "https://schema.org",
    "@type": "AutoPartsStore",
    "@id": `${site.url}/#business`,
    name: site.name,
    url: site.url,
    image: `${site.url}/logo.png`,
    telephone: site.phoneE164,
    address: {
      "@type": "PostalAddress",
      streetAddress: "Kawadipat, Toll Naka, Pune–Solapur Road",
      addressLocality: site.locality,
      addressRegion: site.region,
      addressCountry: "IN",
    },
    areaServed: areas.map((a) => ({ "@type": "Place", name: `${a}, Pune` })),
    aggregateRating: { "@type": "AggregateRating", ratingValue: site.rating, reviewCount: String(site.reviews).replace(/[^\d]/g, ""), bestRating: "5" },
    sameAs: [site.instagramHref, site.youtubeHref, site.facebookHref],
  },
];

export default function AboutUs() {
  return (
    <>
      <JsonLd data={jsonLd} />
      <PageHero
        crumbs={[{ label: "About us" }]}
        eyebrow="About Jay Ganesh"
        title="The car accessories shop eastern Pune trusts"
        lead="A shop on the Pune–Solapur highway at Loni Kalbhor, known for fitting the right parts to the right car. Now with a PPF and detailing studio next door."
        media={<Photo label="The detailing studio" m="studioHero" ratio="4 / 5" eager sizes="(min-width: 1024px) 45vw, 100vw" />}
      >
        <div className="actions">
          <WhatsAppButton message="Hi Jay Ganesh, I'd like to know more. Car model: ">WhatsApp us</WhatsAppButton>
          <CallButton />
        </div>
      </PageHero>

      <Trust />

      <section className="band band--panel">
        <Reveal className="wrap split">
          <div className="stack-lg">
            <SectionHead eyebrow="Our story" title="Started as a shop on the highway" />
            <div className="prose">
              <p>
                Jay Ganesh began as a car accessories shop at Kawadipat Toll Naka, on the road between Pune and Solapur.
                Drivers from Loni Kalbhor, Hadapsar and the villages around kept coming back because the parts fit and the work was neat.
              </p>
              <p>
                Over the years <strong>{site.customers} customers</strong> have trusted us with their cars, and they&apos;ve rated us
                <strong> {site.rating} on Google</strong> across {site.reviews} reviews.
              </p>
              <p>
                Customers started asking for more than accessories: protection for new paint, gloss that lasts, a proper deep clean.
                So we built a <Link href="/detailing-studio/">PPF and detailing studio</Link> at the same toll naka, where that work is done indoors under studio lights.
              </p>
            </div>
          </div>
          <Photo label="Laying paint protection film on a bonnet" m="ppfApply" ratio="4 / 3" sizes="(min-width: 960px) 45vw, 100vw" />
        </Reveal>
      </section>

      <section className="band">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="What we do" title="Accessories and studio work, in one place" lead="Bring your car once. Leave with it protected, cleaned and fitted out." /></Reveal>
          <Reveal className="grid grid-3">
            {whatWeDo.map((w) => (
              <Link key={w.href} href={w.href} className="card card--link">
                <p className="label label--yellow">{w.label}</p>
                <h3>{w.title}</h3>
                <p>{w.text}</p>
                <span className="text-link">Learn more <IconArrow /></span>
              </Link>
            ))}
            <Link href="/car-detailing-pune/" className="card card--link">
              <p className="label label--yellow">Detailing</p>
              <h3>Car detailing</h3>
              <p>Foam wash, polishing and interior deep cleaning, inside and out.</p>
              <span className="text-link">Learn more <IconArrow /></span>
            </Link>
          </Reveal>
        </div>
      </section>

      <section className="band band--panel">
        <Reveal className="wrap split split--start">
          <SectionHead eyebrow="Why customers choose us" title="Simple reasons, kept every day" lead={`It's why we're rated ${site.rating} on Google by ${site.reviews} customers.`} />
          <ul className="points">
            {reasons.map((r) => (
              <li key={r.t}><strong>{r.t}</strong><span>{r.d}</span></li>
            ))}
          </ul>
        </Reveal>
      </section>

      <section className="band">
        <Reveal className="wrap split split--flip">
          <div className="stack-lg">
            <SectionHead eyebrow="The team" title="Hands-on installers, in the black and yellow" />
            <div className="prose">
              <p>
                You&apos;ll know the team by the uniform: a black half-sleeve t-shirt with yellow sleeves.
                The people who talk you through the options are the same people who fit the parts and work on your paint.
              </p>
              <p>
                Ask them anything while you wait. They&apos;ll show you what was done before you take the keys back.
              </p>
            </div>
          </div>
          <div className="about-photos">
            <Photo label="Machine polishing before a coating" m="polish" ratio="4 / 5" sizes="(min-width: 960px) 22vw, 50vw" />
            <Photo label="A finished interior" m="interior" ratio="4 / 5" sizes="(min-width: 960px) 22vw, 50vw" />
          </div>
        </Reveal>
      </section>

      <section className="band band--panel">
        <div className="wrap">
          <Reveal><SectionHead eyebrow="Areas we serve" title="Eastern Pune and the Solapur highway" lead="Most of our customers drive in from these areas. The shop is an easy run down the Pune–Solapur road." /></Reveal>
          <Reveal>
            <ul className="chips">
              {areas.map((a) => <li key={a}><span>{a}</span></li>)}
            </ul>
          </Reveal>
          <Reveal className="about-visit"><Visit /></Reveal>
        </div>
      </section>

      <section className="band">
        <Reveal className="wrap split split--start">
          <SectionHead eyebrow="FAQ" title="About the shop" />
          <Faq items={faqs} />
        </Reveal>
      </section>

      <CtaBand />
    </>
  );
}
