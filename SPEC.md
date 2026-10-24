# Family Tree — Project Specification

**Status:** APPROVED 2026-10-24. Written from the three-round interview; revised the same day for the chosen visual direction, edit-on-tree, and the Dark/Light labels.
**Owner:** Jade (admin, developer). **Editor:** Jade's aunt. **Viewers:** relatives on one side of the family, mostly on phones.

---

## 1. Purpose

A private, interactive family tree for a family reunion (tentatively April 2027). WordPress stores the people and their relationships; a separate Next.js app draws the tree. Relatives open one link from the family Facebook chat, type a shared password, and explore the tree by panning, zooming, and tapping.

Secondary goals, equally real:

1. Learn React, Next.js, and headless WordPress by building something that ships.
2. Learn a professional Git and GitHub workflow.
3. Produce a public repository that reads well to a stranger, as portfolio material for an Automattic Experienced Software Engineer application.

## 2. Decisions from the interview

| Topic | Decision |
|---|---|
| Root | One root couple (the great-grandparents); eight children; tree descends from there |
| Scale | Roughly 200 named people plus unnamed ones, 5 to 6 generations so far; expect 300 to 400 as pages are collected |
| Identity | Nicknames are the primary way people are known; given names are often unknown |
| Names | Filipino convention: given name, surname at birth, married surname |
| Unknowns | Unknown names, unknown partners, unnamed children with known counts are all normal |
| Order | Birth order is recorded explicitly; dates are mostly unknown |
| Places | One optional Lungsod (town) per person; optional occupation |
| Status | Never married (Dalaga, Ulitawo) is recorded; deceased shown with a small cross |
| Couples | Same-sex couples supported by design, with no special emphasis |
| Graph | Cousins' families intermarried; a person is drawn once, partners from other branches show as a jump chip |
| Privacy | Shared viewer password, rotatable; noindex everywhere; birth dates never leave WordPress |
| Editing | Jade is admin; the aunt is the single editor, via a tailored React screen inside wp-admin |
| Photos | One portrait per person, uploaded by the editor only; initials when there is no photo |
| Hosting | WordPress on Jade's Hostinger account on a subdomain; Next.js on Vercel free tier |
| Repo | Public GitHub monorepo; family data never committed |
| Viewer UX | Opens on the root couple and their eight children; expand one generation per tap; search jumps anywhere |
| Look | Warm, paper-like, modern and smooth; large readable text; colour ring per branch; dark mode toggle |
| Language | English and Cebuano labels, Cebuano preferred where natural; glossary to be confirmed (§7.5) |
| Later | "How are we related" path and printable poster wait until after launch |
| Pace | About 2 hours a day; shareable link with complete data targeted for February 2027 |

## 3. Data model

Two record types, modelled the way genealogy software has done it for decades (the GEDCOM standard calls them INDI and FAM): a **Person** and a **Family**. A Family is a unit of up to two partners plus an ordered list of children. Every relationship question (parents, half-siblings, remarriage, adoption, single parent) is answered by Family records, never by fields on the Person.

### 3.1 Person (`ft_person` custom post type)

| Field | Type | Notes |
|---|---|---|
| `display_name` | post title | Shown on the tree. Defaults to nickname, else given name + surname; editable |
| `given_name` | text, optional | |
| `nickname` | text, optional | e.g. Bebot. Doubled forms like Jun-jun are written in full |
| `surname_birth` | text, optional | Maiden surname for married women |
| `surname_married` | text, optional | |
| `name_unknown` | boolean | Placeholder person, e.g. one of a couple's nine unnamed children |
| `sex` | unknown / female / male | Optional; used for nothing visual |
| `is_living` | boolean, default true | False shows a small cross on the tree |
| `never_married` | boolean | Dalaga / Ulitawo |
| `birth_date` | partial date, private | `YYYY`, `YYYY-MM`, `YYYY-MM-DD`, or empty; plus `birth_date_approx` boolean for "about" |
| `death_date` | partial date, private | Same format |
| `lungsod` | text, optional | Town; free text with suggestions from existing values |
| `occupation` | text, optional | |
| `notes` | long text, private | Corrections, spelling variants, sources |
| photo | featured image | One portrait |

Private fields are stored in WordPress and used by the editor, but never included in the data sent to the tree.

