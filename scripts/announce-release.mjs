#!/usr/bin/env node
/**
 * Build a copy-pasteable Twitter/X thread draft for a Phel release.
 *
 * Reads the matching section from CHANGELOG.md, groups bullets by
 * category, packs them into <=280-char tweets, and writes:
 *
 *   - thread.md   human-readable draft with dividers and char counts
 *   - thread.json machine-readable backup of the same data
 *
 * Posting is deliberately out of scope: the X API is paid, so the
 * release announcer copy-pastes the draft into X (or Typefully /
 * Buffer / Hypefury) manually.
 */

import fs from 'node:fs';
import process from 'node:process';

import { parseChangelog } from './announce-release/changelog.mjs';
import { buildThread } from './announce-release/thread.mjs';
import { formatDraft } from './announce-release/draft.mjs';
import { readRunOptions, releaseUrlFor } from './announce-release/config.mjs';

function fail(message) {
    console.error(`announce-release: ${message}`);
    process.exit(1);
}

function printThread(tweets) {
    console.log(`Built thread of ${tweets.length} tweets.`);
    tweets.forEach((text, i) => {
        console.log(`\n--- [${i + 1}/${tweets.length}] (${text.length} chars) ---\n${text}`);
    });
}

function main() {
    const options = readRunOptions();
    const version = options.tag.replace(/^v/, '');
    const releaseUrl = releaseUrlFor(options.repoSlug, version);

    if (!fs.existsSync(options.changelogPath)) {
        fail(`${options.changelogPath} not found`);
    }
    const markdown = fs.readFileSync(options.changelogPath, 'utf8');
    const sections = parseChangelog(markdown, version);
    if (sections === null) {
        fail(`No section for v${version} found in ${options.changelogPath}`);
    }

    const tweets = buildThread({
        version,
        sections,
        releaseUrl,
        docsUrl: options.docsUrl,
    });

    const draft = formatDraft({ version, tweets });
    fs.writeFileSync(options.threadDraftPath, draft);
    fs.writeFileSync(
        options.threadJsonPath,
        JSON.stringify({ version, releaseUrl, tweets }, null, 2),
    );

    printThread(tweets);
    console.log(`\nWrote ${options.threadDraftPath} and ${options.threadJsonPath}.`);
    console.log(`Copy-paste tweets from ${options.threadDraftPath} into X.`);
}

try {
    main();
} catch (err) {
    console.error(err);
    process.exit(1);
}
