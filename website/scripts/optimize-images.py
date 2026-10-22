#!/usr/bin/env python3
"""Make WebP copies of the site's local photos, at several widths.

Run from the website folder after adding or replacing a photo:

    python3 scripts/optimize-images.py

Needs Pillow with WebP support. The JPEGs stay as fallbacks. (AVIF was tried:
only ~15% smaller than WebP for these photos, so it isn't worth the extra files.)

  public/images/gen/<name>.jpg  -> <name>-<w>.webp  for w in 480, 800, 1200, 1600
                                   (never wider than the original; the original
                                   width is always included)
  public/reels/<id>.jpg         -> <id>-<w>.webp for w in 270 and the original
  public/logo.png               -> logo-176.webp            (header/footer logo)

It also writes lib/image-sizes.json: each photo's real size and WebP widths,
keyed by the JPEG's public path, so lib/media.ts can build accurate srcsets
(the "-800.jpg" files of portrait renders are only 640 wide).
"""
import json
import sys
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
PUBLIC = ROOT / "public"
GEN = PUBLIC / "images" / "gen"
REELS = PUBLIC / "reels"

GEN_WIDTHS = [480, 800, 1200, 1600]
REEL_WIDTHS = [270]
WEBP_Q = 72


def widths_for(full: int, wanted: list[int]) -> list[int]:
    out = [w for w in wanted if w < full]
    out.append(full)
    return out


def encode(src: Path, base: Path, widths: list[int], force: bool) -> None:
    with Image.open(src) as im:
        im = im.convert("RGB")
        for w in widths:
            h = round(im.height * w / im.width)
            resized = im if w == im.width else im.resize((w, h), Image.LANCZOS)
            webp = base.with_name(f"{base.name}-{w}.webp")
            if force or not webp.exists():
                resized.save(webp, "WEBP", quality=WEBP_Q, method=6)


def main() -> None:
    force = "--force" in sys.argv
    manifest: dict[str, dict] = {}

    for src in sorted(GEN.glob("*.jpg")):
        if src.stem.endswith("-800"):
            continue
        name = src.stem
        with Image.open(src) as im:
            full_w, full_h = im.size
        small = GEN / f"{name}-800.jpg"
        small_w = Image.open(small).size[0] if small.exists() else None
        widths = widths_for(full_w, GEN_WIDTHS)
        encode(src, GEN / name, widths, force)
        manifest[f"/images/gen/{name}.jpg"] = {"w": full_w, "h": full_h, "jpg800": small_w, "webp": widths}
        print(f"gen   {name}: {widths}")

    for src in sorted(REELS.glob("*.jpg")):
        with Image.open(src) as im:
            full_w, full_h = im.size
        widths = widths_for(full_w, REEL_WIDTHS)
        encode(src, REELS / src.stem, widths, force)
        manifest[f"/reels/{src.name}"] = {"w": full_w, "h": full_h, "jpg800": None, "webp": widths}
        print(f"reel  {src.stem}: {widths}")

    logo = PUBLIC / "logo.png"
    out = PUBLIC / "logo-176.webp"
    if logo.exists() and (force or not out.exists()):
        with Image.open(logo) as im:
            im = im.convert("RGBA")
            h = round(im.height * 176 / im.width)
            im.resize((176, h), Image.LANCZOS).save(out, "WEBP", quality=88, method=6)
        print(f"logo  logo-176.webp (176x{h})")

    lines = ",\n".join(f"  {json.dumps(k)}: {json.dumps(v)}" for k, v in manifest.items())
    (ROOT / "lib" / "image-sizes.json").write_text("{\n" + lines + "\n}\n")
    print("wrote lib/image-sizes.json")


if __name__ == "__main__":
    main()
