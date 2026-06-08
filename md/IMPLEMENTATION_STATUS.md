# CBT SMKN 1 Blora - Implementation Status

Updated: 2026-06-01

## Completed Application
- Laravel 13 production deployment with responsive Blade HTML UI and parallel JWT API v1. Production Blade pages now use the approved `ui/` mockups as the visual baseline: staff sidebar dashboards, student token dashboard, one-question exam screen, offline indicator, and final result card.
- Final CBT schema, five RBAC roles, school master-data CRUD, staff/student CRUD, attendance, Excel student template/import, and role enforcement. SuperAdmin has Blade and API user management plus a role-permission feature matrix.
- Question package wizard: draft creation, manual PG/ISIAN questions, WebP images, Excel question import template, revision, deletion, preview, and validated finalization.
- Student UX: one question per screen, navigator, flag, server timer, heartbeat, confirmed idempotent submit, consistent random order, and result visibility `instant`, `manual`, `scheduled`.
- Reliable answer path: online debounce autosave, IndexedDB offline queue, reconnect sync, Redis-first buffer, async PostgreSQL persistence, atomic compare-and-delete, mandatory submit flush, and deterministic latest server-received timestamp conflict resolution. Client timestamp is audit metadata only.
- Monitoring dashboard/API: progress, remaining time, online/offline state, last seen, IP address, device user agent, reset session, and standby-backed live score.
- Reports JSON/XLSX/PDF: scheduled class target, students who never entered the exam, attendance, PG score, ISIAN score, total, statistics, and ranking.
- Audit trail: web/API login and logout, failed login, answer revisions, exam login/return, submit, offline, online return, tab switch, IP, and device metadata.
- Student-facing schedule, session, question, and option identifiers use Hashids. Student responses do not expose answer keys.
- API wrapper, 8-hour JWT, role and ownership checks, and login/exam/report rate limits. API notes: `API_V1.md`.

## Completed Infrastructure
- Web node `192.168.16.120`: Nginx, PHP-FPM, queue worker, scheduler, UFW, node exporter, replication check cron, log rotation, health `/up`.
- Primary `192.168.16.121`: PostgreSQL, Redis AOF, UFW, node exporter, PostgreSQL exporter, Redis exporter, WAL archiving, nightly dump backup, dump retention 14 days, WAL retention 2 days.
- Standby `192.168.16.122`: PostgreSQL streaming standby, UFW, node exporter, and PostgreSQL exporter active (`pg_up 1`).
- Backup restore drill passed using a temporary database. Nginx, environment, application, and database backups exist under `/var/backups`.

## Verified
- Laravel automated tests: 24 passed, 102 assertions. Run through `/usr/local/sbin/cbt-test-safe` so PHPUnit uses isolated SQLite instead of cached production configuration.
- Real LAN smoke: login and health HTTP 200, JWT API, report target statistics, monitoring fields, hashed IDs, hidden answer keys, online autosave server timestamp, Redis buffer drain to zero, and audit persistence.
- Streaming replication marker replay: 1 second during final verification.
- Redis and PostgreSQL exporters: `redis_up 1`, `pg_up 1` on applicable nodes.
- Lightweight health load smoke: 100/100 parallel requests returned HTTP 200. Login flood is rejected with HTTP 429.

## Intentionally Deferred
- Public TLS and Cloudflare proxy/WAF until the public domain is supplied.
- Octane activation until staged capacity testing on upgraded hardware. Current node is only 2 CPU, 2 GB RAM, 8 GB disk.
- Full 1500-concurrent-user and real-device browser test campaign until capacity and test clients are available.
- Disaster-recovery exercise that promotes standby to primary should be scheduled in a maintenance window.
- Grafana dashboards/alerts and ELK remain optional observability follow-ups. Exporters are active.