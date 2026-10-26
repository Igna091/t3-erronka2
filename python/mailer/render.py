"""Turns an outbox row into the email's subject, plain text and HTML, using the templates in
templates/<kind>/. The HTML is autoescaped, so a name or a medicine can't add markup or links."""

import re
from dataclasses import dataclass
from datetime import datetime
from typing import Any

from jinja2 import (
    Environment,
    PackageLoader,
    StrictUndefined,
    TemplateError,
    TemplateNotFound,
    select_autoescape,
)

_KIND = re.compile(r"[a-z][a-z0-9_]{0,39}")
_MONTHS = (
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December",
)


class RenderError(Exception):
    """The row can't become an email: an unknown kind, or a payload the templates don't expect.
    Trying again won't help."""


@dataclass(frozen=True)
class Rendered:
    subject: str
    text: str
    html: str


def euros(cents: int) -> str:
    """A price in cents the way the site writes it: 195 becomes "€1.95"."""
    if not isinstance(cents, int) or cents < 0:
        raise ValueError(f"not an amount in cents: {cents!r}")
    return f"€{cents // 100:,}.{cents % 100:02d}"


def long_date(value: str) -> str:
    """An ISO 8601 time from the site, as in "26 October 2026 at 9:05". The site sends it in
    the pharmacy's time zone, so it is shown as it is."""
    moment = datetime.fromisoformat(value)
    return f"{moment.day} {_MONTHS[moment.month - 1]} {moment.year} at {moment.hour}:{moment.minute:02d}"


_env = Environment(
    loader=PackageLoader("mailer"),
    autoescape=select_autoescape(["html"]),
    # A missing field fails the email instead of leaving a gap in it
    undefined=StrictUndefined,
    trim_blocks=True,
    lstrip_blocks=True,
    keep_trailing_newline=True,
)
_env.filters["euros"] = euros
_env.filters["long_date"] = long_date


def render(kind: str, payload: dict[str, Any]) -> Rendered:
    if not _KIND.fullmatch(kind):
        raise RenderError(f"Unknown kind of email: {kind!r}")
    try:
        subject = _env.get_template(f"{kind}/subject.txt").render(payload)
        text = _env.get_template(f"{kind}/body.txt").render(payload)
        html = _env.get_template(f"{kind}/body.html").render(payload)
    except TemplateNotFound:
        raise RenderError(f"Unknown kind of email: {kind!r}") from None
    except (TemplateError, TypeError, ValueError, KeyError, IndexError) as error:
        raise RenderError(f"The payload doesn't fit the {kind} templates: {error}") from None
    # One line, whatever the template's whitespace
    return Rendered(" ".join(subject.split()), text, html)
