+++
title = "Request and Response"
weight = 14
+++

## HTTP Request

Phel provides an easy method to access the current HTTP request. While in PHP the request is distribute in different globals variables (`$_GET`, `$_POST`, `$_SERVER`, `$_COOKIES` and `$_FILES`) Phel normalizes them into a single struct. All functions and structs are defined in the `phel\http` module.

The request struct is defined like this:

```phel
(defstruct request [
  method            # HTTP Method ("GET", "POST", ...)
  uri               # the 'uri' struct (see below)
  headers           # Map of all headers. Keys are keywords, Values are string
  parsed-body       # The parsed body ($_POST), when availabe otherwise nil
  query-params      # Map with all query parameters ($_GET)
  cookie-params     # Map with all cookie parameters ($_COOKIE)
  server-params     # Map with all server parameters ($_SERVER)
  uploaded-files    # Map of 'uploaded-file' structs (see below)
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

## HTTP Response

The `phel\http` module also contains a response struct. This struct can be used to send HTTP responses to the client. The response struct takes the following values.

```phel
(defstruct response [
  status    # The HTTP status code
  headers   # A map of headers
  body      # The body of the response (string)
  version   # The HTTP protocol version
  reason    # The HTTP status code reason text
])
```

To make it easier to create responses. Phel has two helpers methods to create a response.

```phel
(ns my-namepace
  (:require phel\http))

# Create response from map
(http/create-response-from-map {:status 200 :body "Test"})
# Evaluates to (response 200 {} "Test" "1.1" "OK")

# Create response from string
(http/create-response-from-string "Hello World")
# Evaluates to (response 200 {} "Hello World" "1.1" "OK")
```

To send the response to the client the `emit-response` function can be used.

```phel
(ns my-namepace
  (:require phel\http))

(let [rsp (http/create-response-from-map
            {:status 404 :body "Page not found"})]
  (http/emit-response rsp))
```
