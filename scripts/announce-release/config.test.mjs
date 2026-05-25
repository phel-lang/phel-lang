import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import { readRunOptions, readXCredentials, releaseUrlFor } from './config.mjs';

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
        assert.equal(opts.threadOutputPath, 'thread.json');
        assert.equal(opts.dryRun, false);
    });

    it('honors DRY_RUN=true', () => {
        const opts = readRunOptions({ TAG: 'v1.0.0', DRY_RUN: 'true' });
        assert.equal(opts.dryRun, true);
    });

    it('rejects any other DRY_RUN value as falsy', () => {
        const opts = readRunOptions({ TAG: 'v1.0.0', DRY_RUN: '1' });
        assert.equal(opts.dryRun, false);
    });
});

describe('readXCredentials', () => {
    it('reports every missing key', () => {
        assert.throws(
            () => readXCredentials({ X_APP_KEY: 'a' }),
            /Missing X API env vars: X_APP_SECRET, X_ACCESS_TOKEN, X_ACCESS_SECRET/,
        );
    });

    it('returns the credentials object when complete', () => {
        const creds = readXCredentials({
            X_APP_KEY: 'a',
            X_APP_SECRET: 'b',
            X_ACCESS_TOKEN: 'c',
            X_ACCESS_SECRET: 'd',
        });
        assert.deepEqual(creds, {
            appKey: 'a',
            appSecret: 'b',
            accessToken: 'c',
            accessSecret: 'd',
        });
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
