<#
.SYNOPSIS
    The GPO that makes domain computers and users enroll for, and renew, their certificates from
    the Guenaga Users CA on their own (issue #10).

.DESCRIPTION
    Run it once, as a Domain Admin, on a domain controller (or any machine with the Group Policy
    Management tools):

        .\Enable-Autoenrollment.ps1

    It creates the GPO "Guenaga - Certificate autoenrollment", turns autoenrollment on for computers
    and users, and links it to the domain. Running it again changes nothing.
#>
[CmdletBinding()]
param([string]$GpoName = 'Guenaga - Certificate autoenrollment')
$ErrorActionPreference = 'Stop'

if (-not (Get-Module -ListAvailable GroupPolicy)) {
    throw 'The GroupPolicy module is missing: run it on a domain controller, or install RSAT Group Policy Management.'
}
Import-Module GroupPolicy

$domainDN = ([ADSI]'LDAP://RootDSE').defaultNamingContext
$gpo = Get-GPO -Name $GpoName -ErrorAction SilentlyContinue
if (-not $gpo) {
    $gpo = New-GPO -Name $GpoName -Comment 'Certificate autoenrollment from the Guenaga Users CA (pki/windows/Enable-Autoenrollment.ps1)'
}

# AEPolicy 7: enroll; renew expired and expiring certificates; update pending ones; remove revoked
# ones; and follow template changes. The same for the computer and for each user.
foreach ($hive in 'HKLM', 'HKCU') {
    Set-GPRegistryValue -Name $GpoName -Key "$hive\Software\Policies\Microsoft\Cryptography\AutoEnrollment" `
        -ValueName AEPolicy -Type DWord -Value 7 | Out-Null
}

$linked = (Get-GPInheritance -Target $domainDN).GpoLinks | Where-Object { $_.DisplayName -eq $GpoName }
if (-not $linked) {
    New-GPLink -Name $GpoName -Target $domainDN | Out-Null
}

Write-Host @"
"$GpoName" is linked to $domainDN.
On a domain PC: gpupdate /force, then certutil -pulse; its Workstation Authentication certificate
appears in certlm.msc > Personal > Certificates, issued by Guenaga Users CA.
"@
