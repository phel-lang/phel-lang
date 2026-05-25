import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import { readRunOptions, releaseUrlFor } from './config.mjs';

describe('readRunOptions', () => {
    it('requires TAG', () => {
        assert.throws(() => readRunOptions({}), /TAG env var is required/);
    });

    it('applies defaults', () => {
        const opts = readRunOptions({ TAG: 'v1.0.0' });
        assert.equal(opts.tag, 'v1.0.0');
        assert.equal(opts.changelogPath, 'CHANGELOG.md');
        assert.equal(opts.repoSlug, 'phel-lang/phel-lang');
        assert.equal(opts.docsUrl, 'https://phel-lang.org');
        assert.equal(opts.threadJsonPath, 'thread.json');
        assert.equal(opts.threadDraftPath, 'thread.md');
    });

    it('honors overrides', () => {
        const opts = readRunOptions({
            TAG: 'v1.0.0',
            CHANGELOG_PATH: 'docs/CHANGELOG.md',
            REPO_SLUG: 'me/forked',
            DOCS_URL: 'https://example.com',
            THREAD_JSON_PATH: 'out/t.json',
            THREAD_DRAFT_PATH: 'out/t.md',
        });
        assert.equal(opts.changelogPath, 'docs/CHANGELOG.md');
        assert.equal(opts.repoSlug, 'me/forked');
        assert.equal(opts.docsUrl, 'https://example.com');
        assert.equal(opts.threadJsonPath, 'out/t.json');
        assert.equal(opts.threadDraftPath, 'out/t.md');
    });
});

describe('releaseUrlFor', () => {
    it('builds the GitHub release URL', () => {
        assert.equal(
            releaseUrlFor('phel-lang/phel-lang', '1.2.3'),
            'https://github.com/phel-lang/phel-lang/releases/tag/v1.2.3',
        );
    });
});
