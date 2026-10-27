"""The weekly reminder to add a profile photo (#22). Every user whose email is confirmed and
whose account has no photo gets a personal email, sent like the others: smtplib over TLS.

The users are read through the reminder role, which may only read five columns of users, in a
read-only session, so this pass can't change anything in the database. It also can't remember
whom it wrote to: a user hears from it every time it runs, once a week, until the account has
a photo."""

import logging
from collections.abc import Callable
from contextlib import AbstractContextManager
from dataclasses import dataclass
from datetime import date
from email.message import EmailMessage
from typing import Any, Protocol

from mailer import database
from mailer.config import Settings
from mailer.message import compose
from mailer.render import RenderError
from mailer.smtp import Rejected, SmtpSender

log = logging.getLogger(__name__)

# The selection: users whose profile_image is NULL or empty (or only spaces). Unconfirmed
# addresses are left out, or anyone could make the pharmacy write every week to an address they
# typed in. Plain SQL that SQLite runs too, so the unit tests check this very query.
WITHOUT_PHOTO = (
    "SELECT id, name, email FROM users"
    " WHERE email_verified_at IS NOT NULL"
    " AND (profile_image IS NULL OR trim(profile_image) = '')"
    " ORDER BY id"
)

# Where the website lets a user add the photo (web/laravel/routes/web.php)
PROFILE_PATH = "/account/profile"


@dataclass(frozen=True)
class User:
    id: int
    name: str
    email: str


class Connection(Protocol):
    def execute(self, query: str) -> Any: ...


class Sender(Protocol):
    def send(self, message: EmailMessage) -> None: ...


def users_without_photo(conn: Connection) -> list[User]:
    """Runs the selection on an open connection: PostgreSQL's, or SQLite's in the tests."""
    return [User(*row) for row in conn.execute(WITHOUT_PHOTO).fetchall()]


def fetch_users_without_photo(settings: Settings) -> list[User]:
    """The selection on the website's database. Raises DatabaseUnavailable."""
    fix = "Apply the website's migrations and create the role: python/README.md, \"The photo reminder\""
    with database.explained(settings, "users", fix):
        with database.connect(settings, read_only=True) as conn:
            return users_without_photo(conn)


def first_name(name: str) -> str:
    """The first word of the name, as the website greets the user (User::firstName())."""
    return name.strip().split(" ", 1)[0] or "there"


def reminder(user: User, settings: Settings, today: date) -> EmailMessage:
    """The user's reminder for today. Raises RenderError when the name or address can't be used."""
    if settings.site_url is None:
        raise ValueError("The reminder needs settings.site_url")
    payload = {
        "to": {"name": user.name, "email": user.email},
        "first_name": first_name(user.name),
        "pharmacy": {"name": settings.mail_from.display_name or settings.mail_from.domain},
        "site_url": settings.site_url,
        "profile_url": settings.site_url + PROFILE_PATH,
    }
    # One Message-ID per user and day: a second run on the same day isn't shown twice
    return compose("photo_reminder", payload, settings.mail_from, f"photo_reminder.{user.id}.{today:%Y%m%d}")


def send_reminders(
    users: list[User],
    settings: Settings,
    today: date,
    connect: Callable[[Settings], AbstractContextManager[Sender]] = SmtpSender,
) -> tuple[int, int]:
    """Sends each user their reminder and returns how many went out and how many didn't. A user
    the mail server turns down is skipped until the next run. Raises MailServerUnavailable when
    the mail server can't take any. The log names users by number, never by name or address."""
    if not users:
        return 0, 0  # nobody to write to, so don't connect to the mail server
    sent = skipped = 0
    with connect(settings) as sender:
        for user in users:
            try:
                sender.send(reminder(user, settings, today))
            except RenderError as error:
                skipped += 1
                log.error("Not reminding user #%d: %s", user.id, error)
            except Rejected as error:
                skipped += 1
                log.warning("The mail server turned down the reminder to user #%d (%s); trying again next week", user.id, error)
            else:
                sent += 1
                log.info("Reminded user #%d", user.id)
    return sent, skipped
