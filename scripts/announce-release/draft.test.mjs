import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import { formatDraft } from './draft.mjs';

describe('formatDraft', () => {
    const generatedAt = new Date('2026-05-25T12:00:00Z');

    it('includes a header with version and tweet count', () => {
        const md = formatDraft({ version: '1.2.3', tweets: ['a', 'b'], generatedAt });
        assert.match(md, /# Phel v1\.2\.3 — X \/ Twitter thread draft/);
        assert.match(md, /Total tweets: 2/);
        assert.match(md, /Generated: 2026-05-25T12:00:00\.000Z/);
    });

    it('renders every tweet with its index and char count', () => {
        const md = formatDraft({
            version: '1.2.3',
            tweets: ['hello', 'world!!'],
            generatedAt,
        });
        assert.match(md, /TWEET 1\/2 \(5 chars\)\n[═]+\nhello/);
        assert.match(md, /TWEET 2\/2 \(7 chars\)\n[═]+\nworld!!/);
    });

    it('handles an empty thread', () => {
        const md = formatDraft({ version: '1.2.3', tweets: [], generatedAt });
        assert.match(md, /Total tweets: 0/);
        assert.doesNotMatch(md, /TWEET /);
    });
});
