/**
 * Env -> typed config for the announce-release entry point.
 */

export function readRunOptions(env = process.env) {
    if (!env.TAG) {
        throw new Error('TAG env var is required');
    }
    return {
        tag: env.TAG,
        changelogPath: env.CHANGELOG_PATH ?? 'CHANGELOG.md',
        repoSlug: env.REPO_SLUG ?? 'phel-lang/phel-lang',
        docsUrl: env.DOCS_URL ?? 'https://phel-lang.org',
        threadJsonPath: env.THREAD_JSON_PATH ?? 'thread.json',
        threadDraftPath: env.THREAD_DRAFT_PATH ?? 'thread.md',
    };
}

export function releaseUrlFor(repoSlug, version) {
    return `https://github.com/${repoSlug}/releases/tag/v${version}`;
}
