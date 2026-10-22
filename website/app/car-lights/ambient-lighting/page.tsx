import ProductCategoryPage, { productCategoryMetadata } from "@/app/components/ProductCategoryPage";

export const metadata = productCategoryMetadata("ambient-lighting");

export default function AmbientLighting() {
  return <ProductCategoryPage slug="ambient-lighting" />;
}
