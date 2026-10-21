"use client";

import dynamic from "next/dynamic";
import { useEffect, useState } from "react";

// WebGL runs only in the browser, and only on large screens: phones get the
// photo background without the 3D shield, which keeps them fast.
const Scene = dynamic(() => import("./Scene"), { ssr: false });

export default function SceneLoader() {
  const [show, setShow] = useState(false);
  useEffect(() => {
    const mq = window.matchMedia("(min-width: 1024px)");
    const update = () => setShow(mq.matches);
    update();
    mq.addEventListener("change", update);
    return () => mq.removeEventListener("change", update);
  }, []);
  return show ? (
    <div className="hero-3d" aria-hidden>
      <Scene />
    </div>
  ) : null;
}
