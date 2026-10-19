"use client";

import dynamic from "next/dynamic";

// WebGL only runs in the browser, so the scene is never rendered on the server.
const Scene = dynamic(() => import("./Scene"), { ssr: false });

export default function SceneLoader() {
  return (
    <div className="stage" aria-hidden>
      <Scene />
    </div>
  );
}
