import ProductCategoryPage, { productCategoryMetadata } from "@/app/components/ProductCategoryPage";

export const metadata = productCategoryMetadata("android-stereo");

export default function AndroidStereo() {
  return <ProductCategoryPage slug="android-stereo" />;
}
