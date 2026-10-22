import type { Service } from "../services";

// TODO(shop): confirm which detailing services are offered and their times.
export const detailing: Service = {
  slug: "car-detailing-pune",
  menuLabel: "Car detailing",
  title: "Car Detailing in Pune: Foam Wash & Interior",
  description:
    "Car detailing at our Loni Kalbhor studio, Pune: snow foam wash, interior deep cleaning and exterior decontamination, done by hand. Book on WhatsApp.",
  eyebrow: "Detailing studio · Washing and deep cleaning",
  h1: "Car detailing in Pune, inside and out",
  lead: "Car detailing is a thorough hand clean of your car, inside and out: snow foam wash, decontamination, and deep cleaning of seats, carpets and vents. It's for owners who want more than a roadside wash, done in our studio at Kawadipat Toll Naka, Loni Kalbhor, Pune.",
  whatsappMessage: "Hi Jay Ganesh, I want to book a detailing slot. Car model: ",
  whatItIs: [
    "Detailing is a careful clean, inside and out, using the right product for each surface. Paint, glass, plastics, leather and fabric are each cleaned differently.",
    "A foam pre-wash lifts dust before anything touches the paint, which is what stops the swirl marks a quick wash leaves behind.",
    "Inside, we vacuum, shampoo and steam where needed, clean vents and door pockets, and treat dashboards and seats so they don't dry out.",
  ],
  goodFor: [
    "Before Diwali, a wedding or a long trip",
    "After the monsoon or a dusty highway run",
    "Used cars you've just bought",
    "Before selling or exchanging your car",
  ],
  packagesTitle: "Detailing services",
  packages: [
    {
      name: "Foam wash and interior",
      includes: ["Snow foam pre-wash", "Two-bucket hand wash", "Interior vacuum and wipe-down", "Glass inside and out"],
      time: "2–3 hours",
      price: "washFrom",
    },
    {
      name: "Interior deep clean",
      includes: ["Seat and carpet shampoo", "Roof liner and door panels", "Vents and crevices", "Odour treatment"],
      time: "Half a day",
      price: "interiorDeepCleanFrom",
      featured: true,
    },
    {
      name: "Exterior decontamination",
      includes: ["Iron and tar removal", "Clay bar", "Sealant for protection", "Wheels and arches"],
      time: "Half a day",
      price: "deepCleanFrom",
    },
  ],
  process: [
    { step: "Check-in", detail: "We walk around the car with you and note stains, odours and problem areas." },
    { step: "Foam pre-wash", detail: "Snow foam softens and lifts dirt before the paint is touched." },
    { step: "Hand wash", detail: "Two-bucket method with clean microfibre, panel by panel." },
    { step: "Interior", detail: "Vacuum, shampoo, steam and dressing, depending on the package." },
    { step: "Finish", detail: "Glass, tyres and a final wipe under studio lights." },
  ],
  faqs: [
    { q: "How long does detailing take?", a: "A foam wash with interior takes 2–3 hours. A full interior deep clean takes about half a day. We'll give you a pickup time when you book." },
    { q: "Do you remove stains from fabric seats?", a: "Most food, mud and water stains come out with shampoo and steam. Some old stains fade but don't disappear fully; we'll tell you honestly at check-in." },
    { q: "Can I wait at the shop?", a: "Yes. For longer services, most customers drop the car and come back later." },
    { q: "How often should I get detailing done?", a: "A foam wash every few weeks keeps the paint safe. An interior deep clean every 6–12 months is enough for most cars." },
    { q: "What is the difference between a car wash and car detailing?", a: "A wash removes surface dirt. Detailing cleans every surface properly, inside and out: a foam pre-wash and hand wash for the paint, decontamination, and deep cleaning of seats, carpets, vents and plastics with the right product for each." },
    { q: "Can you remove bad smells from the car?", a: "Yes. The interior deep clean includes odour treatment along with seat and carpet shampoo. We check where the smell comes from at check-in, such as a spill or damp carpet, and treat the source." },
    { q: "Where can I get car detailing near Loni Kalbhor or Hadapsar?", a: "At our studio at Kawadipat Toll Naka on the Pune–Solapur road, Loni Kalbhor, open 7 days, 10 am to 8 pm. Customers come from Hadapsar, Manjri, Uruli Kanchan and across eastern Pune. WhatsApp +91 99813 97676 to book a slot." },
  ],
  photos: ["foam", "dashClean", "washBay"],
  photoSlots: [
    "Snow foam covering a car in the studio",
    "Interior seat shampoo in progress",
    "Before and after: a muddy footwell",
  ],
};
