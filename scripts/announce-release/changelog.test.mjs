import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import { extractReleaseSection, normaliseVersion, parseChangelog, parseSections } from './changelog.mjs';

const FIXTURE = `# Changelog

## Unreleased

### Fixed

- something pending

## [0.40.0](https://github.com/phel-lang/phel-lang/compare/v0.39.0...v0.40.0) - 2026-05-25

### Added

- feature A (#1)
- feature B (#2)

### Performance

- speedup X (#3)

## [0.39.0] - 2026-04-01

### Added

- older feature
`;

describe('normaliseVersion', () => {
    it('strips a leading v', () => {
        assert.equal(normaliseVersion('v1.2.3'), '1.2.3');
    });
    it('passes through a plain version', () => {
        assert.equal(normaliseVersion('1.2.3'), '1.2.3');
    });
    it('throws on empty input', () => {
        assert.throws(() => normaliseVersion(''), /version is required/);
    });
});

describe('extractReleaseSection', () => {
    it('finds a release with bracketed link heading', () => {
        const block = extractReleaseSection(FIXTURE, '0.40.0');
        assert.match(block, /### Added/);
        assert.match(block, /### Performance/);
        assert.doesNotMatch(block, /## \[0\.39\.0\]/);
    });

    it('finds the oldest release without a successor (uses sentinel)', () => {
        const block = extractReleaseSection(FIXTURE, '0.39.0');
        assert.match(block, /older feature/);
        assert.doesNotMatch(block, /__END_OF_CHANGELOG__/);
    });

    it('accepts the v-prefixed form', () => {
        const block = extractReleaseSection(FIXTURE, 'v0.40.0');
        assert.match(block, /### Added/);
    });

    it('returns null for missing versions', () => {
        assert.equal(extractReleaseSection(FIXTURE, '9.9.9'), null);
    });
});

describe('parseSections', () => {
    it('groups bullets under their preceding heading', () => {
        const block = extractReleaseSection(FIXTURE, '0.40.0');
        const sections = parseSections(block);
        assert.deepEqual(sections.Added, ['feature A (#1)', 'feature B (#2)']);
        assert.deepEqual(sections.Performance, ['speedup X (#3)']);
    });

    it('ignores non-bullet lines', () => {
        const sections = parseSections(`### Added\n\nsome prose\n- only this\n`);
        assert.deepEqual(sections.Added, ['only this']);
    });

    it('returns an empty object for non-string input', () => {
        assert.deepEqual(parseSections(null), {});
    });
});

describe('parseChangelog', () => {
    it('returns the full grouped sections for a known release', () => {
        const sections = parseChangelog(FIXTURE, '0.40.0');
        assert.deepEqual(Object.keys(sections), ['Added', 'Performance']);
    });

    it('returns null when the release is missing', () => {
        assert.equal(parseChangelog(FIXTURE, '9.9.9'), null);
    });
});
