# pki

`openssl.cnf` for each CA, the scripts that drive them, and public certificates and CRLs only. Private keys never enter the repo, and `.gitignore` blocks `*.key`, `*.pem`, `*.p12` and `*.pfx`. Issues #8–#10.
