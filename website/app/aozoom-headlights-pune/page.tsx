import { DealerBrandPage, dealerBrandMetadata } from "@/app/components/DealerBrands";

// Content: lib/dealer-brands.ts. Template: app/components/DealerBrands.tsx.
export const metadata = dealerBrandMetadata("aozoom-headlights-pune");

export default function Page() {
  return <DealerBrandPage slug="aozoom-headlights-pune" />;
}
