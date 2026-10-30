# Google Business Profile posts

Every calendar post that has a `gbp` object also goes on the Google Business Profile as a "What's new" post, with its own SEO-named photo, its own text and one button.

## How it works

- `publish.yml` runs `src/gbp-posts.mjs` right after "Publish due posts" (every 30 minutes). It has its own step, so a failed Instagram or Facebook post does not stop the GBP post, and the log is saved either way.
- A post goes out when it is approved and due, using the same rules and grace period as the other platforms (`approved`, or files synced from Drive; `FORCE_DAY`; `DRY_RUN`). Same day and time as the Instagram creative post.
- It checks that `MEDIA_BASE_URL/gbp/<image>` answers 200 with an image type, creates the post, and records `{name, url, at}` in `state/posted.json` under `<day>.gbp`. A recorded post is never made twice.
- A blocked or failed post is logged, the others continue, and the run exits 1 (GitHub emails once per problem).

## Calendar schema

```json
"gbp": {
  "summary": "Paint protection film fitted on a Creta at our Loni Kalbhor studio. ...",
  "cta": { "actionType": "LEARN_MORE", "url": "https://jayganeshcaraccessories.com/ppf-pune" },
  "image": "ppf-installation-loni-kalbhor-pune.jpg",
  "alt": "PPF installation on a car at Jay Ganesh, Loni Kalbhor, Pune"
}
```

`actionType`: `LEARN_MORE`, `CALL` (no url), `BOOK`, `ORDER`, `SHOP`, `SIGN_UP`. The url must be on `https://jayganeshcaraccessories.com/`; `utm_source=google&utm_medium=gbp&utm_campaign=<dayKey>` is added if missing.

## Content rules (enforced in code)

- Summary at most 1,500 characters; aim for 150 to 300.
- No phone numbers, no URLs, no hashtags, at most one emoji.
- Exactly one button.
- Image is a real photo, 4:3, at least 400x300, 10 KB to 5 MB, no heavy text overlay.

## SEO

- File names: descriptive and hyphenated, ending in `-loni-kalbhor-pune` (`ppf-installation-loni-kalbhor-pune.jpg`).
- First line: keyword-rich but natural, with one service keyword plus Loni Kalbhor or Pune.
- Button goes to the matching service page.
- Never claim work, brands, prices or offers the owner has not confirmed.

## Making and uploading images

```bash
cd automation
node scripts/gbp-latlng.mjs        # shop lat/lng (read-only; needs GBP_* in .env and GBP_LOCATION)

~/.venvs/reel/bin/python scripts/gbp-image.py SOURCE_PHOTO ppf-installation-loni-kalbhor-pune \
  --title "PPF installation in Loni Kalbhor, Pune" \
  --description "Paint protection film installation at Jay Ganesh Car Accessories, Loni Kalbhor, Pune" \
  --keywords "PPF,paint protection film,Loni Kalbhor,Pune" \
  --lat 18.4929465 --lng 73.99336 --focus 0.5,0.5 --out ./gbp-out

DRY_RUN=true node scripts/upload-gbp-images.mjs ./gbp-out   # list only
node scripts/upload-gbp-images.mjs ./gbp-out                # needs FTP_* in .env, uploads to /gbp/ and checks the public URLs
```

The image script makes a 1600x1200 progressive JPEG (under 1 MB) with the logo bottom-right, a yellow bottom line and no text, and embeds EXIF and XMP metadata.

## Testing

```bash
node scripts/test-gbp-posts.mjs          # mocked, never touches the real calendar or state
DRY_RUN=true REQUIRE_APPROVAL=false node src/gbp-posts.mjs
```
