import type { Service } from "../services";

// TODO(shop): confirm the coating brand, layers and package list.
export const ceramic: Service = {
  slug: "ceramic-coating-pune",
  menuLabel: "Ceramic coating",
  title: "Ceramic Coating in Pune: Gloss That Lasts",
  description:
    "Ceramic coating at our Loni Kalbhor studio: deep gloss, water beading and easier washing. Paint prepared and corrected first. WhatsApp your car model for a price.",
  eyebrow: "Detailing studio · Ceramic coating",
  h1: "Ceramic coating in Pune, done on prepared paint",
  lead: "A hard, glossy layer that bonds to your paint. Water beads off, dust washes away easily, and the colour looks deeper for years, not weeks.",
  whatsappMessage: "Hi Jay Ganesh, I want a ceramic coating price for my car. Car model: ",
  whatItIs: [
    "Ceramic coating is a liquid that hardens into a thin, clear layer on your paint. It protects against sun, bird droppings and road grime, and makes washing much quicker.",
    "The finish is only as good as the paint underneath, so we wash, decontaminate and polish the car before the coating goes on. That's what gives the deep, wet look.",
    "Coating does not stop stone chips. For chip protection, combine it with PPF on the front or the full body.",
  ],
  goodFor: [
    "Cars that are washed often and need to stay glossy",
    "Owners who park outdoors in sun and dust",
    "Used cars after paint correction, to keep the result",
    "A top layer over PPF for extra gloss",
  ],
  packagesTitle: "Coating packages",
  packages: [
    {
      name: "Ceramic coating",
      includes: ["Foam wash and decontamination", "One-step polish", "Ceramic on all painted panels", "Wheels and glass treated"],
      time: "2 days",
      price: "ceramicFrom",
      featured: true,
    },
    {
      name: "Ceramic top-up",
      includes: ["Decontamination wash", "Coating refresh", "For cars coated before"],
      time: "Half a day",
      price: "ceramicTopUp",
    },
  ],
  process: [
    { step: "Inspection", detail: "We look at the paint together under studio lights and agree what can be corrected." },
    { step: "Wash and decontamination", detail: "Foam wash, iron remover and clay bar to remove bonded dirt." },
    { step: "Paint correction", detail: "Machine polishing to remove swirls and haze, so the coating seals in a clean finish." },
    { step: "Coating", detail: "Applied panel by panel and levelled by hand." },
    { step: "Curing", detail: "The car stays indoors while the coating hardens." },
    { step: "Handover", detail: "Care instructions and your first maintenance check date." },
  ],
  faqs: [
    { q: "How much does ceramic coating cost?", a: "It depends on the car's size and the paint's condition. WhatsApp us your car model and a photo of the paint, and we'll send a price." },
    { q: "How long does ceramic coating last?", a: "With hand washing and a yearly top-up, coating keeps its gloss and water beading for years. We'll tell you what to expect for the coating on your package." },
    { q: "Does ceramic coating stop scratches?", a: "It resists very light marks, but it does not stop stone chips or deep scratches. For that, choose PPF." },
    { q: "When can I wash the car?", a: "Wait 7 days. After that, hand wash with a pH-neutral shampoo and a clean microfibre mitt." },
    { q: "Can you coat over PPF?", a: "Yes. Many owners fit PPF first and then coat the film for extra gloss and easier washing." },
  ],
  photos: ["beadingDark", "polish", "beadingClose"],
  photoSlots: [
    "Water beading on a coated bonnet",
    "Machine polishing under an inspection light",
    "Deep gloss reflection of the studio lights on a dark car",
  ],
};
