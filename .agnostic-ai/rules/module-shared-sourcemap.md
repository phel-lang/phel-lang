---
name: module-shared-sourcemap
description: 'Shared SourceMap: codec, consumer and file naming for compiled source maps.'
scope: src/php/Shared/SourceMap
---

# Shared SourceMap

Part of the Shared module; read `.agnostic-ai/rules/module-shared.md` for its leaf constraints.

| Class | Purpose / producer → consumer |
|-------|-------------------------------|
| `VLQ` | pure Base64-VLQ codec (`decode`, `encodeIntegers`, `encodeInteger`); used by Compiler's `SourceMapGenerator` and `SourceMapConsumer` |
| `SourceMapConsumer` | decodes `mappings` into `getOriginalLine()` / `getMappedLines()`. Here because Compiler (`EvaluatedCodeException`) and Command (`FilePositionExtractor`) both decode; the writer (`SourceMapGenerator`, `SourceMapState`) stays in Compiler |
| `SourceMapSiblings` | naming for `<file>.php.map` + `<file>.phel`. Written by Build (`FileCompiler`, `SecondaryFileHarvester`), read by Command (`SourceMapExtractor`) |
| `BuiltFilePreamble` | `<?php declare(strict_types=1);` + building-version comment; `prepend()` (`FileCompiler`), `codeStartLine()` (`SourceMapExtractor`) |
| `InlineSourceMapComments` | `// ` / `// ;;` prefixes for inline maps in eval'd code. Written by `Shared\EmitterResult`, parsed by `SourceMapExtractor` + `EvaluatedCodeException` |
| `SupersededSourceMaps` | per-process headers of compiled files this process `require`d before they were overwritten. Written by Build (`CompiledCodeCache::put`), read first by Command (`SourceMapExtractor`), so an earlier code frame maps through its own map |
