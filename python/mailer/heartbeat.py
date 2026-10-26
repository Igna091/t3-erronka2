"""The Docker health check: `python -m mailer.heartbeat` succeeds while the mailer's last pass
over the outbox ended recently, so the container turns unhealthy when the database or the mail
server has been down for a while. The file lives on the container's tmpfs."""

import os
import sys
import time
from pathlib import Path

FILE = Path("/tmp/mailer-heartbeat")


def beat() -> None:
    FILE.touch()


def is_fresh(now: float | None = None) -> bool:
    poll_seconds = float(os.environ.get("MAILER_POLL_SECONDS") or 10)
    try:
        age = (time.time() if now is None else now) - FILE.stat().st_mtime
    except OSError:
        return False
    return age < max(60.0, 3 * poll_seconds)


if __name__ == "__main__":
    sys.exit(0 if is_fresh() else 1)
