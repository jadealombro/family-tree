# Family Tree

A private, interactive family tree built as a headless WordPress project: WordPress stores the people and relationships, and a separate Next.js app draws the tree.

**Status:** in planning and early setup. See [SPEC.md](SPEC.md) for the full specification and phased build plan, and [LEARNING.md](LEARNING.md) for the running log of concepts and decisions.

## How it fits together

- `wp-plugin/family-tree/`: the WordPress plugin (data model, REST API, editor). *Coming in Phase 1 and 2.*
- `packages/tree/`: the shared React tree component. *Coming in Phase 3.*
- `web/`: the Next.js viewer, behind a shared password. *Coming in Phase 3.*

## Privacy

The deployed site is private and not indexed by search engines. **No family data is stored in this repository.** Test data uses invented names, and the real data lives only in the WordPress database.

## Licence

To be added. GPL-2.0-or-later is planned, to match the WordPress ecosystem.
