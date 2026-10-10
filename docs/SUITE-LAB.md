# Isolated IPS verification lab

T-048 needs an authorised isolated IPS 4.7.x installation. Source files and database/network doubles are insufficient. Discovery on 2026-10-10 found no running local IPS/MariaDB forum stack; the adjacent Dogomania compose stack runs its newer backend/frontend, PostgreSQL and Redis.

## Requirements

- A legitimately licensed IPS test installation and matching developer resources, with a separate database and writable datastore/uploads/theme directories.
- Dedicated test hostname, accounts and ACP access; do not reuse production sessions, API credentials or real member/message data.
- Compatible PHP/MariaDB and an isolated API fixture or test key. Disable real email, webhooks, indexing and scheduled external integrations.
- Developer Mode enabled before IPS initialization, ACP native TAR export and restorable clean snapshots.

Never run uninstall, deliberate API failures or synthetic accounts/posts on the live community for this task. Available Dogomania source does not by itself establish an isolated test installation.

## Outstanding checks

1. Fresh developer-bundle install and native TAR export from ACP.
2. Fresh TAR install on a clean production-mode test forum: version, hooks, language/templates, settings/schema and unique task registration.
3. ACP upgrade from an earlier version with custom settings and logs; verify preservation and a zero-query database checker.
4. Real safe/blocked post creation, including first-topic visibility, and registration allow/block/moderate/warn effects.
5. Private-message submission bypasses scanning, as intended since 1.0.2.
6. Timeout, invalid key, quota/rate-limit and malformed API replies fail open without preventing content creation.
7. Dashboard/settings/widget rendering and circuit-breaker behavior across PHP workers.
8. Test-member deletion, ACP uninstall and CLI uninstall on separate snapshots; verify owned-data removal and unrelated-data/task preservation.

Record exact versions, source commit, package SHA256 and expected/actual results in [SMOKE.md](SMOKE.md). T-048 stays open until its acceptance criteria have supporting evidence.
