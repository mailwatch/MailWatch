# PER DOMAIN/PER USER FILTERING

MailWatch for MailScanner 1.0 has new filtering capabilities to be able to support per-domain filtering or per-user filtering more effectively than previously.

To utilise this new functionality - all you need to do is create MailWatch users named by either their domain or their e-mail address and set their user type accordingly.

For example:

If you create a user named 'smf@f2s.com' as user type 'User' and I log-in as that user, I will only be able to see e-mail address to/from me and to be able to add Blocklist/Allowlist entries for my address (if enabled).

If I create a user named 'f2s.com' as type 'Domain Administrator' and I log-in as that user, I will only be able to see messages to/from my domain or create blocklist/allowlist entries for the entire domain or for a specific user.

The 'Administrator' type can do anything for any user or domain.

If you need to have 'aliases' for your users - e.g. 'smf@f2s.com' also has an e-mail alias 'steve.freegard@lbsltd.co.uk', then no problem - use the 'Filters' screen to add 'steve.freegard@lbsltd.co.uk' and the 'smf@f2s.com' user will be able to see both.
