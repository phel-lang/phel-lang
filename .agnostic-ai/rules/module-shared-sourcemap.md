---
name: module-shared-sourcemap
description: 'Load when editing src/php/Shared/SourceMap: codec, consumer and file naming for compiled source maps.'
scope: src/php/Shared/SourceMap
---

# Shared SourceMap

Part of the Shared module (`.agnostic-ai/rules/module-shared.md`). The source map writer (`SourceMapGenerator`, `SourceMapState`) stays in Compiler; this folder holds what more than one module reads, so writer and readers cannot drift.

- `SourceMapConsumer`: here because Compiler (`EvaluatedCodeException`) and Command (`FilePositionExtractor`) both decode.
- `SourceMapSiblings`, `BuiltFilePreamble`: naming of `<file>.php.map` / `<file>.phel` and the compiled-file header. Build writes them; Command's `SourceMapExtractor` reads them.
- `InlineSourceMapComments`: ordinary filenames keep `// `; CR/LF, trim-sensitive and reserved-prefix labels use a single-line base64 header. A malformed encoded filename returns null.
- `SourcePathMarker`: only `decode(encode(file, directory))` is the contract, not the serialized comment bytes. Carries source-path provenance from Compiler to Build.
- `SupersededSourceMaps` (`@internal`): per-process headers of compiled files this process `require`d before they were overwritten. Written by Build's `CompiledCodeCache::put`, read **first** by `SourceMapExtractor`, so an earlier code frame maps through its own map.
