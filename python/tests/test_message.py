"""The confirmation email: what it says, its headers, and what a hostile payload can't do."""

import pytest

from mailer.message import build_message
from mailer.render import RenderError, euros, long_date
from tests.conftest import make_email


def parts(message):
    return {part.get_content_type(): part.get_content() for part in message.iter_parts()}


def test_card_order(payload, settings):
    message = build_message(make_email(payload), settings.mail_from)
    content = parts(message)

    assert message["Subject"] == "Your order GF-7KQ2M9XA is confirmed"
    assert message["From"] == "Guenaga Farmazeutika <no-reply@guenaga.eus>"
    assert message["To"] == "Ane Etxeberria <ane@example.com>"
    assert message["Auto-Submitted"] == "auto-generated"
    assert message.get_content_type() == "multipart/alternative"

    text = content["text/plain"]
    assert "- Paracetamol 500 mg × 2: €3.90" in text
    assert "Total: €8.85" in text
    assert "Paid by card ending 4242. It was a demo card, so nothing was charged." in text
    assert "Placed on 26 October 2026 at 9:05" in text
    assert "Monday to Friday: 9:00–21:00" in text

    html = content["text/html"]
    assert "Paracetamol 500 mg" in html
    assert "€8.85" in html
    assert "card ending 4242" in html
    assert 'href="tel:+34000000000"' in html


def test_paying_at_the_counter(payload, settings):
    payload["payment"] = {"status": "at_counter", "card_last4": None}

    content = parts(build_message(make_email(payload), settings.mail_from))

    assert "You pay €8.85 at the counter when you collect it, cash or card." in content["text/plain"]
    assert "card ending" not in content["text/plain"]
    assert "You pay €8.85 at the counter" in content["text/html"]


def test_the_html_escapes_names_and_medicines(payload, settings):
    payload["to"]["name"] = '<a href="https://evil.example">Ane</a> & co'
    payload["items"][0]["name"] = "<script>alert(1)</script>"

    html = parts(build_message(make_email(payload), settings.mail_from))["text/html"]

    assert "&lt;a href=&#34;https://evil.example&#34;&gt;Ane&lt;/a&gt; &amp; co" in html
    assert "&lt;script&gt;alert(1)&lt;/script&gt;" in html
    assert "evil.example\"" not in html
    assert "<script>" not in html


def test_the_html_loads_nothing_from_the_web(payload, settings):
    html = parts(build_message(make_email(payload), settings.mail_from))["text/html"]

    assert "<img" not in html
    assert "url(" not in html
    assert "<link" not in html


@pytest.mark.parametrize("name", ["Ane\r\nBcc: all@example.com", "Ane\nBcc: all@example.com", "Ane\x00"])
def test_a_line_break_in_the_name_cannot_add_headers(payload, settings, name):
    payload["to"]["name"] = name

    with pytest.raises(RenderError, match="control characters"):
        build_message(make_email(payload), settings.mail_from)


@pytest.mark.parametrize("address", ["ane@example.com\r\nBcc: all@example.com", "not an address", ""])
def test_a_bad_address_is_refused(payload, settings, address):
    payload["to"]["email"] = address

    with pytest.raises(RenderError):
        build_message(make_email(payload), settings.mail_from)


def test_a_missing_field_is_an_error_not_a_gap(payload, settings):
    del payload["pharmacy"]["address"]

    with pytest.raises(RenderError, match="address"):
        build_message(make_email(payload), settings.mail_from)


@pytest.mark.parametrize("kind", ["newsletter", "../order_confirmation", "Order_Confirmation"])
def test_only_known_kinds_of_email(payload, settings, kind):
    with pytest.raises(RenderError, match="Unknown kind"):
        build_message(make_email(payload, kind=kind), settings.mail_from)


def test_every_attempt_has_the_same_message_id(payload, settings):
    first = build_message(make_email(payload, id=7), settings.mail_from)
    again = build_message(make_email(payload, id=7, attempts=3), settings.mail_from)

    assert first["Message-ID"] == again["Message-ID"] == "<order_confirmation.7.20261026080500@guenaga.eus>"


@pytest.mark.parametrize(("cents", "text"), [(0, "€0.00"), (5, "€0.05"), (195, "€1.95"), (123450, "€1,234.50")])
def test_prices_look_like_the_sites(cents, text):
    assert euros(cents) == text


def test_dates_look_like_the_sites():
    assert long_date("2026-10-26T21:30:00+01:00") == "26 October 2026 at 21:30"
