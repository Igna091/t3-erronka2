<#
.SYNOPSIS
    Step 2 of the Guenaga Users CA (issue #9): installs the certificate the root signed and sets
    the CA up.

.DESCRIPTION
    With the three files from the root VM, in an elevated PowerShell, as an Enterprise Admin:

        .\Complete-UsersCA.ps1 -CaCert guenaga-users-ca.crt -RootCert root.crt -RootCrl root.crl

    It:
      - trusts the root here and publishes it in Active Directory, so every domain member trusts
        it without a separate GPO (issue #10)
      - installs the CA's certificate and starts the CA
      - CRL every 7 days, no delta CRLs; CRL and issuer URLs only on http://pki.guenaga.lan, which
        pfSense, the MikroTiks and every other device can read, unlike LDAP
      - issued certificates last at most 1 year; every CA event is audited
      - publishes the templates the domain needs and lets domain computers autoenroll theirs
      - copies the CRL and the CA certificate to pki-pub every day (Publish-UsersCA.ps1)
    Running it again only re-applies the settings.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$CaCert,
    [Parameter(Mandatory)][string]$RootCert,
    [Parameter(Mandatory)][string]$RootCrl,
    [string]$WorkDir = 'C:\PKI'
)
$ErrorActionPreference = 'Stop'
$pkiUrl = 'http://pki.guenaga.lan'

# The templates the CA offers (their AD names), and what each one is for
$templates = [ordered]@{
    'KerberosAuthentication' = 'domain controllers: LDAPS and Kerberos (they autoenroll by default)'
    'RASAndIASServer'        = 'NPS / RADIUS server certificate for EAP (RAS and IAS Servers autoenroll by default)'
    'Workstation'            = 'domain computers: Wi-Fi EAP-TLS and VPN machine authentication (autoenroll granted below)'
    'User'                   = 'staff: remote-access VPN user certificates (requested by the user)'
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
if (-not ([Security.Principal.WindowsPrincipal]$identity).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run it in an elevated PowerShell (Run as administrator).'
}
if (-not ($identity.Groups | Where-Object { $_.Value -match '-519$' })) {
    throw 'Log on as a member of Enterprise Admins: publishing to AD and changing templates needs it.'
}
if (-not (Get-Service CertSvc -ErrorAction SilentlyContinue)) {
    throw 'AD CS is not installed here: run Install-UsersCA.ps1 first.'
}
foreach ($file in $CaCert, $RootCert, $RootCrl) {
    if (-not (Test-Path $file)) { throw "No such file: $file" }
}
$CaCert = (Resolve-Path $CaCert).Path
$RootCert = (Resolve-Path $RootCert).Path
$RootCrl = (Resolve-Path $RootCrl).Path

function Invoke-Certutil {
    $output = & certutil.exe @args 2>&1
    if ($LASTEXITCODE -ne 0) { throw "certutil $($args -join ' ') failed:`n$($output -join "`n")" }
}

function Wait-CA {
    for ($i = 0; $i -lt 30; $i++) {
        & certutil.exe -ping 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) { return }
        Start-Sleep -Seconds 2
    }
    throw 'The CA service did not answer within a minute: see the Application event log.'
}

Write-Host '== The root'
Invoke-Certutil -addstore -f Root $RootCert
# The CA checks its own certificate against the root's CRL every time it starts; from the local
# store this works before pki-pub exists. Add each new root CRL here the same way (pki/README.md).
Invoke-Certutil -addstore -f CA $RootCrl
Invoke-Certutil -dspublish -f $RootCert RootCA

Write-Host "== The CA's certificate"
# A CA waiting for its certificate has no CACertHash yet
$caName = (Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Services\CertSvc\Configuration').Active
$caConfig = Get-ItemProperty "HKLM:\SYSTEM\CurrentControlSet\Services\CertSvc\Configuration\$caName"
if (-not $caConfig.CACertHash) {
    Invoke-Certutil -installcert $CaCert
}
Start-Service CertSvc
Wait-CA

Write-Host '== CRLs, URLs, validity and audit'
Invoke-Certutil -setreg CA\CRLPeriodUnits 7
Invoke-Certutil -setreg CA\CRLPeriod Days
Invoke-Certutil -setreg CA\CRLOverlapUnits 3
Invoke-Certutil -setreg CA\CRLOverlapPeriod Days
Invoke-Certutil -setreg CA\CRLDeltaPeriodUnits 0
Invoke-Certutil -setreg CA\ValidityPeriodUnits 1
Invoke-Certutil -setreg CA\ValidityPeriod Years
# 127: every CA event (start/stop, backups, requests, issued, revoked, settings, security)
Invoke-Certutil -setreg CA\AuditFilter 127
# The "Certification Services" audit subcategory, by GUID so it works in any language
& auditpol.exe /set /subcategory:'{0CCE9221-69AE-11D9-BED3-505054503030}' /success:enable /failure:enable | Out-Null

# The CRL is written locally (Publish-UsersCA.ps1 copies it to pki-pub); certificates point only
# at pki-pub over HTTP
Get-CACrlDistributionPoint | Remove-CACrlDistributionPoint -Force | Out-Null
Add-CACrlDistributionPoint -Uri "$env:SystemRoot\System32\CertSrv\CertEnroll\users-ca.crl" -PublishToServer -Force | Out-Null
Add-CACrlDistributionPoint -Uri "$pkiUrl/crl/users-ca.crl" -AddToCertificateCdp -Force | Out-Null
Get-CAAuthorityInformationAccess | Remove-CAAuthorityInformationAccess -Force | Out-Null
Add-CAAuthorityInformationAccess -Uri "$pkiUrl/ca/users-ca.crt" -AddToCertificateAia -Force | Out-Null

Restart-Service CertSvc
Wait-CA
Invoke-Certutil -crl

Write-Host '== Templates'
# Domain computers may autoenroll Workstation Authentication (by default they can only enroll)
$configNC = ([ADSI]'LDAP://RootDSE').configurationNamingContext
$template = [ADSI]"LDAP://CN=Workstation,CN=Certificate Templates,CN=Public Key Services,CN=Services,$configNC"
$domainComputers = New-Object Security.Principal.SecurityIdentifier ("$($identity.User.AccountDomainSid.Value)-515")
$autoenroll = [Guid]'a05b8cc2-17bc-4802-a710-e7c15ab866a2'
$rule = New-Object DirectoryServices.ActiveDirectoryAccessRule (
    $domainComputers,
    [DirectoryServices.ActiveDirectoryRights]::ExtendedRight,
    [Security.AccessControl.AccessControlType]::Allow,
    $autoenroll)
$template.psbase.ObjectSecurity.AddAccessRule($rule)
$template.psbase.CommitChanges()

$published = (Get-CATemplate).Name
foreach ($name in $templates.Keys) {
    if ($published -notcontains $name) {
        Add-CATemplate -Name $name -Force | Out-Null
    }
    Write-Host ("  {0,-24} {1}" -f $name, $templates[$name])
}

Write-Host '== Publishing to pki-pub'
New-Item -ItemType Directory -Force $WorkDir | Out-Null
Copy-Item (Join-Path $PSScriptRoot 'Publish-UsersCA.ps1') $WorkDir -Force
$conf = Join-Path $WorkDir 'publish.conf'
if (-not (Test-Path $conf)) {
    @(
        '# Where Publish-UsersCA.ps1 copies the CRL and the CA certificate: pki-pub (issue #9), as',
        '# user@host:path, for example PUBLISH_TARGET="pki@pki.guenaga.lan:/srv/pki/". Empty: they stay in',
        '# C:\PKI\pub. pki-pub must accept the key in C:\PKI\publish_ed25519.pub.',
        'PUBLISH_TARGET=""'
    ) | Set-Content -Path $conf -Encoding ASCII
}
$key = Join-Path $WorkDir 'publish_ed25519'
if (-not (Test-Path $key)) {
    & ssh-keygen.exe -q -t ed25519 -N '""' -C 'users-ca publish to pki-pub' -f $key
    if ($LASTEXITCODE -ne 0) { throw 'ssh-keygen failed: is the OpenSSH client installed?' }
    # ssh refuses a key others can read: only SYSTEM and the Administrators group
    & icacls.exe $key /inheritance:r /grant:r '*S-1-5-18:F' '*S-1-5-32-544:F' | Out-Null
}

# Every day as SYSTEM: the CRL is renewed every 7 days, so pki-pub is never more than a day behind
$action = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$WorkDir\Publish-UsersCA.ps1`" -WorkDir `"$WorkDir`""
$trigger = New-ScheduledTaskTrigger -Daily -At '01:00'
Register-ScheduledTask -TaskName 'Guenaga Users CA - publish CRL' -Action $action -Trigger $trigger `
    -User 'SYSTEM' -RunLevel Highest -Force | Out-Null
& (Join-Path $WorkDir 'Publish-UsersCA.ps1') -WorkDir $WorkDir

$ca = New-Object Security.Cryptography.X509Certificates.X509Certificate2 $CaCert
Write-Host @"

The Guenaga Users CA is running.
  Certificate: $($ca.Subject), until $($ca.NotAfter.ToString('yyyy-MM-dd'))
  Next: run Enable-Autoenrollment.ps1 (on a DC) so computers and users enroll by themselves,
  then on a domain PC: gpupdate /force, certutil -pulse, and look in certlm.msc > Personal.
"@
