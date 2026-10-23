# t3-erronka2: Guenaga Farmazeutika

Erronka 2: we rebuild Guenaga Farmazeutika's infrastructure across two sites,
headquarters (EN) and unit U1. Part 1 (build) is presented on 13 Nov 2026 and
part 2 (attack, forensics and governance) on 16 Dec 2026.

## Layout

```
t3-erronka2/
├── .github/        CODEOWNERS, CI/CD workflows
├── docs/           plan, diagrams, decisions, presentations
├── network/        pfSense config exports (sanitised), MikroTik .rsc exports
├── pki/            openssl.cnf per CA, scripts, public certs and CRLs only
├── vpn/            IPsec and OpenVPN settings, test evidence
├── docker/         daemon.json baseline, docker-bench results per host
├── web/            Laravel app, Dockerfile, compose.yaml, nginx, php.ini, PostgreSQL roles
├── python/         users-without-image mailer + Dockerfile
├── hardening/      baselines, GPO exports, backup scripts
├── monitoring/     Wazuh compose file, rules and decoders
├── ot/             PLC programs, SCADA config, honeypot compose file
├── pentest/        reports per scope
├── forensics/      procedure, chain of custody, report
└── grc/            SGSI, risk analysis, plans, compliance
```

## Rules

- No private key, password or `.env` file is ever committed. `.gitignore` blocks
  `*.key`, `*.pem`, `*.p12`, `*.pfx`, `*.kdbx` and `.env`; public certificates go in as `.crt`.
- Changes reach `main` through a pull request with one review.
- Text files use LF line endings (`.gitattributes`), so scripts work inside Linux
  containers even when the repo is cloned on Windows.

## Start here

[web/README.md](web/README.md) runs the website stack (Nginx, Laravel on PHP 8.5.12, PostgreSQL) on any machine with Docker.
