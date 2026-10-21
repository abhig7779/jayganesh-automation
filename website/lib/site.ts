import { countText, ratingText } from "./reviews";

// Business details used across the site. Keep these identical to the Google
// Business Profile (name, address, phone) so search engines see one business.
export const site = {
  name: "Jay Ganesh Car Accessories",
  shortName: "Jay Ganesh",
  tagline: "Car Detailing & Accessories",
  url: "https://jayganeshcaraccessories.com",
  address: "Kawadipat, Toll Naka, Pune–Solapur Road, Loni Kalbhor, Pune, Maharashtra",
  addressShort: "Kawadipat Toll Naka, Pune–Solapur Road, Loni Kalbhor",
  locality: "Loni Kalbhor",
  region: "Maharashtra",
  // TODO(shop): confirm the pincode. The profile editor says 412201, Google shows 412308.
  postalCode: null as string | null,
  phoneDisplay: "+91 99813 97676",
  phoneE164: "+919981397676",
  phoneHref: "tel:+919981397676",
  whatsappNumber: "919981397676",
  hours: "Open every day, 10:00 am – 8:00 pm",
  hoursShort: "Open 7 days · 10 am – 8 pm",
  openingHours: { opens: "10:00", closes: "20:00" },
  instagram: "@jayganeshcar_accessories_pune",
  instagramHref: "https://www.instagram.com/jayganeshcar_accessories_pune/",
  youtubeHref: "https://www.youtube.com/@jayganeshcaraccessories76",
  facebookHref: "https://www.facebook.com/100063980594625",
  mapsHref: "https://www.google.com/maps/search/?api=1&query=Jay+Ganesh+Car+Accessories+Loni+Kalbhor",
  reviewsHref: "https://www.google.com/maps/search/?api=1&query=Jay+Ganesh+Car+Accessories+Loni+Kalbhor",
  // Live from lib/reviews.json (refreshed daily from Google). Never hard-code these.
  rating: ratingText,
  reviews: countText,
  customers: "8,000+",
  instagramFollowers: "12.3K",
  legalUpdated: "19 October 2026",
};

/** A wa.me link that opens WhatsApp with a ready message. */
export function whatsapp(message = "Hi Jay Ganesh, I'd like a price for my car.") {
  return `https://wa.me/${site.whatsappNumber}?text=${encodeURIComponent(message)}`;
}
