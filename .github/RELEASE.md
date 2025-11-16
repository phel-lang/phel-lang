# Release

Guide to creating a new release.

1. Update the version in [VersionFinder](../src/php/Console/Application/VersionFinder.php)
2. Update the version in [CHANGELOG.md](../CHANGELOG.md)
3. Commit and push these changes
4. Create a [new release](https://github.com/phel-lang/phel-lang/releases/new) from GitHub
   1. Create the tag from the main branch (the commits from step 3)
   2. Build the PHAR with an official release flag and attach it to the release:
      ```bash
      OFFICIAL_RELEASE=true build/phar.sh
      ```
      Then upload `build/out/phel.phar` to the GitHub release as an attachment
   3. Publish the release (this creates the tag on GitHub)
