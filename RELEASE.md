# Release

Releases are handled via [Release Please](https://github.com/googleapis/release-please) which automates the CHANGELOG generation, creation of the GitHub release, and version bumps. The packge is pushed to [Packagist](https://packagist.org) which is the default [Composer](https://getcomposer.org/) repository.

## Procedure

- **Merge the Release PR** - Release Please will automatically keep a "Release" Pull Request open and up to date. Merging the PR will trigger a new release.
