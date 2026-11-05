#!/usr/bin/env python3
"""Upload one CRM backup zip to a Google Drive folder and keep only the newest N there.

Used by .github/workflows/crm-backup-pull.yml (crm/DEPLOY.md, "Backups to Google Drive").

Login, first match wins:
  1. GDRIVE_SA_JSON            service-account key (JSON text). The folder must be in a
                               *Shared drive* with the service account added as Content manager,
                               because service accounts have no storage of their own.
  2. GDRIVE_OAUTH_CLIENT_ID + GDRIVE_OAUTH_CLIENT_SECRET + GDRIVE_OAUTH_REFRESH_TOKEN
                               a normal Google login (works with a plain My Drive folder). The repo
                               already has these for the posting system (GBP_CLIENT_ID, …,
                               DRIVE_REFRESH_TOKEN), and the workflow passes them in.

Usage: drive_backup_upload.py FILE --folder FOLDER_ID [--keep 30] [--prefix jayganesh-crm-]
"""

import argparse
import json
import os
import sys

from google.oauth2 import credentials as user_credentials
from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload

SCOPES = ["https://www.googleapis.com/auth/drive"]
ALL_DRIVES = {"supportsAllDrives": True}


def login():
    sa = os.environ.get("GDRIVE_SA_JSON", "").strip()
    if sa:
        return service_account.Credentials.from_service_account_info(json.loads(sa), scopes=SCOPES), "service account"
    cid = os.environ.get("GDRIVE_OAUTH_CLIENT_ID", "").strip()
    secret = os.environ.get("GDRIVE_OAUTH_CLIENT_SECRET", "").strip()
    token = os.environ.get("GDRIVE_OAUTH_REFRESH_TOKEN", "").strip()
    if cid and secret and token:
        creds = user_credentials.Credentials(
            None, refresh_token=token, client_id=cid, client_secret=secret,
            token_uri="https://oauth2.googleapis.com/token", scopes=SCOPES,
        )
        return creds, "Google login (refresh token)"
    sys.exit("::error::No Google Drive login: set the GDRIVE_SA_JSON secret (see crm/DEPLOY.md).")


def list_backups(drive, folder, prefix):
    files, page = [], None
    q = f"'{folder}' in parents and trashed = false and name contains '{prefix}'"
    while True:
        res = drive.files().list(
            q=q, fields="nextPageToken, files(id, name, createdTime, size)", orderBy="name desc",
            pageSize=200, pageToken=page, includeItemsFromAllDrives=True, **ALL_DRIVES,
        ).execute()
        files += [f for f in res.get("files", []) if f["name"].startswith(prefix) and f["name"].endswith(".zip")]
        page = res.get("nextPageToken")
        if not page:
            return files


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("file")
    ap.add_argument("--folder", required=True)
    ap.add_argument("--keep", type=int, default=30)
    ap.add_argument("--prefix", default="jayganesh-crm-")
    args = ap.parse_args()

    creds, how = login()
    drive = build("drive", "v3", credentials=creds, cache_discovery=False)
    print(f"Signed in to Google Drive with a {how}.")

    name = args.prefix + os.path.basename(args.file)  # jayganesh-crm-2026-11-05-18-00-03.zip
    existing = {f["name"] for f in list_backups(drive, args.folder, args.prefix)}
    if name in existing:
        print(f"{name} is already in Drive; not uploading it again.")
    else:
        media = MediaFileUpload(args.file, mimetype="application/zip", resumable=True, chunksize=8 * 1024 * 1024)
        created = drive.files().create(
            body={"name": name, "parents": [args.folder]}, media_body=media, fields="id, size", **ALL_DRIVES,
        ).execute()
        print(f"Uploaded {name} ({int(created.get('size', 0)) // 1024} KB).")

    # Names carry the date (Y-m-d-H-i-s), so name order = date order. Keep the newest N.
    backups = sorted(list_backups(drive, args.folder, args.prefix), key=lambda f: f["name"], reverse=True)
    for old in backups[args.keep:]:
        drive.files().delete(fileId=old["id"], **ALL_DRIVES).execute()
        print(f"Removed old copy {old['name']}.")
    print(f"Drive folder now holds {min(len(backups), args.keep)} backup(s).")


if __name__ == "__main__":
    main()
