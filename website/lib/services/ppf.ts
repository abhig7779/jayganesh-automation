import type { Service } from "../services";

// TODO(shop): confirm the package list, what each includes and the time needed.
export const ppf: Service = {
  slug: "ppf-pune",
  menuLabel: "PPF",
  title: "PPF in Pune: Paint Protection Film for Your Car",
  description:
    "Clear, self-healing paint protection film fitted at our Loni Kalbhor studio. Front kits and full body for new and used cars. WhatsApp your car model for a price.",
  eyebrow: "Detailing studio · Paint protection film",
  h1: "PPF in Pune, fitted at our own studio",
  lead: "A clear film over your paint that takes the stone chips, scratches and bird droppings instead of your car. Fitted panel by panel in our studio on the Pune–Solapur road.",
  whatsappMessage: "Hi Jay Ganesh, I want a PPF price for my car. Car model: ",
  whatItIs: [
    "Paint protection film (PPF) is a clear polyurethane film that sits on top of your car's paint. Most stone chips, light scratches and swirl marks land on the film, not the paint underneath.",
    "Good film heals small scratches on its own in warm weather and stays clear for years. It can be removed later without harming the original paint, which helps resale value.",
    "Ceramic coating adds shine and makes washing easier, but it does not stop chips. If you drive on the highway often, PPF is the protection that matters.",
  ],
  goodFor: [
    "New cars, ideally fitted before the first long drive",
    "Daily highway drives on the Pune–Solapur road",
    "Black and dark colours, where scratches show first",
    "Owners who plan to sell or exchange the car later",
  ],
  packagesTitle: "PPF packages",
  packages: [
    {
      name: "Front kit",
      includes: ["Bonnet", "Front bumper", "Headlights", "Side mirrors"],
      time: "1 day",
      price: "ppfFrontKit",
    },
    {
      name: "Full body",
      includes: ["Every painted panel", "Headlights and tail lights", "Door edges and handle cups", "Post-fitting inspection"],
      time: "3–4 days",
      price: "ppfFullBody",
      featured: true,
    },
    {
      name: "High-impact areas",
      includes: ["Front bumper", "Door edges", "Handle cups", "Boot loading lip"],
      time: "Half a day",
      price: "ppfHighImpact",
    },
  ],
  process: [
    { step: "Inspection", detail: "We check the paint with you under studio lights and note any marks before work starts." },
    { step: "Wash and decontamination", detail: "Foam wash, iron and tar removal, and clay so the film sticks to clean paint." },
    { step: "Paint correction", detail: "Light polishing where needed, so swirl marks are not sealed under the film." },
    { step: "Fitting", detail: "Film is cut and laid panel by panel, with edges wrapped where the panel allows." },
    { step: "Curing", detail: "The car stays in the studio while the film settles and moisture escapes." },
    { step: "Handover", detail: "Final check together, care instructions, and your warranty details." },
  ],
  compare: {
    columns: ["PPF", "Ceramic coating", "Wax"],
    rows: ["Stops stone chips", "Heals light scratches", "Gloss and easy washing", "Typical life"],
    cells: [
      ["Yes", "No", "No"],
      ["Yes", "No", "No"],
      ["Yes", "Yes", "For a few weeks"],
      ["Several years", "1–3 years", "A few weeks"],
    ],
  },
  faqs: [
    { q: "How much does PPF cost?", a: "It depends on the car's size and the package. Send us your car model on WhatsApp and we'll reply with an exact price, usually within the hour during shop timings." },
    { q: "How long does a full-body PPF take?", a: "Plan for 3–4 days, including washing, fitting and curing. A front kit is usually ready the same day." },
    { q: "Will PPF damage my paint when it's removed?", a: "No. Good film comes off cleanly from factory paint. Repainted panels need a check first, which we do during inspection." },
    { q: "Can I wash the car after PPF?", a: "Wait 7 days, then hand wash with a pH-neutral shampoo. Avoid automatic brush washes." },
    { q: "Should I do PPF or ceramic coating?", a: "PPF protects against chips and scratches. Ceramic adds gloss and makes washing easier. Many owners do PPF on the front or full body, then ceramic on top." },
    { q: "Do you give a warranty?", a: "Yes, PPF comes with a warranty card. Ask us on WhatsApp for the film brand and warranty period for your package." },
  ],
  photos: ["ppfApply", "ppfEdge", "polishBlue", "newCar"],
  photoSlots: [
    "Technician laying film on a bonnet under studio lights",
    "Close-up of a film edge wrapped around a panel",
    "Before and after: stone chips on a bumper",
    "Finished SUV under the studio's ceiling lights",
  ],
};
