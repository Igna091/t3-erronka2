"""The email itself: headers, plain text and HTML, ready for smtplib."""

from datetime import UTC, datetime
from email.errors import HeaderParseError
from email.headerregistry import Address
from email.message import EmailMessage
from email.utils import format_datetime
from typing import Any

from mailer.outbox import OutboxEmail
from mailer.render import RenderError, render


def build_message(email: OutboxEmail, sender: Address) -> EmailMessage:
    """Raises RenderError when the row can't become an email."""
    # The same on every attempt, so a mail client shows a copy that was sent twice only once
    return compose(email.kind, email.payload, sender, f"{email.kind}.{email.id}.{email.created_at:%Y%m%d%H%M%S}")


def compose(kind: str, payload: dict[str, Any], sender: Address, message_key: str) -> EmailMessage:
    """The email of that kind for the payload's "to". message_key makes its Message-ID, so the
    same key means the same email. Raises RenderError when the payload can't become one."""
    to = payload.get("to")
    if not isinstance(to, dict):
        raise RenderError("The payload has no recipient")
    recipient = _recipient(to.get("name", ""), to.get("email", ""))
    rendered = render(kind, payload)

    message = EmailMessage()
    message["From"] = sender
    message["To"] = recipient
    message["Subject"] = rendered.subject
    message["Date"] = format_datetime(datetime.now(UTC))
    message["Message-ID"] = f"<{message_key}@{sender.domain}>"
    # RFC 3834: tells autoresponders not to answer it
    message["Auto-Submitted"] = "auto-generated"
    message.set_content(rendered.text)
    message.add_alternative(rendered.html, subtype="html")
    return message


def _recipient(name: object, address: object) -> Address:
    if not isinstance(name, str) or not isinstance(address, str) or not address:
        raise RenderError("The payload's recipient needs a name and an email address")
    # Header injection: a line break in the name or address could add headers such as Bcc
    if not (name + address).isprintable():
        raise RenderError("The recipient's name or address contains control characters such as line breaks")
    try:
        recipient = Address(display_name=name, addr_spec=address)
    except (ValueError, IndexError, HeaderParseError):
        recipient = None
    if recipient is None or not recipient.domain:
        raise RenderError("The recipient's email address isn't valid")
    return recipient
