"""Shared test data, and in-memory stand-ins for the database and the mail server."""

from contextlib import contextmanager
from datetime import datetime
from email.headerregistry import Address

import pytest

from mailer.config import Settings
from mailer.outbox import OutboxEmail


@pytest.fixture
def payload() -> dict:
    """What Order::confirmationEmail() queues for a card order (web/laravel/app/Models/Order.php)."""
    return {
        "to": {"name": "Ane Etxeberria", "email": "ane@example.com"},
        "reference": "GF-7KQ2M9XA",
        "placed_at": "2026-10-26T09:05:00+01:00",
        "phone": "600 000 000",
        "items": [
            {"name": "Paracetamol 500 mg", "quantity": 2, "line_cents": 390},
            {"name": "Omeprazole 20 mg", "quantity": 1, "line_cents": 495},
        ],
        "total_cents": 885,
        "payment": {"status": "paid", "card_last4": "4242"},
        "ready_within": "[2 hours]",
        "pharmacy": {
            "name": "Guenaga Farmazeutika",
            "address": ["[Street and number]", "[Postcode, city]"],
            "phone": {"display": "[000 000 000]", "dial": "+34000000000"},
            "hours": [["Monday to Friday", "9:00–21:00"], ["Saturday", "9:30–14:00"], ["Sunday", "Closed"]],
        },
        "site_url": "https://localhost",
    }


@pytest.fixture
def settings() -> Settings:
    return Settings(
        db_host="db",
        db_port=5432,
        db_name="guenaga",
        db_user="mailer",
        db_password="db-secret",
        mail_host="mailpit",
        mail_port=1025,
        mail_scheme="smtp",
        mail_ca_file=None,
        mail_username="mailer",
        mail_password="smtp-secret",
        mail_from=Address("Guenaga Farmazeutika", addr_spec="no-reply@guenaga.eus"),
        poll_seconds=10,
        max_attempts=3,
    )


def make_email(payload: dict, id: int = 1, attempts: int = 0, kind: str = "order_confirmation") -> OutboxEmail:
    return OutboxEmail(id=id, kind=kind, payload=payload, attempts=attempts, created_at=datetime(2026, 10, 26, 8, 5, 0))


class FakeOutbox:
    """The outbox in memory. Like the real one, what next_due()'s block records is rolled back
    if the block raises."""

    def __init__(self, *emails: OutboxEmail) -> None:
        self.due = list(emails)
        self.outcomes: dict[int, tuple] = {}

    def has_due(self) -> bool:
        return bool(self.due)

    @contextmanager
    def next_due(self):
        before = dict(self.outcomes)
        try:
            yield self.due[0] if self.due else None
        except BaseException:
            self.outcomes = before
            raise
        self.due = [email for email in self.due if email.id not in self.outcomes]

    def mark_sent(self, email_id: int) -> None:
        self.outcomes[email_id] = ("sent",)

    def mark_retry(self, email_id: int, error: str, delay_seconds: int) -> None:
        self.outcomes[email_id] = ("retry", error, delay_seconds)

    def mark_failed(self, email_id: int, error: str) -> None:
        self.outcomes[email_id] = ("failed", error)

    def close(self) -> None:
        pass


class FakeSender:
    """A mail server that takes every email, or raises the next of the given errors. Pass it as
    send_due(connect=...)."""

    def __init__(self, *errors: Exception | None) -> None:
        self.errors = list(errors)
        self.messages = []
        self.connections = 0

    def __call__(self, settings: Settings) -> "FakeSender":
        self.connections += 1
        return self

    def __enter__(self) -> "FakeSender":
        return self

    def __exit__(self, *exc_info) -> None:
        pass

    def send(self, message) -> None:
        self.messages.append(message)
        error = self.errors.pop(0) if self.errors else None
        if error is not None:
            raise error
