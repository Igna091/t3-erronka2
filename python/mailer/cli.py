"""Sends the emails the website queues. Runs until stopped, checking the outbox every
MAILER_POLL_SECONDS; with --once it makes one pass and exits, with status 1 when the database
or the mail server is down. With --remind-photos it instead emails every user without a profile
photo once, then exits the same way (a weekly timer runs it). Either way a problem is logged as
one clear line, not a traceback."""

import argparse
import logging
import os
import signal
import sys
import threading
from datetime import UTC, datetime

from mailer import heartbeat
from mailer.config import ConfigError, Settings
from mailer.database import DatabaseUnavailable
from mailer.outbox import Outbox
from mailer.reminders import fetch_users_without_photo, send_reminders
from mailer.smtp import MailServerUnavailable
from mailer.worker import send_due

log = logging.getLogger("mailer")

# While the database or the mail server is down, the wait between tries doubles up to this
MAX_BACKOFF_SECONDS = 60.0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="python -m mailer", description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--once", action="store_true", help="make one pass over the outbox, then exit")
    mode.add_argument("--remind-photos", action="store_true", help="email every user without a profile photo, then exit")
    args = parser.parse_args(argv)

    level = logging.getLevelNamesMapping().get(os.environ.get("LOG_LEVEL", "").upper(), logging.INFO)
    logging.basicConfig(level=level, format="%(asctime)s %(levelname)s %(name)s: %(message)s", stream=sys.stdout)

    try:
        settings = Settings.from_env()
    except ConfigError as error:
        log.error("Bad setting: %s", error)
        return 2

    if args.remind_photos:
        return remind_photos(settings)
    outbox = Outbox(settings)
    try:
        return run_once(outbox, settings) if args.once else run_forever(outbox, settings)
    finally:
        outbox.close()


def run_once(outbox: Outbox, settings: Settings) -> int:
    try:
        sent = send_due(outbox, settings, threading.Event())
    except (DatabaseUnavailable, MailServerUnavailable) as error:
        log.error("%s", error)
        return 1
    log.info("Sent %d email(s)", sent)
    return 0


def remind_photos(settings: Settings) -> int:
    if settings.site_url is None:
        log.error("Bad setting: SITE_URL isn't set, and the reminder links to the profile page")
        return 2
    try:
        users = fetch_users_without_photo(settings)
        sent, skipped = send_reminders(users, settings, datetime.now(UTC).date())
    except (DatabaseUnavailable, MailServerUnavailable) as error:
        log.error("%s", error)
        return 1
    log.info("%d user(s) without a photo: reminded %d, skipped %d", len(users), sent, skipped)
    return 0


def run_forever(outbox: Outbox, settings: Settings) -> int:
    # docker stop sends SIGTERM: finish the email being sent, then exit
    stop = threading.Event()
    for signum in (signal.SIGTERM, signal.SIGINT):
        signal.signal(signum, lambda *_: stop.set())

    log.info(
        "Checking the outbox every %gs, sending through %s:%d (%s) as %s",
        settings.poll_seconds, settings.mail_host, settings.mail_port,
        "STARTTLS" if settings.mail_scheme == "smtp" else "TLS", settings.mail_from.addr_spec,
    )
    wait = settings.poll_seconds
    while not stop.is_set():
        try:
            send_due(outbox, settings, stop)
        except (DatabaseUnavailable, MailServerUnavailable) as error:
            log.error("%s. Trying again in %gs", error, wait)
            stop.wait(wait)
            wait = min(wait * 2, MAX_BACKOFF_SECONDS)
            continue
        heartbeat.beat()
        wait = settings.poll_seconds
        stop.wait(wait)
    log.info("Stopped")
    return 0
