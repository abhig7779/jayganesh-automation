"use client";

import { useState } from "react";
import { Photo } from "./ui";

export default function BeforeAfter({
  label,
  before,
  after,
}: {
  label: string;
  before?: string;
  after?: string;
}) {
  const [split, setSplit] = useState(50);
  return (
    <div className="ba" style={{ ["--split" as string]: `${split}%` }}>
      <Photo label={`${label}, before`} src={before} ratio="auto" />
      <Photo label={`${label}, after`} src={after} ratio="auto" className="after" />
      <span className="ba-tag ba-tag--before price-ask">Before</span>
      <span className="ba-tag ba-tag--after price-ask">After</span>
      <span className="ba-handle" aria-hidden />
      <input
        type="range"
        min={0}
        max={100}
        value={split}
        onChange={(e) => setSplit(Number(e.target.value))}
        aria-label={`Compare before and after: ${label}`}
      />
    </div>
  );
}
