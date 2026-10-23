# docker

The baseline every container host shares: web01, sec01, pki-pub and ot-dmz01 (issue #17).
Per-container hardening (read-only root, `cap_drop: ALL`, limits, internal networks) lives in
each stack's `compose.yaml`; this folder covers the engine itself.

## daemon.json

| Setting | Why |
|---|---|
| `userns-remap: default` | root in a container maps to an unprivileged UID range on the host |
| `live-restore: true` | containers keep running while the daemon restarts or upgrades |
| `no-new-privileges: true` | no container can gain privileges through setuid binaries |
| `icc: false` | containers on the default bridge cannot talk to each other |
| `userland-proxy: false` | published ports go through iptables NAT, not a `docker-proxy` process |
| `log-opts` | json-file logs rotate at 10 MB, 5 files per container |

Two side effects to know about:

- **userns-remap:** bind-mounted files must be readable by the remapped UID, for example
  `100000 + 101` for nginx with the default `/etc/subuid` range. Enable it *before* pulling
  images; switching it later hides the existing images and volumes, which live in a different directory.
- **userland-proxy off:** published ports no longer show up in `ss -tlnp`. Check them with
  `docker compose ps` on the host and `nmap` from another machine instead.

## Install on a Debian 13 host

```sh
# Docker's official apt repo
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
sudo tee /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: $(. /etc/os-release && echo "$VERSION_CODENAME")
Components: stable
Signed-By: /etc/apt/keyrings/docker.asc
EOF
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Baseline
sudo install -m 0644 docker/daemon.json /etc/docker/daemon.json
sudo systemctl restart docker
docker info --format '{{.SecurityOptions}}'   # should list name=userns
```

Keep the `docker` group empty: being in it is equivalent to root. Admins use `sudo docker`.
Never mount `/var/run/docker.sock` into a container or expose the API over TCP.

## Host firewall

Docker's own iptables rules bypass INPUT-chain rules for published ports, so per-host filtering
goes in the `DOCKER-USER` chain. Write the rules on each real host, once its interface names and
zones are known (#6, #7), and commit them here as `docker-user-<host>.sh`.

## docker-bench-security

Run the script from a clone on the host; the container version needs the Docker socket mounted, which this baseline forbids:

```sh
git clone https://github.com/docker/docker-bench-security.git /tmp/docker-bench
cd /tmp/docker-bench && sudo sh docker-bench-security.sh -l /tmp/bench-$(hostname).log
```

Commit the log as `bench/<host>.log` and write a reason for every remaining `WARN` in
`bench/<host>-exceptions.md` (the acceptance criterion of #17).

## Dev machines

Docker Desktop does not use this `daemon.json`. The stacks still run hardened locally
because their compose files carry the per-container settings.
