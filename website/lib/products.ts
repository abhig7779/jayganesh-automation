import type { MediaKey } from "./media";

// Product category pages under Accessories: lights, audio and screens,
// dashcams. One entry per page; the shared template is
// app/components/ProductCategoryPage.tsx.
//
// FACTS RULE (brand book): no prices, specs, wattages, warranty years or
// compatibility promises here. Brands are only those the shop has fitted, as
// shown in its own Instagram posts and real Google reviews ("brands we've
// fitted include …", never "authorised dealer"). Reviews are quoted from
// lib/reviews.json by id, never retyped.

export type Faq = { q: string; a: string };

export type ProductCategory = {
  slug: string;
  path: string;
  /** Parent hub, for breadcrumbs. Undefined = sits directly under Accessories. */
  hub?: HubSlug;
  /** Short name for breadcrumbs, menus and chips. */
  menuLabel: string;
  /** <title>, ≤ 60 characters. */
  title: string;
  /** Meta description, ≤ 155 characters. */
  description: string;
  eyebrow: string;
  h1: string;
  /** Answer-first intro, 40–60 words, names Loni Kalbhor / Pune. */
  lead: string;
  /** Schema.org Service serviceType. */
  serviceType: string;
  /** What goes in the WhatsApp message: "I'd like {item} for my …". */
  item: string;
  whatWeFit: { name: string; detail: string }[];
  /** Brands the shop has fitted (Instagram posts, Google reviews). Empty = section hidden. */
  brands: string[];
  brandsNote?: string;
  /** Review ids from lib/reviews.json. */
  reviews: string[];
  reviewsTitle: string;
  faqs: Faq[];
  photo: MediaKey;
  photoLabel: string;
};

export type HubSlug = "car-lights" | "audio-screens";

export type Hub = {
  slug: HubSlug;
  path: string;
  menuLabel: string;
  title: string;
  description: string;
  eyebrow: string;
  h1: string;
  lead: string;
  photo: MediaKey;
  /** Category pages in this hub, in order. */
  children: string[];
  /** Things in this group without their own page yet; the card opens WhatsApp. */
  more: { name: string; blurb: string; photo: MediaKey }[];
};

const steps = (item: string) => [
  { step: "Send your car model", detail: `Model, year and variant on WhatsApp, and what you want from ${item}.` },
  { step: "Get options and prices", detail: "We reply with the options that suit your car, with prices." },
  { step: "Fitted at our shop", detail: "Our team fits and wires it at Loni Kalbhor, then tests it with you before you drive out." },
];

