"""The photo reminder (#22): who gets one, what it says, and how a run goes."""

import logging
import sqlite3
from dataclasses import replace
from datetime import date

import pytest

from mailer.reminders import User, first_name, reminder, send_reminders, users_without_photo
from mailer.smtp import MailServerUnavailable, Rejected
from tests.conftest import FakeSender

TODAY = date(2026, 10, 27)
ANE = User(1, "Ane Etxeberria", "ane@example.com")
JON = User(2, "Jon Arana", "jon@example.com")


@pytest.fixture
def users():
    """The columns of the website's users table that the selection reads, in SQLite, which runs
    the same query as PostgreSQL."""
    conn = sqlite3.connect(":memory:")
    conn.execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, profile_image TEXT, email_verified_at TEXT)")
    yield conn
    conn.close()


@pytest.fixture
def site(settings):
    return replace(settings, site_url="https://localhost")


def add(conn, id, profile_image, confirmed=True):
    conn.execute(
        "INSERT INTO users VALUES (?, ?, ?, ?, ?)",
        (id, f"User {id}", f"user{id}@example.com", profile_image, "2026-10-27 09:00:00" if confirmed else None),
    )


def test_users_whose_image_is_null_or_empty_are_selected(users):
    add(users, 1, None)
    add(users, 2, "")
    add(users, 3, "   ")
    add(users, 4, "profile-photos/5f0c.webp")

    assert [user.id for user in users_without_photo(users)] == [1, 2, 3]


def test_unconfirmed_addresses_are_left_out(users):
    add(users, 1, None, confirmed=False)
    add(users, 2, None)

    assert users_without_photo(users) == [User(2, "User 2", "user2@example.com")]


def test_nobody_is_selected_when_everyone_has_a_photo(users):
    add(users, 1, "profile-photos/a.webp")
    add(users, 2, "profile-photos/b.webp")

    assert users_without_photo(users) == []


def test_users_come_in_order(users):
    for id in (3, 1, 2):
        add(users, id, None)

    assert [user.id for user in users_without_photo(users)] == [1, 2, 3]


@pytest.mark.parametrize(
    ("name", "first"),
    [("Ane Etxeberria", "Ane"), ("  Jon  ", "Jon"), ("Mari Carmen Ruiz", "Mari"), ("", "there")],
)
def test_the_greeting_uses_the_first_name(name, first):
    assert first_name(name) == first


def test_the_reminder_is_personal(site):
    message = reminder(ANE, site, TODAY)

    assert message["To"] == "Ane Etxeberria <ane@example.com>"
    assert message["Subject"] == "Ane, add a photo to your Guenaga Farmazeutika account"
    text = message.get_body(("plain",)).get_content()
    html = message.get_body(("html",)).get_content()
    assert text.startswith("Hello Ane,")
    assert "https://localhost/account/profile" in text
    assert 'href="https://localhost/account/profile"' in html
    assert "once a week until your account has a photo" in html


def test_a_second_run_on_the_same_day_sends_the_same_email(site):
    first = reminder(ANE, site, TODAY)

    assert reminder(ANE, site, TODAY)["Message-ID"] == first["Message-ID"] == "<photo_reminder.1.20261027@guenaga.eus>"
    assert reminder(ANE, site, date(2026, 11, 3))["Message-ID"] != first["Message-ID"]


def test_a_name_from_the_website_cant_add_markup(site):
    html = reminder(replace(ANE, name="<b>Ane</b> Etxeberria"), site, TODAY).get_body(("html",)).get_content()

    assert "<b>Ane</b>" not in html
    assert "&lt;b&gt;Ane&lt;/b&gt;" in html


def test_everyone_selected_gets_a_reminder(site):
    server = FakeSender()

    assert send_reminders([ANE, JON], site, TODAY, connect=server) == (2, 0)
    assert [message["To"].addresses[0].addr_spec for message in server.messages] == ["ane@example.com", "jon@example.com"]


def test_nobody_to_remind_means_no_connection(site):
    server = FakeSender()

    assert send_reminders([], site, TODAY, connect=server) == (0, 0)
    assert server.connections == 0


def test_a_user_the_mail_server_turns_down_doesnt_stop_the_others(site, caplog):
    server = FakeSender(Rejected(550, "No such mailbox"), None)

    assert send_reminders([ANE, JON], site, TODAY, connect=server) == (1, 1)
    assert "turned down the reminder to user #1" in caplog.text


def test_an_address_that_cant_be_used_is_skipped(site):
    server = FakeSender()

    sent = send_reminders([replace(ANE, email="ane@example.com\nBcc: x@example.com"), JON], site, TODAY, connect=server)

    assert sent == (1, 1)
    assert len(server.messages) == 1


def test_the_log_names_users_by_number_only(site, caplog):
    caplog.set_level(logging.INFO)

    send_reminders([ANE], site, TODAY, connect=FakeSender())

    assert "Reminded user #1" in caplog.text
    assert "ane@example.com" not in caplog.text
    assert "Etxeberria" not in caplog.text


def test_the_mail_server_going_down_ends_the_run(site):
    server = FakeSender(MailServerUnavailable("Lost the connection to the mail server"))

    with pytest.raises(MailServerUnavailable):
        send_reminders([ANE, JON], site, TODAY, connect=server)
