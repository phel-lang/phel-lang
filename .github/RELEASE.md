# Release

Guide to creating a new release.

1. Update the version in [VersionFinder](../src/php/Console/Application/VersionFinder.php)
2. Update the boolean in [IS_OFFICIAL_RELEASE](../src/php/Console/ConsoleFactory.php)
3. Update the version in [CHANGELOG.md](../CHANGELOG.md)
4. Create a [new release](https://github.com/phel-lang/phel-lang/releases/new) from GitHub 
5. Attach the new PHAR to the new release
   1. Use this script `build/phar.sh`
