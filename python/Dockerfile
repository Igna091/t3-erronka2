# The mailer (issue #22): sends the emails the website queues, over SMTP with TLS.
# Bump the tag and the digest together:  docker buildx imagetools inspect python:<tag>
#
# Stages: base (Python and the pinned libraries) -> dev (adds pytest; the "mailer-tests" service
#         in web/compose.dev.yaml) -> runtime (what web01 runs; no pip).

FROM python:3.15.0-slim-trixie@sha256:f5b92f2a669a4c347044f29367f31983280b648250ca94dea66f98c53bc87e58 AS base

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PIP_ROOT_USER_ACTION=ignore

WORKDIR /app

# Only ready-built wheels whose hashes match requirements.txt: a swapped or tampered package
# fails the build, and nothing is compiled from source
COPY requirements.txt .
RUN pip install --require-hashes --only-binary=:all: -r requirements.txt

FROM base AS dev
COPY requirements-dev.txt .
RUN pip install --require-hashes --only-binary=:all: -r requirements-dev.txt

FROM base AS runtime
# Without pip nothing can be installed into a running container, and an unprivileged user
# with a fixed uid that owns no files
RUN python -m pip uninstall --yes pip \
    && groupadd --system --gid 10001 mailer \
    && useradd --system --uid 10001 --gid mailer --no-create-home --home-dir /nonexistent --shell /usr/sbin/nologin mailer
COPY mailer/ mailer/
# The root filesystem is read-only at run time, so the bytecode is compiled now
RUN python -m compileall -q mailer
USER 10001:10001
ENTRYPOINT ["python", "-m", "mailer"]
