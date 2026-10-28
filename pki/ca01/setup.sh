#!/usr/bin/env bash
# Sets up ca01 (10.10.64.20, VLAN 64 ZERB) as the Guenaga Infra TLS CA (issue #9), from a copy of
# this repo on the server. Ubuntu Server 24.04 or Debian 13, as root:
#
#   sudo bash pki/ca01/setup.sh [--admin-net 10.10.112.0/24]
#
# It installs OpenSSL, the CA's config and the guenaga-ca tool, the daily CRL timer, and makes the
# CA's key and its request for the offline root. --admin-net also turns on a firewall that lets
# nothing in but SSH from that network: leave it out when you're connected from anywhere else.
# Running it again is safe: it keeps the CA's key and data.
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
pki="$(cd "$here/.." && pwd)"
etc=/etc/guenaga-ca
home=/var/lib/guenaga-ca

die() { echo "setup: $*" >&2; exit 1; }

admin_net=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --admin-net)
            admin_net="${2:-}"
            [[ "$admin_net" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}/[0-9]{1,2}$ ]] || die "--admin-net needs a network such as 10.10.112.0/24"
            shift 2 ;;
        *) die "unknown option $1 (usage: setup.sh [--admin-net CIDR])" ;;
    esac
done

[[ $EUID -eq 0 ]] || die "run it as root: sudo bash $0"
# shellcheck source=/dev/null
. /etc/os-release
[[ "$ID" == ubuntu || "$ID" == debian || "${ID_LIKE:-}" == *debian* ]] || die "made for Ubuntu or Debian, not $ID"
[[ -f "$pki/infra-tls/openssl.cnf" && -f "$pki/infra-tls/guenaga-ca" ]] || die "run it from the repo: pki/infra-tls/ is missing"

echo "== Packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq --no-install-recommends openssl ca-certificates openssh-client > /dev/null

echo "== The CA's config and tool"
install -d -m 750 "$etc"
install -m 644 "$pki/infra-tls/openssl.cnf" "$etc/infra-tls.cnf"
install -m 755 "$pki/infra-tls/guenaga-ca" /usr/local/sbin/guenaga-ca
if [[ ! -f "$etc/publish.conf" ]]; then
    cat > "$etc/publish.conf" <<'EOF'
# Where guenaga-ca copies the CRLs and the CA certificates: pki-pub (issue #9), as user@host:path,
# for example PUBLISH_TARGET="pki@pki.guenaga.lan:/srv/pki/". Empty: they stay in
# /var/lib/guenaga-ca/pub. pki-pub must accept the key in /etc/guenaga-ca/publish_ed25519.pub.
PUBLISH_TARGET=""
EOF
    chmod 640 "$etc/publish.conf"
fi
# The key ca01 copies to pki-pub with: only for that, so pki-pub can restrict it to SFTP
if [[ ! -f "$etc/publish_ed25519" ]]; then
    ssh-keygen -q -t ed25519 -N '' -C "ca01 publish to pki-pub" -f "$etc/publish_ed25519"
fi

echo "== Daily CRL timer"
install -m 644 "$pki/infra-tls/systemd/guenaga-ca-crl.service" "$pki/infra-tls/systemd/guenaga-ca-crl.timer" /etc/systemd/system/
if [[ -d /run/systemd/system ]]; then
    systemctl daemon-reload
    systemctl enable --now guenaga-ca-crl.timer > /dev/null
else
    echo "systemd isn't running here: enable guenaga-ca-crl.timer once it is"
fi

if [[ -n "$admin_net" ]]; then
    echo "== Firewall: only SSH from $admin_net comes in"
    apt-get install -y -qq --no-install-recommends nftables > /dev/null
    cat > /etc/nftables.conf <<EOF
#!/usr/sbin/nft -f
# ca01 (pki/ca01/setup.sh): nothing comes in but SSH from the admin network. ca01 starts its
# own connections (updates, publishing to pki-pub) and needs no open port for the CA itself.
flush ruleset

table inet filter {
    chain input {
        type filter hook input priority 0; policy drop;
        iif lo accept
        ct state established,related accept
        ct state invalid drop
        meta l4proto { icmp, ipv6-icmp } limit rate 10/second accept
        ip saddr $admin_net tcp dport 22 accept
        limit rate 10/minute log prefix "ca01 drop: "
    }
    chain forward {
        type filter hook forward priority 0; policy drop;
    }
}
EOF
    if [[ -d /run/systemd/system ]]; then
        systemctl enable --now nftables > /dev/null
        nft -f /etc/nftables.conf
    fi
fi

echo "== The CA's key and request"
if [[ -f "$home/infra-tls/private/infra-tls.key" ]]; then
    echo "The CA already has a key; keeping it."
else
    /usr/local/sbin/guenaga-ca init
fi

csr="$home/infra-tls.csr"
if [[ -f "$home/infra-tls/certs/infra-tls.crt" ]]; then
    echo
    echo "ca01 is ready and signed: guenaga-ca list, guenaga-ca issue <name> ..."
else
    cat <<EOF

ca01 is ready. Next, the offline root signs the CA:

  1. Copy $csr to a USB drive.
     Its SHA-256, to compare at the root: $(openssl req -in "$csr" -outform DER | sha256sum | cut -d' ' -f1)
  2. On the root VM:  sudo ./root-ca.sh sign infra-tls.csr tls
  3. Bring back out/guenaga-infra-tls-ca.crt, out/root.crt and out/root.crl, then here:
     sudo guenaga-ca install-chain guenaga-infra-tls-ca.crt root.crt root.crl
  4. Then issue certificates, for example:  sudo guenaga-ca issue www.guenaga.eus
EOF
fi
