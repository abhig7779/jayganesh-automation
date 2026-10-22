import type { Service } from "../services";

// TODO(shop): confirm package contents and whether delivery-day fitting is offered for all models.
export const newCar: Service = {
  slug: "new-car-protection",
  menuLabel: "New-car protection",
  title: "New Car PPF & Accessories in Pune",
  description:
    "Taking delivery of a new car in Pune? Book PPF, ceramic, seat covers and a dashcam before delivery, fitted in one visit at Loni Kalbhor.",
  eyebrow: "Detailing studio · New cars",
  h1: "Protect your new car from the first day",
  lead: "New-car protection is one package of PPF or ceramic coating, seat covers, mats and a dashcam, planned for your exact model before delivery. It's for anyone taking delivery of a new car in Pune, with everything fitted in one visit at our shop in Loni Kalbhor, on the Pune–Solapur road.",
  whatsappMessage: "Hi Jay Ganesh, I'm taking delivery of a new car and want a protection package. Car model and delivery date: ",
  whatItIs: [
    "A new car's paint is at its best on delivery day, and the first few weeks of driving do the most damage: stone chips, wash swirls and parking scratches.",
    "Booking before delivery means we can keep film and products ready for your model, and plan everything for one visit instead of several.",
    "Tell us the model, variant, colour and delivery date. We'll suggest a package that fits how you drive and your budget.",
  ],
  goodFor: [
    "Cars being delivered in the next few weeks",
    "Families who want everything done in one visit",
    "Owners who drive the Pune–Solapur highway",
    "Gifting a car with accessories already fitted",
  ],
  packagesTitle: "New-car packages",
  packages: [
    {
      name: "Essentials",
      includes: ["Seat covers for your model", "Floor mats", "Dashcam installed"],
      time: "Same day",
      price: "starterKitFrom",
    },
    {
      name: "Protection",
      includes: ["PPF front kit or full body", "Ceramic on the remaining panels", "Seat covers and mats"],
      time: "2–4 days",
      price: "newCarPackageFrom",
      featured: true,
    },
  ],
  process: [
    { step: "Book", detail: "Send us the model, variant, colour and delivery date on WhatsApp." },
    { step: "Plan", detail: "We confirm the package and keep film and products ready for your model." },
    { step: "Bring the car", detail: "Drive straight from the showroom to our shop, or bring it within the first week." },
    { step: "Fitting", detail: "Everything is fitted in one visit, with the studio work done indoors." },
    { step: "Handover", detail: "Final check together, care tips, and warranty details for every product." },
  ],
  faqs: [
    { q: "When should I book?", a: "As soon as you know your delivery date. Booking 1–2 weeks ahead lets us keep film and seat covers ready for your model." },
    { q: "Can you fit everything on delivery day?", a: "Accessories usually, yes. Full-body PPF needs 3–4 days in the studio, so plan the car's first days around that." },
    { q: "Does PPF affect the manufacturer's warranty?", a: "No. Film sits on top of the paint and doesn't change anything mechanical or electrical." },
    { q: "Which accessories should a new car get first?", a: "Seat covers and mats protect the interior from day one, and a dashcam is worth having from the first drive. Lights and audio upgrades can come later." },
    { q: "Should I get PPF before or after taking delivery?", a: "As soon after delivery as you can, ideally before the first long drive. The paint is at its best on day one, and film fitted then seals in a clean, unmarked finish." },
    { q: "Can I drive straight from the showroom to your shop?", a: "Yes. Many owners bring the car straight from the showroom to our shop at Kawadipat Toll Naka, Loni Kalbhor. Send the delivery date on WhatsApp so we can keep a slot and products ready for your model." },
  ],
  photos: ["newCar", "ppfApply", "studioBay"],
  photoSlots: [
    "A brand-new car arriving at the shop with the showroom bow",
    "PPF being fitted on a new car's front bumper",
    "Finished new car handed over to the family",
  ],
};
