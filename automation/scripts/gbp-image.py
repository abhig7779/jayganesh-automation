#!/usr/bin/env python3
"""Make a Google Business Profile post photo: 1600x1200 JPEG, logo bottom-right, yellow bottom line,
no text overlay, with SEO metadata (EXIF + XMP) embedded.

  ~/.venvs/reel/bin/python scripts/gbp-image.py PHOTO seo-file-name \
      --title "..." --description "..." --keywords a,b,c [--lat 18.49 --lng 73.99] [--focus 0.5,0.5] [--out DIR]
"""
import argparse, io, os, struct, sys
from xml.sax.saxutils import escape
from PIL import Image, ImageDraw, ImageOps
import piexif

W, H = 1600, 1200
OWNER = "Jay Ganesh Car Accessories & Detailing Studio"
HERE = os.path.dirname(os.path.abspath(__file__))
LOGOS = [os.path.join(HERE, "../../website/public/logo.png"), os.path.join(HERE, "../../../website/public/logo.png")]


def cover(img, focus):
    img = ImageOps.exif_transpose(img).convert("RGB")
    scale = max(W / img.width, H / img.height)
    img = img.resize((round(img.width * scale), round(img.height * scale)), Image.LANCZOS)
    left = min(max(round(focus[0] * img.width - W / 2), 0), img.width - W)
    top = min(max(round(focus[1] * img.height - H / 2), 0), img.height - H)
    return img.crop((left, top, left + W, top + H))


def brand(img):
    # soft dark fade over the bottom 300 px, so the logo reads on any photo
    fade = Image.new("L", (1, 300))
    for y in range(300):
        fade.putpixel((0, y), round(170 * (y / 299) ** 1.6))
    fade = fade.resize((W, 300))
    img.paste(Image.new("RGB", (W, 300), (0, 0, 0)), (0, H - 300), fade)
    logo_path = next((p for p in LOGOS if os.path.exists(p)), None)
    if not logo_path:
        sys.exit("logo not found: website/public/logo.png")
    logo = Image.open(logo_path).convert("RGBA")
    logo = logo.resize((230, round(logo.height * 230 / logo.width)), Image.LANCZOS)
    img.paste(logo, (W - 230 - 40, H - 8 - logo.height - 28), logo)
    ImageDraw.Draw(img).rectangle((0, H - 8, W, H), fill=(0xFF, 0xE5, 0x00))
    return img


def ucs2(s):
    return (s + "\x00").encode("utf-16-le")


def exif_bytes(a):
    zeroth = {
        piexif.ImageIFD.ImageDescription: a.description.encode("utf-8"),
        piexif.ImageIFD.Artist: OWNER.encode("utf-8"),
        piexif.ImageIFD.Copyright: f"Copyright {OWNER}".encode("utf-8"),
        piexif.ImageIFD.XPTitle: ucs2(a.title),
        piexif.ImageIFD.XPKeywords: ucs2(";".join(a.kw)),
        piexif.ImageIFD.XPComment: ucs2(a.description),
    }
    exif = {piexif.ExifIFD.UserComment: b"UNICODE\x00" + a.description.encode("utf-16-le")}
    gps = {}
    if a.lat is not None and a.lng is not None:
        def dms(v):
            v = abs(v); d = int(v); m = int((v - d) * 60); s = round(((v - d) * 60 - m) * 60 * 10000)
            return ((d, 1), (m, 1), (s, 10000))
        gps = {
            piexif.GPSIFD.GPSVersionID: (2, 3, 0, 0),
            piexif.GPSIFD.GPSLatitudeRef: b"N" if a.lat >= 0 else b"S",
            piexif.GPSIFD.GPSLatitude: dms(a.lat),
            piexif.GPSIFD.GPSLongitudeRef: b"E" if a.lng >= 0 else b"W",
            piexif.GPSIFD.GPSLongitude: dms(a.lng),
        }
    return piexif.dump({"0th": zeroth, "Exif": exif, "GPS": gps, "1st": {}, "thumbnail": None})


