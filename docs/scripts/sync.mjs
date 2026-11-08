// Renders the samples' READMEs and UPGRADING.md as pages, so they keep one source
// next to the code they describe. Runs before every dev server and build; the
// pages it writes are gitignored.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const docs = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const root = path.dirname(docs);
const content = path.join(docs, 'src/content/docs');

const base = '/eloquent-viewable';
const site = `https://cyrildewit.github.io${base}`;
const repository = 'https://github.com/cyrildewit/eloquent-viewable';
// The branch the site is deployed from, see .github/workflows/docs.yml.
const branch = '9.x';

const kebab = (name) => name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();

const alerts = { NOTE: 'note', TIP: 'tip', IMPORTANT: 'note', WARNING: 'caution', CAUTION: 'danger' };

// Calls `prose` on the text outside code fences, a paragraph at a time, because
// a link may wrap onto the next line.
function mapProse(markdown, prose) {
	const out = [];
	let paragraph = [];
	let fence = null;
	const flush = () => {
		if (paragraph.length) out.push(prose(paragraph.join('\n')));
		paragraph = [];
	};
	for (const line of markdown.split('\n')) {
		const marker = line.match(/^\s*(```+|~~~+)/)?.[1];
		if (marker && (!fence || line.trim().startsWith(fence))) {
			flush();
			fence = fence ? null : marker;
			out.push(line);
		} else if (fence) {
			out.push(line);
		} else {
			paragraph.push(line);
			if (!line.trim()) flush();
		}
	}
	flush();
	return out.join('\n');
}

function convertAlerts(text) {
	return text.replace(/^> \[!(\w+)\]\n((?:>.*\n?)*)/gm, (_, type, body) =>
		`:::${alerts[type] ?? 'note'}\n${body.replace(/^> ?/gm, '').trimEnd()}\n:::\n`,
	);
}

// `from` is the directory of the source file, relative to the repository root.
function rewriteLinks(text, from, samples) {
	return text.replace(/\]\(([^)\s]+)\)/g, (match, target) => {
		if (target.startsWith(site)) return `](${base}${target.slice(site.length)})`;
		if (/^(https?:|mailto:|#)/.test(target)) return match;
		if (target.startsWith('/')) return `](https://github.com${target})`;

		const [file, hash] = target.split('#');
		const resolved = path.posix.normalize(path.posix.join(from, file)).replace(/\/$/, '');
		const sample = resolved.match(/^samples\/([A-Za-z]+)$/)?.[1];
		if (sample && samples.includes(sample)) return `](${base}/recipes/${kebab(sample)}/)`;
		if (resolved === 'samples') return `](${base}/recipes/)`;

		const kind = path.posix.extname(resolved) ? 'blob' : 'tree';
		return `](${repository}/${kind}/${branch}/${resolved}${hash ? `#${hash}` : ''})`;
	});
}

function firstSentence(markdown) {
	const paragraph = mapProse(markdown, (p) => p)
		.split(/\n\s*\n/)
		.map((p) => p.trim())
		.find((p) => p && !/^(#|```|\||-|>|:::|<)/.test(p));
	if (!paragraph) return undefined;
	const plain = paragraph.replace(/\s+/g, ' ').replace(/\[([^\]]+)\]\([^)]+\)/g, '$1').replace(/[`*]/g, '');
	return plain.match(/^(.+?[.:])(\s|$)/)?.[1].replace(/:$/, '.') ?? plain;
}

function write(file, frontmatter, body) {
	const yaml = Object.entries(frontmatter)
		.filter(([, value]) => value !== undefined)
		.map(([key, value]) => (typeof value === 'object'
			? `${key}:\n${Object.entries(value).map(([k, v]) => `  ${k}: ${JSON.stringify(v)}`).join('\n')}`
			: `${key}: ${JSON.stringify(value)}`))
		.join('\n');
	fs.mkdirSync(path.dirname(file), { recursive: true });
	fs.writeFileSync(file, `---\n${yaml}\n---\n\n${body.trim()}\n`);
}

function convert(markdown, from, samples) {
	const withoutTitle = markdown.replace(/^# .*\n+/, '');
	return mapProse(withoutTitle, (p) => rewriteLinks(convertAlerts(p), from, samples));
}

// The samples, in the order samples/README.md lists them.
const index = fs.readFileSync(path.join(root, 'samples/README.md'), 'utf8');
const samples = [...index.matchAll(/^\| \[[^\]]+\]\(([A-Za-z]+)\)/gm)].map((m) => m[1]);

samples.forEach((sample, i) => {
	const markdown = fs.readFileSync(path.join(root, 'samples', sample, 'README.md'), 'utf8');
	const body = convert(markdown, `samples/${sample}`, samples);
	write(path.join(content, 'recipes', `${kebab(sample)}.md`), {
		title: markdown.match(/^# (.+)$/m)?.[1] ?? sample,
		description: firstSentence(body),
		sidebar: { order: i + 1 },
		editUrl: `${repository}/edit/${branch}/samples/${sample}/README.md`,
	}, `${body}

:::tip[Full code]
Every file of this recipe, with its migration and Pest test, is in
[\`samples/${sample}\`](${repository}/tree/${branch}/samples/${sample}).
:::`);
});

// Starlight builds its own table of contents, so the one in the file goes.
const upgrading = fs.readFileSync(path.join(root, 'UPGRADING.md'), 'utf8')
	.replace(/^## Table of contents\n+(?:- .*\n)+/m, '');
write(path.join(content, 'project/upgrading.md'), {
	title: 'Upgrade guide',
	description: 'What changed between major versions, and what to change in your application.',
	editUrl: `${repository}/edit/${branch}/UPGRADING.md`,
	tableOfContents: { maxHeadingLevel: 3 },
}, convert(upgrading, '', samples));

console.log(`Synced ${samples.length} recipes and the upgrade guide.`);
