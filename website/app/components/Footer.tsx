import Link from "next/link";
import { site } from "@/lib/site";

export default function Footer() {
  return (
    <footer className="site-footer">
      <span>
        © {new Date().getFullYear()} {site.name}, Loni Kalbhor, Pune
      </span>
      <nav aria-label="Legal">
        <Link href="/">Home</Link>
        <Link href="/privacy-policy">Privacy policy</Link>
        <Link href="/terms">Terms &amp; conditions</Link>
      </nav>
    </footer>
  );
}
