// Seat cover lines (owner's list, 24 Oct 2026). Used by /seat-covers/,
// /autoform-seat-covers-pune/, llms.txt and the business JSON-LD.
//
// FACTS RULE: no prices, specs or warranty years.

/**
 * Autoform ranges the shop offers, spelt as the owner gave them. Not yet
 * checked against autoformindia.com: its catalogue loads from an API that
 * isn't readable without a browser (Oct 2026), so no range descriptions are
 * shown. "Lucenzo" matches a "Lucenzo series" post tagged @autoformindia.
 * TODO(shop): confirm the exact range names against the Autoform catalogue.
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
