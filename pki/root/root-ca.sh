#!/usr/bin/env bash
# The Guenaga Farmazeutika Root CA (issue #8), on the offline root VM: Debian with no network
# adapter, powered off between ceremonies. Copy this folder over on a USB drive, then, as root:
#
#   ./root-ca.sh init                          create the root key and certificate, and the first CRL
#   ./root-ca.sh sign <request.csr> [tls|windows]
#                                              sign an intermediate CA: tls = ca01's Infra TLS CA,
#                                              windows = the AD CS Enterprise Subordinate CA
#   ./root-ca.sh crl                           a new root CRL (it lasts 180 days)
#   ./root-ca.sh revoke <intermediate.crt>     revoke an intermediate, then a new CRL
#
# Every command asks for the root key's passphrase. What it writes to out/ (certificates and
# CRLs only, never the key) goes back on the USB drive; ceremony.log records each step.
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
export ROOT_CA_DIR="${ROOT_CA_DIR:-/var/lib/guenaga-root-ca}"
cnf="$here/openssl.cnf"
key="$ROOT_CA_DIR/private/root.key"
crt="$ROOT_CA_DIR/certs/root.crt"
out="$here/out"
log="$here/ceremony.log"

# The passphrase is typed at each prompt. ROOT_CA_PASS_FILE is only for the automated tests.
pass=()
if [[ -n "${ROOT_CA_PASS_FILE:-}" ]]; then
    pass=(-passin "file:$ROOT_CA_PASS_FILE")
fi

die() { echo "root-ca: $*" >&2; exit 1; }

record() {
    printf '%s  %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >> "$log"
    echo "$*"
}

# A new CRL, as PEM and as DER (the format served over HTTP), copied to out/
new_crl() {
    openssl ca -config "$cnf" -gencrl "${pass[@]}" -out "$ROOT_CA_DIR/crl/root.crl.pem"
    openssl crl -in "$ROOT_CA_DIR/crl/root.crl.pem" -outform DER -out "$out/root.crl"
    record "root CRL $(openssl crl -in "$ROOT_CA_DIR/crl/root.crl.pem" -noout -nextupdate), sha256 $(sha256sum "$out/root.crl" | cut -d' ' -f1)"
}

cmd_init() {
    [[ -e "$key" ]] && die "a root already exists in $ROOT_CA_DIR"
    umask 077
    mkdir -p "$ROOT_CA_DIR"/{certs,crl,newcerts,private} "$out"
    chmod 700 "$ROOT_CA_DIR/private"
    : > "$ROOT_CA_DIR/index.txt"
    openssl rand -hex 16 > "$ROOT_CA_DIR/serial"
    echo 1000 > "$ROOT_CA_DIR/crlnumber"

    echo "Choose the root key's passphrase (it is asked twice). Write it down for the two backups."
    if [[ -n "${ROOT_CA_PASS_FILE:-}" ]]; then
        openssl genpkey -quiet -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -aes-256-cbc -pass "file:$ROOT_CA_PASS_FILE" -out "$key"
    else
        openssl genpkey -quiet -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -aes-256-cbc -out "$key"
    fi
    chmod 400 "$key"

    openssl req -config "$cnf" -new -x509 -sha256 -days 3650 -extensions v3_root "${pass[@]}" -key "$key" -out "$crt"
    umask 022
    cp "$crt" "$out/root.crt"
    record "root created: $(openssl x509 -in "$crt" -noout -subject), $(openssl x509 -in "$crt" -noout -enddate)"
    record "root SHA-256 fingerprint $(openssl x509 -in "$crt" -noout -fingerprint -sha256 | cut -d= -f2)"
    record "root key sha256 $(sha256sum "$key" | cut -d' ' -f1) (back it up to both USB drives now)"
    new_crl
    echo "Done. out/ holds root.crt and root.crl for ca01 and pki-pub; commit root.crt to pki/certs/."
}

cmd_sign() {
    local csr="${1:-}" profile="${2:-tls}"
    [[ -f "$key" ]] || die "no root yet: run init first"
    [[ -f "$csr" ]] || die "usage: root-ca.sh sign <request.csr> [tls|windows]"
    [[ "$profile" == tls || "$profile" == windows ]] || die "the profile is tls or windows, not $profile"
    openssl req -in "$csr" -noout -verify 2> /dev/null || die "$csr isn't a valid signed request"

    local subject cn name
    subject="$(openssl req -in "$csr" -noout -subject -nameopt RFC2253 | sed 's/^subject=//')"
    cn="$(openssl req -in "$csr" -noout -subject -nameopt multiline | sed -n 's/^ *commonName *= //p')"
    name="$(echo "$cn" | tr '[:upper:] ' '[:lower:]-' | tr -cd 'a-z0-9-')"
    [[ -n "$name" ]] || die "the request has no common name"

    echo "Request:   $subject"
    echo "Key:       $(openssl req -in "$csr" -noout -text | sed -n 's/^ *Public-Key: //p')"
    echo "Profile:   $profile (5 years, pathlen:0)"
    echo "Request SHA-256: $(openssl req -in "$csr" -outform DER | sha256sum | cut -d' ' -f1)"
    read -r -p "Compare the fingerprint with the one the CA printed. Sign it? Type yes: " answer
    [[ "$answer" == yes ]] || die "not signed"

    # AD CS expects its certificate's subject exactly as it wrote it (DC=lan, DC=guenaga, CN=...),
    # so for it the subject keeps the request's order instead of the policy's
    local keep=()
    [[ "$profile" == windows ]] && keep=(-preserveDN)
    mkdir -p "$out"
    openssl ca -config "$cnf" -batch -notext "${pass[@]}" "${keep[@]}" \
        -policy "policy_$profile" -extensions "v3_intermediate_$profile" \
        -in "$csr" -out "$out/$name.crt"
    openssl verify -CAfile "$crt" "$out/$name.crt" > /dev/null
    record "signed $subject as $profile, serial $(openssl x509 -in "$out/$name.crt" -noout -serial | cut -d= -f2), $(openssl x509 -in "$out/$name.crt" -noout -enddate)"
    cp "$crt" "$out/root.crt"
    new_crl
    echo "Done. Take out/$name.crt, out/root.crt and out/root.crl back to the CA."
}

cmd_crl() {
    [[ -f "$key" ]] || die "no root yet: run init first"
    mkdir -p "$out"
    new_crl
}

cmd_revoke() {
    local cert="${1:-}"
    [[ -f "$cert" ]] || die "usage: root-ca.sh revoke <intermediate.crt>"
    openssl ca -config "$cnf" "${pass[@]}" -revoke "$cert" -crl_reason keyCompromise
    record "revoked $(openssl x509 -in "$cert" -noout -subject), serial $(openssl x509 -in "$cert" -noout -serial | cut -d= -f2)"
    new_crl
}

case "${1:-}" in
    init) cmd_init ;;
    sign) shift; cmd_sign "$@" ;;
    crl) cmd_crl ;;
    revoke) shift; cmd_revoke "$@" ;;
    *) sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac
