# Jay Ganesh Car Accessories

Two projects for Jay Ganesh Car Accessories, Loni Kalbhor, Pune.

| Folder | What it is | Runs on |
|---|---|---|
| [`automation/`](automation/README.md) | Posts the content calendar to Instagram, Facebook and YouTube, and replies to Google reviews | GitHub Actions (`.github/workflows/`) |
| [`website/`](website/) | The Next.js website: home page, privacy policy, terms | Vercel or Cloudflare Pages, with the root directory set to `website` |

Each folder has its own `package.json`. Run commands from inside the folder:

```bash
cd automation && node src/validate.mjs
cd website && npm install && npm run dev
```

Secrets go in `automation/.env` locally and in the repository's GitHub secrets for Actions. Neither is committed.
