"""The acceptance criterion of issue #22: with the database or the mail server down, the mailer
logs a clear error and exits, without a traceback."""

import logging

import pytest

from mailer import cli, heartbeat
from tests.conftest import FakeOutbox, make_email


@pytest.fixture
def env(monkeypatch, tmp_path):
    (tmp_path / "password").write_text("secret")
    settings = {
        # Nothing listens on port 1 of the loopback, so both connections are refused at once
        "DB_HOST": "127.0.0.1",
        "DB_PORT": "1",
        "DB_DATABASE": "guenaga",
        "DB_USERNAME": "mailer",
        "DB_PASSWORD_FILE": str(tmp_path / "password"),
        "MAIL_HOST": "127.0.0.1",
        "MAIL_PORT": "1",
        "MAIL_FROM_ADDRESS": "no-reply@guenaga.eus",
    }
    for name, value in settings.items():
        monkeypatch.setenv(name, value)


def errors(caplog):
    return [record for record in caplog.records if record.levelno >= logging.ERROR]


def test_with_the_database_down(env, caplog):
    assert cli.main(["--once"]) == 1

    [error] = errors(caplog)
    assert error.getMessage().startswith("Can't use the database guenaga at 127.0.0.1:1: ")
    assert error.exc_info is None


def test_with_the_mail_server_down(env, caplog, monkeypatch, payload):
    monkeypatch.setattr(cli, "Outbox", lambda settings: FakeOutbox(make_email(payload)))

    assert cli.main(["--once"]) == 1

    [error] = errors(caplog)
    assert error.getMessage().startswith("Can't reach the mail server at 127.0.0.1:1: ")
    assert error.exc_info is None


def test_with_a_missing_setting(env, caplog, monkeypatch):
    monkeypatch.delenv("MAIL_HOST")

    assert cli.main(["--once"]) == 2
    assert errors(caplog)[0].getMessage() == "Bad setting: MAIL_HOST isn't set"


def test_the_photo_reminder_with_the_database_down(env, caplog, monkeypatch):
    monkeypatch.setenv("SITE_URL", "https://localhost")

    assert cli.main(["--remind-photos"]) == 1

    [error] = errors(caplog)
    assert error.getMessage().startswith("Can't use the database guenaga at 127.0.0.1:1: ")
    assert error.exc_info is None


def test_the_photo_reminder_needs_the_site_url(env, caplog, monkeypatch):
    monkeypatch.delenv("SITE_URL", raising=False)

    assert cli.main(["--remind-photos"]) == 2
    assert errors(caplog)[0].getMessage().startswith("Bad setting: SITE_URL isn't set")


def test_the_reminder_and_the_outbox_are_separate_runs(env, capsys):
    with pytest.raises(SystemExit) as exit:
        cli.main(["--once", "--remind-photos"])

    assert exit.value.code == 2
    assert "not allowed with argument" in capsys.readouterr().err


def test_the_health_check_follows_the_heartbeat(tmp_path, monkeypatch):
    monkeypatch.setattr(heartbeat, "FILE", tmp_path / "heartbeat")
    assert not heartbeat.is_fresh()

    heartbeat.beat()

    assert heartbeat.is_fresh()
    assert not heartbeat.is_fresh(now=heartbeat.FILE.stat().st_mtime + 61)