### 3.2 Family (`ft_family` custom post type)

| Field | Type | Notes |
|---|---|---|
| `partners` | 0 to 2 person IDs | One partner when the other parent is unknown; zero is invalid |
| `union_type` | married / partnered / unknown / none | `none` means a single parent with no recorded union |
| `status` | together / separated / divorced / widowed / unknown | |
| `start_date`, `end_date` | partial dates, private | |
| `sequence` | integer | Orders a person's families when they had more than one (first marriage, second marriage) |
| `children` | ordered list of `{ person_id, relation }` | `relation`: biological / adopted / step / foster / unknown |

### 3.3 Rules

- A person may be a partner in any number of Families (remarriage) and a child in one or more (adoption keeps both birth and adoptive families). The tree draws a person under the first Family that lists them; other links appear on the person's card.
- Half-siblings fall out automatically: same parent, different Family.
- A partner with unknown name but known surname (e.g. "Santos") is a placeholder Person with `name_unknown` false and only a surname. A completely unknown second parent is simply a one-partner Family.
- Unnamed children are placeholder People with `name_unknown` true, so the count is visible on the tree.
- No cycles: a person cannot be their own ancestor. The editor validates this on save.

### 3.4 Derived data (computed by the API, never typed in)

- **Branch**: which of the eight root children a person descends from. People who married in get their partner's branch. People from two branches (cousin marriages) keep their birth branch. Used for the colour ring.
- **Descendant count** per Family, for the "6 anak" badges on collapsed nodes.
- **Generation** number from the root couple.

### 3.5 Examples from the paper notes

- "Berto Lim / Nena Cruz Dela Rosa" (invented names): Person A (nickname Berto, surname Lim), Person B (given Nena, surname at birth Dela Rosa, married surname Lim), one Family, married, two children in a fixed birth order.
- A person with a partner and five tick marks for children: the person, a Family with one known partner, and five placeholder children with `name_unknown` true.
- A man marked "Ulitawo" in the notes: Person with `never_married` true and no Family as partner.
- A name crossed out and rewritten on paper: the correction goes in `given_name`; the old spelling goes in `notes`.

## 4. Privacy and security

Layers, from the outside in:

1. **Not indexed.** The Next.js app sends `noindex, nofollow` on every page and a robots.txt that disallows everything. The WordPress site has search-engine visibility discouraged, an `X-Robots-Tag: noindex` header, no public theme output, author archives and sitemaps disabled, and XML-RPC off.
2. **Viewer password.** A single shared passphrase, stored only as an environment variable on Vercel. The login page posts it to a route handler, which compares in constant time and sets a signed, httpOnly cookie valid for 30 days. Next.js middleware checks the cookie on every request; without it you see only the login page. Changing the passphrase or the cookie secret logs everyone out, which is how a leaked link is cut off.
3. **Browser never talks to WordPress.** The Next.js server fetches the tree from WordPress with a secret header read from an environment variable. WordPress refuses the tree endpoint without it. Nothing in the browser can reach the data directly.
4. **WordPress REST locked down.** Person and Family post types are not exposed through the default `/wp/v2` routes. All plugin endpoints have permission callbacks: the tree endpoint needs the secret header; editor endpoints need a logged-in user with the `edit_family_tree` capability and a valid nonce. The public users endpoint is closed.
5. **Private fields never leave WordPress.** Birth and death dates, notes, and union dates are excluded from the tree payload at the API layer, not hidden in the UI.
6. **Secrets live in environment variables**, never in the repo: viewer passphrase, cookie secret, WordPress API key, revalidation secret. The repo contains `.env.example` files with blank values.
7. **Accepted risk, documented:** photos are served from the WordPress uploads folder with unguessable filenames but no password. If the family wants photo URLs protected too, that is a backlog item (proxy through Next.js with signed URLs).
8. **Editor security:** the aunt's account has a custom "Family Editor" role with only the capabilities the editor screen needs. Two-factor authentication is recommended for both WordPress accounts.

## 5. Stack recommendation

### 5.1 Recommended

