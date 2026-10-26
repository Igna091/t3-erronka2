"""Sends the emails the website queues. Runs until stopped, checking the outbox every
MAILER_POLL_SECONDS; with --once it makes one pass and exits, with status 1 when the database
or the mail server is down. Either way a problem is logged as one clear line, not a traceback."""

import argparse
import logging
import os
import signal
import sys
import threading

from mailer import heartbeat
from mailer.config import ConfigError, Settings
from mailer.outbox import DatabaseUnavailable, Outbox
from mailer.smtp import MailServerUnavailable
from mailer.worker import send_due

log = logging.getLogger("mailer")

# While the database or the mail server is down, the wait between tries doubles up to this
MAX_BACKOFF_SECONDS = 60.0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="python -m mailer", description=__doc__)
    parser.add_argument("--once", action="store_true", help="make one pass over the outbox, then exit")
    args = parser.parse_args(argv)

    level = logging.getLevelNamesMapping().get(os.environ.get("LOG_LEVEL", "").upper(), logging.INFO)
    logging.basicConfig(level=level, format="%(asctime)s %(levelname)s %(name)s: %(message)s", stream=sys.stdout)

    try:
        settings = Settings.from_env()
    except ConfigError as error:
        log.error("Bad setting: %s", error)
        return 2

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
