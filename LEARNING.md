# LEARNING.md

A running log of what this project taught, written for a WordPress and PHP developer learning React, Next.js, and a professional Git workflow. Each entry says what the concept is in plain terms, how it compares to the WordPress way, and where it appears in this project. The last section logs every model and effort recommendation and how it turned out, to build judgment over time.

---

## Concepts

### Headless WordPress
**What:** WordPress keeps the data and the admin, and gives it out through an API. A separate app draws the pages.
**WordPress way:** In a theme, PHP templates read the database and print HTML in one process.
**Here:** The whole project. WordPress on Hostinger holds people and families; Next.js on Vercel draws the tree. See SPEC.md §5 and §6.

### The API is a contract
**What:** Once two apps only talk through an API, the shape of that API is a promise both sides depend on. Changing it means changing both.
**WordPress way:** In a theme you can add an ACF field and fix the template in the same breath. Here that is two apps and two deploys.
**Here:** Why planning came before code, and why the data model (SPEC.md §3) was settled from the paper notes before anything was built.

### Family-unit data model
**What:** Genealogy data is two record types: a Person and a Family (up to two partners plus ordered children). Every relationship is a Family record, never a field on a Person. The GEDCOM standard has used this since the 1980s.
**WordPress way:** Two custom post types with meta, instead of one post type with "parent" fields.
**Here:** SPEC.md §3. Half-siblings, remarriage, adoption, and unknown parents all fall out of this one idea.

### Derived data belongs in the API, not in the editor
**What:** Things that can be computed from other data (branch colour, descendant counts, generation number) are computed when the API builds its response, never typed in by a person.
**WordPress way:** Like computing a post's reading time in a filter instead of storing it as a field.
**Here:** SPEC.md §3.4; built in Phase 2.2.

### REST versus GraphQL (a judgment call)
**What:** GraphQL lets each client ask for exactly the shape it needs; REST endpoints return fixed shapes. GraphQL pays off with many clients and many shapes. One client with one shape is a REST endpoint.
**Here:** SPEC.md §5.2. We chose one custom REST endpoint returning the whole tree.

### Where the password lives
**What:** A gate is only real if the check happens on a server the visitor cannot read. A static site cannot keep a secret; a server can.
**WordPress way:** Like the difference between hiding a menu item with CSS and checking `current_user_can()` in PHP.
**Here:** SPEC.md §4. Next.js middleware checks a signed cookie; the WordPress API key never reaches the browser.

---

## Git

Rules of thumb adopted for this project (SPEC.md §8.2). Each gets its exact commands here the first time it is used in Phase 1.

- Commit when a small thing works and you can say what it does in one sentence. The message says why.
- One branch per step; `main` always runs.
- Push at the end of every session and before anything risky.
- One pull request per step, even solo. Describe what, why, how tested, trade-offs. Read your own diff.
- Squash-merge, delete the branch, tag the end of each phase.
- Family data never enters the repo.

### git init, and why `main`
**What:** `git init` turns a folder into a repository by creating a hidden `.git` folder that holds the whole history. Nothing is tracked until you say so. `-b main` names the first branch, because Git's old default (`master`) is now `main` almost everywhere, including GitHub.
**WordPress way:** Like the difference between a theme folder and a theme folder with a backup system that remembers every version of every file.
**Here:** Step 1.1. We used `git init -b main` so no global setting of yours was changed.

### .gitignore is a privacy control, not just tidiness
**What:** A list of patterns Git pretends not to see. Ignored files can never be committed by accident, even with `git add .`.
**WordPress way:** The same job `.gitignore` does for `wp-config.php` and `uploads/` in a site repo, except here the stakes are living relatives' data.
**Here:** Step 1.1. `notes/` (paper-note photos), `.env` files (secrets), database dumps, and `node_modules/` are ignored. Check with `git check-ignore -v notes/paper/1.jpg`.

### The three places a change lives
**What:** Your working folder (files as you edit them), the staging area (what you have selected for the next commit), and the repository (permanent history). `git add` moves a change to staging; `git commit` moves staged changes into history.
**WordPress way:** Staging is like choosing which edits go into this release note while you keep working on others.
**Here:** Step 1.1. We made two commits from one working folder by staging the housekeeping files first and the documents second.

### A commit is one idea
**What:** A commit is a labelled snapshot. A good one does one thing and its message explains why. Small commits are easy to review, to undo, and to find a bug in (`git bisect`).
**Here:** Step 1.1: "Add gitignore and README" and "Add specification and learning log" are separate commits because they are separate ideas.

### Author identity, and when rewriting history is safe
**What:** Every commit stores an author name and email, visible to anyone who can see the repo. GitHub gives each account a private noreply address for public work. History can be rewritten freely while it exists only on your machine; once pushed, rewriting breaks everyone else's copy and is a last resort.
**WordPress way:** Like changing the author on draft posts before publishing, versus after the posts are live and linked.
**Here:** Step 1.1. `git config user.email` without `--global` set the noreply address for this repo only; `git rebase --root -x "git commit --amend --reset-author"` rewrote the two unpushed commits. Note the commit hashes changed: a commit's id is a hash of its full content, author included.

### Composer, and why the lock file is committed
**What:** Composer is the PHP package manager. `composer.json` says what you want; `composer.lock` records the exact versions it installed. Committing the lock means your Mac and the CI server get identical tools.
**WordPress way:** Like the difference between "any recent jQuery" and a pinned file. `package.json` and `package-lock.json` will be the JavaScript twin of this pair.
**Here:** Step 1.4. `vendor/` (the installed packages) is ignored; the two Composer files are committed.

### Linting with the WordPress Coding Standards
**What:** A tool that reads code and reports style and safety problems before a human has to. The WordPress standard checks escaping, naming, and documentation. A check is only trustworthy if it can fail: we proved this one exits 0 on our code and 2 on a deliberately bad file with an unescaped `$_GET`.
**WordPress way:** The same ruleset WordPress core uses on itself. Prefix rules do automatically what you do by habit in theme functions.
**Here:** Step 1.4, `phpcs.xml.dist`. Run it with `composer lint` from `wp-plugin/family-tree`.

### Continuous integration (CI)
**What:** A server runs your checks on every proposed change, so quality does not depend on anyone remembering. GitHub Actions reads workflow files from `.github/workflows/`.
**Here:** Step 1.4, `ci.yml`. It runs on every pull request, with read-only permissions (least privilege), and later becomes a required check so `main` cannot receive failing code.

---

## Model and effort log

| Date | Step | Recommendation | Why | How it went |
|---|---|---|---|---|
| 2026-10-24 | Planning interview (Phase 0) | Stay on Fable, no switch | Data modelling and architecture are where the strongest reasoning earns its cost; the interview itself is cheap either way | Good: the paper notes reshaped the model, and the strongest model caught the cross-branch marriage issue |
| 2026-10-24 | Stack recommendation and SPEC.md | `/effort high` for one turn | The most consequential output of the project; worth extra thinking time | Pending review |
| 2026-10-24 | Phase 1.1 Git and repo setup | `/model sonnet`, `/effort medium` | Routine, well-documented setup work; speed matters more than depth | Pending review |

### How to read this table over time
- If a Sonnet step went smoothly, that kind of step is a Sonnet step from now on.
- If a Sonnet step needed two or three corrections, try Opus or Fable for the next step of that kind and note the difference.
- High effort is for decisions and debugging. Medium is the default. Low is for rote edits where you already know the answer.
