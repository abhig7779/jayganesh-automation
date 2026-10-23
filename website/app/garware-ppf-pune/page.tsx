import { DealerBrandPage, dealerBrandMetadata } from "@/app/components/DealerBrands";

// Content: lib/dealer-brands.ts. Template: app/components/DealerBrands.tsx.
export const metadata = dealerBrandMetadata("garware-ppf-pune");

export default function Page() {
  return <DealerBrandPage slug="garware-ppf-pune" />;
}
