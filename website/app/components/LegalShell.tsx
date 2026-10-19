import Image from "next/image";
import Link from "next/link";
import Footer from "./Footer";
import { site } from "@/lib/site";

export default function LegalShell({
  title,
  current,
  children,
}: {
  title: string;
  current: "privacy" | "terms";
  children: React.ReactNode;
}) {
  return (
    <>
      <header className="legal-header">
        <Link href="/" aria-label={`${site.name} home`}>
          <Image src="/logo.png" alt={site.name} width={63} height={44} />
        </Link>
        <nav aria-label="Legal">
          <Link href="/privacy-policy" aria-current={current === "privacy" ? "page" : undefined}>
            Privacy
          </Link>
          <Link href="/terms" aria-current={current === "terms" ? "page" : undefined}>
            Terms
          </Link>
        </nav>
      </header>
      <main className="legal">
        <p className="eyebrow">{site.name}</p>
        <h1>{title}</h1>
        <p className="updated">Last updated {site.legalUpdated}</p>
        {children}
      </main>
      <Footer />
    </>
  );
}

export function ContactBox() {
  return (
    <div className="contact-box">
      <p>
        <strong>{site.name}</strong>
      </p>
      <p>{site.address}</p>
      <p>
        Phone and WhatsApp: <a href={site.phoneHref}>{site.phoneDisplay}</a>
      </p>
      <p>{site.hours}</p>
    </div>
  );
}
