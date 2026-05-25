/**
 * Env -> typed config for the announce-release entry point.
 */

const REQUIRED_X_CREDENTIAL_KEYS = [
    'X_APP_KEY',
    'X_APP_SECRET',
    'X_ACCESS_TOKEN',
    'X_ACCESS_SECRET',
];

export function readRunOptions(env = process.env) {
    if (!env.TAG) {
        throw new Error('TAG env var is required');
    }
    return {
        tag: env.TAG,
        changelogPath: env.CHANGELOG_PATH ?? 'CHANGELOG.md',
        repoSlug: env.REPO_SLUG ?? 'phel-lang/phel-lang',
        docsUrl: env.DOCS_URL ?? 'https://phel-lang.org',
        threadOutputPath: env.THREAD_OUTPUT_PATH ?? 'thread.json',
        dryRun: env.DRY_RUN === 'true',
    };
}

export function readXCredentials(env = process.env) {
    const missing = REQUIRED_X_CREDENTIAL_KEYS.filter((key) => !env[key]);
    if (missing.length) {
        throw new Error(`Missing X API env vars: ${missing.join(', ')}`);
    }
    return {
        appKey: env.X_APP_KEY,
        appSecret: env.X_APP_SECRET,
        accessToken: env.X_ACCESS_TOKEN,
        accessSecret: env.X_ACCESS_SECRET,
    };
}

export function releaseUrlFor(repoSlug, version) {
    return `https://github.com/${repoSlug}/releases/tag/v${version}`;
}
