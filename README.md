# SplitShare Web

An independent Laravel + React web application for SplitShare, the shared-expense tracker. It is deliberately separate from `../MoneyTracker`, which remains the future Flutter mobile application.

## Stack

- Laravel 13 with Inertia and Sanctum-ready authentication
- React and Vite
- PWA manifest and service worker registration via `vite-plugin-pwa`
- SQLite for local development by default; configure your cloud database in `.env` for deployment

## Local development

```bash
npm run dev
php artisan serve
```

Then open `http://127.0.0.1:8000`. Create an account from the Register link.

## Production build

```bash
npm run build
php artisan config:cache
php artisan route:cache
```

Serve Laravel's `public/` directory through your cloud server with HTTPS. HTTPS is required for the PWA install prompt and offline caching.

## Real-time tracker conversations

Each tracker now has its own Messenger-style conversation, available from the **Conversation** card on its detail page. Messages are private to active tracker members, persist in the database, arrive live through Reverb, and support standard Unicode emoji plus quick emoji buttons. Owners, editors, and commenters can post; viewers can read only. Expense-detail comments remain available as contextual discussion on an individual transaction.

The Conversation card shows an unread-message badge. Opening the thread marks the latest loaded messages as read for that member. The initial thread request is limited to the latest 100 messages and uses indexed tracker/time lookups; the page never loads an unbounded message history.

Local real-time testing requires three terminals:

```bash
php artisan serve
php artisan reverb:start --host=127.0.0.1 --port=8080
php artisan queue:work --tries=1
```

Open the same tracker conversation in two authenticated browser sessions, then post a message in one session. It appears in the other without a refresh. For production, run Reverb and the queue worker under a process manager and proxy the WebSocket host through HTTPS.
