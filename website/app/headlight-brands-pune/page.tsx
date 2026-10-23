import { DealerHubPage, dealerHubMetadata } from "@/app/components/DealerBrands";

// Content: lib/dealer-brands.ts. Template: app/components/DealerBrands.tsx.
export const metadata = dealerHubMetadata("headlights");

export default function Page() {
  return <DealerHubPage category="headlights" />;
}
