# SplitShare as an ALAS child system

SplitShare is deployed as an isolated Docker stack on the same server as ALAS.

- It does **not** share ALAS's database, storage, queue worker, or Docker containers.
- It has its own PostgreSQL database, Laravel storage, Reverb process, and queue worker.
- It binds only to `127.0.0.1:8081`; the existing server/ALAS reverse proxy keeps ownership of public ports 80 and 443.
- A SplitShare subdomain must proxy to `http://127.0.0.1:8081`, including WebSocket upgrade headers for `/app/` and `/apps/`.
- The Firebase service account must be placed on the server at `secrets/firebase-service-account.json`. It is ignored by Git and mounted read-only.

Before the first deployment, create `.env` from `.env.production.example`, generate an `APP_KEY`, provide production PostgreSQL credentials, and add the Firebase service-account JSON. The deployment workflow then builds services, starts the isolated stack, migrates PostgreSQL, and caches Laravel configuration.
