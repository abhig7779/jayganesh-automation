// Lead forms (contact, partnership, vendor) post here. The PHP endpoint lives
// on a subdomain because the site itself is a static export.
export const LEAD_ENDPOINT = "https://api.jayganeshcaraccessories.com/lead.php";

export type LeadKind = "contact" | "partnership" | "vendor";

export const serviceOptions = [
  "PPF",
  "Ceramic / graphene coating",
  "Detailing",
  "Seat covers",
  "Lights",
  "Audio & screens",
  "Cameras & dashcams",
  "Other",
];

export const partnerTypeOptions = [
  "Car dealership / showroom",
  "Fleet or corporate",
  "Insurance or workshop",
  "Influencer / content",
  "Other",
];

export const vendorCategoryOptions = [
  "PPF & films",
  "Coatings & care",
  "Seat covers & interiors",
  "Lighting",
  "Audio & electronics",
  "Other",
];

/** Returns the 10-digit Indian mobile number, or null if it isn't one. Accepts +91, 91, 0 and spaces/dashes. */
export function normalizeIndianMobile(raw: string): string | null {
  let d = raw.replace(/[\s\-().]/g, "");
  if (d.startsWith("+91")) d = d.slice(3);
  else if (d.length === 12 && d.startsWith("91")) d = d.slice(2);
  else if (d.length === 11 && d.startsWith("0")) d = d.slice(1);
  return /^[6-9]\d{9}$/.test(d) ? d : null;
}
