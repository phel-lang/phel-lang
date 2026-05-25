#!/usr/bin/env node
/**
 * Build and post a Twitter/X thread announcing a Phel release.
 *
 * The heavy lifting lives in scripts/announce-release/*.mjs; this file
 * is the thin entry point invoked by .github/workflows/announce-release.yml.
 */

import fs from 'node:fs';
import process from 'node:process';

import { parseChangelog } from './announce-release/changelog.mjs';
import { buildThread } from './announce-release/thread.mjs';
import { postThread } from './announce-release/twitter.mjs';
import { readRunOptions, readXCredentials, releaseUrlFor } from './announce-release/config.mjs';

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

async function main() {
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

    fs.writeFileSync(options.threadOutputPath, JSON.stringify({ version, tweets }, null, 2));
    printThread(tweets);

    if (options.dryRun) {
        console.log('\nDRY_RUN=true — not posting.');
        return;
    }

    const postedIds = await postThread(tweets, { credentials: readXCredentials() });
    fs.writeFileSync(
        options.threadOutputPath,
        JSON.stringify({ version, tweets, postedIds }, null, 2),
    );
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
