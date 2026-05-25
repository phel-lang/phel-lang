/**
 * Pure CHANGELOG.md parser.
 *
 * Accepts the raw Markdown plus a version string ("0.40.0" or "v0.40.0")
 * and returns the bullets grouped by `### Section` heading.
 */

const SECTION_HEADING = /^###\s+(.+)$/;
const BULLET = /^-\s+(.+)$/;
const END_OF_CHANGELOG_SENTINEL = '\n## __END_OF_CHANGELOG__\n';

export function normaliseVersion(raw) {
    if (!raw) {
        throw new Error('version is required');
    }
    return String(raw).replace(/^v/, '');
}

export function extractReleaseSection(markdown, version) {
    const escaped = normaliseVersion(version).replace(/\./g, '\\.');
    const haystack = markdown + END_OF_CHANGELOG_SENTINEL;
    const re = new RegExp(
        `^##\\s*\\[?${escaped}\\]?[^\\n]*\\n([\\s\\S]*?)(?=^##\\s)`,
        'm',
    );
    const match = haystack.match(re);
    return match ? match[1].trim() : null;
}

export function parseSections(block) {
    if (typeof block !== 'string') return {};
    const sections = {};
    let current = null;
    for (const rawLine of block.split('\n')) {
        const line = rawLine.trimEnd();
        const heading = line.match(SECTION_HEADING);
        if (heading) {
            current = heading[1].trim();
            sections[current] = [];
            continue;
        }
        if (!current) continue;
        const bullet = line.match(BULLET);
        if (bullet) {
            sections[current].push(bullet[1].trim());
        }
    }
    return sections;
}

export function parseChangelog(markdown, version) {
    const block = extractReleaseSection(markdown, version);
    if (block === null) return null;
    return parseSections(block);
}
