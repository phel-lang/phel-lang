/**
 * Pure formatter that turns a tweet array into a human-readable
 * Markdown draft ready to copy-paste into X / Typefully / Buffer.
 */

const DIVIDER = '═══════════════════════════════════════';

export function formatDraft({ version, tweets, generatedAt = new Date() }) {
    const lines = [
        `# Phel v${version} — X / Twitter thread draft`,
        '',
        `Source: CHANGELOG.md section for v${version}`,
        `Total tweets: ${tweets.length}`,
        `Generated: ${generatedAt.toISOString()}`,
        '',
        'How to post: tweet #1 first, then reply to it with #2, then reply to that with #3, and so on. Or paste the whole sequence into a thread tool (Typefully / Buffer / Hypefury).',
        '',
    ];

    tweets.forEach((text, index) => {
        lines.push(
            DIVIDER,
            `TWEET ${index + 1}/${tweets.length} (${text.length} chars)`,
            DIVIDER,
            text,
            '',
        );
    });

    return lines.join('\n');
}
