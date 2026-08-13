# Product Decisions

## 2026-08-13 — Tracker-wide conversation approved

SplitShare includes one private, persistent conversation for each tracker. It is distinct from expenses, splits, settlements, and activity records, and does not change financial balances.

- Active tracker members can read the conversation.
- Owners, editors, and commenters can send messages; viewers are read-only.
- Messages are delivered in real time over the tracker private channel and remain stored in the application database.
- Standard Unicode emoji are supported, including the quick emoji picker in the message composer.
- Each member has a read marker. The tracker Conversation entry displays their unread count and the conversation initially loads only the latest 100 messages.
- Expense-detail comments remain available for a transaction-specific discussion.
