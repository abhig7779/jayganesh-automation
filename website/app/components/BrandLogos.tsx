import type { Brand } from "@/lib/brands";

// Row of official brand logos (files in public/brands/, sources in
// lib/brands.ts). Greyscale and dimmed by default, full colour on hover. No links out. Styles: "Brand logos" block in app/styles/pages.css.
//
// Readability on both themes is handled per logo (Brand.ink): a light pill
// behind dark-ink logos in the dark theme, a dark pill behind white-ink logos
// in the light theme, a theme-specific file where the brand publishes one,
// and a plain invert only for single-colour white marks. Nothing is recoloured
// beyond that.

type Props = {
  brands: Brand[];
  /** "lg" for a standalone lockup (Autoform page). */
  size?: "md" | "lg";
  /** Show logos in full colour (no greyscale), e.g. the authorised-dealer lockup. */
  color?: boolean;
  className?: string;
};

export function BrandLogo({ brand, size = "md", color = false }: { brand: Brand; size?: "md" | "lg"; color?: boolean }) {
  const cls = ["brand-logo", `brand-logo--${brand.ink}`, `brand-logo--${size}`, color && "brand-logo--color", brand.emblemOnly && "brand-logo--emblem"]
    .filter(Boolean)
    .join(" ");
  // PNG logos have a WebP copy in public/brands/ at 2x their largest displayed
  // size (60px tall, or 88px for the large lockup); SVGs are used as they are.
  const img = (src: string, variant?: "light" | "dark") => {
    const tag = (
    <img
      src={src}
      alt={`${brand.name} logo`}
      width={brand.width}
      height={brand.height}
      loading="lazy"
      decoding="async"
      className={variant ? `brand-logo__img brand-logo__img--${variant}` : "brand-logo__img"}
    />
    );
    if (!src.endsWith(".png")) return tag;
    return (
      <picture>
        <source type="image/webp" srcSet={src.replace(/\.png$/, ".webp")} />
        {tag}
      </picture>
    );
  };
  return (
    <span className={cls}>
      {brand.logoDark ? (
        <>
          {img(brand.logo, "light")}
          {img(brand.logoDark, "dark")}
        </>
      ) : (
        img(brand.logo)
      )}
      {brand.emblemOnly && <span className="brand-logo__name" aria-hidden="true">{brand.name}</span>}
    </span>
  );
}

export default function BrandLogos({ brands, size = "md", color = false, className }: Props) {
  if (brands.length === 0) return null;
  const thirdParty = brands.some((b) => b.slug !== "autoform");
  return (
    <div className={["brand-logos", className].filter(Boolean).join(" ")}>
      <ul className="brand-logos__list">
        {brands.map((b) => (
          <li key={b.slug}>
            <BrandLogo brand={b} size={size} color={color} />
          </li>
        ))}
      </ul>
      {thirdParty && <p className="brand-logos__legal">Brand names and logos belong to their owners.</p>}
    </div>
  );
}
