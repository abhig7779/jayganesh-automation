import Link from "next/link";
import { Photo, IconArrow } from "./ui";
import { categories } from "@/lib/catalog";
import type { MediaKey } from "@/lib/media";

// Horizontal, swipeable rail of accessory categories. Photos come from the
// catalogue, so changing a category photo there changes it here too.
const items: { name: string; href: string; key: string }[] = [
  { name: "Seat covers", href: "/seat-covers/", key: "seat-covers" },
  { name: "Headlights", href: "/accessories/#headlights", key: "headlights" },
  { name: "Ambient lighting", href: "/accessories/#ambient", key: "ambient" },
  { name: "Android stereos", href: "/accessories/#stereo", key: "stereo" },
  { name: "360° cameras", href: "/accessories/#camera", key: "camera" },
  { name: "Dashcams", href: "/accessories/#dashcam", key: "dashcam" },
];

export default function AccessoryRail() {
  return (
    <ul className="rail-x" aria-label="Accessory categories">
      {items.map((a) => {
        const cat = categories.find((c) => c.key === a.key);
        const photo: MediaKey | undefined = cat?.photo;
        return (
          <li key={a.key}>
            <Link href={a.href} className="rail-x-card">
              <Photo label={a.name} m={photo} ratio="4 / 5" sizes="(min-width: 1024px) 22vw, 70vw" />
              <span className="rail-x-body">
                <strong>{a.name}</strong>
                {cat?.blurb && <span>{cat.blurb}</span>}
              </span>
            </Link>
          </li>
        );
      })}
      <li>
        <Link href="/accessories/" className="rail-x-card rail-x-all">
          <span className="rail-x-all-inner">
            <strong>All accessories</strong>
            <span className="rail-x-all-go" aria-hidden><IconArrow /></span>
          </span>
        </Link>
      </li>
    </ul>
  );
}
