# Embedded webmail core

This directory contains the webmail core shipped with NextSnapMail.

NextSnapMail is an independent, Nextcloud-focused webmail application based on
SnappyMail. The embedded core currently uses SnappyMail 2.38.2 as its technical
base and includes NextSnapMail-specific fixes and features. Compatibility-
sensitive internal paths, namespaces and API names may therefore continue to
use `SnappyMail` or `RainLoop` even where the user-facing product is presented
as NextSnapMail.

The canonical installation, configuration and release documentation is the
[NextSnapMail README](../README.md) in the repository root. This directory must
not be installed or updated separately from the complete NextSnapMail app.

## Upstream project

SnappyMail is developed at:

<https://github.com/the-djmaze/snappymail>

General fixes and security improvements from upstream may be adopted when they
are compatible with NextSnapMail. NextSnapMail-specific integration and release
decisions remain part of this project.

## License and attribution

NextSnapMail and its embedded webmail core are distributed under the GNU Affero
General Public License version 3. See the repository root [LICENSE](../LICENSE)
and [README](../README.md) for licensing details and contributor credits.

NextSnapMail retains attribution to the SnappyMail contributors, the RainLoop
Team and the other contributors whose work forms the basis of the embedded
webmail client.