| Layer | Choice |
|---|---|
| Backend | WordPress on Hostinger, one custom plugin, no ACF, no WPGraphQL |
| Data | Two custom post types with meta registered in code (`register_post_meta`), WP-CLI import command |
| API | Custom REST namespace `family-tree/v1`: `GET /tree` (whole graph, one request), CRUD endpoints for the editor, revalidation hook |
| Editor | React app inside wp-admin, built with `@wordpress/scripts`, using `@wordpress/components` and `@wordpress/api-fetch`; the media library for photos |
| Frontend | Next.js (App Router) with TypeScript, deployed on Vercel |
| Tree drawing | SVG rendered by React; layout computed by our own TypeScript module over Family units (tidy-tree style), pan and zoom with `d3-zoom` (pinch supported) |
| Styling | CSS Modules plus CSS custom properties for theming and dark mode; no Tailwind |
| Tests | PHP: PHPCS with WordPress Coding Standards, PHPUnit with the WordPress test suite in CI. Web: ESLint, TypeScript, Vitest for the layout module; one Playwright smoke test later |
| CI/CD | GitHub Actions: lint and test on every pull request; deploy the plugin to Hostinger over SSH on merge to `main`; Vercel deploys the web app on merge |

### 5.2 Why, and what was considered

**WordPress REST instead of WPGraphQL.** The tree needs the entire graph in one request to lay it out and let you pan around it. One custom REST endpoint returns exactly that shape from one query, with no pagination limit, no extra plugin, and no second query language to learn. GraphQL earns its keep when many different clients ask for many different shapes of the same data. We have one client and one shape. You will still learn the GraphQL trade-off, because it goes in LEARNING.md as a judgment call.

**No ACF.** ACF's value is ready-made admin forms. The aunt will never see a stock admin form, and the meta fields must be exposed to REST and edited by our own React screen anyway. ACF would be a second source of truth for the field definitions. Registering meta in code with sanitisers and a REST schema is more work up front and much easier to reason about. Trade-off: during Phase 2 you have no form to edit one person; corrections go in the import file and are re-imported until the editor exists in Phase 4.

**Editor inside wp-admin, not inside Next.js.** The aunt already logs in to WordPress, so authentication, roles, nonces, and photo uploads come free. The public app stays strictly read-only, which is safer. Cross-origin authenticated writes from a Next.js app on Vercel to WordPress on Hostinger are the single most painful part of headless WordPress for a first project, and this choice removes them entirely. And a React app mounted on a WordPress admin page with `@wordpress/scripts` is exactly how Gutenberg is built, which matters for the Automattic goal.

**Next.js instead of a plain React single-page app.** Honest version: for the viewer alone, a static Vite app would be simpler and could even be hosted on Hostinger as plain files. Next.js wins for three reasons. The password gate and the WordPress secret must live on a server, and Next.js middleware and route handlers give us that server without running one. The browser then never talks to WordPress, which simplifies security and removes CORS. And your learning list named rendering modes, routing, data fetching, environment variables, and deployment, all of which Next.js teaches directly and all of which appear in job postings.

**Our own layout versus a genealogy library: decided by a spike.** Jade liked the look and the in-place editing of the family-chart library (D3, MIT-licensed), which makes adopting it a serious option, not just inspiration. Its data model is Person-centred (each person lists parents, spouses, and children) rather than our Family-unit model, and our design needs collapsed-by-default families, placeholder children, several marriages per person, and cross-branch chips. Step 3b.0 is a half-day spike: load a 30-person invented sample into family-chart and test those four requirements. If it handles them, we adopt it and put our work into the data adapter, card styling, and editor; if not, we build the layout ourselves, which is the single best piece of learning in the project. Either way the decision and its reasons go into LEARNING.md as an adopt-versus-build entry. Topola is rejected on look.

**CSS Modules instead of Tailwind.** You are strong in CSS. CSS Modules keep that skill front and centre, scope styles per component, and make dark mode a matter of CSS variables. Tailwind is common in Next.js projects and you will meet it; it is not needed here.

**Vercel for the frontend.** Hostinger's shared WordPress plans run PHP, not Node. Vercel's free tier covers a private family site, deploys from GitHub on merge, and handles environment variables and custom subdomains. Moving later is possible because nothing Vercel-specific is used.

## 6. Architecture

