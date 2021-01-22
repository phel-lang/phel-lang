# Phel Lang Support for VS Code

This VS Code plugin gives very basic support for the Phel Language. Currently only syntax highlighting is supported.

## Install

This plugin is currently __not__ installable via the VS Code marketplace. To install the plugin you must manually copy or link the code of this plugin to the extension directory. Depending on your platform, the location is in the following folder:

* Windows `%USERPROFILE%\.vscode\extensions`
* macOS `~/.vscode/extensions`
* Linux `~/.vscode/extensions`

### Example installation on Linux:

```
cd ~
git clone https://github.com/phel-lang/phel-lang.git
cd ~/.vscode/extensions
ln -s ~/phel-lang/editor-support/vscode phel-lang
```

Restart VS Code.

### Example installation on Windows using PowerShell:

Open PowerShell as an administrator, then run the following:

```
cd ~
git clone https://github.com/phel-lang/phel-lang.git
cd ~/.vscode/extensions
New-Item -ItemType SymbolicLink -Target "%USERPROFILE%\phel-lang\editor-support\vscode" -Path "phel-lang"
```

Restart VS Code.


