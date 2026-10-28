<#
.SYNOPSIS
    Copies the Guenaga Users CA's CRL and certificate to pki-pub (issue #9), which serves them at
    http://pki.guenaga.lan/crl/users-ca.crl and /ca/users-ca.crt.

.DESCRIPTION
    Complete-UsersCA.ps1 installs it in C:\PKI and runs it every day as SYSTEM. The files are always
    refreshed in C:\PKI\pub; they reach pki-pub once PUBLISH_TARGET is set in C:\PKI\publish.conf.
#>
[CmdletBinding()]
param([string]$WorkDir = 'C:\PKI')
$ErrorActionPreference = 'Stop'

$pub = Join-Path $WorkDir 'pub'
New-Item -ItemType Directory -Force "$pub\crl", "$pub\ca" | Out-Null
Copy-Item "$env:SystemRoot\System32\CertSrv\CertEnroll\users-ca.crl" "$pub\crl\users-ca.crl" -Force
# The CA's own certificate, DER, as the AIA URL serves it
& certutil.exe -f -ca.cert "$pub\ca\users-ca.crt" | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'certutil -ca.cert failed: is the CA running?' }

$target = ''
$conf = Join-Path $WorkDir 'publish.conf'
if (Test-Path $conf) {
    $line = Get-Content $conf | Where-Object { $_ -match '^\s*PUBLISH_TARGET\s*=' } | Select-Object -Last 1
    if ($line -match '=\s*"?([^"]*)"?\s*$') { $target = $Matches[1].Trim() }
}
if (-not $target) {
    Write-Output "Published to $pub (set PUBLISH_TARGET in $conf to copy it to pki-pub)"
    return
}

# pki-pub's host key is remembered on first contact and must match from then on
& scp.exe -q -r -i (Join-Path $WorkDir 'publish_ed25519') -o BatchMode=yes `
    -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile="$(Join-Path $WorkDir 'known_hosts')" `
    "$pub\crl" "$pub\ca" $target
if ($LASTEXITCODE -ne 0) { throw "Could not copy to $target" }
Write-Output "Published the CRL and CA certificate to $target"
