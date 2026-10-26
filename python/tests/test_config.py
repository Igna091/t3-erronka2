import pytest

from mailer.config import ConfigError, Settings


@pytest.fixture
def env(tmp_path):
    (tmp_path / "db_password").write_text("db-secret\n")
    (tmp_path / "mail_password").write_text("smtp-secret")
    (tmp_path / "ca.crt").write_text("-----BEGIN CERTIFICATE-----\n")
    return {
        "DB_HOST": "db",
        "DB_DATABASE": "guenaga",
        "DB_USERNAME": "mailer",
        "DB_PASSWORD_FILE": str(tmp_path / "db_password"),
        "MAIL_HOST": "mailpit",
        "MAIL_PORT": "1025",
        "MAIL_CA_FILE": str(tmp_path / "ca.crt"),
        "MAIL_USERNAME": "mailer",
        "MAIL_PASSWORD_FILE": str(tmp_path / "mail_password"),
        "MAIL_FROM_ADDRESS": "no-reply@guenaga.eus",
        "MAIL_FROM_NAME": "Guenaga Farmazeutika",
    }


def test_passwords_come_from_secret_files(env):
    settings = Settings.from_env(env)

    assert settings.db_password == "db-secret"
    assert settings.mail_password == "smtp-secret"
    assert settings.db_port == 5432
    assert settings.mail_scheme == "smtp"
    assert str(settings.mail_from) == "Guenaga Farmazeutika <no-reply@guenaga.eus>"


def test_the_default_port_follows_the_scheme(env):
    del env["MAIL_PORT"]

    assert Settings.from_env(env).mail_port == 587
    assert Settings.from_env(env | {"MAIL_SCHEME": "smtps"}).mail_port == 465


@pytest.mark.parametrize(
    ("change", "error"),
    [
        ({"DB_HOST": ""}, "DB_HOST isn't set"),
        ({"DB_PASSWORD_FILE": "/nowhere/db_password"}, r"Can't read DB_PASSWORD_FILE \(/nowhere/db_password\)"),
        ({"MAIL_SCHEME": "plain"}, "MAIL_SCHEME must be smtp .* or smtps"),
        ({"MAIL_CA_FILE": "/nowhere/ca.crt"}, "MAIL_CA_FILE .* doesn't exist"),
        ({"MAIL_PORT": "twenty-five"}, "MAIL_PORT must be a number"),
        ({"MAILER_POLL_SECONDS": "0"}, "MAILER_POLL_SECONDS must be more than 0"),
        ({"MAIL_FROM_ADDRESS": "no-reply"}, "MAIL_FROM_ADDRESS isn't an email address"),
    ],
)
def test_a_bad_setting_says_which_one(env, change, error):
    with pytest.raises(ConfigError, match=error):
        Settings.from_env(env | change)