```
 Relative's phone                      Vercel                               Hostinger
 ───────────────                       ──────                               ─────────
 browser ── HTTPS ──▶ Next.js app  ── HTTPS + secret header ──▶ WordPress + family-tree plugin
            cookie     middleware                                 GET /wp-json/family-tree/v1/tree
            (signed)   login page                                 (people, families, branches)
                       tree page ◀── cached tree JSON ───────────
                       revalidate ◀── webhook on save ───────────

 Aunt's laptop or phone
 ───────────────────────
 browser ── WordPress login ──▶ wp-admin "Family Tree" page (React) ── nonce + cookie ──▶ editor endpoints
```

- The tree JSON is cached on the Next.js side and refreshed when WordPress calls the revalidation hook after a save, or at most every few minutes.
- The WordPress site serves no public pages; its front page redirects to the tree app.

## 7. UI and UX

### 7.1 Viewer

- **Opening view:** The root couple at the top, their eight children below, each child couple with a badge like "6 anak". The view is centred and zoomed so the eight branches fit the phone width.
- **Expand:** tapping a collapsed family expands one generation. Tapping again collapses it. Expanding keeps the tapped family in view.
- **Pan and zoom:** one-finger drag, pinch to zoom, double-tap to zoom in. Mouse wheel and drag on desktop. A "home" button returns to the opening view.
- **Nodes:** circle photo, or initials on a soft background when there is no photo; a thin colour ring for the branch; display name below in large type; a small cross after the name for deceased relatives.
- **Couples:** drawn side by side with a short connector. A partner from another branch shows a small chip "Sanga ni Maria" that jumps to their own place in the tree. A person with two partners shows both, children under each.
- **Person card:** tapping a person slides up a card with photo, full name, nickname, Lungsod, occupation, partners, parents, children, and "also child of" links. Every name on the card is tappable. No dates anywhere.
- **Search:** a search field at the top, matching nickname, given name, and surnames; choosing a result expands the path and centres on the person.
- **Legend:** a small branch legend, collapsible.
- **Dark mode:** follows the phone setting, with a manual toggle remembered on the device.
- **Accessibility and older relatives:** minimum 17px body text, large tap targets (44px), high contrast in both themes, reduced-motion respected, no gesture that has no button equivalent.
- **Performance:** the whole tree is one JSON payload, loaded once; expand and collapse are instant; the SVG only renders visible nodes.

### 7.2 Editor (wp-admin)

- After login the aunt lands on one page, "Family Tree". The rest of the admin menu is hidden for her role.
- **Edit on the tree (primary):** the same tree component as the viewer, in edit mode. Tapping a person opens a side panel (a bottom sheet on phones) with their form. "Add" buttons in the panel create a child, partner, or parent already linked to the right Family, so she never has to think about Family records.
- **People list (secondary):** searchable, sortable, with a photo thumbnail and branch, for finding someone fast and for adding people not yet connected to anyone.
- **Person form:** the fields from §3.1 with helpful labels; photo picker using the media library; save and "view on tree" link.
- **Relationships:** in the panel, "Partners" and "Children" sections: pick a person by search, set union type and status, drag to reorder children, set relation type per child.
- **Shared code:** the tree component lives in a shared package used by both the Next.js viewer (read-only) and the wp-admin editor (edit mode), so the two can never drift apart.
- **Safety:** validation messages in plain language; no cycles; delete asks for confirmation and refuses if the person has children or partners.
- **Sync:** every save pings the Next.js revalidation hook so the tree updates within a minute.

### 7.3 Visual direction

Chosen 2026-10-24 from three references:

- **Familymeet family-tree concept (Dribbble):** the overall feel. Clean white rounded cards, circular portraits with small round action badges, soft background gradient, a profile sheet with a large circular portrait. Not the green: ours is a cooler, darker palette.
- **family-chart "card styling" example:** the presentation. Circle portraits with the name beneath, and the staggered, animated way each node appears as the tree loads. The animation is a requirement, within the reduced-motion rule below.
- **family-chart "edit tree" example:** the editing model. Tap a person on the tree and a side panel opens with their form; add a relative from the same panel. See §7.2.

Rejected: every other family-chart example, and the Topola viewer's plain boxes.

Fixed points: cool, darker palette (deep blue-slate and teal accents, to be finalised in Phase 3c with real contrast checks) instead of Familymeet's green; a light theme that is soft rather than stark white; a calm dark theme; circle portraits; a soft colour ring per branch; rounded cards; smooth short animations that switch off for people who ask for reduced motion; one friendly readable typeface.

