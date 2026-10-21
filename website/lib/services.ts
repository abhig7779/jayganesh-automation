import type { PriceKey } from "./prices";
import type { MediaKey } from "./media";
import { ppf } from "./services/ppf";
import { ceramic } from "./services/ceramic";
import { detailing } from "./services/detailing";
import { newCar } from "./services/new-car";

export type Package = {
  name: string;
  includes: string[];
  time: string;
  price: PriceKey;
  featured?: boolean;
};

export type Service = {
  slug: string;
  menuLabel: string;
  title: string; // <title>
  description: string; // meta description
  eyebrow: string;
  h1: string;
  lead: string;
  whatsappMessage: string;
  whatItIs: string[];
  goodFor: string[];
  packagesTitle: string;
  packages: Package[];
  process: { step: string; detail: string }[];
  compare?: { rows: string[]; columns: string[]; cells: string[][] };
  faqs: { q: string; a: string }[];
  photoSlots: string[]; // descriptions of the real photos to shoot for this page
  photos: MediaKey[]; // stand-in photos, same order as photoSlots
};

export const services: Service[] = [ppf, ceramic, detailing, newCar];

export function getService(slug: string) {
  return services.find((s) => s.slug === slug);
}
