"""Which emails go out, and what is recorded when they don't."""

import threading

import pytest

from mailer.smtp import MailServerUnavailable, Rejected
from mailer.worker import retry_delay, send_due
from tests.conftest import FakeOutbox, FakeSender, make_email


def test_sends_every_due_email_and_marks_it_sent(payload, settings):
    outbox = FakeOutbox(make_email(payload, id=1), make_email(payload, id=2))
    sender = FakeSender()

    assert send_due(outbox, settings, threading.Event(), connect=sender) == 2

    assert outbox.outcomes == {1: ("sent",), 2: ("sent",)}
    assert [message["To"] for message in sender.messages] == ["Ane Etxeberria <ane@example.com>"] * 2
    assert sender.connections == 1


def test_does_not_connect_to_the_mail_server_when_nothing_is_due(settings):
    sender = FakeSender()

    assert send_due(FakeOutbox(), settings, threading.Event(), connect=sender) == 0
    assert sender.connections == 0


def test_a_temporary_refusal_is_tried_again_later(payload, settings):
    outbox = FakeOutbox(make_email(payload, attempts=1))

    send_due(outbox, settings, threading.Event(), connect=FakeSender(Rejected(451, "4.3.0 Try again later")))

    assert outbox.outcomes == {1: ("retry", "451 4.3.0 Try again later", 120)}


def test_a_permanent_refusal_is_not_tried_again(payload, settings):
    outbox = FakeOutbox(make_email(payload))

    send_due(outbox, settings, threading.Event(), connect=FakeSender(Rejected(550, "5.1.1 No such user")))

    assert outbox.outcomes == {1: ("failed", "550 5.1.1 No such user")}


def test_gives_up_after_the_last_attempt(payload, settings):
    outbox = FakeOutbox(make_email(payload, attempts=settings.max_attempts - 1))

    send_due(outbox, settings, threading.Event(), connect=FakeSender(Rejected(451, "4.3.0 Try again later")))

    assert outbox.outcomes[1][0] == "failed"


def test_an_email_that_cannot_be_built_is_not_sent(payload, settings):
    del payload["items"]
    outbox = FakeOutbox(make_email(payload))
    sender = FakeSender()

    send_due(outbox, settings, threading.Event(), connect=sender)

    assert outbox.outcomes[1][0] == "failed"
    assert "items" in outbox.outcomes[1][1]
    assert sender.messages == []


def test_one_failed_email_does_not_hold_up_the_rest(payload, settings):
    broken = dict(payload, to={"name": "Ane\r\nBcc: all@example.com", "email": "ane@example.com"})
    outbox = FakeOutbox(make_email(broken, id=1), make_email(payload, id=2))

    assert send_due(outbox, settings, threading.Event(), connect=FakeSender()) == 1
    assert outbox.outcomes[1][0] == "failed"
    assert outbox.outcomes[2] == ("sent",)


def test_losing_the_mail_server_leaves_the_email_due(payload, settings):
    email = make_email(payload)
    outbox = FakeOutbox(email)

    with pytest.raises(MailServerUnavailable):
        send_due(outbox, settings, threading.Event(), connect=FakeSender(MailServerUnavailable("connection lost")))

    assert outbox.outcomes == {}
    assert outbox.due == [email]


def test_stops_between_emails_when_asked(payload, settings):
    stop = threading.Event()
    stop.set()
    outbox = FakeOutbox(make_email(payload))

    assert send_due(outbox, settings, stop, connect=FakeSender()) == 0
    assert outbox.outcomes == {}


@pytest.mark.parametrize(("failed_attempts", "seconds"), [(1, 60), (2, 120), (3, 240), (6, 1920), (7, 3600), (20, 3600)])
def test_retry_delay_doubles_up_to_an_hour(failed_attempts, seconds):
    assert retry_delay(failed_attempts) == seconds
