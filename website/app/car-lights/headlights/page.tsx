import ProductCategoryPage, { productCategoryMetadata } from "@/app/components/ProductCategoryPage";

export const metadata = productCategoryMetadata("headlights");

export default function Headlights() {
  return <ProductCategoryPage slug="headlights" />;
}
