# Morning checklist: what the owner does personally to go live

Only you can do these. Passwords and keys are typed by you into mPanel, GitHub or the server. Never paste them into chat or git (the repo is public). Full detail is in `DEPLOY.md`, `WHATSAPP-SETUP-STATUS.md` and `CRM-PLAN.md` section 9.

Times are rough. Do the sections in order.

---

## A. MilesWeb (hosting)

- [x] **Database + user.** ✅ Done 6 Nov: database `jayganes1_crm` (MariaDB) with user `jayganes1_crm`. Keep the password safe; it is typed once into the server `.env` at first deploy.
  *Why:* the CRM stores every bill and customer here. *Time:* 10 minutes.
- [x] **PHP version.** No change needed: the CRM is built and tested for PHP 8.2 (MilesWeb's current version). On this plan the PHP version appears to be account-wide, and changing it could affect the `api.` and `media.` subdomains. Leave it on 8.2.
  *Why:* the app will not start on an older PHP. *Time:* 5 minutes.
- [ ] **Create the mailbox** `crm@jayganeshcaraccessories.com` in mPanel, Email.
  *Why:* backup and error emails are sent from it. *Time:* 5 minutes.
- [ ] **Add the SSH key.** On your Mac Terminal make the key (`ssh-keygen -t ed25519 ...`, see DEPLOY step 3). Paste the **public** key into mPanel, SSH Access, SSH Keys, Import, then **Authorize**. Note the host, port and username.
  *Why:* GitHub uses it to upload the code. *Time:* 10 minutes.
- [ ] **Add the GitHub secrets.** GitHub repo, Settings, Secrets and variables, Actions. Secrets: `CRM_SSH_HOST`, `CRM_SSH_PORT`, `CRM_SSH_USER`, `CRM_SSH_KEY` (the private key), optional `CRM_SSH_KNOWN_HOSTS`. Variable: `CRM_PHP_BIN`. Then make the **production** environment.
  *Why:* lets the deploy button work. *Time:* 15 minutes.
- [ ] **Backups to Google Drive.** Add `GDRIVE_BACKUP_FOLDER_ID` (and `GDRIVE_SA_JSON` if you use a service account). The simple way is to reuse the existing Google login (DEPLOY, Option B).
  *Why:* a copy of your data outside the server. *Time:* 15 minutes.
- [ ] **Run the first deploy and set up the server.** GitHub, Actions, **CRM deploy**, Run workflow. Then follow DEPLOY steps 6 to 10 with the developer (fill `.env`, `migrate`, cron line every minute, free SSL). Save the **backup password** and **APP_KEY** in your password manager.
  *Why:* this is the actual go-live. *Time:* 45 minutes with the developer.

## B. Vercel

- [ ] **Storage cleanup.** Vercel says "Deployment Storage 12.11 GB / 10 GB", so new website deploys are held (including the privacy-policy update Meta needs).
  1. vercel.com → project **jayganesh-website** (shows "No Production Deployment"; it's an unused duplicate) → Settings → bottom → **Delete Project**.
  2. Project **jayganesh-automation** (the live site, jayganeshcaraccessories.com) → Deployments → delete old deployments. **Keep the one marked "Current" / Production.**
  3. Tell the developer when it's done. They'll redeploy and check https://jayganeshcaraccessories.com/privacy-policy/ shows "Last updated 5 November 2026".
  *Why:* website updates are stuck until storage is under the limit. *Time:* 10 minutes. CRM pushes no longer rebuild the website (fixed on 6 Nov), so it won't fill up again.

## C. WhatsApp (Meta)

- [ ] **Add a working payment method.** Meta Business Settings, Billing & payments, WhatsApp account "Jai Ganesh Car Accessories", add a card or UPI autopay. This clears error 141006.
  *Why:* without it, no bill can go out on the API. *Time:* 10 minutes.
- [ ] **Approve the Meta review submission.** Five templates were submitted on 5 Nov (`invoice_ebill`, `payment_receipt`, `quotation_send`, `vehicle_ready`, `followup_reminder`). Check WhatsApp Manager, Message templates. If Meta asks you to confirm, approve. If `quotation_send` is re-labelled Marketing, accept it (about ₹0.86 instead of ₹0.115). Also approve the Tech Provider app review when the developer submits it.
  *Why:* WhatsApp bills only work with approved templates. *Time:* 10 minutes, plus Meta's wait (minutes to 24 hours for templates, 1 to 3 weeks for app review).
- [ ] **Choose the number plan.** Option A: connect 98231 07676 by coexistence (wait 1 to 3 weeks). Option B: new SIM only for bills (about 1 day).
  *Why:* decides when automatic bills start. *Time:* 5 minutes to decide.
- [ ] **Tell us about AiSensy.** Who set it up on "Jayganesh sound 76" and is it still paid?
  *Why:* avoids connecting the same number to two partners. *Time:* 5 minutes.
- [ ] **Update the WhatsApp Business app** on the 98231 07676 phone (version 2.24.17 or newer).
  *Why:* needed before the connect day. *Time:* 5 minutes.
- [ ] **QR scan day (connect day).** With the phone in hand: open the CRM connect page, log in with Facebook, pick "Connect existing WhatsApp Business app", enter the number, then in the app tap **Connect**, **Connect to the Business Platform**, **Confirm**, and choose **Share chat history**. Keep the app open until it says connected. Re-link the counter PC with WhatsApp **Web** in a browser (not the Windows app).
  *Why:* turns on automatic bills from your own number. *Time:* about 10 minutes (the developer must have the webhook ready first).
- [ ] **Open the Business app at least every 14 days** after connecting.
  *Why:* otherwise Meta disconnects the link. *Time:* 1 minute each time.

Until this is done, the CRM works in **Manual** WhatsApp mode (staff press Send themselves), so billing is not blocked.

## D. Shop data to gather

- [ ] **GSTIN: do you have one?** If yes, send the GSTIN and HSN codes. If no, bills will be a Bill of Supply with no GST.
  *Why:* decides the bill format and tax lines. *Time:* 5 minutes.
- [ ] **UPI ID** and payee name.
  *Why:* puts the payment QR on bills. *Time:* 2 minutes.
- [ ] **Price list** as Excel or CSV (name, brand, our price, MRP, stock, code). A photo of the notebook is a start. Template: Stock, Import price list, Download template.
  *Why:* the counter cannot bill without products. *Time:* 1 to 3 hours to prepare, 10 minutes to import.
- [ ] **Staff names, mobile numbers and roles** (Sales, Technician, Finance, Admin, Marketing).
  *Why:* each person gets their own login. *Time:* 15 minutes.
- [ ] **Printer type:** A4 or 80 mm thermal? Also which devices are at the counter (PC, tablet) and whether you have a USB barcode scanner.
  *Why:* sets the Print button and the bill layout. *Time:* 2 minutes.
- [ ] **Old customers:** any phone contacts or notebooks to import?
  *Why:* gives offers and follow-ups a head start. *Time:* 5 minutes to decide.
- [ ] **CA software:** does your CA use Tally? (Excel is what the CRM gives today.)
  *Why:* sets the export. *Time:* 2 minutes.

## E. First login

- [ ] **Create your Owner login.** The developer (or you over SSH) runs, in the `crm-app` folder:
  ```
  php artisan crm:create-owner
  ```
  It asks for your name, mobile (your login), optional email and a password (at least 8 characters). You type the password at the prompt.
  *Why:* the first account that can add everyone else. *Time:* 2 minutes.
- [ ] **Sign in** at https://crm.jayganeshcaraccessories.com, then open **Settings** and fill in shop details, GSTIN (if any), UPI ID and print format.
  *Why:* bills print these. *Time:* 10 minutes.
- [ ] **Add staff** (Staff, Add staff), **import the price list**, and make one test bill to your own number.
  *Why:* proves everything works before paper bills stop. *Time:* 30 minutes.
- [ ] **Check `/health`** is green (the Last backup line turns green after the first 23:30 run).
  *Why:* proves backups and the scheduler are running. *Time:* 2 minutes.

## Decisions waiting for you (added overnight, 6 Nov)

- [ ] **AI auto-reply for ad chats: choose the provider.** It's built and **off** by default. Claude (paid, chats stay private) or OpenAI free tier (chats shared with OpenAI). Settings → WhatsApp → AI auto-reply: off / ads-only / all.
- [ ] **Meta App Review: pick what to submit.** Open `crm/WHATSAPP-APP-REVIEW.md`; each permission is marked Used / Built / Planned / No use. Only items you approve get submitted. Nothing has been deleted or submitted.
- [x] **Meta app settings:** policy, terms and data-deletion URLs now point to jayganeshcaraccessories.com; app domain and icon set; the "WA coexistence - CRM" Embedded Signup config was created.
