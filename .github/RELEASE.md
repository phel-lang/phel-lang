# Release

Guide to creating a new release.

1. Update the version in [VersionFinder](../src/php/Console/Application/VersionFinder.php)
2. Update the version in [CHANGELOG.md](../CHANGELOG.md)
3. Create a [new release](https://github.com/phel-lang/phel-lang/releases/new) from GitHub
4. Attach the new PHAR to the new release
   1. Use this script `build/phar.sh`
