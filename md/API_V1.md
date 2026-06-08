# CBT API v1

Base URL: `/api/v1`. Authentication uses `Authorization: Bearer <jwt>` with an 8-hour lifetime.

## Response Wrapper
Success: `{"success":true,"data":{},"message":null,"error":null}`.
Error: `{"success":false,"data":null,"message":null,"error":"...","code":422,"details":{}}`.

## Main Groups
- Auth: `POST /auth/login`, `GET /auth/me`, `POST /auth/logout`.
- Student exam: `GET /jadwal`, `POST /ujian/{jadwal_hash}/masuk`, then question, answer, batch sync, ping, event, and submit under `/ujian/sesi/{session_hash}`.
- Monitoring: `GET /monitoring/sesi-aktif`, `GET /monitoring/live-score`.
- Master data: `/admin/master/{jurusan|rombel|kelas|mapel}`, `/admin/kelas`, `/admin/users`, `/admin/siswa` with create/update/delete operations.
- SuperAdmin RBAC: `GET /admin/roles`, `PUT /admin/roles/{id}/permissions`, and `PUT /admin/users/{id}`.
- Question packages: `/guru/paket-soal`, preview, validated ready transition, question create/update/delete, and Excel bulk upload.
- Grading: `/grading/isian` and `/grading/isian/{id}`.
- Reports: `/laporan/{jadwal}` and `/laporan/{jadwal}/{json|xlsx|pdf}`.

## Answer Save And Offline Sync
Online save returns a server timestamp:
```json
{"success":true,"data":{"server_updated_at":"2026-06-01T14:00:49.208951Z"}}
```
Batch sync payload:
```json
{"answers":[{"soal_hash":"...","opsi_hash":"...","client_updated_at":"2026-06-01T12:00:00Z"}]}
```
The server receipt timestamp decides conflicts deterministically. `client_updated_at` is retained only as audit metadata. Student-facing schedule, session, question, and option identifiers are Hashids. Answer keys are never included in student responses.