+++
title = "Composer configuration"
weight = 1
+++

## Structure

Phel specific configuration is stored in `composer.json` file under `extra.phel`.

```json
"extra": {
    "phel": {
        "loader": {
            "hello-world\\": "src/"
        },
        "loader-dev": {
            "hello-world\\": "tests/"
        }
    }
},
```

Until Phel is officialy released, setting `minimum-stability` to `dev` in `composer.json` is required.

```json
"minimum-stability": "dev"
```

## Options


### `loader`

Define main namespace/s here.

Namespaces and paths defined here are automatically added to the Phel runtime.


### `loader-dev`

Define namespaces used for testing and/or development.

Namespaces and paths defined here are automatically added to the Phel runtime, if `--no-dev` command is not specified during dependency installation.


## Phel Composer Plugin

This Composer plugin handles loading the Phel configuration and initalizing the Phel runtime.

View source code of the [Composer plugin for the Phel language](https://github.com/jenshaase/phel-composer-plugin).