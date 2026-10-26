"""Settings from environment variables. A password can also come from the file that NAME_FILE
points to (a Docker secret), like the website's env_secret(), so it never sits in the
environment."""

import os
from collections.abc import Mapping
from dataclasses import dataclass
from email.errors import HeaderParseError
from email.headerregistry import Address
from pathlib import Path


class ConfigError(Exception):
    """A setting is missing or invalid."""


@dataclass(frozen=True)
class Settings:
    db_host: str
    db_port: int
    db_name: str
    db_user: str
    db_password: str
    mail_host: str
    mail_port: int
    # "smtp" upgrades the connection with STARTTLS, "smtps" is TLS from the first byte.
    # There is no unencrypted choice.
    mail_scheme: str
    # The CA that signed the mail server's certificate; None trusts the system's CAs
    mail_ca_file: str | None
    mail_username: str | None
    mail_password: str | None
    mail_from: Address
    poll_seconds: float
    max_attempts: int
    timeout_seconds: float = 10.0

    @classmethod
    def from_env(cls, env: Mapping[str, str] = os.environ) -> "Settings":
        scheme = env.get("MAIL_SCHEME") or "smtp"
        if scheme not in ("smtp", "smtps"):
            raise ConfigError(f"MAIL_SCHEME must be smtp (STARTTLS) or smtps (TLS), not {scheme!r}")
        username = env.get("MAIL_USERNAME") or None

        return cls(
            db_host=_required(env, "DB_HOST"),
            db_port=_number(env, "DB_PORT", 5432),
            db_name=_required(env, "DB_DATABASE"),
            db_user=_required(env, "DB_USERNAME"),
            db_password=_secret(env, "DB_PASSWORD"),
            mail_host=_required(env, "MAIL_HOST"),
            mail_port=_number(env, "MAIL_PORT", 465 if scheme == "smtps" else 587),
            mail_scheme=scheme,
            mail_ca_file=_readable_file(env, "MAIL_CA_FILE"),
            mail_username=username,
            mail_password=_secret(env, "MAIL_PASSWORD") if username else None,
            mail_from=_sender(env),
            poll_seconds=_number(env, "MAILER_POLL_SECONDS", 10.0, float),
            max_attempts=_number(env, "MAILER_MAX_ATTEMPTS", 8),
        )


def _required(env: Mapping[str, str], name: str) -> str:
    value = env.get(name, "").strip()
    if not value:
        raise ConfigError(f"{name} isn't set")
    return value


def _secret(env: Mapping[str, str], name: str) -> str:
    """The value of NAME, or else the contents of the file NAME_FILE names."""
    if value := env.get(name):
        return value
    path = env.get(f"{name}_FILE")
    if not path:
        raise ConfigError(f"Set {name}_FILE to the file holding the password")
    try:
        return Path(path).read_text(encoding="utf-8").strip()
    except OSError as error:
        raise ConfigError(f"Can't read {name}_FILE ({path}): {error.strerror}") from None


def _number(env: Mapping[str, str], name: str, default: float, kind: type = int) -> float:
    """A positive number of the given kind (int or float), or the default when unset."""
    raw = env.get(name)
    if not raw:
        return default
    try:
        value = kind(raw)
    except ValueError:
        raise ConfigError(f"{name} must be a number, not {raw!r}") from None
    if value <= 0:
        raise ConfigError(f"{name} must be more than 0")
    return value


def _readable_file(env: Mapping[str, str], name: str) -> str | None:
    path = env.get(name)
    if not path:
        return None
    if not os.access(path, os.R_OK):
        raise ConfigError(f"{name} ({path}) doesn't exist or can't be read")
    return path


def _sender(env: Mapping[str, str]) -> Address:
    address = _required(env, "MAIL_FROM_ADDRESS")
    try:
        sender = Address(display_name=env.get("MAIL_FROM_NAME", ""), addr_spec=address)
    except (ValueError, IndexError, HeaderParseError):
        sender = None
    if sender is None or not sender.domain:
        raise ConfigError(f"MAIL_FROM_ADDRESS isn't an email address: {address!r}")
    return sender
