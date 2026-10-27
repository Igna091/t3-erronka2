"""The account's emails: confirm the address, choose a new password, and security alerts."""

import pytest

from mailer.message import build_message
from mailer.render import RenderError, render
from tests.conftest import make_email

URL = "https://localhost/email/verify/7/abc?expires=1793100000&signature=f00d"


@pytest.fixture
def account() -> dict:
    """What App\\Support\\AccountEmails puts in every account email (web/laravel)."""
    return {
        "to": {"name": "Ane Etxeberria", "email": "ane@example.com"},
        "first_name": "Ane",
        "pharmacy": {"name": "Guenaga Farmazeutika", "phone": {"display": "[000 000 000]", "dial": "+34000000000"}},
        "site_url": "https://localhost",
    }


def test_confirm_your_email(account):
    payload = account | {"url": URL, "expires_minutes": 60}

    email = render("verify_email", payload)

    assert email.subject == "Confirm your email for Guenaga Farmazeutika"
    assert URL in email.text
    assert "for 60 minutes" in email.text
    assert 'href="https://localhost/email/verify/7/abc?expires=1793100000&amp;signature=f00d"' in email.html
    assert "Confirm my email" in email.html


def test_choose_a_new_password(account):
    payload = account | {"url": "https://localhost/reset-password/t0ken?email=ane%40example.com", "expires_minutes": 60}

    email = render("reset_password", payload)

    assert email.subject == "Choose a new password for Guenaga Farmazeutika"
    assert "https://localhost/reset-password/t0ken?email=ane%40example.com" in email.text
    assert "your password stays as it is" in email.text
    assert "Choose a new password" in email.html


@pytest.mark.parametrize(
    ("event", "subject", "sentence"),
    [
        ("new_device_login", "New login to your Guenaga Farmazeutika account", "from a browser we haven't seen it use before"),
        ("password_changed", "Your password was changed", "every other browser was logged out"),
        ("password_reset", "Your password was reset", "with a link sent to this email"),
        ("two_factor_enabled", "2-step verification is on", "takes a code from your authenticator app"),
        ("two_factor_disabled", "2-step verification was turned off", "takes only your password now"),
        ("recovery_code_used", "A recovery code was used to log in", "That code doesn't work any more"),
    ],
)
def test_security_alerts(account, event, subject, sentence):
    payload = account | {
        "event": event,
        "when": "2026-10-27T21:05:00+01:00",
        "device": "Safari on iPhone",
        "ip": "203.0.113.7",
        "security_url": "https://localhost/account/security",
    }

    email = render("security_alert", payload)

    assert email.subject == subject
    assert sentence in email.text
    assert "When: 27 October 2026 at 21:05" in email.text
    assert "Device: Safari on iPhone" in email.text
    assert "https://localhost/account/security" in email.text
    assert sentence in email.html
    assert "Check my account’s security" in email.html


def test_an_unknown_alert_is_not_sent_with_a_gap(account):
    payload = account | {"event": "something_else", "when": "2026-10-27T21:05:00+01:00", "device": "x", "ip": "x", "security_url": "x"}

    with pytest.raises(RenderError):
        render("security_alert", payload)


def test_the_layout_is_not_a_kind_of_email(account):
    with pytest.raises(RenderError, match="Unknown kind"):
        render("_layout", account)


def test_values_from_the_request_are_escaped_in_the_html(account):
    payload = account | {
        "event": "new_device_login",
        "when": "2026-10-27T21:05:00+01:00",
        "device": "<script>alert(1)</script>",
        "ip": "203.0.113.7",
        "security_url": "https://localhost/account/security",
    }

    html = render("security_alert", payload).html

    assert "<script>" not in html
    assert "&lt;script&gt;alert(1)&lt;/script&gt;" in html


def test_an_account_email_is_addressed_like_the_order_email(account, settings):
    message = build_message(make_email(account | {"url": URL, "expires_minutes": 60}, kind="verify_email"), settings.mail_from)

    assert message["To"] == "Ane Etxeberria <ane@example.com>"
    assert message["Subject"] == "Confirm your email for Guenaga Farmazeutika"
    assert message["Message-ID"].startswith("<verify_email.1.")
