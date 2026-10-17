# NextSnapMail

NextSnapMail is a SnappyMail fork focused on integration with Nextcloud.

It provides a lightweight webmail interface inside Nextcloud, using the
embedded SnappyMail application for IMAP/SMTP mail access.

This project is now maintained as a dedicated Nextcloud app. The repository root
is the installable app directory that belongs in a Nextcloud `apps/` folder as
`nextsnapmail`.

## Looking for a standalone webmail client?

NextSnapMail is maintained specifically as a Nextcloud app.

If you are looking for a more broadly developed SnappyMail-based project with
standalone installation and additional deployment options, you may also want to
look at [Tachyon](https://github.com/kimusan/Tachyon).

Tachyon is an independent project and is not affiliated with NextSnapMail.

## About this project

I maintain NextSnapMail primarily for my own private use, because I wanted to
continue using SnappyMail inside Nextcloud after upstream development slowed
down.

Everyone is welcome to use it, but please do so at your own risk. I am happy to
receive bug reports, hints, and practical feedback, and I will try to help where
I can. However, there is no guarantee of support, fixes, compatibility, or
continued maintenance.

## Current development status

Newest changes first:

- Updated MySQL PDO SSL options for PHP 8.5 while retaining compatibility with
  older PHP versions, preventing recurring deprecation entries in the
  Nextcloud log.
- Fixed empty message bodies when an HTML-only email contains visible content
  inside a leaked MJML `mj-raw` wrapper. The wrapper is removed while its
  contents still pass through the regular HTML sanitizer.
- Integrated the complete NextSnapMail administration directly into the
  Nextcloud administration settings. Domains, Google OAuth, webmail settings,
  extensions, Nextcloud integration, import and reset functions can now be
  managed without a separate webmail administrator login.
- Reworked the administration pages with dedicated navigation, Nextcloud-style
  forms and icons, clearer domain actions and visible IMAP, SMTP and Sieve test
  results.
- The bundled Nextcloud integration is now treated as an internal system
  component and is no longer presented as an optional extension.
- Fixed Sieve script detection in the optimized JavaScript bundle so existing
  server-side scripts and filters are also displayed outside debug mode.
- Protected the shared CardDAV configuration from being overwritten while an
  additional mail account is active. The settings remain visible but can only
  be changed from the main account.
- Added a dedicated multilingual out-of-office settings page with start and
  end dates, reply intervals and automatic Sieve capability checks.
- Out-of-office responses can be configured separately for the main address
  and identities of each Sieve-enabled account. Sender names and addresses are
  taken from the account identities automatically.
- Out-of-office settings stored by another NextSnapMail instance on the same
  Sieve server are detected and preserved. Their addresses are shown and can
  be removed explicitly when they are no longer needed.
- New managed Sieve filters use `nextsnapmail.user`. Existing `rainloop.user`
  and `nextsnapmail.user` scripts remain independently selectable and editable.
  A separate migration button can explicitly merge the legacy filters into
  `nextsnapmail.user` and remove `rainloop.user` after a successful transfer.
- Added account-wide and subfolder search scopes to both the regular and
  Advanced Search while keeping the current folder as the default.
- Clarified the unread-folder control in the folder sidebar by presenting it as
  a separate filter instead of an apparent parent folder.
- Integrated Gmail / Google OAuth2 login directly into the Nextcloud app, so
  Gmail accounts can be connected from the personal settings and as additional
  accounts without requiring a separate plugin.
- Added Nextcloud admin settings for Gmail / Google OAuth2 client configuration,
  including the redirect URI that has to be entered in the Google Cloud Console.
- Added support for a separate NextSnapMail plugin repository and admin-side
  upload of custom plugins, so optional plugins do not have to be bundled with
  every app release.
- Added tools in the NextSnapMail admin settings to import existing SnappyMail
  accounts and app data into NextSnapMail.
- Added controls to remove imported NextSnapMail account entries and old
  SnappyMail data where needed.
- Fixed Squire list handling so numbered lists such as `1. ` do not disappear
  while composing messages.
- Added a confirmed S/MIME signing fix to the shipped SnappyMail frontend files.
- Introduced NextSnapMail as a standalone Nextcloud app id.
- Restored compatibility with newer Nextcloud versions.
- Bundled SnappyMail plugins so installations do not depend on the external
  SnappyMail package server.
- Improved Nextcloud file handling for saving to and uploading from Nextcloud.
- Preserved stored personal login credentials when a mail server is temporarily
  unreachable.

## Installation

Install NextSnapMail from the Nextcloud App Store, or place this app directory
as `nextsnapmail` in your Nextcloud `apps/` directory.

After installation, enable the app in Nextcloud.

## Configuration

NextSnapMail can be configured in two places:

- Nextcloud administration settings: instance-wide NextSnapMail settings
- Nextcloud personal settings: user-specific login credentials for automatic
  login and, when supported by the configured mail server, personal
  out-of-office responses

The complete webmail administration is integrated into the dedicated
NextSnapMail section of the Nextcloud administration settings. A separate
NextSnapMail administrator login is not required. This includes mail domains,
Google OAuth, general webmail settings, extensions, Nextcloud integration and
the import and reset tools.

If you previously used the original SnappyMail Nextcloud app, you can use the
NextSnapMail admin settings to import existing SnappyMail accounts and app data.
The import is intended to copy data into NextSnapMail. It does not automatically
overwrite the old SnappyMail installation.

### Out-of-office responses

When Sieve is enabled for the currently selected mail account and the server
provides the required Sieve capabilities, users can manage out-of-office
responses in the personal NextSnapMail settings.

The main address and existing identities of the selected account are listed
automatically. Each address can have its own sender name, active period,
subject, message and reply interval. The interval can be set to once for the
entire absence, once per day, once per week or a custom number of days. It is
applied per sender, so repeated messages from the same sender do not trigger
another response before the interval has elapsed. Mail servers may impose
their own limit on very long intervals. Accounts without Sieve support do not
offer editable out-of-office settings.

Sieve scripts belong to the mail account rather than to a particular
Nextcloud installation. If the same mailbox is used from multiple NextSnapMail
instances, settings created by another instance are therefore detected and
preserved. Such addresses are displayed separately and can be explicitly
removed before saving if they are no longer required.

## Relationship to SnappyMail

NextSnapMail is based on SnappyMail and keeps the focus on Nextcloud
integration. General SnappyMail functionality, concepts, and upstream history
belong to the original SnappyMail project and its contributors.

The previous SnappyMail-oriented repository structure has been moved to
`legacy-upstream/` for reference. It is not part of the active Nextcloud App
Store release workflow.

## Development

The repository root is the installable Nextcloud app. The old upstream-oriented
repository layout is kept in `legacy-upstream/` for reference only.

Nextcloud App Store releases must be built from the repository root app
structure only. Do not build release packages from `legacy-upstream/`.

## License

NextSnapMail is licensed under the GNU Affero General Public License v3.0 only
(`AGPL-3.0-only`).

See [LICENSE](LICENSE).

## Credits

NextSnapMail is based on SnappyMail.

Thanks to:

- SnappyMail contributors
- RainLoop Team
- Pierre-Alain Bandinelli
- Tab Fitts
- Nextgen Networks
- Nathan Kinkade
- everyone who contributed to the previous Nextcloud integration work

NextSnapMail-specific maintenance and Nextcloud-focused development by
Erhard Scharf.
