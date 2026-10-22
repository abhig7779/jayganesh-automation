import ProductCategoryPage, { productCategoryMetadata } from "@/app/components/ProductCategoryPage";

export const metadata = productCategoryMetadata("360-camera");

export default function Camera360() {
  return <ProductCategoryPage slug="360-camera" />;
}
