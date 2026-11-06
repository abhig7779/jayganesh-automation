# Where we stopped (6 Nov 2026)

**Done and pushed to GitHub (main):** every module merged (621 tests pass on SQLite); MySQL migration fix + test fixes pushed. The final MySQL CI run was in progress when we paused; check it with `gh run list --workflow "CRM CI" --limit 1`.

**Paused mid-work (uncommitted fixes saved on the SSD, nothing lost):**

| QA area | Folder (git worktree) | Branch |
|---|---|---|
| Counter: POS, customers, stock, quotes/follow-ups | `.claude/worktrees/agent-ae4191e6c533c1fc0` | `worktree-agent-ae4191e6c533c1fc0` |
| Workshop, finance, purchases | `.claude/worktrees/agent-addb75ae12d8e8541` | `worktree-agent-addb75ae12d8e8541` |
| Owner, marketing, admin + shell consistency | `.claude/worktrees/agent-a733891d10a62cd0c` | `worktree-agent-a733891d10a62cd0c` |

**To resume:** for each folder, review `git status`, run the tests there, commit, then merge into main. Then do one full run on SQLite + MySQL CI, and the first MilesWeb deploy (`crm/DEPLOY.md`, needs your SSH key + the DB password).

**Your part:** `crm/MORNING-CHECKLIST.md`.

**Open CI items at pause (test-only, the app itself is unaffected):**
1. SQLite jobs: run `./vendor/bin/pint` in `crm/app` (likely the fully-qualified `\App\Models\Branch` added in `tests/Support/BillingRows.php`; import it instead), then rerun.
2. MySQL job: 36–37 tests still fail with `payments_branch_id_foreign`. The test data in `tests/Support/BillingRows.php` / the finance tests insert payments for a branch id that doesn't exist on MySQL (auto-increment isn't reset between tests). Make every insert use the test's real `$this->branch->id`.
