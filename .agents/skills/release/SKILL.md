---
name: release
description: Prepare a theme release: quality gate, version bump, changelog, production zip and git tag. Use when asked to release, deploy, ship or package the theme.
---

# Release

1. **Quality gate.** All must pass; stop and report if one fails:
   ```bash
   composer lint
   composer audit
   npm audit --omit=dev
   ```
2. **Version.** Bump in both `style.css` (`Version:`) and `package.json` (`version`). Semver: fix = patch, feature = minor, breaking template/context change = major.
3. **Changelog.** Add a dated section to `CHANGELOG.md` (create it if missing): Added / Changed / Fixed, written for the site owner, not the developer.
4. **Build.** `npm run build:zip` creates `build/{theme}.zip`. Pushing the tag does the same in CI and attaches the zip to a GitHub release.
5. **Tag**, only if the user asked to commit: `git commit -am "Release vX.Y.Z"` and `git tag vX.Y.Z`. Never push without being asked.
6. Report the zip path and size and the version.
