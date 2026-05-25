/**
 * Pure thread builder.
 *
 * Given parsed CHANGELOG sections, produces an ordered list of tweet
 * strings, each <= MAX_TWEET_LEN characters, ready to be posted as a
 * reply chain.
 */

export const MAX_TWEET_LEN = 280;

const SECTION_ORDER = ['Added', 'Changed', 'Performance', 'Fixed', 'Deprecated', 'Removed', 'Security'];

const SECTION_LABEL = {
    Added: 'NEW',
    Changed: 'CHG',
    Performance: 'PERF',
    Fixed: 'FIX',
    Deprecated: 'DEP',
    Removed: 'RM',
    Security: 'SEC',
};

function clamp(text, max = MAX_TWEET_LEN) {
    if (text.length <= max) return text;
    return text.slice(0, Math.max(0, max - 1)) + '…';
}

function compressItem(text) {
    return text
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1')
        .replace(/`([^`]+)`/g, '$1')
        .replace(/\s+/g, ' ')
        .trim();
}

function headerFor(name) {
    const label = SECTION_LABEL[name] ?? name.toUpperCase();
    return `${label} — ${name}`;
}

function packSection(header, bullets) {
    const tweets = [];
    let current = header;
    for (const raw of bullets) {
        const bullet = `\n• ${compressItem(raw)}`;
        if (current.length + bullet.length > MAX_TWEET_LEN) {
            if (current === header) {
                tweets.push(clamp(`${header}${bullet}`));
                current = `${header} (cont.)`;
                continue;
            }
            tweets.push(current);
            current = `${header} (cont.)${bullet}`;
            if (current.length > MAX_TWEET_LEN) {
                tweets.push(clamp(current));
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

function orderedSectionNames(sections) {
    const known = SECTION_ORDER.filter((name) => sections[name]?.length);
    const extras = Object.keys(sections).filter(
        (name) => !SECTION_ORDER.includes(name) && sections[name]?.length,
    );
    return [...known, ...extras];
}

export function buildIntroTweet(version, releaseUrl) {
    const text = `Phel v${version} is out.\n\nLisp on PHP. Release highlights below.\n\n${releaseUrl}`;
    return clamp(text);
}

export function buildOutroTweet(releaseUrl, docsUrl) {
    return clamp(`Full changelog: ${releaseUrl}\nDocs: ${docsUrl}`);
}

export function buildThread({ version, sections, releaseUrl, docsUrl = 'https://phel-lang.org' }) {
    const tweets = [buildIntroTweet(version, releaseUrl)];
    for (const name of orderedSectionNames(sections)) {
        tweets.push(...packSection(headerFor(name), sections[name]));
    }
    tweets.push(buildOutroTweet(releaseUrl, docsUrl));
    return tweets;
}
