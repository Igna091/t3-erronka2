# Leaked passwords

`leaked.txt` is what `App\Rules\NotLeakedPassword` checks new passwords against: the entries of
12 characters or more (shorter ones already fail the minimum) from `Pwdb_top-1000000.txt` in
[SecLists](https://github.com/danielmiessler/SecLists) (MIT License, Daniel Miessler), lower-cased
and sorted. The site can't reach an online service such as Have I Been Pwned, so the list ships
with it.

To refresh it:

```sh
curl -sfLO https://raw.githubusercontent.com/danielmiessler/SecLists/master/Passwords/Common-Credentials/Pwdb_top-1000000.txt
tr -d '\r' < Pwdb_top-1000000.txt | awk 'length($0) >= 12 && length($0) <= 128' | LC_ALL=C tr 'A-Z' 'a-z' | LC_ALL=C sort -u > leaked.txt
```
