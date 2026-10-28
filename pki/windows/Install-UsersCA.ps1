<#
.SYNOPSIS
    Step 1 of the Guenaga Users CA (issue #9): installs AD CS as an Enterprise Subordinate CA and
    writes its request for the offline root.

.DESCRIPTION
    Run it in an elevated PowerShell, as an Enterprise Admin, on the domain-joined Windows Server
    that will be the CA (ca-win01; or dc01 in the lab, see pki/README.md):

        .\Install-UsersCA.ps1

    It writes CAPolicy.inf (RSA 4096 renewals, no default templates), installs the AD CS role and
    makes the CA's key and request, C:\PKI\users-ca.req. The root signs that request
    (root-ca.sh sign users-ca.req windows); Complete-UsersCA.ps1 then installs the result.
#>
[CmdletBinding()]
param(
    [string]$CommonName = 'Guenaga Users CA',
    [string]$WorkDir = 'C:\PKI'
)
$ErrorActionPreference = 'Stop'

# An Enterprise CA can only be installed by an Enterprise Admin (SID ending in -519, whatever the
# language of Windows), on a machine in the domain
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
if (-not ([Security.Principal.WindowsPrincipal]$identity).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run it in an elevated PowerShell (Run as administrator).'
}
if (-not (Get-CimInstance Win32_ComputerSystem).PartOfDomain) {
    throw 'This server is not in the domain: join it to guenaga.lan first (issue #14).'
}
if (-not ($identity.Groups | Where-Object { $_.Value -match '-519$' })) {
    throw 'Log on as a member of Enterprise Admins: only they can install an Enterprise CA.'
}
if (Get-Service CertSvc -ErrorAction SilentlyContinue) {
    throw 'AD CS is already installed here. If its request is waiting for the root, run Complete-UsersCA.ps1.'
}

New-Item -ItemType Directory -Force $WorkDir | Out-Null

# Read by AD CS during setup. LoadDefaultTemplates=0: no template is offered until
# Complete-UsersCA.ps1 publishes the ones we chose. Renewals keep RSA 4096.
$capolicy = @'
[Version]
Signature="$Windows NT$"

[Certsrv_Server]
RenewalKeyLength=4096
RenewalValidityPeriod=Years
RenewalValidityPeriodUnits=5
LoadDefaultTemplates=0
AlternateSignatureAlgorithm=0
CRLPeriod=Days
CRLPeriodUnits=7
CRLDeltaPeriod=Days
CRLDeltaPeriodUnits=0

[BasicConstraintsExtension]
PathLength=0
Critical=Yes
'@
# INF files want Windows line endings, whatever the repo's are
($capolicy -split "`r?`n") | Set-Content -Path "$env:SystemRoot\CAPolicy.inf" -Encoding ASCII
Write-Host "Wrote $env:SystemRoot\CAPolicy.inf"

Write-Host 'Installing the AD CS role...'
Install-WindowsFeature ADCS-Cert-Authority -IncludeManagementTools | Out-Null

$request = Join-Path $WorkDir 'users-ca.req'
Write-Host "Making the CA's RSA 4096 key and its request..."
Install-AdcsCertificationAuthority `
    -CAType EnterpriseSubordinateCa `
    -CACommonName $CommonName `
    -CryptoProviderName 'RSA#Microsoft Software Key Storage Provider' `
    -KeyLength 4096 `
    -HashAlgorithmName SHA256 `
    -OutputCertRequestFile $request `
    -Force | Out-Null

# The same SHA-256 root-ca.sh shows before signing: of the request's DER bytes
$base64 = (Get-Content $request | Where-Object { $_ -notmatch '^-----' }) -join ''
$sha = [Security.Cryptography.SHA256]::Create()
$fingerprint = ($sha.ComputeHash([Convert]::FromBase64String($base64)) | ForEach-Object { $_.ToString('x2') }) -join ''

Write-Host @"

The CA's request is ready: $request
Its SHA-256, to compare at the root: $fingerprint

Next:
  1. Copy users-ca.req to the USB drive.
  2. On the root VM:  sudo ./root-ca.sh sign users-ca.req windows
  3. Bring back out/guenaga-users-ca.crt, out/root.crt and out/root.crl, then here:
     .\Complete-UsersCA.ps1 -CaCert guenaga-users-ca.crt -RootCert root.crt -RootCrl root.crl
"@
