# pki

A two-tier PKI (issues #8–#10). The offline Root CA signs two intermediates, each on the platform
it serves: the **Infra TLS CA** on ca01 (OpenSSL, this folder) for the website and everything
outside the domain, and a Windows **AD CS Enterprise Subordinate CA** for the domain side, with
auto-enrollment. Every certificate carries its CRL and issuer URLs on `http://pki.guenaga.lan`.

```
Guenaga Farmazeutika Root CA        offline Debian VM, no network        root/root-ca.sh
├── Guenaga Infra TLS CA            ca01 (10.10.64.20), Ubuntu Server    ca01/setup.sh, then guenaga-ca
│     www.guenaga.eus, fw-en, vpn-en, vpn-u1, rt-en, rt-u1, rt-ot, hv-en, radius, sec01
└── Guenaga Users CA                Windows AD CS (Enterprise Subordinate)
      domain controllers, computers, users, Wi-Fi EAP-TLS, VPN users
```

| CA | Key | Lasts | Limits |
|---|---|---|---|
| Root | RSA 4096, AES-256 encrypted, SHA-256 | 10 years | Signs intermediates only; CRL every 180 days |
| Infra TLS | RSA 4096, SHA-256 | 5 years | `pathlen:0`; serverAuth and IPsec IKE only; CRL every 7 days |
| Users (AD CS) | set in AD CS | 5 years | `pathlen:0` |
| Leaf certificates | RSA 2048, SHA-256 | 397 days | Names (SAN) required, from the command line only |

Private keys never enter the repo: `.gitignore` blocks `*.key`, `*.pem`, `*.p12` and `*.pfx`.
Public certificates go in `certs/` once they exist.

| Path | What |
|---|---|
| `root/root-ca.sh`, `root/openssl.cnf` | The Root CA: create it, sign intermediates, CRLs, revoke |
| `ca01/setup.sh` | Turns an Ubuntu (or Debian) server into ca01 in one command |
| `infra-tls/guenaga-ca`, `infra-tls/openssl.cnf` | The Infra TLS CA's tool and config, installed on ca01 by `setup.sh` |
| `infra-tls/systemd/` | The timer that renews and publishes the CRL daily |

## 1. The root ceremony (once)

On the root VM (Debian, no network adapter), with this `root/` folder copied from a USB drive:

```sh
sudo ./root-ca.sh init
```

It asks for a new passphrase and makes the root's key and certificate and its first CRL. Back
up `/var/lib/guenaga-root-ca` to the two encrypted USB drives, and keep `ceremony.log` (who,
when, fingerprints and file hashes) for the record. `out/` holds `root.crt` and `root.crl`: commit
`root.crt` to `pki/certs/`.

## 2. ca01

On a fresh Ubuntu Server 24.04 VM in VLAN 64 (10.10.64.20), with this repo cloned on it:

```sh
sudo bash pki/ca01/setup.sh --admin-net 10.10.112.0/24
```

It installs OpenSSL, the CA's config, the `guenaga-ca` tool and the daily CRL timer, and makes
the CA's key and its request, `/var/lib/guenaga-ca/infra-tls.csr`. `--admin-net` turns on a
firewall that lets nothing in but SSH from VLAN 112 SISTEMA: leave it out if you're connected
from anywhere else, or you lock yourself out. Running it again is safe.

## 3. The root signs ca01

1. Copy `infra-tls.csr` to the USB drive. `setup.sh` prints its SHA-256.
2. On the root VM: `sudo ./root-ca.sh sign infra-tls.csr tls`. It shows the request's SHA-256:
   it must match. Type `yes`.
3. Bring `out/guenaga-infra-tls-ca.crt`, `out/root.crt` and `out/root.crl` back to ca01:

```sh
sudo guenaga-ca install-chain guenaga-infra-tls-ca.crt root.crt root.crl
```

It checks the certificate is for ca01's own key and signed by the root, makes ca01 trust the
root, and makes the first CRL. Commit `guenaga-infra-tls-ca.crt` to `pki/certs/`.

The AD CS subordinate is signed the same way, with its request from the Windows CA's setup:
`sudo ./root-ca.sh sign <its .req> windows`.

## 4. Issuing certificates

On ca01, one command per certificate in the plan:

```sh
sudo guenaga-ca issue www.guenaga.eus                    # web01: fullchain.crt → web/secrets/tls.crt, the key → tls.key
sudo guenaga-ca issue fw-en.guenaga.lan                  # pfSense GUI
sudo guenaga-ca issue --ipsec vpn-en.guenaga.lan         # IPsec gateways
sudo guenaga-ca issue --ipsec vpn-u1.guenaga.lan
sudo guenaga-ca issue rt-en.guenaga.lan                  # and rt-u1, rt-ot
sudo guenaga-ca issue hv-en.guenaga.lan                  # the hypervisor
sudo guenaga-ca issue sec01.guenaga.lan                  # Wazuh dashboard
sudo guenaga-ca issue radius.guenaga.lan                 # the RADIUS EAP certificate
```

More names or IP addresses can follow the first one (`issue rt-en.guenaga.lan 10.10.112.1`).
Each lands in `/var/lib/guenaga-ca/issued/<name>/`: the certificate, its key, `chain.crt` (Infra TLS
+ root) and `fullchain.crt` (certificate + Infra TLS). A server that can make its own key and
request should, and keep the key: `sudo guenaga-ca sign <request.csr> <name>`. Issuing a name
again keeps the previous files in a dated folder. `sudo guenaga-ca list` shows them all.

## Revocation and CRLs

- `sudo guenaga-ca revoke <name> [reason]` revokes a certificate and publishes a new CRL at once.
  The Infra TLS CRL lasts 7 days; `guenaga-ca-crl.timer` renews it every day.
- The root's CRL lasts 180 days, so the root VM comes out at least every 5 months:
  `sudo ./root-ca.sh crl`, then on ca01 `sudo guenaga-ca root-crl root.crl`.
- Every step is logged to syslog as `guenaga-ca` (for Wazuh), and at the root in `ceremony.log`.

## Publishing (pki-pub)

pki-pub serves `/crl/infra-tls.crl`, `/crl/root.crl`, `/ca/infra-tls.crt` and `/ca/root.crt` (DER) at
`http://pki.guenaga.lan`. Until it exists, `guenaga-ca` keeps them in `/var/lib/guenaga-ca/pub`.
Once it does, give it the public key in `/etc/guenaga-ca/publish_ed25519.pub` (SFTP only) and set
`PUBLISH_TARGET` in `/etc/guenaga-ca/publish.conf`; every new CRL is then copied there.

## On ca01

| Path | What |
|---|---|
| `/etc/guenaga-ca/` (root only) | The config, the passphrase of the CA's key, and the publishing key |
| `/var/lib/guenaga-ca/infra-tls/` | The CA: its encrypted key, its certificate, the database of issued certificates, the CRL |
| `/var/lib/guenaga-ca/issued/` | Every certificate issued, with the keys `issue` made |
| `/var/lib/guenaga-ca/pub/` | What pki-pub serves |

The CA's key is encrypted with a random passphrase kept in `/etc`, apart from its data, so a copy
of `/var/lib/guenaga-ca` alone can't sign anything. A backup needs both folders, kept apart.
