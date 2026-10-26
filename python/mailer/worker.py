"""One pass over the outbox: send every email that is due and record how each one went."""

import logging
import threading
from collections.abc import Callable
from contextlib import AbstractContextManager
from typing import Protocol

from mailer.config import Settings
from mailer.message import build_message
from mailer.outbox import Outbox, OutboxEmail
from mailer.render import RenderError
from mailer.smtp import Rejected, SmtpSender

log = logging.getLogger(__name__)


class Sender(Protocol):
    def send(self, message: object) -> None: ...


def retry_delay(failed_attempts: int) -> int:
    """Seconds to wait before trying a refused email again: a minute after the first refusal,
    doubling each time, and never more than an hour."""
    return min(60 * 2 ** (failed_attempts - 1), 3600)


def send_due(
    outbox: Outbox,
    settings: Settings,
    stop: threading.Event,
    connect: Callable[[Settings], AbstractContextManager[Sender]] = SmtpSender,
) -> int:
    """Sends the emails that are due and returns how many went out. Raises DatabaseUnavailable
    or MailServerUnavailable when either goes down, and the email being sent then stays due."""
    if not outbox.has_due():
        return 0  # nothing for the mail server, so don't connect to it
    sent = 0
    with connect(settings) as sender:
        while not stop.is_set():
            with outbox.next_due() as email:
                if email is None:
                    break
                sent += _deliver(email, outbox, sender, settings)
    return sent


def _deliver(email: OutboxEmail, outbox: Outbox, sender: Sender, settings: Settings) -> bool:
    """Sends one email and records the outcome; True when it went out."""
    reference = email.payload.get("reference")
    label = f"email #{email.id} ({email.kind}" + (f", order {reference})" if reference else ")")
    attempt = email.attempts + 1

    try:
        message = build_message(email, settings.mail_from)
    except RenderError as error:
        outbox.mark_failed(email.id, str(error))
        log.error("Not sending %s: %s", label, error)
        return False

    try:
        sender.send(message)
    except Rejected as error:
        if error.permanent or attempt >= settings.max_attempts:
            outbox.mark_failed(email.id, str(error))
            log.error("Gave up on %s after %d attempt(s); the mail server answered %s", label, attempt, error)
        else:
            delay = retry_delay(attempt)
            outbox.mark_retry(email.id, str(error), delay)
            log.warning("The mail server turned down %s for now (%s); trying again in %d min", label, error, delay // 60)
        return False

    outbox.mark_sent(email.id)
    log.info("Sent %s", label)
    return True
