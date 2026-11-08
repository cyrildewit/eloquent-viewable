// The repository's star count, fetched once per build and shared by every page. The site is static, so the number is
// as fresh as the last deploy; the Docs workflow rebuilds it every month. Without network access, or past the API's
// rate limit, it resolves to null and the header shows the GitHub icon alone.
const repository = 'cyrildewit/eloquent-viewable';

async function fetchStars(): Promise<number | null> {
	try {
		const token = process.env.GITHUB_TOKEN;
		const response = await fetch(`https://api.github.com/repos/${repository}`, {
			headers: {
				Accept: 'application/vnd.github+json',
				...(token ? { Authorization: `Bearer ${token}` } : {}),
			},
			signal: AbortSignal.timeout(5000),
		});
		if (!response.ok) return null;
		const { stargazers_count: stars } = (await response.json()) as { stargazers_count?: unknown };
		return typeof stars === 'number' ? stars : null;
	} catch {
		return null;
	}
}

export const stars = fetchStars();

export function formatStars(count: number): string {
	return count < 1000 ? String(count) : `${(count / 1000).toFixed(1).replace(/\.0$/, '')}k`;
}
