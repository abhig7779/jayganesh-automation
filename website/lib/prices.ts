// Prices and product facts that the shop has not confirmed yet.
// Every value is null until the shop confirms it. A null price shows as
// "Price on WhatsApp" on the site; a null fact hides the sentence that needs it.
// Fill these in (rupees, whole numbers) and rebuild. Nothing else needs changing.

export const prices = {
  ppfFrontKit: null as number | null,
  ppfFullBody: null as number | null,
  ppfHighImpact: null as number | null,
  ceramicFrom: null as number | null,
  ceramicTopUp: null as number | null,
  washFrom: null as number | null,
  interiorDeepCleanFrom: null as number | null,
  deepCleanFrom: null as number | null,
  newCarPackageFrom: null as number | null,
  seatCoverFrom: null as number | null,
  starterKitFrom: null as number | null,
};

export type PriceKey = keyof typeof prices;

export const facts = {
  /** e.g. "XPEL", "3M", "Garware". Shown on the PPF page when set. */
  ppfFilmBrand: null as string | null,
  /** Years of PPF warranty on the warranty card. */
  ppfWarrantyYears: null as number | null,
  ceramicBrand: null as string | null,
  ceramicWarrantyYears: null as number | null,
  /** Launch bonus for the first 50 full-body PPF cars, e.g. "free ceramic top-up". */
  ppfLaunchBonus: null as string | null,
  /** Diwali booking slots left, shown on the offers page. */
  slotsLeft: null as number | null,
};

export function formatPrice(value: number | null) {
  return value == null ? null : `₹${value.toLocaleString("en-IN")}`;
}
