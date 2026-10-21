"use client";

import { useMemo, useRef } from "react";
import * as THREE from "three";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { Environment, Lightformer, Sparkles } from "@react-three/drei";

const YELLOW = "#ffe500";
const RED = "#ff3b30";

// The shield from the logo: a pitched "roof" on top, straight sides, and a
// curved point at the bottom. Built as an outline (outer shape minus inner).
export function shieldPath(s: number) {
  const p = new THREE.Shape();
  p.moveTo(0, 1.55 * s);
  p.lineTo(1.25 * s, 0.85 * s);
  p.lineTo(1.25 * s, -0.25 * s);
  p.quadraticCurveTo(1.15 * s, -1.05 * s, 0, -1.6 * s);
  p.quadraticCurveTo(-1.15 * s, -1.05 * s, -1.25 * s, -0.25 * s);
  p.lineTo(-1.25 * s, 0.85 * s);
  p.closePath();
  return p;
}

export function Shield() {
  const geometry = useMemo(() => {
    const outer = shieldPath(1);
    const inner = shieldPath(0.86);
    outer.holes.push(new THREE.Path(inner.getPoints(48)));
    const g = new THREE.ExtrudeGeometry(outer, {
      depth: 0.22,
      bevelEnabled: true,
      bevelThickness: 0.05,
      bevelSize: 0.04,
      bevelSegments: 4,
      curveSegments: 48,
    });
    g.center();
    return g;
  }, []);

  return (
    <mesh geometry={geometry} castShadow>
      <meshStandardMaterial color="#ffffff" metalness={0.55} roughness={0.28} envMapIntensity={1.6} />
    </mesh>
  );
}

// The yellow car swoosh: a glowing tube along the roof line of the logo's car.
export function Swoosh() {
  const [roof, sill] = useMemo(() => {
    const roofCurve = new THREE.CatmullRomCurve3(
      [
        [-2.3, -0.05, 0.35],
        [-1.4, 0.18, 0.4],
        [-0.6, 0.58, 0.45],
        [0.35, 0.62, 0.45],
        [1.15, 0.22, 0.42],
        [1.9, 0.08, 0.38],
        [2.45, -0.3, 0.32],
      ].map(([x, y, z]) => new THREE.Vector3(x, y, z)),
    );
    const sillCurve = new THREE.CatmullRomCurve3(
      [
        [-2.1, -0.18, 0.36],
        [-0.9, -0.02, 0.42],
        [0.4, 0.12, 0.44],
        [1.6, -0.02, 0.4],
        [2.3, -0.24, 0.34],
      ].map(([x, y, z]) => new THREE.Vector3(x, y, z)),
    );
    return [
      new THREE.TubeGeometry(roofCurve, 160, 0.055, 12, false),
      new THREE.TubeGeometry(sillCurve, 120, 0.03, 10, false),
    ];
  }, []);

  return (
    <group position={[0, 0.15, 0]}>
      <mesh geometry={roof}>
        <meshStandardMaterial color={YELLOW} emissive={YELLOW} emissiveIntensity={1.4} toneMapped={false} />
      </mesh>
      <mesh geometry={sill}>
        <meshStandardMaterial color={YELLOW} emissive={YELLOW} emissiveIntensity={0.9} toneMapped={false} />
      </mesh>
      {/* The thin red line under the name in the logo */}
      <mesh position={[0, -0.62, 0.3]}>
        <boxGeometry args={[2.2, 0.025, 0.025]} />
        <meshStandardMaterial color={RED} emissive={RED} emissiveIntensity={1.2} toneMapped={false} />
      </mesh>
    </group>
  );
}

function Badge() {
  const group = useRef<THREE.Group>(null);
  const { pointer } = useThree();
  const reduced = useMemo(
    () => typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches,
    [],
  );

  useFrame((state, delta) => {
    const g = group.current;
    if (!g) return;
    // Turn gently as the hero scrolls away; follow the pointer a little.
    const scroll = Math.min(1, window.scrollY / Math.max(1, window.innerHeight));
    const idle = reduced ? 0 : Math.sin(state.clock.elapsedTime * 0.5) * 0.06;
    const k = reduced ? 1000 : 3.5;
    g.rotation.y = THREE.MathUtils.damp(g.rotation.y, -0.42 + scroll * 0.9 + (reduced ? 0 : pointer.x * 0.22), k, delta);
    g.rotation.x = THREE.MathUtils.damp(g.rotation.x, reduced ? 0 : -pointer.y * 0.12, k, delta);
    g.position.y = THREE.MathUtils.damp(g.position.y, 0.15 + idle - scroll * 0.6, k, delta);
  });

  return (
    <group ref={group} scale={0.86}>
      <Shield />
      <Swoosh />
    </group>
  );
}

export default function Scene() {
  return (
    <Canvas
      dpr={[1, 1.75]}
      camera={{ position: [0, 0.4, 7], fov: 40 }}
      gl={{ antialias: true, alpha: true, powerPreference: "high-performance" }}
    >
            
      <ambientLight intensity={0.25} />
      <spotLight position={[4, 6, 6]} angle={0.4} penumbra={0.8} intensity={60} color="#ffffff" />
      <pointLight position={[-4, 1, 3]} intensity={12} color={YELLOW} />

      <Badge />
      <Sparkles count={40} scale={[8, 5, 5]} size={2.2} speed={0.35} color={YELLOW} opacity={0.55} />

      {/* Studio-style reflections without loading any image files */}
      <Environment resolution={256}>
        <Lightformer intensity={3} position={[0, 4, -6]} scale={[12, 2, 1]} />
        <Lightformer intensity={1.5} position={[-6, 1, 0]} rotation-y={Math.PI / 2} scale={[8, 1, 1]} />
        <Lightformer intensity={1.5} position={[6, 1, 0]} rotation-y={-Math.PI / 2} scale={[8, 1, 1]} />
        <Lightformer intensity={2} color={YELLOW} position={[0, -3, 2]} rotation-x={Math.PI / 2} scale={[10, 1, 1]} />
      </Environment>
    </Canvas>
  );
}
