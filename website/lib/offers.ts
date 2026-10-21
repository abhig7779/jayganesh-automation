import type { PriceKey } from "./prices";

// Current offers. Set `active: false` to hide an offer, or empty the list when
// there is none; the home banner and the Offers page both read this.
export type Offer = {
  title: string;
  detail: string;
  price?: PriceKey;
  until: string;
  whatsappMessage: string;
  active: boolean;
};

export const offerSeason = "Diwali 2026";

export const offers: Offer[] = [
  {
    title: "Diwali-ready foam wash and interior",
    detail: "Snow foam wash, interior clean and glass, ready for the festival.",
    price: "washFrom",
    until: "Till Diwali, 8 Nov",
    whatsappMessage: "Hi Jay Ganesh, I want the Diwali foam wash + interior offer. Car model: ",
    active: true,
  },
  {
    title: "Full-body PPF launch offer",
    detail: "Our new studio's launch offer for the first 50 cars, with a launch bonus on full-body PPF.",
    price: "ppfFullBody",
    until: "First 50 cars",
    whatsappMessage: "Hi Jay Ganesh, I want the full-body PPF launch offer. Car model: ",
    active: true,
  },
  {
    title: "New car? Book before delivery",
    detail: "PPF or ceramic, seat covers and mats planned for your model and fitted in one visit.",
    price: "newCarPackageFrom",
    until: "Book 1–2 weeks before delivery",
    whatsappMessage: "Hi Jay Ganesh, I'm getting a new car and want to book protection before delivery. Car model and delivery date: ",
    active: true,
  },
  {
    title: "Accessories combos",
    detail: "Seat covers, mats and more, put together for your car.",
    price: "starterKitFrom",
    until: "This Diwali",
    whatsappMessage: "Hi Jay Ganesh, I want an accessories combo for my car. Car model: ",
    active: true,
  },
];
