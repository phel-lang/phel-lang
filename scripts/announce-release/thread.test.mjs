import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import { MAX_TWEET_LEN, buildIntroTweet, buildOutroTweet, buildThread } from './thread.mjs';

const RELEASE_URL = 'https://github.com/phel-lang/phel-lang/releases/tag/v1.2.3';
const DOCS_URL = 'https://phel-lang.org';

function shortBullets(count) {
    return Array.from({ length: count }, (_, i) => `bullet ${i + 1} (#${i + 1})`);
}

function longBullet(length) {
    return 'x'.repeat(length);
}

describe('buildIntroTweet', () => {
    it('is under the X char cap', () => {
        const text = buildIntroTweet('1.2.3', RELEASE_URL);
        assert.ok(text.length <= MAX_TWEET_LEN, `len=${text.length}`);
        assert.match(text, /Phel v1\.2\.3 is out\./);
        assert.match(text, /Lisp on PHP/);
    });
});

describe('buildOutroTweet', () => {
    it('includes both URLs', () => {
        const text = buildOutroTweet(RELEASE_URL, DOCS_URL);
        assert.ok(text.includes(RELEASE_URL));
        assert.ok(text.includes(DOCS_URL));
    });
});

describe('buildThread', () => {
    it('emits intro + outro even with no sections', () => {
        const tweets = buildThread({
            version: '1.2.3',
            sections: {},
            releaseUrl: RELEASE_URL,
            docsUrl: DOCS_URL,
        });
        assert.equal(tweets.length, 2);
    });

    it('keeps every tweet within the char cap', () => {
        const tweets = buildThread({
            version: '1.2.3',
            sections: {
                Added: shortBullets(8),
                Fixed: shortBullets(8),
                Performance: shortBullets(8),
            },
            releaseUrl: RELEASE_URL,
            docsUrl: DOCS_URL,
        });
        for (const [i, text] of tweets.entries()) {
            assert.ok(text.length <= MAX_TWEET_LEN, `tweet ${i + 1} len=${text.length}: ${text}`);
        }
    });

    it('emits sections in the canonical order', () => {
        const tweets = buildThread({
            version: '1.2.3',
            sections: {
                Fixed: ['fix one'],
                Added: ['add one'],
                Performance: ['perf one'],
            },
            releaseUrl: RELEASE_URL,
            docsUrl: DOCS_URL,
        });
        const sectionTweets = tweets.slice(1, -1);
        assert.match(sectionTweets[0], /Added/);
        assert.match(sectionTweets[1], /Performance/);
        assert.match(sectionTweets[2], /Fixed/);
    });

    it('appends unknown sections after the canonical ones', () => {
        const tweets = buildThread({
            version: '1.2.3',
            sections: {
                Added: ['add'],
                Notes: ['note one', 'note two'],
            },
            releaseUrl: RELEASE_URL,
            docsUrl: DOCS_URL,
        });
        const sectionTweets = tweets.slice(1, -1);
        assert.match(sectionTweets.at(-1), /Notes/);
    });

    it('splits an oversized section into (cont.) follow-ups', () => {
        const big = shortBullets(40);
        const tweets = buildThread({
            version: '1.2.3',
            sections: { Added: big },
            releaseUrl: RELEASE_URL,
            docsUrl: DOCS_URL,
        });
        const added = tweets.filter((t) => t.includes('Added'));
        assert.ok(added.length > 1, 'expected continuation tweets');
        assert.ok(added.some((t) => t.includes('(cont.)')));
    });

    it('clamps a single bullet that exceeds the cap on its own', () => {
        const tweets = buildThread({
            version: '1.2.3',
            sections: { Added: [longBullet(MAX_TWEET_LEN + 50)] },
            releaseUrl: RELEASE_URL,
            docsUrl: DOCS_URL,
        });
        for (const text of tweets) {
            assert.ok(text.length <= MAX_TWEET_LEN);
        }
    });

    it('strips markdown links and backticks from bullets', () => {
        const tweets = buildThread({
            version: '1.2.3',
            sections: { Added: ['see [link](https://x) using `code`'] },
            releaseUrl: RELEASE_URL,
            docsUrl: DOCS_URL,
        });
        const added = tweets.find((t) => t.includes('Added'));
        assert.match(added, /see link using code/);
        assert.doesNotMatch(added, /\(https:\/\/x\)/);
        assert.doesNotMatch(added, /`/);
    });
});
