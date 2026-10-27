"""The outbox the website fills: the email_outbox table, created by a Laravel migration in
web/laravel/database/migrations. The mailer's database role may read each row's content and
update only its delivery columns."""

from collections.abc import Iterator
from contextlib import AbstractContextManager, contextmanager
from dataclasses import dataclass
from datetime import datetime, timedelta
from typing import Any

import psycopg
from psycopg.rows import class_row

from mailer import database
from mailer.config import Settings

# Due: not sent yet, not given up on, and not waiting for a retry
_DUE = (
    "sent_at IS NULL AND failed_at IS NULL"
    " AND (next_attempt_at IS NULL OR next_attempt_at <= now())"
)
_HAS_DUE = "SELECT EXISTS (SELECT FROM email_outbox WHERE " + _DUE + ")"
# SKIP LOCKED: a second mailer would take the next email instead of waiting for this one
_NEXT_DUE = (
    "SELECT id, kind, payload, attempts, created_at FROM email_outbox WHERE " + _DUE
    + " ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED"
)
# The payload goes once the email is out: the order keeps the details
_MARK_SENT = "UPDATE email_outbox SET sent_at = now(), payload = NULL, updated_at = now() WHERE id = %s"
_MARK_RETRY = (
    "UPDATE email_outbox SET attempts = attempts + 1, last_error = %s,"
    " next_attempt_at = now() + %s, updated_at = now() WHERE id = %s"
)
# A failed email keeps its payload, so an admin can see what it was and queue it again
_MARK_FAILED = (
    "UPDATE email_outbox SET attempts = attempts + 1, last_error = %s,"
    " failed_at = now(), updated_at = now() WHERE id = %s"
)


@dataclass(frozen=True)
class OutboxEmail:
    id: int
    kind: str
    payload: dict[str, Any]
    attempts: int
    created_at: datetime


class Outbox:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._conn: psycopg.Connection | None = None

    def has_due(self) -> bool:
        with self._errors():
            row = self._connection().execute(_HAS_DUE).fetchone()
            return bool(row and row[0])

    @contextmanager
    def next_due(self) -> Iterator[OutboxEmail | None]:
        """Locks the oldest email that is due (None when there is none) until the block ends.
        What the block records about it is committed together when the block ends, or rolled
        back if the block raises, which leaves the email due."""
        with self._errors():
            conn = self._connection()
            with conn.transaction():
                with conn.cursor(row_factory=class_row(OutboxEmail)) as cursor:
                    email = cursor.execute(_NEXT_DUE).fetchone()
                yield email

    def mark_sent(self, email_id: int) -> None:
        self._execute(_MARK_SENT, (email_id,))

    def mark_retry(self, email_id: int, error: str, delay_seconds: int) -> None:
        self._execute(_MARK_RETRY, (error[:500], timedelta(seconds=delay_seconds), email_id))

    def mark_failed(self, email_id: int, error: str) -> None:
        self._execute(_MARK_FAILED, (error[:500], email_id))

    def close(self) -> None:
        if self._conn is not None:
            self._conn.close()
            self._conn = None

    def _execute(self, query: str, params: tuple[Any, ...]) -> None:
        with self._errors():
            self._connection().execute(query, params)

    def _connection(self) -> psycopg.Connection:
        if self._conn is not None and (self._conn.closed or self._conn.broken):
            self.close()
        if self._conn is None:
            self._conn = database.connect(self._settings)
        return self._conn

    def _errors(self) -> AbstractContextManager[None]:
        # A lost connection is replaced on the next call (see _connection)
        return database.explained(
            self._settings, "email_outbox", "Apply the website's migrations: docker compose run --rm migrate"
        )