def xmp_packet(a):
    li = lambda s: f'<rdf:li xml:lang="x-default">{escape(s)}</rdf:li>'
    subj = "".join(f"<rdf:li>{escape(k)}</rdf:li>" for k in a.kw)
    body = (
        '<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        f"<dc:title><rdf:Alt>{li(a.title)}</rdf:Alt></dc:title>"
        f"<dc:description><rdf:Alt>{li(a.description)}</rdf:Alt></dc:description>"
        f"<dc:subject><rdf:Bag>{subj}</rdf:Bag></dc:subject>"
        f"<dc:creator><rdf:Seq><rdf:li>{escape(OWNER)}</rdf:li></rdf:Seq></dc:creator>"
        f"<dc:rights><rdf:Alt>{li('Copyright ' + OWNER)}</rdf:Alt></dc:rights>"
        "</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end=\"w\"?>"
    )
    return b"http://ns.adobe.com/xap/1.0/\x00" + body.encode("utf-8")


def insert_xmp(jpeg, xmp):
    # APP1 segment straight after SOI and the EXIF APP1 (if any)
    seg = b"\xff\xe1" + struct.pack(">H", len(xmp) + 2) + xmp
    pos = 2
    while jpeg[pos:pos + 2] == b"\xff\xe0" or (jpeg[pos:pos + 2] == b"\xff\xe1" and jpeg[pos + 4:pos + 8] == b"Exif"):
        pos += 2 + struct.unpack(">H", jpeg[pos + 2:pos + 4])[0]
    return jpeg[:pos] + seg + jpeg[pos:]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("source")
    ap.add_argument("name", help="SEO file name without .jpg, e.g. ppf-installation-loni-kalbhor-pune")
    ap.add_argument("--title", required=True)
    ap.add_argument("--description", required=True)
    ap.add_argument("--keywords", required=True)
    ap.add_argument("--lat", type=float)
    ap.add_argument("--lng", type=float)
    ap.add_argument("--focus", default="0.5,0.5", help="x,y of the subject, 0-1")
    ap.add_argument("--out", default=".")
    a = ap.parse_args()
    a.kw = [k.strip() for k in a.keywords.split(",") if k.strip()]
    focus = tuple(float(v) for v in a.focus.split(","))
    name = a.name[:-4] if a.name.lower().endswith(".jpg") else a.name

    img = brand(cover(Image.open(a.source), focus))
    exif = exif_bytes(a)
    for q in (90, 86, 82, 78):
        buf = io.BytesIO()
        img.save(buf, "JPEG", quality=q, progressive=True, optimize=True, exif=exif)
        if buf.tell() < 1_000_000:
            break
    data = insert_xmp(buf.getvalue(), xmp_packet(a))
    os.makedirs(a.out, exist_ok=True)
    out = os.path.join(a.out, name + ".jpg")
    open(out, "wb").write(data)

    # read it all back
    chk = Image.open(out)
    ex = piexif.load(out)
    xmp = chk.info.get("xmp") or b""
    print(f"{out}\n  {chk.size[0]}x{chk.size[1]}  {len(data) / 1024:.0f} KB  progressive={chk.info.get('progressive', chk.info.get('progression', 0))}")
    print("  EXIF ImageDescription:", ex["0th"][piexif.ImageIFD.ImageDescription].decode())
    print("  EXIF Artist/Copyright:", ex["0th"][piexif.ImageIFD.Artist].decode(), "/", ex["0th"][piexif.ImageIFD.Copyright].decode())
    print("  XPTitle:", bytes(ex["0th"][piexif.ImageIFD.XPTitle]).decode("utf-16-le").rstrip("\x00"))
    print("  XPKeywords:", bytes(ex["0th"][piexif.ImageIFD.XPKeywords]).decode("utf-16-le").rstrip("\x00"))
    print("  UserComment:", ex["Exif"][piexif.ExifIFD.UserComment][8:].decode("utf-16-le"))
    if ex["GPS"]:
        g = lambda t: t[0][0] / t[0][1] + t[1][0] / t[1][1] / 60 + t[2][0] / t[2][1] / 3600
        print(f"  GPS: {g(ex['GPS'][piexif.GPSIFD.GPSLatitude]):.5f}, {g(ex['GPS'][piexif.GPSIFD.GPSLongitude]):.5f}")
    print("  XMP:", "yes" if b"<dc:title>" in xmp and b"<dc:subject>" in xmp and b"<dc:rights>" in xmp else "MISSING", f"({len(xmp)} bytes)")
    if chk.size != (W, H) or len(data) >= 1_000_000 or not xmp:
        sys.exit("verification failed")


main()
