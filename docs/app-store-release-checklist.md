# NextSnapMail App Store release checklist

This checklist tracks the preparation for publishing NextSnapMail as a
standalone Nextcloud app.

## App identity

- App id: `nextsnapmail`
- App name: `NextSnapMail`
- Summary: `A SnappyMail fork focused on integration with Nextcloud`
- Repository: <https://github.com/oe79/NextSnapMail>
- Support/bugs: <https://github.com/oe79/NextSnapMail/issues>
- Discussion: <https://github.com/oe79/NextSnapMail/discussions>
- Licence: `AGPL-3.0-only`
- Initial release version: `0.1.0`
- Nextcloud compatibility: `20` through `34`

## Positioning

- Describe the app as an independent community fork of SnappyMail focused on
  Nextcloud integration.
- Do not describe the app as official, sponsored by, endorsed by, or affiliated
  with Nextcloud GmbH.
- Keep upstream attribution to SnappyMail and RainLoop.
- Avoid reusing old upstream screenshots for the App Store entry. Use own
  screenshots from a NextSnapMail installation.

## Package requirements

- Build package root must be `nextsnapmail/`.
- Package must include `nextsnapmail/appinfo/info.xml`.
- Package must include the compiled SnappyMail core under
  `nextsnapmail/app/snappymail/v/<core-version>/` (currently `2.38.2`).
- Package must include bundled extensions under
  `nextsnapmail/app/bundled-plugins/`.
- Package must not include local backup files such as `*.old` or `*.bak`.
- Unsigned test packages must not include `appinfo/signature.json`.
- Final App Store packages must include a valid `appinfo/signature.json`.

## Local package build

Prepare or update the local app directory first:

```bash
build/local-nextsnapmail-app/nextsnapmail
```

Create an unsigned package for local structure checks:

```bash
php build/nextcloud-release.php
```

Expected output:

```bash
build/dist/nextcloud/0.1.0/nextsnapmail-0.1.0-nextcloud-unsigned.tar.gz
```

After the Nextcloud certificate has been issued, save it as:

```bash
~/.nextcloud/certificates/nextsnapmail.crt
```

The private key must stay local and must not be committed:

```bash
~/.nextcloud/certificates/nextsnapmail.key
```

Create the signed App Store package:

```bash
php build/nextcloud-release.php --sign
```

Expected output:

```bash
build/dist/nextcloud/0.1.0/nextsnapmail-0.1.0-nextcloud.tar.gz
```

## App Store submission fields

- Name: `NextSnapMail`
- Short description:
  `A SnappyMail fork focused on integration with Nextcloud`
- Longer description:
  Use the description from `integrations/nextcloud/nextsnapmail/appinfo/info.xml`
  and keep the independent community fork disclaimer.
- Categories:
  - Integration
  - Office
  - Search
- Screenshots:
  - NextSnapMail loading/login view
  - NextSnapMail personal settings section
  - NextSnapMail admin settings section
  - Mailbox view inside Nextcloud
- Release notes:
  Use the `0.1.0` section from `CHANGELOG.md`.

## Before publishing

- Certificate request is merged by Nextcloud.
- `nextsnapmail.crt` is saved locally next to `nextsnapmail.key`.
- Signed package has been created with `php build/nextcloud-release.php --sign`.
- Package has been tested on a fresh Nextcloud installation.
- GitHub release `v0.1.0` exists and contains the signed `.tar.gz`.
- App Store entry points to the GitHub release artifact.
