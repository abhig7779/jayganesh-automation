import { DealerHubPage, dealerHubMetadata } from "@/app/components/DealerBrands";

// Content: lib/dealer-brands.ts. Template: app/components/DealerBrands.tsx.
export const metadata = dealerHubMetadata("ppf");

export default function Page() {
  return <DealerHubPage category="ppf" />;
}
