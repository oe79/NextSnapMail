# NextSnapMail

NextSnapMail is an independent community fork of
[SnappyMail](https://github.com/the-djmaze/snappymail), focused exclusively on
providing and maintaining the webmail client as an app for
[Nextcloud](https://nextcloud.com/).

The project continues development of the existing SnappyMail codebase for the
Nextcloud use case. It is not an official continuation of SnappyMail and is not
affiliated with, endorsed by, or sponsored by Nextcloud GmbH.

## Project status

NextSnapMail is currently in the initial restructuring phase and is **not yet
ready for production use**.

The repository still contains the original SnappyMail application structure,
name, app identifier, integrations, and release tooling. These will be reviewed
and migrated incrementally. Until a first NextSnapMail release is published,
use the official SnappyMail releases for existing installations.

## Scope

NextSnapMail intends to maintain:

- the SnappyMail webmail core required by the Nextcloud app;
- integration with current supported Nextcloud releases;
- IMAP, SMTP, Sieve, contacts, calendar, and file integration used through
  Nextcloud;
- a self-contained Nextcloud app package and release process;
- security, compatibility, accessibility, and localization fixes.

The project does not intend to publish or maintain separate distributions for:

- Docker or standalone container images;
- ownCloud;
- Cloudron, cPanel, CyberPanel, HestiaCP, Virtualmin, or similar hosting panels;
- Debian, Arch Linux, or other system packages;
- standalone SnappyMail installations outside Nextcloud.

This scope describes the direction of the project. Files for unsupported
targets remain in the repository during the initial migration and will only be
removed after their dependencies have been reviewed.

## Planned first milestones

1. Establish the NextSnapMail project identity and preserve provenance.
2. Decide and document the new Nextcloud app identifier and migration path.
3. Bundle the required Nextcloud integration without relying on the former
   SnappyMail package service.
4. Introduce a reproducible Nextcloud-only build and test process.
5. Modernize the integration for supported Nextcloud and PHP versions.
6. Publish signed, source-backed Nextcloud app releases.

## Contributing

The contribution workflow and compatibility targets are still being prepared.
Bug reports and changes should be submitted to the
[NextSnapMail repository](https://github.com/oe79/NextSnapMail).

Please avoid large mechanical renames of the internal `RainLoop` and
`SnappyMail` namespaces. They are part of the inherited architecture and will
be migrated only where doing so is technically justified.

## Provenance and modifications

NextSnapMail is based on SnappyMail, which is itself a fork of RainLoop Webmail
Community Edition.

The NextSnapMail project began modifying and restructuring the codebase on
2026-06-18. A summary of inherited projects and contributors is maintained in
[CREDITS.md](CREDITS.md). Project changes are recorded in Git history and will
be documented in release notes.

Copyright notices from SnappyMail, RainLoop, and bundled third-party components
must remain intact:

- Copyright (c) 2020 - 2024 SnappyMail
- Copyright (c) 2013 - 2022 RainLoop
- Copyright for NextSnapMail modifications belongs to their respective
  contributors

## License

This project remains licensed under the **GNU Affero General Public License,
version 3 (AGPLv3)**. See [LICENSE](LICENSE) for the complete terms.

Modified versions must retain applicable notices, identify modifications, and
remain available under the AGPL. Users interacting with a modified version over
a network must be offered access to its corresponding source code as required
by section 13 of the AGPLv3.

Bundled third-party components may carry additional compatible license and
copyright notices. Those notices remain applicable to their respective files.

## Trademarks

NextSnapMail is an independent project. “Nextcloud” and the Nextcloud logo are
trademarks of Nextcloud GmbH. The Nextcloud name is used only to describe
compatibility and the intended platform. See [TRADEMARKS.md](TRADEMARKS.md).
