"""The connection to the mail server, with smtplib.SMTP replaced by a fake one."""

import smtplib
import ssl

import pytest

from mailer import smtp
from mailer.smtp import MailServerUnavailable, Rejected, SmtpSender


class FakeSMTP:
    """Records what the sender does; fail={"step": error} makes a step raise."""

    instances: list["FakeSMTP"] = []
    fail: dict[str, BaseException] = {}

    def __init__(self, host, port, timeout):
        if "connect" in self.fail:
            raise self.fail["connect"]
        self.calls = [("connect", host, port)]
        FakeSMTP.instances.append(self)

    def _step(self, name, *args):
        self.calls.append((name, *args))
        if name in self.fail:
            raise self.fail[name]

    def starttls(self, context):
        self._step("starttls")
        assert context.verify_mode == ssl.CERT_REQUIRED and context.check_hostname

    def login(self, user, password):
        self._step("login", user, password)

    def send_message(self, message):
        self._step("send_message")

    def quit(self):
        self._step("quit")

    def close(self):
        self.calls.append(("close",))


@pytest.fixture
def fake_smtp(monkeypatch):
    FakeSMTP.instances = []
    FakeSMTP.fail = {}
    monkeypatch.setattr(smtp.smtplib, "SMTP", FakeSMTP)
    return FakeSMTP


def test_starts_tls_and_logs_in_before_sending(fake_smtp, settings):
    with SmtpSender(settings) as sender:
        sender.send(object())

    assert [call[0] for call in fake_smtp.instances[0].calls] == ["connect", "starttls", "login", "send_message", "quit"]
    assert fake_smtp.instances[0].calls[2] == ("login", "mailer", "smtp-secret")


def test_never_sends_unencrypted(fake_smtp, settings):
    fake_smtp.fail = {"starttls": smtplib.SMTPNotSupportedError("STARTTLS extension not supported by server.")}

    with pytest.raises(MailServerUnavailable, match="doesn't offer STARTTLS, and the mailer never sends unencrypted"):
        SmtpSender(settings).__enter__()
    assert ("close",) in fake_smtp.instances[0].calls


def test_an_untrusted_certificate(fake_smtp, settings):
    fake_smtp.fail = {"starttls": ssl.SSLCertVerificationError(1, "certificate verify failed")}

    with pytest.raises(MailServerUnavailable, match="isn't trusted .* check MAIL_CA_FILE"):
        SmtpSender(settings).__enter__()


def test_an_unreachable_server(fake_smtp, settings):
    fake_smtp.fail = {"connect": ConnectionRefusedError(111, "Connection refused")}

    with pytest.raises(MailServerUnavailable, match=r"^Can't reach the mail server at mailpit:1025: \[Errno 111\] Connection refused$"):
        SmtpSender(settings).__enter__()


def test_a_refused_login(fake_smtp, settings):
    fake_smtp.fail = {"login": smtplib.SMTPAuthenticationError(535, b"5.7.8 Authentication credentials invalid")}

    with pytest.raises(MailServerUnavailable, match="refused the login 'mailer': 535 5.7.8"):
        SmtpSender(settings).__enter__()


@pytest.mark.parametrize(
    ("error", "permanent"),
    [
        (smtplib.SMTPRecipientsRefused({"ane@example.com": (550, b"5.1.1 No such user")}), True),
        (smtplib.SMTPRecipientsRefused({"ane@example.com": (452, b"4.2.2 Mailbox full")}), False),
        (smtplib.SMTPDataError(554, b"5.6.0 Message rejected"), True),
        (smtplib.SMTPDataError(451, b"4.3.0 Try again later"), False),
    ],
)
def test_a_refused_email(fake_smtp, settings, error, permanent):
    fake_smtp.fail = {"send_message": error}

    with SmtpSender(settings) as sender, pytest.raises(Rejected) as refusal:
        sender.send(object())

    assert refusal.value.permanent is permanent


@pytest.mark.parametrize(
    "error",
    [
        smtplib.SMTPSenderRefused(530, b"5.7.0 Authentication required", "no-reply@guenaga.eus"),
        smtplib.SMTPServerDisconnected("Connection unexpectedly closed"),
        TimeoutError("timed out"),
    ],
)
def test_a_problem_with_every_email_stops_the_pass(fake_smtp, settings, error):
    fake_smtp.fail = {"send_message": error}

    with SmtpSender(settings) as sender, pytest.raises(MailServerUnavailable):
        sender.send(object())
