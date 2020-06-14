+++
title = "HTTP Request"
weight = 14
+++

Phel provides a easy method to access the current HTTP request. While in PHP the request is distrubute in different globals variables (`$_GET`, `$_POST`, `$_SERVER`, `$_COOKIES` and `$_FILES`) Phel normalizes them into a single struct. All functions and structs are defined in the `phel\http` module.

The request struct is defined like this:

```phel
(defstruct request [
  method            # HTTP Method ("GET", "POST", ...)
  uri               # the 'uri' struct (see below)
  headers           # Table of all headers. Keys are keywords, Values are string
  parsed-body       # The parsed body ($_POST), when availabe otherwise nil
  query-params      # Table with all query parameters ($_GET)
  cookie-params     # Table with all cookie parameters ($_COOKIE)
  server-params     # Table with all server parameters ($_SERVER)
  uploaded-files    # Table of 'uploaded-file' structs (see below)
  version           # The HTTP Version
])

(defstruct uri [
  scheme            # Scheme of the URI ("http", "https")
  userinfo          # User info string
  host              # Hostname of the URI
  port              # Port of the URI
  path              # Path of the URI
  query             # Query string of the URI
  fragment          # Fragement string of the URI
])

(defstruct uploaded-file [
  tmp-file          # The location of the temporary file
  size              # The file size
  error-status      # The upload error status
  client-filename   # The client filename
  client-media-type # The client media type
])
```

To create a request struct the `phel\http` module must be imported. Then the `request-from-globals` function can be called.

```phel
(ns my-namepace
  (:require phel\http))

(http/request-from-globals) # Evaluates to a request struct
```