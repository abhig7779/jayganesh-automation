import ProductCategoryPage, { productCategoryMetadata } from "@/app/components/ProductCategoryPage";

export const metadata = productCategoryMetadata("dashcams");

export default function Dashcams() {
  return <ProductCategoryPage slug="dashcams" />;
}
