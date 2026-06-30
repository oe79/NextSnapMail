# NextSnapMail for Nextcloud

NextSnapMail is an independent community fork of SnappyMail focused on
integration with Nextcloud. It provides the inherited SnappyMail webmail client
as a self-contained Nextcloud app.

NextSnapMail is not affiliated with, endorsed by, or sponsored by Nextcloud
GmbH. It is not an official Nextcloud app.

Thank you to all contributors to SnappyMail for nextcloud:
- RainLoop Team, who initiated it
- [pierre-alain-b](https://github.com/pierre-alain-b/rainloop-nextcloud)
- Tab Fitts (@tabp0le)
- Nextgen Networks (@nextgen-networks)
- [All testers of issue 96](https://github.com/the-djmaze/snappymail/issues/96)

## How to install

Install and enable the `nextsnapmail` app through the Nextcloud app management
interface or by deploying a signed NextSnapMail release archive.

After enabling the app, open the Nextcloud administration settings and go to
the dedicated "NextSnapMail" section. From there, open the NextSnapMail admin
panel and configure the instance-wide mail domains and login behaviour.

To enter the NextSnapMail admin area, you must be a Nextcloud administrator
or use the generated NextSnapMail admin credentials. The default login is
`admin` and the generated password is stored in
`[nextcloud-data]/appdata_nextsnapmail/_data_/_default_/admin_password.txt`.
Change this password after the first login.

From that point, all instance-wide NextSnapMail settings can be configured.
One important point is the "Domains" section where you set up the IMAP/SMTP
parameters associated with the email addresses of your users. For example, if
a user signs in with `firstname@example.com`, NextSnapMail must know how to
connect to the IMAP and SMTP services for `example.com`.

![grafik](https://user-images.githubusercontent.com/63400209/199767908-fbef0f50-ecb7-47ae-9ac1-771959d4b7f5.png)

![grafik](https://user-images.githubusercontent.com/63400209/199768097-7bd939a7-56d0-47ba-b481-aeac08776fb4.png)

## App Integrations
### Contacts
SnappyMail automatically connects with the Nextcloud contacts app. Download and install the [contacts app](https://apps.nextcloud.com/apps/contacts) for SnappyMail to obtain access to all registered users on the Nextcloud system, as well as users' personal contacts saved in here.

## NextSnapMail settings

NextSnapMail has both administrator settings and user settings.

### Administrator settings

NextSnapMail administrator settings are available to Nextcloud administrators
in the dedicated "NextSnapMail" section of the Nextcloud administration
settings. The linked NextSnapMail admin panel contains settings that apply to
all users, including domains, login rules, branding, extensions, and security
rules.

### User settings

Each user can configure user-specific behaviour inside NextSnapMail by opening
the user menu and choosing "Settings". Users can manage contacts, mail
accounts, folders, appearance, OpenPGP, and related settings there.

### The specificity of SnappyMail user accounts
The plugin passes the login information of the user to the SnappyMail app which then creates and manages the user accounts. Accounts in SnappyMail are based soley on the authenticated email accounts, and do not take into account the nextcloud user which created them in the first place. If two or more Nextcloud users have the same email account in additional settings, they will in fact share the same 'email account' in SnappyMail including any additional email accounts that they may have added subsequently to their main account.
This is to be kept in mind for the use case where multiple users shall have the same email account but may be also tempted to add additionnal acounts to their SnappyMail.

## How to auto-connect to SnappyMail?

### Default Domain
As already said SnappyMail uses the domain part (@example.com) to choose the IMAP/SMTP server to use. If in the following settings the username passed to SnappyMail does not contain a domain, the "default domain" is added to this username. In this way SnappyMail can lookup the "Domain" configuration to use (IMAP, SMTP, SIEVE server ecc.).
Example: if the username `john` is passed to SnappyMail, the "default domain" `example.com` would be added to the username basing on your configuration. So SnappyMail would try to login the user with the username `john@example.com`.

You can configure the "default domain" and connected settings in the SnappyMail Admin Panel under the menu "Login".

### Auto-connect options
The Nextcloud administrator can choose how NextSnapMail tries to automatically login when a user clicks on the NextSnapMail app icon. These options are available in the dedicated NextSnapMail section of the Nextcloud administration settings:

#### Option 1: Users will login manually, or define credentials in their personal settings for automatic logins.
If the user sets mailbox credentials in the dedicated NextSnapMail section of their personal settings, these credentials are used by NextSnapMail to login.
If no personal credentials are defined the user is prompted by SnappyMail to insert his credentials every time he tries to open the SnappyMail App within Nextcloud.

#### Option 2: Attempt to automatically login users with their Nextcloud username and password, or user-defined credentials, if set.
If the user sets mailbox credentials in the dedicated NextSnapMail section of their personal settings, these credentials are used by NextSnapMail to login.
If no personal credentials are defined the Nextcloud username and password is used by SnappyMail to login (eventually adding the [default domain](#default-domain)).

If your IMAP server only accepts usernames without a domain (for example the ldap username of your user) the automatic addition of the "default domain" would block your users from logging in to your IMAP server - but on the other side it is needed by SnappyMail to determine the server settings to use. In such a case you must configure SnappyMail to strip off the domain part before sending the credentials to your IMAP server. This is done by entering to the SnappyMail Admin Panel -> Domains -> clicking on your default domain -> flagging the checkbox "Use short login" under IMAP and SMTP.

#### Option 3: Attempt to automatically login users with their Nextcloud email and password, or user-defined credentials, if set.
If the user sets mailbox credentials in the dedicated NextSnapMail section of their personal settings, these credentials are used by NextSnapMail to login.
If no personal credentials are defined the mail address of the Nextcloud user and his password are used by SnappyMail to login. SnappyMail will lookup the "Domain" settings for a configuration that meets the domain part of the mail address passed as username.

#### Option 4: Attempt to automatically login with OIDC when active

### Auto-connection for all Nextcloud users
If your Nextcloud users base is synchronized with an email system, then it is possible that Nextcloud credentials could be used right away to access the centralized email system. In the SnappyMail admin settings, the Nextcloud administrator can then tick the "Automatically login with Nextcloud/Nextcloud user credentials" checkbox.

Beware, if you tick this box, all Nextcloud users will *not* be able to use the override it with the setting below.

### Auto-connection for one user at a time
Except if the above setting is activated, any Nextcloud user can have Nextcloud and SnappyMail keep in mind the default email/password to connect to SnappyMail. There, logging in Nextcloud is sufficient to then access SnappyMail within Nextcloud.

To fill in the default email address and password to use, each Nextcloud user
can open their personal settings and select the dedicated "NextSnapMail"
section.


## How to Activate SnappyMail Logging and then Find Logs

You can activate SnappyMail logging here: `/path/to/nextcloud/data/appdata_nextsnapmail/_data_/_default_/configs/application.ini`
```
[logs]
enable = On
```
Logs are then available in `/path/to/nextcloud/data/appdata_nextsnapmail/_data_/_default_/logs/`
