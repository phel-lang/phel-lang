/**
 * X (Twitter) posting adapter.
 *
 * Thin wrapper around `twitter-api-v2` that posts an ordered list of
 * tweets as a reply chain. Imported lazily so dry-runs work without the
 * dependency installed.
 */

export async function createXClient(credentials) {
    const { TwitterApi } = await import('twitter-api-v2');
    const client = new TwitterApi(credentials);
    return client.readWrite;
}

export async function postThread(tweets, { credentials, logger = console } = {}) {
    if (!tweets.length) {
        throw new Error('thread is empty; nothing to post');
    }
    const client = await createXClient(credentials);
    const postedIds = [];
    let previousId;
    for (const [index, text] of tweets.entries()) {
        const response = previousId
            ? await client.v2.reply(text, previousId)
            : await client.v2.tweet(text);
        const id = response?.data?.id;
        if (!id) {
            throw new Error(
                `X API returned no tweet id for tweet ${index + 1}: ${JSON.stringify(response)}`,
            );
        }
        previousId = id;
        postedIds.push(id);
        logger.log(`Posted tweet ${index + 1}/${tweets.length}: ${id}`);
    }
    return postedIds;
}
