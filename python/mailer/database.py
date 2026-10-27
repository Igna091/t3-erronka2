"""The connection to PostgreSQL, shared by the outbox and the photo reminder, and the driver's
errors turned into one line that says what's wrong."""

from collections.abc import Iterator
from contextlib import contextmanager

import psycopg
from psycopg import errors

from mailer.config import Settings


class DatabaseUnavailable(Exception):
    """The database can't be reached, or won't let the mailer use what it needs."""


def connect(settings: Settings, read_only: bool = False) -> psycopg.Connection:
    # Laravel's timestamps are UTC, so the mailer's are too
    options = "-c TimeZone=UTC"
    if read_only:
        # Every statement of the session is then refused if it would write, whatever the grants
        options += " -c default_transaction_read_only=on"
    return psycopg.connect(
        host=settings.db_host,
        port=settings.db_port,
        dbname=settings.db_name,
        user=settings.db_user,
        password=settings.db_password,
        connect_timeout=5,
        application_name="guenaga-mailer",
        # Each statement commits on its own; whoever needs a transaction opens it
        autocommit=True,
        options=options,
    )


@contextmanager
def explained(settings: Settings, table: str, fix: str) -> Iterator[None]:
    """Turns the driver's errors into a DatabaseUnavailable: for the role and the table, what
    went wrong and how to fix it, and otherwise which database couldn't be used."""
    try:
        yield
    except (errors.UndefinedTable, errors.InsufficientPrivilege) as error:
        raise DatabaseUnavailable(f"The {settings.db_user} role can't use {table} ({first_line(error)}). {fix}") from None
    except psycopg.Error as error:
        s = settings
        raise DatabaseUnavailable(f"Can't use the database {s.db_name} at {s.db_host}:{s.db_port}: {first_line(error)}") from None


def first_line(error: Exception) -> str:
    lines = str(error).strip().splitlines()
    return lines[0] if lines else type(error).__name__
