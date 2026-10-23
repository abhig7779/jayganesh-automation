import type { PriceKey } from "./prices";
import type { MediaKey } from "./media";

export type NavLink = { label: string; href: string; note?: string };

// Accessory categories. `href` is set once a category has its own page;
// until then the card opens WhatsApp with the category in the message.
export type Category = {
  key: string;
  group: "Interior" | "Lights" | "Tech" | "Exterior";
  name: string;
  blurb: string;
  href?: string;
  price?: PriceKey;
  photo?: MediaKey;
};

export const categories: Category[] = [
  { key: "seat-covers", group: "Interior", name: "Seat covers", blurb: "Made and fitted for your car model.", href: "/seat-covers/", price: "seatCoverFrom", photo: "interior" },
  { key: "autoform", group: "Interior", name: "Autoform seat covers", blurb: "Authorised dealer. Made for your model.", href: "/autoform-seat-covers-pune/", photo: "interior" },
  { key: "mats", group: "Interior", name: "Mats and interior", blurb: "Floor mats, steering covers and trims.", photo: "mats" },
  { key: "ambient", group: "Lights", name: "Ambient lighting", blurb: "Soft interior lighting, wired neatly.", href: "/car-lights/ambient-lighting/", photo: "ambient" },
  { key: "headlights", group: "Lights", name: "Headlights", blurb: "LED and projector headlight upgrades.", href: "/car-lights/headlights/", photo: "headlight" },
  { key: "fog", group: "Lights", name: "Fog lamps", blurb: "For winter mornings on the highway.", photo: "lightsDark" },
  { key: "stereo", group: "Tech", name: "Android stereos", blurb: "Touchscreen stereos fitted for your dashboard.", href: "/audio-screens/android-stereo/", photo: "stereo" },
  { key: "audio", group: "Tech", name: "Speakers and subwoofers", blurb: "Clearer sound and deeper bass.", photo: "audio" },
  { key: "camera", group: "Tech", name: "360° cameras", blurb: "See all around the car when parking.", href: "/audio-screens/360-camera/", photo: "camera360" },
  { key: "dashcam", group: "Tech", name: "Dashcams", blurb: "Installed, wired and tested.", href: "/dashcams/", photo: "dashcam" },
  { key: "exterior", group: "Exterior", name: "Exterior accessories", blurb: "Guards, kits and styling parts.", photo: "studioBay" },
];

export const studioLinks: NavLink[] = [
  { label: "PPF", href: "/ppf-pune/", note: "Paint protection film" },
  { label: "Ceramic coating", href: "/ceramic-coating-pune/", note: "Gloss and easy washing" },
  { label: "Car detailing", href: "/car-detailing-pune/", note: "Foam wash, interior deep clean" },
  { label: "New-car protection", href: "/new-car-protection/", note: "Book before delivery" },
];

export const primaryNav = [
  { label: "Detailing Studio", href: "/detailing-studio/", menu: "studio" as const },
  { label: "Accessories", href: "/accessories/", menu: "accessories" as const },
  { label: "Our Work", href: "/our-work/" },
  { label: "Offers", href: "/offers/" },
  { label: "About", href: "/about-us/" },
  { label: "Contact", href: "/contact-us/" },
];

// Company pages: footer "Company" column and the mobile menu.
export const companyLinks: NavLink[] = [
  { label: "About us", href: "/about-us/" },
  { label: "Contact", href: "/contact-us/" },
  { label: "Partnerships", href: "/partnerships/" },
  { label: "Become a vendor", href: "/become-a-vendor/" },
];

// The car models customers ask about most (Leads sheet, Oct 2026).
export const popularCars = [
  "Toyota Innova Crysta",
  "Toyota Fortuner",
  "Mahindra Scorpio N",
  "Mahindra Thar Roxx",
  "Hyundai Creta",
  "Ford Endeavour",
  "Maruti Grand Vitara",
  "Maruti Brezza",
  "Maruti Ertiga",
  "Maruti Swift",
  "Tata Nexon",
  "Kia Sonet",
];
