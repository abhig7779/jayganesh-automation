import ProductCategoryPage, { productCategoryMetadata } from "@/app/components/ProductCategoryPage";

export const metadata = productCategoryMetadata("speakers");

export default function Speakers() {
  return <ProductCategoryPage slug="speakers" />;
}
