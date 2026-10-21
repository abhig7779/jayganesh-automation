import { site } from "@/lib/site";
import { Crumbs } from "./ui";

export default function LegalShell({
  title,
  children,
}: {
  title: string;
  current?: "privacy" | "terms";
  children: React.ReactNode;
}) {
  return (
    <>
      <section className="page-hero">
        <div className="wrap">
          <div className="page-hero-copy">
            <Crumbs items={[{ label: title }]} />
            <h1>{title}</h1>
            <p className="label">Last updated {site.legalUpdated}</p>
          </div>
        </div>
      </section>
      <section className="band band--paper">
        <div className="wrap">
          <div className="legal prose">{children}</div>
        </div>
      </section>
    </>
  );
}

export function ContactBox() {
  return (
    <div className="contact-box">
      <p><strong>{site.name}</strong></p>
      <p>{site.address}</p>
      <p>Phone and WhatsApp: <a href={site.phoneHref}>{site.phoneDisplay}</a></p>
      <p>{site.hours}</p>
    </div>
  );
}
