"""Sending with smtplib, always encrypted: STARTTLS (MAIL_SCHEME=smtp) or TLS from the first
byte (smtps). The server's certificate must chain to MAIL_CA_FILE, or to the system's CAs, and
match MAIL_HOST, so an email never travels in plain text or to an impostor."""

import smtplib
import ssl
from email.message import EmailMessage
from types import TracebackType

from mailer.config import Settings


class MailServerUnavailable(Exception):
    """The mail server can't be reached, isn't trusted, or won't take email from the mailer."""


class Rejected(Exception):
    """The mail server refused one email. A 5xx reply is final, a 4xx one is worth retrying."""

    def __init__(self, code: int, reply: str) -> None:
        super().__init__(f"{code} {reply}")
        self.permanent = 500 <= code < 600


class SmtpSender:
    """One connection to the mail server, for one pass over the outbox:

        with SmtpSender(settings) as sender:
            sender.send(message)
    """

    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._smtp: smtplib.SMTP | None = None

    def __enter__(self) -> "SmtpSender":
        s = self._settings
        server = f"the mail server at {s.mail_host}:{s.mail_port}"
        try:
            self._smtp = self._connect()
        except ssl.SSLCertVerificationError as error:
            reason = getattr(error, "verify_message", None) or error
            raise MailServerUnavailable(f"The certificate of {server} isn't trusted ({reason}); check MAIL_CA_FILE") from None
        except ssl.SSLError as error:
            raise MailServerUnavailable(f"TLS with {server} failed: {error.reason or error}") from None
        except smtplib.SMTPAuthenticationError as error:
            raise MailServerUnavailable(f"{server.capitalize()} refused the login {s.mail_username!r}: {_reply(error)}") from None
        except (OSError, smtplib.SMTPException) as error:  # SMTPException is an OSError too
            raise MailServerUnavailable(f"Can't reach {server}: {_describe(error)}") from None
        return self

    def __exit__(self, kind: type[BaseException] | None, error: BaseException | None, traceback: TracebackType | None) -> None:
        if self._smtp is not None:
            try:
                self._smtp.quit()
            except (OSError, smtplib.SMTPException):
                self._smtp.close()
            self._smtp = None

    def send(self, message: EmailMessage) -> None:
        """Raises Rejected when the server refuses this email, and MailServerUnavailable when it
        can't take any."""
        if self._smtp is None:
            raise RuntimeError("SmtpSender.send() outside its with block")
        try:
            self._smtp.send_message(message)
        except smtplib.SMTPRecipientsRefused as error:
            code, reply = next(iter(error.recipients.values()))
            raise Rejected(code, reply.decode(errors="replace")) from None
        except smtplib.SMTPSenderRefused as error:
            # Wrong for every email, not only this one: a setting or the server's policy
            raise MailServerUnavailable(f"The mail server refused the sender {error.sender}: {_reply(error)}") from None
        except smtplib.SMTPNotSupportedError as error:
            # For example an address with accents, and a server without SMTPUTF8
            raise Rejected(553, str(error)) from None
        except smtplib.SMTPResponseException as error:
            raise Rejected(error.smtp_code, _reply(error, with_code=False)) from None
        except (OSError, smtplib.SMTPException) as error:
            raise MailServerUnavailable(f"Lost the connection to the mail server: {_describe(error)}") from None

    def _connect(self) -> smtplib.SMTP:
        s = self._settings
        context = ssl.create_default_context(cafile=s.mail_ca_file)
        if s.mail_scheme == "smtps":
            smtp: smtplib.SMTP = smtplib.SMTP_SSL(s.mail_host, s.mail_port, timeout=s.timeout_seconds, context=context)
        else:
            smtp = smtplib.SMTP(s.mail_host, s.mail_port, timeout=s.timeout_seconds)
        try:
            if s.mail_scheme == "smtp":
                try:
                    smtp.starttls(context=context)
                except smtplib.SMTPNotSupportedError:
                    raise MailServerUnavailable(
                        f"The mail server at {s.mail_host}:{s.mail_port} doesn't offer STARTTLS, "
                        "and the mailer never sends unencrypted"
                    ) from None
            if s.mail_username:
                smtp.login(s.mail_username, s.mail_password or "")
        except BaseException:
            smtp.close()
            raise
        return smtp


def _reply(error: smtplib.SMTPResponseException, with_code: bool = True) -> str:
    text = error.smtp_error.decode(errors="replace") if isinstance(error.smtp_error, bytes) else str(error.smtp_error)
    return f"{error.smtp_code} {text}" if with_code else text


def _describe(error: BaseException) -> str:
    if isinstance(error, smtplib.SMTPResponseException):
        return _reply(error)
    return str(error) or type(error).__name__
