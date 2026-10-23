import { DealerBrandPage, dealerBrandMetadata } from "@/app/components/DealerBrands";

// Content: lib/dealer-brands.ts. Template: app/components/DealerBrands.tsx.
export const metadata = dealerBrandMetadata("ultraguard-ppf-pune");

export default function Page() {
  return <DealerBrandPage slug="ultraguard-ppf-pune" />;
}
