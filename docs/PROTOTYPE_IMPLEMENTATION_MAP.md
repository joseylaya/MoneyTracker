# Prototype Implementation Map

Every meaningful file in `../../prototype` was reviewed on 2026-08-13. The design variants are consolidated into the canonical product routes below. Tracker-wide conversation was explicitly approved on 2026-08-13 and is implemented as a private, real-time tracker feature.

| Prototype file | Purpose / state | Canonical route or component | Status |
|---|---|---|---|
| `welcome_screen/screen.png` | Onboarding welcome | `/` (`Pages/Welcome`) | IMPLEMENTED |
| `financial_focused_dashboard/screen.png` | Tracker summary dashboard | `/home`, `/trackers` | IMPLEMENTED |
| `tracker_list_dashboard/screen.png` | Tracker list and floating CTA | `/trackers` | IMPLEMENTED |
| `social_tracker_dashboard/screen.png` | Social tracker card variant | `/trackers` tracker cards | STATE_OF_EXISTING_PAGE |
| `tracker_detail/screen.png` | Tracker transactions / balances | `/trackers/{tracker}` | IMPLEMENTED |
| `financial_focused_tracker_detail/screen.png` | Financial tracker detail variant | `/trackers/{tracker}` | STATE_OF_EXISTING_PAGE |
| `chat_based_tracker_detail_1/screen.png` | Conversation styling / message entry | `/trackers/{tracker}/conversation` | IMPLEMENTED |
| `chat_based_tracker_detail_2/screen.png` | Conversation styling / message states | `/trackers/{tracker}/conversation` | IMPLEMENTED |
| `collaborative_feed_view/screen.png` | Tracker timeline | `/activity` | STATE_OF_EXISTING_PAGE |
| `add_transaction/screen.png` | Add expense details | `/trackers/{tracker}/expenses/create` | IMPLEMENTED |
| `split_expense/screen.png` | Equal split participant confirmation | `/trackers/{tracker}/expenses/create` step two | IMPLEMENTED |
| `members_permissions/screen.png` | Members and invitations | `/trackers/{tracker}/members` | IMPLEMENTED |
| `group_members_roles/screen.png` | Member roles / invitation empty state | `/trackers/{tracker}/members` | STATE_OF_EXISTING_PAGE |
| `enhanced_members_permissions/screen.png` | Detailed role-management variant | `/trackers/{tracker}/members` | STATE_OF_EXISTING_PAGE |
| `activity_feed/screen.png` | Global activity feed | `/activity` | IMPLEMENTED |
| `social_activity_feed/screen.png` | Rich activity cards variant | `/activity` | STATE_OF_EXISTING_PAGE |

`.DS_Store` files are excluded as non-design metadata. Each `code.html` is the HTML companion of its screen image, not a separate page.
