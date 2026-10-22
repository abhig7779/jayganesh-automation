import { HubPage, hubMetadata } from "@/app/components/ProductCategoryPage";

export const metadata = hubMetadata("car-lights");

export default function CarLights() {
  return <HubPage slug="car-lights" />;
}