### 7.4 Branch colours

Eight soft hues, one per root child, each with a light-mode and dark-mode value, all meeting contrast requirements for the ring and the legend. Chosen in Phase 3c.

### 7.5 Language glossary (Cebuano first, to confirm with Jade)

| Where | Proposed Cebuano | English | Confirm? |
|---|---|---|---|
| Children section, badges | Mga Anak / "6 anak" | Children | from the notes |
| Parents section | Ginikanan | Parents | yes |
| Partner(s) section | Kapikas | Partner / spouse | yes, neutral term chosen on purpose |
| Siblings | Mga Igsoon | Siblings | yes |
| Nickname | Angga | Nickname | yes |
| Name | Ngalan | Name | yes |
| Town | Lungsod | Town | from the interview |
| Occupation | Trabaho | Occupation | yes |
| Search placeholder | Pangitaa ang ngalan | Search a name | yes |
| Deceased, on the card | Mitaliwan na | Passed away | yes, formality? |
| Never married | Dalaga / Ulitawo | Never married | from the notes |
| Branch chip | Sanga ni Maria | Maria's branch | yes |
| Close / Back | Sirado / Balik | Close / Back | yes |
| Theme toggle | Dark / Light (English, by Jade's choice) | Dark / Light | confirmed |
| Home button | Balik sa sinugdanan | Back to the start | yes |

## 8. Repository and Git workflow

### 8.1 Layout

```
family-tree/                      ← this folder becomes the Git repo
├── README.md                     ← what it is, how to run it, screenshots (no family data)
├── SPEC.md                       ← this file
├── LEARNING.md                   ← concepts, Git, model/effort log
├── .gitignore                    ← notes/, .env*, node_modules/, build output
├── .github/workflows/            ← CI and deploy
├── notes/                        ← paper-note photos and transcriptions; NEVER committed
├── wp-plugin/
│   └── family-tree/              ← the WordPress plugin, symlinked into the Local site
│       ├── family-tree.php
│       ├── includes/             ← PHP: post types, meta, REST, import, roles
│       ├── admin/                ← React editor source, built with @wordpress/scripts
│       ├── tests/                ← PHPUnit
│       ├── composer.json         ← PHPCS + WordPress Coding Standards
│       └── package.json          ← wp-scripts
├── packages/
│   └── tree/                     ← shared React tree component (viewer and editor both import it)
└── web/                          ← the Next.js app (Vercel root directory)
    ├── app/                      ← routes: login, tree
    ├── components/
    ├── lib/                      ← layout module, data fetching, auth helpers
    ├── .env.example
    └── package.json
```

The Local by Flywheel site lives outside the repo at `~/Local Sites/family-tree-wp`. The plugin folder is symlinked into its `wp-content/plugins`, so WordPress core, uploads, and the database never enter Git.

### 8.2 Rules

- `main` is always runnable and is protected: no direct pushes, merge through pull requests only, CI must pass.
- One branch per step of the build plan: `feat/person-post-type`, `feat/tree-endpoint`, `fix/…`, `docs/…`, `chore/…`.
- Commit when a small thing works and you can describe it in one sentence. Message format: a short imperative title, a blank line, then why. Several commits an hour is normal.
- Push at the end of every working session and before anything risky.
- One pull request per step, even solo: description says what changed, why, how it was tested, and any trade-off. Read your own diff before merging. Squash-merge, delete the branch.
- Tag the end of each phase: `v0.1-foundations`, `v0.2-data`, and so on.
- Family data never enters the repo: no photos of notes, no transcriptions, no database dumps, no real names in fixtures. Test fixtures use invented names.

## 9. Build plan

Each step ends at a checkpoint you run yourself. Steps are sized for one sitting of about two hours. Model and effort recommendations are given per phase; "stay" means no switch.

### Phase 1 — Foundations (about 1 week)

| Step | What | Checkpoint |
|---|---|---|
| 1.1 | Initialise Git, set `main` as default branch, `.gitignore`, README, first commit; create the public GitHub repo with `gh`; push; protect `main` | Repo visible on GitHub, `notes/` absent from it |
| 1.2 | Create the Local site `family-tree-wp` with the settings in §9.1 | wp-admin loads, you can log in |
| 1.3 | Plugin skeleton: main file, autoloader, activation hook, symlink into the Local site, activate | Plugin listed and active in wp-admin |
| 1.4 | Code quality: Composer, PHPCS with WordPress Coding Standards, GitHub Actions running PHPCS on pull requests; first pull request | First PR merged with green CI |

Model: `/model sonnet`, `/effort medium`. Routine, well-documented work; Sonnet is fast and accurate here.

#### 9.1 Local by Flywheel settings

- Create a new site → "Create a new site" (not from a blueprint).
- Site name: `family-tree-wp`. Local will propose the domain `family-tree-wp.local` and the path `~/Local Sites/family-tree-wp`; keep both.
- Environment: **Preferred**. If it asks, choose PHP 8.2 or newer, nginx, MySQL 8.
- WordPress username, password, email: your choice; local-only, so a simple password is fine. Do not reuse a real password.
- Advanced options → "Is this a WordPress multisite?" → No.
- After it is created: Site Shell is how we run WP-CLI against it; Live Link is how your phone can reach it over the internet for testing.

### Phase 2 — Data model and API (about 2 weeks)

| Step | What | Checkpoint |
|---|---|---|
| 2.1 | Register `ft_person` and `ft_family` with meta, sanitisers, and REST schema; the Family Editor role and capability | Create a person and a family with WP-CLI and read them back |
| 2.2 | `GET /family-tree/v1/tree` with secret header, derived branch, counts, generation; PHPUnit tests in CI | `curl` with the header returns JSON; without it returns 401 |
| 2.3 | JSON import format and `wp family-tree import` command, idempotent on a stable key | Import a 10-person sample twice, get 10 people |
| 2.4 | **You** collect the remaining paper pages from the Facebook chat (branches 4 and 8 are missing; others may continue). **Claude** transcribes all pages to the import JSON. You and your aunt verify. Import. | `/tree` returns the full family; counts match the notes |

Model: `/model sonnet`, `/effort medium` for 2.1 to 2.3. For 2.4 transcription switch to `/model fable` and `/effort high`: reading handwriting accurately is the whole job, and a mistake here is expensive to find later. Switch back after.

### Phase 3 — Viewer (about 5 to 6 weeks)

**3a. Scaffold, data, gate (about 1 week)**

| Step | What | Checkpoint |
|---|---|---|
| 3a.1 | `create-next-app` in `web/` with TypeScript and App Router; ESLint; `.env.example`; explain the folder layout | `npm run dev` shows the starter page |
| 3a.2 | Server-only data module that fetches `/tree` with the secret header; a plain page listing names | Names from Local appear in the browser |
| 3a.3 | Login page, route handler, signed cookie, middleware gate, noindex | Wrong password refused; right password shows the list; phone over Live Link works |

Model: `/model sonnet`, `/effort medium`.

**3b. Layout and drawing (about 2 to 3 weeks)**

| Step | What | Checkpoint |
|---|---|---|
| 3b.0 | Spike: test family-chart against our four hard requirements with an invented 30-person sample; decide adopt or build; write it up | A written decision with the evidence |
| 3b.1 | Layout module (only if we build): Family-unit tree, widths bottom-up, coordinates for people, couples, and connectors; Vitest tests with invented data | Tests pass; a console dump shows sensible coordinates |
| 3b.2 | SVG rendering of nodes and connectors; branch rings; initials fallback; deceased cross | Static tree of the root family renders |
| 3b.3 | Pan and zoom with `d3-zoom`; pinch on phone; home button | Smooth on your phone |
| 3b.4 | Collapse and expand with counts; keep the tapped family in view | Opening view matches §7.1 |

Model: start with `/model opusplan` and `/effort high` for 3b.1 so Opus designs the algorithm and Sonnet writes the routine parts. If the layout fights back, `/model opus` with `/effort high` for that step only. `/model sonnet`, `/effort medium` for 3b.2 to 3b.4.

**3c. Interaction and polish (about 2 weeks)**

| Step | What | Checkpoint |
|---|---|---|
| 3c.1 | Person card (bottom sheet) with tappable relatives | Tap through three generations without the tree |
| 3c.2 | Cross-branch partner chips; "also child of" links | A cousin-marriage example works both ways |
| 3c.3 | Search with path expansion | Find "Bebot" and pick the right one |
| 3c.4 | Branch colours, legend, dark mode, Cebuano labels, typography | Side-by-side light and dark on a phone |
| 3c.5 | Accessibility and loading/error states; performance check on an older phone | Your aunt tries it on her phone |

Model: `/model sonnet`, `/effort medium`.

### Phase 4 — Editor in wp-admin (about 3 to 4 weeks)

| Step | What | Checkpoint |
|---|---|---|
| 4.1 | `@wordpress/scripts` build in the plugin; admin page; menu trimmed for the editor role; login redirect | Aunt's test account lands on an empty "Family Tree" page |
| 4.2 | People list with search and thumbnails | List shows imported people |
| 4.3 | Person form with media-library photo picker | Edit a person, photo appears on the tree after revalidation |
| 4.4 | Partners and children: create and edit Families, reorder, relation types, cycle check | Add a newborn to the right family from a phone |
| 4.5 | Edit on the tree: mount the shared tree component in edit mode with the side panel; add child, partner, parent from the panel | Your aunt adds a relative without ever opening the people list |
| 4.6 | Revalidation hook from WordPress to Next.js | Change appears on the tree within a minute |

Model: `/model opusplan` with `/effort high` for 4.1 to design the screen structure, then `/model sonnet`, `/effort medium`.

### Phase 5 — Deployment (about 1 to 2 weeks)

| Step | What | Checkpoint |
|---|---|---|
| 5.1 | Hostinger: subdomain WordPress install, PHP version matched to Local, SSH access confirmed, secrets in `wp-config.php`, hardening checklist (§4), plugin deploy from GitHub Actions over SSH on merge to `main`, data import | `/tree` works on the subdomain with the header |
| 5.2 | Vercel: project with `web/` as root, environment variables, subdomain, deploy on merge | Tree works on the public URL behind the password |
| 5.3 | Security review and phone smoke tests; set the real passphrase; confirm Google cannot see it | Checklist signed off |

Model: `/model sonnet`, `/effort medium` for 5.1 and 5.2. `/model fable`, `/effort high` for the 5.3 security review: this is the last look before relatives' data goes live.

### Phase 6 — Launch and care

Share the link and passphrase in the Facebook chat. Corrections go to the aunt, who edits. Rotate the passphrase if it leaks. Then the backlog.

Model: `/model haiku` for quick questions and tiny edits; `/model sonnet` for fixes.

### 9.2 Timeline (start early November 2026)

| Phase | Target |
|---|---|
| 1 Foundations | first week of November |
| 2 Data and API | mid to late November |
| 3 Viewer | December to mid January |
| 4 Editor | late January to mid February |
| 5 Deployment | late February |
| Buffer | March |
| Reunion | April 2027 |

## 10. Backlog (after launch)

- "How are we related" path between two people.
- Printable poster export for the reunion hall.
- "Suggest a correction" button that opens a prefilled message to the aunt.
- Photo URLs behind the password (signed URLs through Next.js).
- Correction log visible to the editor.
- A small Gutenberg block that embeds a mini-tree on a WordPress page (learning exercise for the Automattic goal).

## 11. Open questions

1. ~~Confirm the Cebuano glossary~~ Confirmed 2026-10-24; Dark and Light stay in English.
2. ~~Pick a visual direction~~ Chosen 2026-10-24, see §7.3.
3. Confirm the Hostinger plan has SSH access (hPanel → Advanced → SSH Access). If not, deployment uses Hostinger's Git deploy or SFTP instead.
4. Subdomain names for the two halves, for example `tree.` for the app and `tree-api.` for WordPress. Decide at Phase 5.

## 12. Environment check (2026-10-24)

| Tool | Found | Note |
|---|---|---|
| Node | v22 via nvm | good |
| npm | 10.9 | good |
| Git | 2.50 | `init.defaultBranch` unset; we will set `main` |
| GitHub CLI | 2.90 | repo creation from the terminal |
| WP-CLI | 2.12 | also available inside Local's Site Shell |
| PHP on PATH | missing | Local bundles PHP; use Site Shell for PHP work, or install PHP with Homebrew for PHPCS |
| Composer | missing | install in Phase 1.4 |
| Docker | missing | PHPUnit runs in CI; local PHPUnit is optional |
| Local | installed | |
