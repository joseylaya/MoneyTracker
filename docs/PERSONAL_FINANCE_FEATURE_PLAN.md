# PERSONAL FINANCE — SHORT FEATURE PLAN

## 1. Goal

Add a **fast, low-effort Personal Finance module** to SplitShare that helps the user:

- avoid running short before the end of the month;
- know how much money is actually safe to spend;
- control lifestyle spending;
- reserve recurring bills before spending;
- build savings and an emergency fund;
- know where money is currently stored across multiple accounts;
- recover easily from forgotten small expenses or transfers.

The core principle is:

> **Do not just tell the user where the money went. Tell the user how much can still be safely spent.**

---

# 2. IMPORTANT SCOPE RULE

> **DO NOT modify, redesign, refactor, remove, rename, or change the behavior of any existing SplitShare/shared-expense feature unless explicitly instructed.**

The Personal Finance module must be added as an **isolated feature**.

Existing features such as:

- Shared Trackers;
- Members;
- Invitations;
- Shared Expenses;
- Splits;
- Settlements;
- Shared Balances;
- Activity/Audit;
- Existing RBAC;

must continue working exactly as they do now.

Only create the minimum integration points required to expose the new Personal Finance feature.

---

# 3. Product Direction

SplitShare will have two financial contexts:

```text
SHARED
Who owes whom?

PERSONAL
How much can I safely spend?
```

Personal Finance must remain clearly separated from shared Tracker balances.

---

# 4. Personal Dashboard

Show the most important numbers first:

```text
Total Liquid Money
Reserved for Bills
Savings Reserved
Emergency Fund Reserved
Lifestyle Remaining
Safe to Spend
```

Also show:

- upcoming bills;
- spending pace;
- account balance confidence;
- warnings if the current spending rate may cause a shortage before payday.

---

# 5. Safe to Spend

The app must calculate:

```text
Safe to Spend
=
Available Personal Money
- Reserved Bills
- Protected Savings
- Protected Emergency Fund
- Other Required Commitments
```

Reserved money must **not** appear as spendable even if it still physically exists in a bank/account.

---

# 6. Income Allocation

When income is added, allow the user to define automatic rules.

Example:

```text
Income: ₱30,000

Lifestyle Limit: 15%
Savings: configurable %
Emergency Fund: configurable %
Recurring Bills: reserved automatically
```

The app should immediately calculate what portion is actually available for flexible spending.

---

# 7. Lifestyle Spending Limit

Support percentage-based lifestyle limits.

Example:

```text
Monthly Income: ₱30,000
Lifestyle Limit: 15%
Lifestyle Budget: ₱4,500
```

Lifestyle may include:

- dining out;
- coffee/drinks;
- entertainment;
- shopping;
- hobbies;
- non-essential purchases.

Warnings:

```text
50% used
75% used
90% used
100% reached
```

Do not block real-world spending, but show:

```text
Lifestyle Safe to Spend: ₱0
```

after the limit is reached.

---

# 8. Recurring Bills / Commitments

Allow recurring commitments with:

```text
Name
Amount
Frequency
Due date(s)
Category
Status
```

Statuses:

```text
Upcoming
Reserved
Paid
Overdue
```

Example commitments:

### Every 15th

```text
Car Parking Rental  ₱2,000
Parents Allowance   ₱3,500
Loan Payment        ₱2,000
Motor Mortgage      ₱2,400

Total: ₱9,900
```

### Every 30th

```text
Parents Allowance   ₱3,000
Motor Mortgage      ₱2,400

Total: ₱5,400
```

Total recurring monthly commitments:

```text
₱15,300
```

These amounts must reduce **Safe to Spend before they are actually paid**.

---

# 9. Personal Accounts / Wealth

Allow the user to register places where money is stored.

Examples:

```text
Maribank
GCash
Metrobank
Security Bank
Cash
```

Each account should show:

```text
Current Balance
Reserved Amount
Available Amount
Last Reconciled
Confidence Status
```

Example:

```text
Maribank
₱18,500
Verified Today

Metrobank
₱12,000
Needs Checking
```

---

# 10. Transfers Between Accounts

Transfers are **not Expenses**.

Example:

```text
Maribank → GCash
₱2,000
```

Effect:

```text
Maribank -₱2,000
GCash    +₱2,000

Total Personal Wealth = unchanged
```

Transfer statuses:

```text
Pending
Completed
Failed
Reversed
```

Pending transfers must not be double-counted as available in both accounts.

---

# 11. Quick Expense Entry

Daily input must be extremely fast.

