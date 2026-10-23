import { site } from "@/lib/site";
import { PageHero } from "./PageHero";

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
      <PageHero crumbs={[{ label: title }]} title={title}>
        <p className="label">Last updated {site.legalUpdated}</p>
      </PageHero>
      <section className="band band--panel">
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
