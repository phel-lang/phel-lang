---
name: module-shared-sourcemap
description: 'Load when editing src/php/Shared/SourceMap: codec, consumer and file naming for compiled source maps.'
scope: src/php/Shared/SourceMap
---

# Shared SourceMap

Part of the Shared module; `.agnostic-ai/rules/module-shared.md` has its leaf constraints.

| Class | Purpose / producer → consumer |
|-------|-------------------------------|
| `VLQ` | pure Base64-VLQ codec (`decode`, `encodeIntegers`, `encodeInteger`); used by Compiler's `SourceMapGenerator` and `SourceMapConsumer` |
| `SourceMapConsumer` | decodes `mappings` into `getOriginalLine()` / `getMappedLines()`. Here because Compiler (`EvaluatedCodeException`) and Command (`FilePositionExtractor`) both decode; the writer (`SourceMapGenerator`, `SourceMapState`) stays in Compiler |
| `SourceMapSiblings` | naming for `<file>.php.map` + `<file>.phel`. Written by Build (`FileCompiler`, `SecondaryFileHarvester`), read by Command (`SourceMapExtractor`) |
| `BuiltFilePreamble` | `<?php declare(strict_types=1);` + building-version comment; `prepend()` (`FileCompiler`), `codeStartLine()` (`SourceMapExtractor`) |
| `InlineSourceMapComments` | Inline filename/mapping comment codec. Ordinary filenames retain `// `; CR/LF, trim-sensitive and reserved-prefix labels use a single-line base64 header. Public writer/parser shared by `EmitterResult`, `SourceMapExtractor` and `EvaluatedCodeException`; malformed encoded filenames return null |
| `SourcePathMarker` | Public, pure opaque comment codec. `decode(encode(file, directory))` preserves both fields; serialized comment bytes are not a separate contract. Carries source-path provenance from Compiler to Build |
| `SupersededSourceMaps` | per-process headers of compiled files this process `require`d before they were overwritten. Written by Build (`CompiledCodeCache::put`), read first by Command (`SourceMapExtractor`), so an earlier code frame maps through its own map |
