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

### Custom post types as a data model, no ACF
**What:** A custom post type is a table of records with built-in storage, permissions, and admin screens. Fields are post meta registered in code with `register_post_meta`, each with a type, a default, and a sanitize callback.
**WordPress way:** ACF generates the same thing from a UI. Code gives you the same result that is reviewable in a pull request, identical on every machine, and testable.
**Here:** Step 2.1. `Post_Types`, `Meta`, and `Sanitize` in `includes/`. People and Families are separate types because every relationship lives on a Family (SPEC.md section 3).

### Sanitize at the door, not in the form
**What:** Validation attached to the field itself means bad data is cleaned whether it arrives by WP-CLI, an import file, the editor, or a future API. A sanitizer is a pure function: a raw value in, a clean value out, which makes it easy to test.
**Here:** `Sanitize` handles partial dates ("1950-02-29" is rejected because 1950 was not a leap year), enumerations, and the ordered children list. We exercised 23 edge cases with a throwaway script before real tests exist in step 2.2.

### Protected meta (the underscore prefix)
**What:** Meta keys that start with `_` are hidden from the Custom Fields box, so nobody can edit them in a way that skips our sanitizers.
**Here:** Every field is stored as `_ft_...`.

### Capabilities read from the post type, not hand-typed
**What:** `capability_type` plus `map_meta_cap` makes WordPress generate permission names such as `edit_ft_people`. `get_post_type_object()->cap` lists them, so the Family Editor role is built from the real names.
**WordPress way:** The same machinery that gives Editors rights over posts and Authors rights over their own. We add one custom role.
**Here:** `Roles::install()`.

### Version-check upgrades instead of activation hooks
**What:** An activation hook runs once, when someone clicks Activate. A stored version compared to the code version runs on the next page load after any deploy, including a GitHub Action that copies new files over an active plugin.
**Here:** `Plugin::maybe_upgrade()` on `init` priority 20, after the post types are registered. We removed the activation hook from step 1.3 for this reason, which is a normal thing to do when a design outgrows an early choice.

### Lint false positives, and when to rename instead of exclude
**What:** PHPCompatibility flagged our method `family_fields` as a removed PHP extension, because of its name prefix. Renaming it was cheaper than adding an exclusion rule. Keep exclusions for when renaming is not possible.

### A REST route, and what `permission_callback` really is
**What:** `register_rest_route` makes a URL under `/wp-json/`. Its `permission_callback` runs before the handler and decides whether the caller may proceed. A route with no real check is public; WordPress now warns if you forget one.
**WordPress way:** The same API your block editor uses. Instead of PHP templates reading the database, a URL returns JSON.
**Here:** Step 2.2, `Rest::register_routes()` and `Rest::check_key()`. The route is `/wp-json/family-tree/v1/tree`.

### A shared secret between two servers
**What:** The Next.js server and WordPress share one long random key. Next.js sends it in a header; WordPress compares. Because only servers hold it, the visitor's browser never sees WordPress at all.
**Here:** `Auth` compares in constant time with `hash_equals`, so timing leaks nothing about how close a guess was, and refuses any key under 32 characters so an empty `wp-config.php` entry never opens the API. The key lives in `wp-config.php` (outside Git) as `FAMILY_TREE_API_KEY`.

### Keep logic out of WordPress so it can be tested fast
**What:** `Tree_Builder` and `Auth` call no WordPress functions. They take arrays in and return arrays out, so 20 tests run in a few milliseconds with no database. The thin WordPress layer (`Tree_Source`, `Rest`) only gathers data and passes it along.
**WordPress way:** Like moving the real work out of a template and into a plain function.
**Here:** Step 2.2. The WordPress-dependent pieces will get integration tests later; for now they are covered by your manual checkpoint.

### A test that cannot fail proves nothing
**What:** After tests pass, break the code on purpose (a "mutation") and check that a test turns red. We removed the new-branch rule and weakened the key check; both were caught. We did the same to the linter in step 1.4.
**Here:** `tests/Tree_Builder_Test.php`, `tests/Auth_Test.php`. Run them with `composer test`.

### Privacy by allow-list
**What:** Safe code lists what it will include instead of listing what to hide. `Tree_Source` reads ten named fields and nothing else, so adding a new private field to the data model can never leak by default. One test pins the exact keys of a person in the payload, so adding a key means changing the test on purpose.
**Here:** SPEC.md section 4, items 5 and 6.

### Shape the API for the client: normalised maps and precomputed facts
**What:** The payload holds people and families as maps keyed by ID, with relationships as ID lists. This is how React apps like to hold graph data: any person is one lookup, and nothing is duplicated. Generation, branch, married-in, and descendant counts are computed once on the server, in one place with tests.
**Here:** `Tree_Builder::build()`. Cousin marriages are resolved by a written rule: a child takes the branch of the first listed partner.

### Dev dependencies and CI steps
**What:** `composer require --dev` adds a tool that is installed for development but not shipped. CI now runs `composer lint` and `composer test` on every pull request.
**Here:** `phpunit/phpunit` in `composer.json`; second step added to `ci.yml`.

---

## Model and effort log

| Date | Step | Recommendation | Why | How it went |
|---|---|---|---|---|
| 2026-10-24 | Planning interview (Phase 0) | Stay on Fable, no switch | Data modelling and architecture are where the strongest reasoning earns its cost; the interview itself is cheap either way | Good: the paper notes reshaped the model, and the strongest model caught the cross-branch marriage issue |
| 2026-10-24 | Stack recommendation and SPEC.md | `/effort high` for one turn | The most consequential output of the project; worth extra thinking time | Pending review |
| 2026-10-24 | Phase 1.1 Git and repo setup | `/model sonnet`, `/effort medium` | Routine, well-documented setup work; speed matters more than depth | Pending review |
| 2026-10-24 | Phase 1.2 to 1.4: Local site, plugin skeleton, lint and CI | stayed on Sonnet, medium | Routine setup | Went well. One real mistake to log: a `PATCH` branch-protection command that cannot create a missing section; caught by reading the rule back. Verify, do not assume. |
| 2026-10-24 | Phase 2.1 data model | stayed on Sonnet, medium | The design is already written in SPEC.md, so this is careful implementation | Pending review |
| 2026-10-24 | Phase 2.2 tree endpoint | stayed on Sonnet, medium | Written spec; the branch-assignment algorithm is the hardest part, so I wrote its edge cases as tests first-class and mutation-tested them instead of upgrading the model | Pending review. Signal to watch: if cousin-marriage or remarriage rules need repeated fixes, that is when to try Opus for this kind of algorithm. |

### How to read this table over time
- If a Sonnet step went smoothly, that kind of step is a Sonnet step from now on.
- If a Sonnet step needed two or three corrections, try Opus or Fable for the next step of that kind and note the difference.
- High effort is for decisions and debugging. Medium is the default. Low is for rote edits where you already know the answer.
