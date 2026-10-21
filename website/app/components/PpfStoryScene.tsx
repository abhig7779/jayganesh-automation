"use client";

import { useMemo, useRef } from "react";
import * as THREE from "three";
import { Canvas, useFrame } from "@react-three/fiber";
import { Environment, Html, Lightformer, RoundedBox } from "@react-three/drei";

// Progress (0–1) through the four steps, written by the section's scroll handler.
export type ProgressRef = { current: number };

const clamp01 = (v: number) => Math.min(1, Math.max(0, v));
const stage = (p: number, from: number, to: number) => clamp01((p - from) / (to - from));
const ease = (t: number) => t * t * (3 - 2 * t);

const W = 3.4; // layer width
const D = 2.2; // layer depth

// The paint stack, bottom to top. `h` is thickness (exaggerated, not to scale).
const LAYERS = [
  { key: "panel", label: "Body panel", h: 0.16, color: "#8a8e95", metalness: 0.85, roughness: 0.32 },
  { key: "primer", label: "Primer", h: 0.08, color: "#4d5158", metalness: 0.1, roughness: 0.8 },
  { key: "base", label: "Colour coat", h: 0.09, color: "#b0111b", metalness: 0.35, roughness: 0.3 },
  { key: "clear", label: "Clear coat", h: 0.07, color: "#ffffff", metalness: 0, roughness: 0.05, glass: true },
];
const FILM_H = 0.09;

