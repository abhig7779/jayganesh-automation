"use client";

import dynamic from "next/dynamic";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import type { ProgressRef } from "./PpfStoryScene";
import { IconArrow } from "./ui";

const Scene = dynamic(() => import("./PpfStoryScene"), { ssr: false });

const steps = [
  { title: "Inspection", body: "Your paint is four thin layers. We check the top one, the clear coat, under studio lights and mark every chip and swirl with you." },
  { title: "Wash and decontamination", body: "Foam wash, iron and tar removal and clay, so nothing is trapped between the paint and the film." },
  { title: "Film goes on", body: "A clear layer of film is laid over the clear coat, panel by panel, and squeegeed flat." },
  { title: "Protected", body: "Stone chips and scratches now hit the film, not your paint. Light marks on good film fade with heat, and you get a warranty card." },
];

export default function PpfStory() {
  const section = useRef<HTMLElement>(null);
  const progress = useRef(0) as ProgressRef;
  const [active, setActive] = useState(0);
  const [near, setNear] = useState(false);
  const [mobile, setMobile] = useState(false);
  const bar = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    setMobile(window.matchMedia("(max-width: 899px)").matches);
    const el = section.current;
    if (!el) return;

    // Only start WebGL when the section is close to the screen.
    const io = new IntersectionObserver(([e]) => e.isIntersecting && setNear(true), { rootMargin: "400px 0px" });
    io.observe(el);

    let raf = 0;
    const update = () => {
      raf = 0;
      const r = el.getBoundingClientRect();
      const total = r.height - window.innerHeight;
      const p = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 0;
      progress.current = p;
      if (bar.current) bar.current.style.transform = `scaleY(${p})`;
      setActive(Math.min(steps.length - 1, Math.floor(p * steps.length)));
    };
    const onScroll = () => { if (!raf) raf = requestAnimationFrame(update); };
    update();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
    return () => {
      io.disconnect();
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onScroll);
      if (raf) cancelAnimationFrame(raf);
    };
  }, [progress]);

  return (
    <section ref={section} className="story" aria-labelledby="story-title">
      <div className="story-pin">
        <div className="wrap story-grid">
          <div className="story-copy">
            <p className="label label--yellow">How PPF protects your car</p>
            <h2 id="story-title">What PPF actually does to your paint</h2>
            <ol className="story-steps">
              {steps.map((s, i) => (
                <li key={s.title} className={i === active ? "is-active" : i < active ? "is-done" : undefined} aria-current={i === active ? "step" : undefined}>
                  <span className="story-num">{String(i + 1).padStart(2, "0")}</span>
                  <div>
                    <strong>{s.title}</strong>
                    <p>{s.body}</p>
                  </div>
                </li>
              ))}
            </ol>
            <Link className="text-link" href="/ppf-pune/">PPF packages and prices <IconArrow /></Link>
          </div>
          <div className="story-stage" aria-hidden>
            <span className="story-progress"><span ref={bar} /></span>
            {near && <Scene progress={progress} mobile={mobile} />}
          </div>
        </div>
      </div>
    </section>
  );
}
