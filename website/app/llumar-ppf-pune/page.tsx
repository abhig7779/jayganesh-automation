import { DealerBrandPage, dealerBrandMetadata } from "@/app/components/DealerBrands";

// Content: lib/dealer-brands.ts. Template: app/components/DealerBrands.tsx.
export const metadata = dealerBrandMetadata("llumar-ppf-pune");

export default function Page() {
  return <DealerBrandPage slug="llumar-ppf-pune" />;
}