Default flow:

```text
Amount
Category
Account
Save
```

Automatically default when possible:

```text
Date = Today
Account = Last/Most Used
Category = Smart Suggestion
```

Optional fields belong under:

```text
More Details
```

Examples:

```text
Description
Merchant
Note
Custom Date
Receipt
Tags
```

The user should be able to record a simple Expense in a few seconds.

---

# 12. Forgotten Small Expenses

The app must work even when the user forgets to record small purchases.

Do not require perfect bookkeeping.

Example:

```text
App Expected GCash: ₱3,420
Actual GCash:       ₱3,360
Difference:            ₱60
```

Allow:

```text
Record as Untracked Spending
Review Transactions
Record Missing Transfer
Adjust After Reconciliation
```

This lets the system recover without requiring the user to remember every ₱10 purchase.

---

# 13. Balance Reconciliation

Allow periodic account reconciliation.

Flow:

```text
Select Account
↓
Enter Actual Balance
↓
Compare With Expected Balance
↓
Show Difference
↓
Resolve Difference
```

Possible resolution:

```text
Missing Expense
Missing Transfer
Untracked/Miscellaneous Spending
Manual Reconciliation Adjustment
```

Track:

```text
last_reconciled_at
```

---

# 14. Account Confidence

Show how trustworthy each account balance is.

Example:

```text
Maribank       ✓ Verified today
GCash          ✓ Verified 2 days ago
Metrobank      ⚠ Needs checking
Security Bank  ⚠ Needs checking
Cash           ? Estimated
```

This prevents the application from presenting an old balance as certain.

---

# 15. Savings and Emergency Fund

Support protected money buckets:

```text
Savings
Emergency Fund
```

These can use:

```text
Target Amount
Current Reserved Amount
Optional Income Percentage
```

Money assigned here should be removed from **Safe to Spend**.

---

# 16. Smart Spending Guidance

Provide lightweight insights such as:

```text
You have ₱420/day available until payday.

You have used 82% of your Lifestyle budget.

Dining spending is higher than your normal pace.

At your current spending rate, you may be short by ₱2,300 before payday.

Your next recurring bills require ₱9,900.
```

Insights should help decision-making without overwhelming the user.

---

# 17. Low-Input UX Principle

Personal Finance must be designed around:

> **Track by exception, not every peso.**

User effort should be divided into:

### One-Time Setup

```text
Income
Accounts
Recurring Bills
Lifestyle Limit
Savings Goal
Emergency Fund Goal
```

### Daily Use

```text
Quick Expense
Quick Transfer
Mark Bill Paid
```

### Occasional Maintenance

```text
Reconcile Account Balance
Resolve Differences
```

Avoid repetitive manual input whenever the app already knows the value.

---

# 18. Shared Expense Integration — Later / Optional

A future integration may allow a shared SplitShare Expense to create a Personal Expense entry for:

```text
the current user's own share only
```

Example:

```text
Shared Dinner Total: ₱1,500
Your Share:           ₱500
```

Personal Finance may optionally record:

```text
Dining Expense: ₱500
```

Do **not** count the entire ₱1,500 as the user's Personal Expense.

Do not implement this integration unless explicitly requested.

---

# 19. Initial MVP for Personal Finance

Implement in this order:

```text
1. Personal Dashboard
2. Personal Accounts
3. Income
4. Quick Expenses
5. Lifestyle Limit
6. Recurring Bills / Commitments
7. Safe to Spend
8. Account Transfers
9. Savings / Emergency Fund
10. Reconciliation
11. Account Confidence
12. Smart Warnings / Forecast
```

Keep the first release simple and fast.

---

# 20. Definition of Success

The Personal Finance module is successful when the user can quickly answer:

```text
How much money do I have?

Where is my money?

How much is already committed?

How much can I safely spend?

How much Lifestyle budget remains?

What bills are coming?

How much have I protected for savings?

How much have I protected for emergencies?

Did I forget an Expense or Transfer?

Will I run short before my next income?
```

The system must remain useful even if some small Expenses are forgotten.

---

# 21. FINAL IMPLEMENTATION NOTE

> **Build this feature in isolation. Do not touch existing SplitShare/shared-expense features unless a specific integration is explicitly requested.**

Do not use this Personal Finance work as an opportunity to:

- refactor unrelated existing modules;
- redesign existing pages;
- rename existing domain concepts;
- change shared balance calculations;
- change existing RBAC;
- change existing shared Tracker workflows.

The task is to **add Personal Finance safely without destabilizing the existing application**.

---

# END
