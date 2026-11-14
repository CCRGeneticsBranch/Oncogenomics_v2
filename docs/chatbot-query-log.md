# Chatbot query logs and feedback

## Installation

Create `chatbot_query_logs` using the script for your database:

- Oracle: `database/sql/create_chatbot_query_logs.oracle.sql`
- MySQL: `database/sql/create_chatbot_query_logs.mysql.sql`

The application needs SELECT, INSERT, and UPDATE permissions. No production DDL
is executed by this change. The MySQL script uses UTC DATETIME values and utf8mb4
for questions, answers, and feedback.

Alternatively, use the isolated migration (do not also run the SQL script):

```sh
php artisan migrate --path=database/migrations/2026_11_12_000001_create_chatbot_query_logs_table.php
```

The default database connection is used. `CHATBOT_LOG_DB_CONNECTION` can select
another configured connection. `CHATBOT_LOG_TIMEZONE` controls report display and
date filters (default `America/New_York`); stored timestamps are UTC. Rebuild the
configuration cache using the application's normal deployment process if these
settings change.

## Behavior

Each authenticated chatbot query creates one UUID record, including streamed,
redirected, failed and interrupted responses. It records the user ID, question,
answer when available, start/completion time, server duration in milliseconds,
scope/conversation, provider/model, unique tool names, and per-LLM-call usage.
Tool arguments and raw tool results are not separately logged. Duration includes
server orchestration and tool execution, not subsequent page navigation.

Provider-reported tokens are aggregated across observed agent and synthesis
calls. `reported` means all observed calls supplied totals; `partial` means some
did; `unavailable` means no totals were supplied. Null is not zero. Rules-only
responses have zero tokens and `not_applicable` usage. Cache/reasoning tokens are
reported separately and are not blindly added to input/output totals. Calls that
fail before returning usage cannot supply a measured token count.

Each saved chatbot response offers an optional 1–5 star rating and an optional
comment (up to 5,000 characters). Users can update or clear either field. Feedback
is stored on the original record, restricted to its submitting user, and survives
conversation-cache expiration. Earlier conversations without log IDs do not show
feedback controls. Logging failure does not prevent answering a query; controls
are omitted when the initial log record cannot be created, and a diagnostic is
written to the application log.

Super-admins (`_superadmin`) can open **Report → Chatbot query log** at
`/viewChatbotLog`. Other users, including project managers, cannot access it.
Search covers question, answer, feedback, provider, model and tool names. Filters
include user name (case-insensitive partial first, last, or full name), rating/unrated, status and local calendar date; sorting supports
newest, duration and token total. Results are paginated at 25 rows. Query and
feedback text is escaped in the report. Feedback POSTs use session authentication,
CSRF protection and ownership checks.

Records have no automatic expiry. An abruptly terminated PHP process may leave a
record in `running`; ordinary stream exceptions are finalized as errors. Feedback
requires a completed/error/interrupted record. Database-unavailable reports show a
setup/error message to admins instead of exposing SQL or bindings.
