import { Photo } from "./ui";
import type { MediaKey } from "@/lib/media";

// Asymmetric grid: one large tile, then smaller ones.
const tiles: { m: MediaKey; label: string }[] = [
  { m: "polish", label: "Polishing" },
  { m: "foamDark", label: "Foam wash" },
  { m: "beadingClose", label: "Water beading" },
  { m: "dashClean", label: "Interior detailing" },
  { m: "ppfEdge", label: "PPF edge work" },
];

export default function HomeGallery() {
  return (
    <div className="gal-x">
      {tiles.map((t, i) => (
        <Photo
          key={t.m}
          label={t.label}
          m={t.m}
          ratio={i === 0 ? "1 / 1" : "4 / 3"}
          className={i === 0 ? "gal-x-lead" : undefined}
          sizes={i === 0 ? "(min-width: 900px) 50vw, 100vw" : "(min-width: 900px) 25vw, 50vw"}
        />
      ))}
    </div>
  );
}
