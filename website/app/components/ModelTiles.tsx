import { popularCars } from "@/lib/catalog";
import { whatsapp } from "@/lib/site";
import { IconArrow } from "./ui";

// Shop-by-car tiles: one per popular model, each opening WhatsApp with the
// model filled in. Photos are studio renders made at 3:2, shown uncropped.

const slug = (car: string) => car.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");

export default function ModelTiles() {
  return (
    <ul className="models-x">
      {popularCars.map((car) => {
        const [make, ...rest] = car.split(" ");
        const src = `/images/gen/car-${slug(car)}`;
        return (
          <li key={car}>
            <a
              href={whatsapp(`Hi Jay Ganesh, what accessories and PPF options do you have for my ${car}?`)}
              target="_blank"
              rel="noopener noreferrer"
              data-track="whatsapp"
              aria-label={`Ask about ${car} on WhatsApp`}
            >
              <span className="models-x-img">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={`${src}-800.jpg`}
                  srcSet={`${src}-800.jpg 800w, ${src}.jpg 1600w`}
                  sizes="(min-width: 1100px) 300px, (min-width: 720px) 33vw, 50vw"
                  alt={`${car} in the Jay Ganesh studio`}
                  width={1536}
                  height={1024}
                  loading="lazy"
                  decoding="async"
                />
              </span>
              <span className="models-x-body">
                <span className="models-x-make">{make}</span>
                <strong>{rest.join(" ")}</strong>
                <span className="models-x-go" aria-hidden><span className="models-x-go-text">Ask on WhatsApp</span> <IconArrow /></span>
              </span>
            </a>
          </li>
        );
      })}
    </ul>
  );
}