function Stack({ progress, mobile }: { progress: ProgressRef; mobile: boolean }) {
  const group = useRef<THREE.Group>(null);
  const layerRefs = useRef<(THREE.Group | null)[]>([]);
  const film = useRef<THREE.Group>(null);
  const filmMat = useRef<THREE.MeshPhysicalMaterial>(null);
  const scan = useRef<THREE.Mesh>(null);
  const drops = useRef<THREE.InstancedMesh>(null);
  const stones = useRef<THREE.InstancedMesh>(null);
  const ripples = useRef<THREE.InstancedMesh>(null);
  const filmLabel = useRef<HTMLDivElement>(null);
  const layerLabels = useRef<(HTMLDivElement | null)[]>([]);

  const dummy = useMemo(() => new THREE.Object3D(), []);
  // Water drops sit on the clear coat; stones hit the film at fixed spots.
  const dropSpots = useMemo(
    () => Array.from({ length: 26 }, (_, i) => ({ x: (((i * 37) % 100) / 100) * (W - 0.5) - (W - 0.5) / 2, z: (((i * 61) % 100) / 100) * (D - 0.4) - (D - 0.4) / 2, s: 0.05 + ((i * 13) % 7) / 100 })),
    [],
  );
  const stoneSpots = useMemo(
    () => Array.from({ length: 6 }, (_, i) => ({ x: -1.1 + i * 0.45, z: -0.6 + ((i * 3) % 5) * 0.3, delay: i * 0.37 })),
    [],
  );

  useFrame((state, delta) => {
    const p = progress.current;
    const t = state.clock.elapsedTime;
    const inspect = stage(p, 0, 0.25);
    const wash = stage(p, 0.25, 0.5);
    const apply = ease(stage(p, 0.5, 0.75));
    const protect = stage(p, 0.75, 1);

    // Turn the stack slowly; tilt a little more when the stones hit.
    if (group.current) {
      const g = group.current;
      g.rotation.y = THREE.MathUtils.damp(g.rotation.y, -0.55 + inspect * 0.25 + protect * 0.15 + Math.sin(t * 0.3) * 0.04, 3, delta);
      g.rotation.x = THREE.MathUtils.damp(g.rotation.x, 0.42 + protect * 0.08, 3, delta);
    }

    // Layers start spread apart and close up as the film goes on.
    const gap = THREE.MathUtils.lerp(0.22, 0.015, apply);
    let y = -0.35;
    LAYERS.forEach((l, i) => {
      const ref = layerRefs.current[i];
      if (ref) ref.position.y = y + l.h / 2;
      y += l.h + gap;
    });
    const clearTop = y - gap; // top surface of the clear coat

    // 1. Inspection: a light line sweeps across the top.
    if (scan.current) {
      scan.current.visible = p < 0.27;
      scan.current.position.set(-W / 2 + ((inspect * 1.25) % 1) * W, clearTop + 0.02, 0);
      (scan.current.material as THREE.MeshBasicMaterial).opacity = 0.9 * (1 - stage(p, 0.2, 0.27));
    }

    // 2. Wash: drops bead up, then run off to one side.
    if (drops.current) {
      const show = Math.sin(Math.PI * wash);
      dropSpots.forEach((d, i) => {
        const run = stage(wash, 0.55, 1) * (0.6 + (i % 5) * 0.15);
        dummy.position.set(d.x + run * 1.4, clearTop + d.s * 0.5, d.z);
        dummy.rotation.set(0, 0, 0);
        dummy.scale.setScalar(Math.max(0.0001, show) * d.s * 10);
        dummy.updateMatrix();
        drops.current!.setMatrixAt(i, dummy.matrix);
      });
      drops.current.instanceMatrix.needsUpdate = true;
      drops.current.visible = show > 0.01;
    }

    // 3. Film: drops from above and seals onto the clear coat.
    const filmY = THREE.MathUtils.lerp(clearTop + 1.5, clearTop + FILM_H / 2 + 0.005, apply);
    if (film.current) {
      film.current.position.y = filmY;
      film.current.visible = p > 0.48;
    }
    if (filmMat.current) {
      filmMat.current.opacity = 0.18 + 0.22 * apply;
      filmMat.current.emissiveIntensity = 0.04 + protect * 0.2;
    }
    if (filmLabel.current) filmLabel.current.style.opacity = String(stage(p, 0.5, 0.6));
    // Once the layers close up their labels would overlap, so only the film keeps one.
    const labelOpacity = String(1 - stage(p, 0.55, 0.68));
    layerLabels.current.forEach((el) => { if (el) el.style.opacity = labelOpacity; });

    // 4. Protected: stones fly in, hit the film and bounce off.
    const filmTop = filmY + FILM_H / 2;
    if (stones.current && ripples.current) {
      const live = protect > 0.02;
      stoneSpots.forEach((s, i) => {
        const cycle = ((t * 0.9 + s.delay) % 1.4) / 1.4; // 0..1 per throw
        let px: number, py: number, pz: number;
        if (cycle < 0.45) {
          const k = cycle / 0.45;
          px = THREE.MathUtils.lerp(s.x - 1.6, s.x, k);
          py = THREE.MathUtils.lerp(filmTop + 1.6, filmTop + 0.05, k * k);
          pz = s.z;
        } else {
          const k = (cycle - 0.45) / 0.55;
          px = s.x + k * 1.2;
          py = filmTop + 0.05 + Math.sin(k * Math.PI) * 0.7 + k * 0.4;
          pz = s.z + k * 0.3;
        }
        dummy.position.set(px, py, pz);
        dummy.rotation.set(t * 3 + i, t * 2, 0);
        dummy.scale.setScalar(live ? 0.07 * Math.min(1, protect * 4) : 0.0001);
        dummy.updateMatrix();
        stones.current!.setMatrixAt(i, dummy.matrix);

        // A ripple on the film where the stone lands.
        const r = cycle >= 0.45 && cycle < 0.8 ? (cycle - 0.45) / 0.35 : 0;
        dummy.position.set(s.x, filmTop + 0.006, s.z);
        dummy.rotation.set(-Math.PI / 2, 0, 0);
        dummy.scale.setScalar(live && r > 0 ? 0.08 + r * 0.45 : 0.0001);
        dummy.updateMatrix();
        ripples.current!.setMatrixAt(i, dummy.matrix);
      });
      stones.current.instanceMatrix.needsUpdate = true;
      ripples.current.instanceMatrix.needsUpdate = true;
    }
  });

  return (
    <group ref={group} scale={mobile ? 0.86 : 0.9} position={mobile ? [0, 0, 0] : [-0.45, 0, 0]}>
      {LAYERS.map((l, i) => (
        <group key={l.key} ref={(el) => { layerRefs.current[i] = el; }}>
          <RoundedBox args={[W, l.h, D]} radius={Math.min(0.03, l.h / 2.2)} smoothness={3}>
            {l.glass ? (
              <meshPhysicalMaterial color={l.color} roughness={l.roughness} metalness={0} clearcoat={1} transparent opacity={0.35} />
            ) : (
              <meshStandardMaterial color={l.color} metalness={l.metalness} roughness={l.roughness} />
            )}
          </RoundedBox>
          {!mobile && (
            <Html position={[W / 2 + 0.12, 0, D / 2]} zIndexRange={[5, 0]}>
              <div ref={(el) => { layerLabels.current[i] = el; }} className="layer-label">{l.label}</div>
            </Html>
          )}
        </group>
      ))}

      <group ref={film} visible={false}>
        <RoundedBox args={[W + 0.04, FILM_H, D + 0.04]} radius={0.035} smoothness={3}>
          <meshPhysicalMaterial
            ref={filmMat}
            color="#dff3ff"
            roughness={0.02}
            metalness={0}
            clearcoat={1}
            clearcoatRoughness={0}
            transparent
            opacity={0.2}
            emissive="#9fd4ff"
            emissiveIntensity={0.04}
          />
        </RoundedBox>
        {!mobile && (
          <Html position={[W / 2 + 0.12, 0, D / 2]} zIndexRange={[5, 0]}>
            <div ref={filmLabel} className="layer-label layer-label--film">PPF film</div>
          </Html>
        )}
      </group>

      <mesh ref={scan} rotation={[-Math.PI / 2, 0, 0]}>
        <planeGeometry args={[0.05, D + 0.2]} />
        <meshBasicMaterial color="#ffffff" transparent opacity={0.9} toneMapped={false} />
      </mesh>

      <instancedMesh ref={drops} args={[undefined, undefined, 26]} visible={false}>
        <sphereGeometry args={[0.1, 16, 12]} />
        <meshPhysicalMaterial color="#e6f4ff" roughness={0} transmission={0.6} thickness={0.2} clearcoat={1} transparent opacity={0.85} />
      </instancedMesh>

      <instancedMesh ref={stones} args={[undefined, undefined, 6]}>
        <dodecahedronGeometry args={[1, 0]} />
        <meshStandardMaterial color="#6f6a63" roughness={0.9} />
      </instancedMesh>
      <instancedMesh ref={ripples} args={[undefined, undefined, 6]}>
        <ringGeometry args={[0.8, 1, 40]} />
        <meshBasicMaterial color="#ffe500" transparent opacity={0.8} toneMapped={false} side={THREE.DoubleSide} />
      </instancedMesh>
    </group>
  );
}

export default function PpfStoryScene({ progress, mobile }: { progress: ProgressRef; mobile: boolean }) {
  return (
    <Canvas
      dpr={mobile ? [1, 1.5] : [1, 1.75]}
      camera={{ position: [0, 1.4, 6.2], fov: 38 }}
      gl={{ antialias: true, alpha: true, powerPreference: "high-performance" }}
    >
      <ambientLight intensity={0.45} />
      <directionalLight position={[3, 6, 4]} intensity={2.2} />
      <pointLight position={[-4, 2, 3]} intensity={8} color="#ffe500" />
      <Stack progress={progress} mobile={mobile} />
      <Environment resolution={128}>
        <Lightformer intensity={3} position={[0, 5, -4]} scale={[12, 2, 1]} />
        <Lightformer intensity={2} position={[-6, 2, 0]} rotation-y={Math.PI / 2} scale={[8, 1, 1]} />
        <Lightformer intensity={2} position={[6, 2, 0]} rotation-y={-Math.PI / 2} scale={[8, 1, 1]} />
      </Environment>
    </Canvas>
  );
}
