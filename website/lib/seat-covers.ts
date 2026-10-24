// Seat cover lines (owner's list, 24 Oct 2026). Used by /seat-covers/,
// /autoform-seat-covers-pune/, llms.txt and the business JSON-LD.
//
// FACTS RULE: no prices, specs or warranty years.

/**
 * Autoform ranges the shop offers (owner wrote "signecher Emporio pro lucenzo
 * pro"). Spellings checked on Google, Oct 2026: "Lucenzo" is the name Autoform
 * uses on its own product pages (e.g. U-Impress covers), and "Emporio" is
 * Autoform's own name ("Emporio by Autoform" stores). Range details aren't
 * public (autoformindia.com loads its catalogue in the browser only), so no
 * descriptions are shown.
 */
export const autoformRanges = ["Signature", "Emporio Pro", "Lucenzo Pro"];

/** Custom seat covers made in-house: materials. Neutral descriptions only. */
export const customMaterials = [
  { name: "Napa leatherette", detail: "A smooth, nappa-style leatherette." },
  { name: "Nova", detail: "A fabric option." },
  { name: "Velour", detail: "A soft, plush fabric." },
];

/**
 * Custom seat covers: quilting designs. "Bentley" and "Porsche" are the names
 * the trade uses for these quilting patterns; they are design styles only,
 * with no connection to the car makers.
 */
export const customDesigns = [
  { name: "Bentley-style quilting", detail: "A quilted pattern in the style the trade calls “Bentley”." },
  { name: "Porsche-style quilting", detail: "A quilted pattern in the style the trade calls “Porsche”." },
];

export const designDisclaimer =
  "“Bentley-style” and “Porsche-style” are names for quilting design styles only. They have no connection with, and are not endorsed by, Bentley Motors or Porsche.";
