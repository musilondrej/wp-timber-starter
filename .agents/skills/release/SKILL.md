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
5. **Pull request and tag**, only if the user asked: commit steps 2–3 on a `release/vX.Y.Z` branch and open a pull request titled `Release vX.Y.Z`. After it's squash-merged, tag `main`: `git switch main && git pull && git tag vX.Y.Z && git push origin vX.Y.Z`. The tag builds the zip and the GitHub release.
6. Report the zip path and size and the version.