export const productCategories: ProductCategory[] = [
  {
    slug: "headlights",
    path: "/car-lights/headlights/",
    hub: "car-lights",
    menuLabel: "Headlights",
    title: "Car LED & Projector Headlights in Pune | Jay Ganesh",
    description: "LED, HID and projector headlight upgrades fitted at our Loni Kalbhor shop, Pune. Brands we've fitted include DHC and Aozoom. WhatsApp your car model.",
    eyebrow: "Car lights · Headlights",
    h1: "LED and projector headlights for your car",
    lead: "Jay Ganesh Car Accessories upgrades car headlights to LED, HID and projector units, chosen for your model and fitted by our own team. Brands we've fitted include DHC, Moto Ranger, Alphatron and Aozoom. Visit our shop at Kawadipat Toll Naka, Loni Kalbhor, Pune, or WhatsApp your car model for options and prices.",
    serviceType: "Car LED headlight installation",
    item: "LED headlights",
    whatWeFit: [
      { name: "LED headlight bulbs", detail: "A brighter, whiter light in your car's existing headlamps." },
      { name: "Projector headlights", detail: "Projector units for a sharper, more focused beam." },
      { name: "HID lighting", detail: "HID kits, wired with care and tested before you leave." },
      { name: "Fog lamps and auxiliary lights", detail: "Extra lights for highway and early-morning driving." },
    ],
    brands: ["DHC", "Moto Ranger", "Alphatron", "Aozoom"],
    brandsNote: "From our own installs, like a DHC U-type headlight on a Thar and a Moto Ranger upgrade on a new Creta.",
    reviews: ["sarthak-chatle", "sarthak-choudhari"],
    reviewsTitle: "What customers say about their headlights",
    faqs: [
      { q: "Are LED headlights legal?", a: "Rules on headlights cover things like brightness, colour and how the beam is aimed, and they can change. We'll suggest options suited to your car and the roads you drive, and aim the beam so it doesn't dazzle oncoming drivers. If you're unsure, ask us on WhatsApp before you buy." },
      { q: "LED, HID or projector: which should I choose?", a: "It depends on your car's headlamps and how you drive. LED bulbs are the simplest upgrade, projectors give a sharper beam, and HID suits some lamps better than others. Send your model and we'll explain the options that fit your car." },
      { q: "Which headlight brands do you fit?", a: "Brands we've fitted include DHC, Moto Ranger, Alphatron and Aozoom. What's available changes, so WhatsApp your car model and we'll share what we have for it." },
      { q: "How much does a headlight upgrade cost?", a: "It depends on the car, the type of light and the brand. Send your car model, year and variant on WhatsApp and we'll reply with options and prices." },
      { q: "Will new headlights fit my car?", a: "Most cars can take an upgrade, but the right fitting depends on the model, year and headlamp type. Send a photo of your headlights with the model on WhatsApp and we'll confirm what fits." },
    ],
    photo: "headlight",
    photoLabel: "LED headlight on a car",
  },
  {
    slug: "ambient-lighting",
    path: "/car-lights/ambient-lighting/",
    hub: "car-lights",
    menuLabel: "Ambient lighting",
    title: "Car Ambient Lighting in Pune | Jay Ganesh",
    description: "Car ambient lighting fitted neatly, with the wiring hidden, at our Loni Kalbhor shop, Pune. Kia Sonet, Creta, Nexon and more. WhatsApp your car model.",
    eyebrow: "Car lights · Interior",
    h1: "Ambient lighting for your car's interior",
    lead: "Car ambient lighting adds a soft glow along the dashboard, doors and footwells, fitted so the wiring stays hidden. Jay Ganesh Car Accessories fits ambient lights at our shop at Kawadipat Toll Naka, Loni Kalbhor, Pune, in cars like the Kia Sonet. WhatsApp your car model for the options that suit it.",
    serviceType: "Car ambient lighting installation",
    item: "ambient lighting",
    whatWeFit: [
      { name: "Dashboard and door strips", detail: "Light lines along the dash and door trims." },
      { name: "Footwell lights", detail: "A soft glow at your feet when the doors open or at night." },
      { name: "Neat, hidden wiring", detail: "Cables tucked behind the trims, not taped across the cabin." },
    ],
    brands: [],
    reviews: ["arnav-girme"],
    reviewsTitle: "What customers say about our lighting work",
    faqs: [
      { q: "Will ambient lighting suit my car?", a: "It depends on the car's interior: where the trims and gaps are decides where the light can sit. Send your model, year and a photo of the dashboard on WhatsApp and we'll suggest a layout." },
      { q: "Can I choose the colour?", a: "Many ambient light kits offer more than one colour. Tell us the look you want and we'll show the options we have for your car." },
      { q: "Will the wiring be visible?", a: "We route the wiring behind the trims so it stays out of sight, and test everything with you before you drive out." },
      { q: "How much does ambient lighting cost?", a: "It depends on the car and how much of the cabin you want lit. WhatsApp your car model and we'll reply with options and prices." },
    ],
    photo: "ambient",
    photoLabel: "Ambient lighting in a car cabin",
  },
  {
    slug: "android-stereo",
    path: "/audio-screens/android-stereo/",
    hub: "audio-screens",
    menuLabel: "Android stereo",
    title: "Android Stereo for Car in Pune | Jay Ganesh",
    description: "Android stereos and touchscreens fitted for your dashboard at our Loni Kalbhor shop, Pune. Brands we've fitted include Blaupunkt and Innavi.",
    eyebrow: "Audio & screens · Stereo",
    h1: "Android stereos and touchscreens for your car",
    lead: "Jay Ganesh Car Accessories fits Android stereos and touchscreen players with the right frame and wiring for your car's dashboard. Brands we've fitted include Blaupunkt and Innavi, with Morel speakers when you want better sound. Visit us at Kawadipat Toll Naka, Loni Kalbhor, Pune, or WhatsApp your car model.",
    serviceType: "Car Android stereo installation",
    item: "an Android stereo",
    whatWeFit: [
      { name: "Android stereos and touchscreens", detail: "Players sized for your dashboard, with a matching frame." },
      { name: "Reverse and 360° camera hook-up", detail: "Connect a camera so the view shows on the screen." },
      { name: "Speakers and car audio", detail: "Speaker upgrades to go with the new stereo." },
    ],
    brands: ["Blaupunkt", "Innavi", "Morel"],
    brandsNote: "Blaupunkt and Innavi screens, Morel speakers. For example, a Blaupunkt screen in a Honda City.",
    reviews: ["nilesh-more", "dattatraya-r-patil"],
    reviewsTitle: "What customers say about their stereo and audio",
    faqs: [
      { q: "Will an Android stereo fit my car?", a: "Most cars can take a touchscreen stereo with the right frame and wiring for the dashboard. Send your model, year and variant on WhatsApp and we'll confirm what fits." },
      { q: "Will I keep my steering-wheel controls?", a: "That depends on the car and the stereo. Tell us your model and variant, and we'll tell you which options can work with your steering controls." },
      { q: "Which stereo brands do you fit?", a: "Brands we've fitted include Blaupunkt and Innavi Android players, and Morel speakers. What's in stock changes, so WhatsApp your car model for current options." },
      { q: "How much does an Android stereo cost?", a: "It depends on the screen, the brand and the frame your car needs. WhatsApp your car model, year and variant and we'll reply with options and prices." },
    ],
    photo: "stereo",
    photoLabel: "Android stereo in a car dashboard",
  },
  {
    slug: "360-camera",
    path: "/audio-screens/360-camera/",
    hub: "audio-screens",
    menuLabel: "360° camera",
    title: "360° Camera for Cars in Pune | Fitted | Jay Ganesh",
    description: "360° camera systems fitted and set up at our Loni Kalbhor shop, Pune, so you see all around the car when parking. WhatsApp your car model for options.",
    eyebrow: "Audio & screens · Cameras",
    h1: "360° cameras for your car, fitted and set up",
    lead: "A 360° camera shows a view all around your car on the screen, which helps in tight parking and narrow lanes. Jay Ganesh Car Accessories fits and sets up 360° cameras at our shop at Kawadipat Toll Naka, Loni Kalbhor, Pune, including on cars like the Hyundai Creta. WhatsApp your car model for options.",
    serviceType: "Car 360-degree camera installation",
    item: "a 360° camera",
    whatWeFit: [
      { name: "360° camera systems", detail: "Cameras around the car, set up to show one combined view." },
      { name: "Reverse cameras", detail: "A rear view on the screen when you reverse." },
      { name: "Screen hook-up", detail: "Connected to your car's screen, or to a new Android stereo." },
    ],
    brands: [],
    reviews: ["dattatraya-r-patil"],
    reviewsTitle: "A 360° camera on a Hyundai Creta",
    faqs: [
      { q: "Will a 360° camera fit my car?", a: "It depends on the model, the variant and the screen in your car. Send your model, year and variant on WhatsApp, with a photo of the dashboard, and we'll tell you what fits." },
      { q: "Do I need a new screen for a 360° camera?", a: "Not always. Some cars' screens can show the camera view and some need a compatible screen or Android stereo. We'll check this for your car before you buy." },
      { q: "Where are the cameras placed?", a: "Usually at the front, the rear and under each side mirror, so together they cover all around the car. The exact spots depend on your model." },
      { q: "How much does a 360° camera cost?", a: "It depends on the system and your car. WhatsApp your car model, year and variant and we'll reply with options and prices." },
    ],
    photo: "camera360",
    photoLabel: "360° camera view on a dashboard screen",
  },
  {
    slug: "dashcams",
    path: "/dashcams/",
    menuLabel: "Dashcams",
    title: "Car Dashcam Fitting in Pune | Jay Ganesh",
    description: "Dashcams fitted with hidden wiring at our Loni Kalbhor shop, Pune. Brands we've fitted include 70mai. WhatsApp your car model for options and prices.",
    eyebrow: "Accessories · Dashcams",
    h1: "Dashcams, fitted with the wiring hidden",
    lead: "Jay Ganesh Car Accessories fits dashcams with the cable routed behind the trims, so nothing hangs across the windscreen. Brands we've fitted include 70mai. Visit our shop at Kawadipat Toll Naka, Loni Kalbhor, Pune, or WhatsApp your car model and we'll suggest a dashcam and placement that suit it.",
    serviceType: "Car dashcam installation",
    item: "a dashcam",
    whatWeFit: [
      { name: "Front dashcams", detail: "Placed near the mirror, out of your line of sight." },
      { name: "Front and rear dashcams", detail: "A second camera for the rear window." },
      { name: "Hidden wiring", detail: "Cable tucked along the roof lining and pillar trims." },
    ],
    brands: ["70mai"],
    reviews: ["dattatraya-r-patil"],
    reviewsTitle: "A 70mai dashcam on a Hyundai Creta",
    faqs: [
      { q: "Which dashcam should I buy?", a: "It depends on whether you want front only or front and rear, and how you use the car. Tell us on WhatsApp and we'll suggest options. Brands we've fitted include 70mai." },
      { q: "Will the wires show?", a: "We route the cable behind the roof lining and pillar trims, so it stays out of sight, and test the dashcam with you before you drive out." },
      { q: "Will a dashcam fit my car?", a: "Dashcams fit most cars. Placement depends on your windscreen and mirror, so send your model on WhatsApp and we'll suggest the best spot." },
      { q: "How much does a dashcam cost with fitting?", a: "It depends on the dashcam and your car. WhatsApp your car model and we'll reply with options and prices." },
    ],
    photo: "dashcam",
    photoLabel: "Dashcam behind the rear-view mirror",
  },
];

