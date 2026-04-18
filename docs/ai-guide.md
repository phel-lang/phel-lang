# AI Module Guide

`phel\ai` provides a small, provider-agnostic client for LLM chat, structured extraction, tool use, embeddings, and semantic search.

Supported providers:

| Provider | Chat | Tools | Embeddings |
|----------|------|-------|------------|
| `:anthropic` (default) | yes | yes | no |
| `:openai` | yes | yes | yes |
| `:voyageai` | no | no | yes |

## Quickstart

```phel
(ns my-app\main
  (:require phel\ai :as ai))

;; Either set env vars (ANTHROPIC_API_KEY, OPENAI_API_KEY, VOYAGE_API_KEY)
;; or configure explicitly:
(ai/configure {:api-key "sk-ant-..."})

(ai/complete "Say hi in one word")
; => "Hi"
```

## Configuration

`configure` merges options into a shared atom `ai/config`.

| Key | Default | Purpose |
|-----|---------|---------|
| `:provider` | `:anthropic` | `:anthropic`, `:openai`, `:voyageai` |
| `:model` | `"claude-sonnet-4-20250514"` | Model name |
| `:max-tokens` | `1024` | Output token cap |
| `:api-key` | `nil` | Falls back to env var |
| `:base-url` | `nil` | Override endpoint (proxies, self-hosted) |
| `:timeout` | `120` | HTTP timeout in seconds |
| `:max-retries` | `2` | Retry 429/5xx with exponential backoff |

Every per-call `opts` map on `chat`, `complete`, `chat-with-tools`, `extract`, and `extract-many` accepts the same keys to override per request.

```phel
(ai/complete "Summarize the news" {:provider :openai :model "gpt-4o-mini"})
```

## Chat

```phel
(ai/chat [{:role "user" :content "What's 2+2?"}]
         {:system "Answer with a single integer."})
; => "4"
```

Multi-turn via `chat-with-history`:

```phel
(let [h1 (ai/chat-with-history [] "My name is Alice.")
      h2 (ai/chat-with-history h1 "What's my name?")]
  (get (last h2) :content))
; => "Alice"
```

## Structured extraction

Ask the model to populate a schema:

```phel
(ai/extract
  {:name "string" :age "integer" :email "email address"}
  "Hi, I'm Alice, 30, alice@example.com")
; => {:name "Alice" :age 30 :email "alice@example.com"}
```

`extract-many` returns a vector when the input contains multiple items.

## Tool use

Define tools with provider-agnostic `tool`, pass them to `chat-with-tools`, dispatch on the returned calls, and feed results back with `tool-result`.

```phel
(def tools
  [(ai/tool "get-weather"
            "Returns current weather for a city."
            {:city {:type "string" :description "City name"}})])

(defn dispatch [call]
  (case (get call :name)
    "get-weather" (str "72F sunny in " (get (get call :input) :city))
    "unknown tool"))

(defn run-loop [messages]
  (let [resp (ai/chat-with-tools messages tools)
        calls (get resp :tool-calls)]
    (if (empty? calls)
      (get resp :text)
      (let [tool-msgs (map #(ai/tool-result (get % :id) (dispatch %)) calls)
            assistant-msg {:role "assistant" :content (get resp :raw)}]
        (recur (concat messages [assistant-msg] tool-msgs))))))
```

`chat-with-tools` returns:

```phel
{:text       "..."    ; assistant text (may be nil if only tool calls)
 :tool-calls [{:name "..." :id "..." :input {...}}]
 :stop-reason "..."
 :raw        {...}}   ; full provider body
```

## Embeddings & semantic search

```phel
(ai/configure {:provider :openai})

(def index (ai/build-index ["cats purr" "dogs bark" "birds sing"]))
(ai/search "feline sounds" index {:k 1})
; => [{:text "cats purr" :embedding [...] :similarity 0.87}]
```

Vector math primitives are exported for custom pipelines: `dot-product`, `magnitude`, `cosine-similarity`, `nearest`.

## Retry & timeouts

`:max-retries` (default `2`) retries on HTTP 429 and 5xx with exponential backoff (500ms, 1s, 2s, ...). Network errors bubble up. Tune per call:

```phel
(ai/complete "long task" {:timeout 300 :max-retries 4})
```

## Errors

All failures throw `\RuntimeException`. The message includes the HTTP status and provider error body when available.

## See also

- Source: `src/phel/ai.phel`
- Tests: `tests/phel/test/ai.phel`
