#!/usr/bin/env node
/**
 * Build and post a Twitter/X thread announcing the highlights of a Phel
 * release. Reads the matching section from CHANGELOG.md, groups items by
 * category (Added / Changed / Performance / Fixed / ...), splits each
 * category into <= 280-char tweets, and posts them as a reply chain.
 *
 * Required env:
 *   TAG               release tag (with or without leading "v")
 *   X_APP_KEY         X API consumer key
 *   X_APP_SECRET      X API consumer secret
 *   X_ACCESS_TOKEN    X API user access token (OAuth 1.0a user context)
 *   X_ACCESS_SECRET   X API user access token secret
 *
 * Optional env:
 *   DRY_RUN           "true" => build thread.json artifact, skip posting.
 *   CHANGELOG_PATH    override (default "CHANGELOG.md")
 *   REPO_SLUG         override (default "phel-lang/phel-lang")
 */

import fs from 'node:fs';
import process from 'node:process';

const MAX_TWEET_LEN = 280;
const CHANGELOG_PATH = process.env.CHANGELOG_PATH ?? 'CHANGELOG.md';
const REPO_SLUG = process.env.REPO_SLUG ?? 'phel-lang/phel-lang';
const DRY_RUN = process.env.DRY_RUN === 'true';

const SECTION_ORDER = ['Added', 'Changed', 'Performance', 'Fixed', 'Deprecated', 'Removed', 'Security'];
const SECTION_EMOJI = {
    Added: 'NEW',
    Changed: 'CHG',
    Performance: 'PERF',
    Fixed: 'FIX',
    Deprecated: 'DEP',
    Removed: 'RM',
    Security: 'SEC',
};

function die(msg) {
    console.error(`announce-release: ${msg}`);
    process.exit(1);
}

function normaliseTag(raw) {
    if (!raw) {
        die('TAG env var is required');
    }
    return raw.replace(/^v/, '');
}

function extractReleaseSection(markdown, version) {
    const escaped = version.replace(/\./g, '\\.');
    const sentinel = '\n## __END_OF_CHANGELOG__\n';
    const haystack = markdown + sentinel;
    const re = new RegExp(
        `^##\\s*\\[?${escaped}\\]?[^\\n]*\\n([\\s\\S]*?)(?=^##\\s)`,
        'm',
    );
    const match = haystack.match(re);
    return match ? match[1].trim() : null;
}

function parseSections(block) {
    const sections = {};
    let current = null;
    for (const rawLine of block.split('\n')) {
        const line = rawLine.trimEnd();
        const heading = line.match(/^###\s+(.+)$/);
        if (heading) {
            current = heading[1].trim();
            sections[current] = [];
            continue;
        }
        if (!current) continue;
        const bullet = line.match(/^-\s+(.+)$/);
        if (bullet) {
            sections[current].push(bullet[1].trim());
        }
    }
    return sections;
}

function compressItem(text) {
    return text
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1')
        .replace(/`([^`]+)`/g, '$1')
        .replace(/\s+/g, ' ')
        .trim();
}

function clampSingleLine(line, max) {
    if (line.length <= max) return line;
    return line.slice(0, Math.max(0, max - 1)) + '…';
}

function packBullets(header, bullets) {
    const tweets = [];
    let current = header;
    for (const raw of bullets) {
        const bullet = `\n• ${compressItem(raw)}`;
        if (current.length + bullet.length > MAX_TWEET_LEN) {
            if (current === header) {
                tweets.push(clampSingleLine(`${header}${bullet}`, MAX_TWEET_LEN));
                current = `${header} (cont.)`;
                continue;
            }
            tweets.push(current);
            current = `${header} (cont.)${bullet}`;
            if (current.length > MAX_TWEET_LEN) {
                tweets.push(clampSingleLine(current, MAX_TWEET_LEN));
                current = `${header} (cont.)`;
            }
        } else {
            current += bullet;
        }
    }
    if (current !== header && current !== `${header} (cont.)`) {
        tweets.push(current);
    }
    return tweets;
}

function buildThread(version, sections, releaseUrl) {
    const tweets = [];
    const intro = `Phel v${version} is out.\n\nLisp on PHP. Release highlights below.\n\n${releaseUrl}`;
    tweets.push(clampSingleLine(intro, MAX_TWEET_LEN));

    for (const name of SECTION_ORDER) {
        const bullets = sections[name];
        if (!bullets?.length) continue;
        const header = `${SECTION_EMOJI[name] ?? name.toUpperCase()} — ${name}`;
        tweets.push(...packBullets(header, bullets));
    }

    for (const name of Object.keys(sections)) {
        if (SECTION_ORDER.includes(name)) continue;
        const bullets = sections[name];
        if (!bullets?.length) continue;
        const header = `${name.toUpperCase()} — ${name}`;
        tweets.push(...packBullets(header, bullets));
    }

    tweets.push(
        clampSingleLine(
            `Full changelog: ${releaseUrl}\nDocs: https://phel-lang.org`,
            MAX_TWEET_LEN,
        ),
    );
    return tweets;
}

async function postThread(tweets) {
    const { TwitterApi } = await import('twitter-api-v2');
    const client = new TwitterApi({
        appKey: requireEnv('X_APP_KEY'),
        appSecret: requireEnv('X_APP_SECRET'),
        accessToken: requireEnv('X_ACCESS_TOKEN'),
        accessSecret: requireEnv('X_ACCESS_SECRET'),
    });
    const rw = client.readWrite;

    let previousId;
    const postedIds = [];
    for (const [index, text] of tweets.entries()) {
        const response = previousId
            ? await rw.v2.reply(text, previousId)
            : await rw.v2.tweet(text);
        const id = response?.data?.id;
        if (!id) {
            die(`X API returned no tweet id for tweet ${index + 1}: ${JSON.stringify(response)}`);
        }
        previousId = id;
        postedIds.push(id);
        console.log(`Posted tweet ${index + 1}/${tweets.length}: ${id}`);
    }
    return postedIds;
}

function requireEnv(name) {
    const value = process.env[name];
    if (!value) die(`${name} env var is required`);
    return value;
}

async function main() {
    const version = normaliseTag(process.env.TAG);
    const releaseUrl = `https://github.com/${REPO_SLUG}/releases/tag/v${version}`;

    if (!fs.existsSync(CHANGELOG_PATH)) {
        die(`${CHANGELOG_PATH} not found`);
    }
    const markdown = fs.readFileSync(CHANGELOG_PATH, 'utf8');
    const block = extractReleaseSection(markdown, version);
    if (!block) {
        die(`No section for v${version} found in ${CHANGELOG_PATH}`);
    }

    const sections = parseSections(block);
    const tweets = buildThread(version, sections, releaseUrl);

    fs.writeFileSync('thread.json', JSON.stringify({ version, tweets }, null, 2));
    console.log(`Built thread of ${tweets.length} tweets.`);
    tweets.forEach((text, i) => {
        console.log(`\n--- [${i + 1}/${tweets.length}] (${text.length} chars) ---\n${text}`);
    });

    if (DRY_RUN) {
        console.log('\nDRY_RUN=true — not posting.');
        return;
    }

    const postedIds = await postThread(tweets);
    fs.writeFileSync(
        'thread.json',
        JSON.stringify({ version, tweets, postedIds }, null, 2),
    );
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