export const hubs: Hub[] = [
  {
    slug: "car-lights",
    path: "/car-lights/",
    menuLabel: "Car lights",
    title: "Car Lights in Pune: LED Headlights, Ambient | Jay Ganesh",
    description: "LED and projector headlights, fog lamps and ambient lighting, fitted at our Loni Kalbhor shop, Pune. WhatsApp your car model for options and prices.",
    eyebrow: "Accessories · Lights",
    h1: "Car lights, fitted at our shop",
    lead: "Jay Ganesh Car Accessories fits LED and projector headlights, fog lamps and interior ambient lighting, chosen for your car model. Everything is wired and tested by our own team at Kawadipat Toll Naka, Loni Kalbhor, Pune. Pick a type below, or WhatsApp your car model for options.",
    photo: "lightsDark",
    children: ["headlights", "ambient-lighting"],
    more: [{ name: "Fog lamps", blurb: "For winter mornings on the highway.", photo: "lightsDark" }],
  },
  {
    slug: "audio-screens",
    path: "/audio-screens/",
    menuLabel: "Audio & screens",
    title: "Car Audio, Android Stereos & 360° Cameras, Pune | Jay Ganesh",
    description: "Android stereos, speakers and 360° cameras fitted for your dashboard at our Loni Kalbhor shop, Pune. WhatsApp your car model for options and prices.",
    eyebrow: "Accessories · Audio & screens",
    h1: "Car audio, screens and cameras",
    lead: "Jay Ganesh Car Accessories fits Android stereos, speakers and 360° cameras, matched to your car's dashboard and wiring. Our team fits and tests everything at Kawadipat Toll Naka, Loni Kalbhor, Pune. Pick a type below, or WhatsApp your car model and we'll reply with the options that suit it.",
    photo: "stereo",
    children: ["android-stereo", "360-camera"],
    more: [{ name: "Speakers and subwoofers", blurb: "Clearer sound and deeper bass.", photo: "audio" }],
  },
];

export function getProductCategory(slug: string) {
  return productCategories.find((c) => c.slug === slug);
}

export function getHub(slug: HubSlug) {
  return hubs.find((h) => h.slug === slug)!;
}

export const howItWorks = steps;
