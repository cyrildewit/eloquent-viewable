// Renders the samples' READMEs and UPGRADING.md as pages, so they keep one source
// next to the code they describe. Runs before every dev server and build; the
// pages it writes are gitignored.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { branch } from '../src/branch.mjs';

const docs = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const root = path.dirname(docs);
const content = path.join(docs, 'src/content/docs');

const base = '/eloquent-viewable';
const site = `https://cyrildewit.github.io${base}`;
const repository = 'https://github.com/cyrildewit/eloquent-viewable';

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

function firstParagraph(markdown) {
	const paragraph = mapProse(markdown, (p) => p)
		.split(/\n\s*\n/)
		.map((p) => p.trim())
		.find((p) => p && !/^(#|```|\||-|>|:::|<)/.test(p));
	return paragraph?.replace(/\s+/g, ' ').replace(/\[([^\]]+)\]\([^)]+\)/g, '$1').replace(/[`*]/g, '');
}

// As many whole sentences of the first paragraph as fit in a search result snippet. A first sentence that is too
// long on its own is cut at its colon, which in these READMEs ends the setup and starts the details.
function summary(markdown, suffix, limit = 160) {
	const sentences = firstParagraph(markdown)?.split(/(?<=[.!?])\s+/) ?? [];
	let text = sentences.shift() ?? '';
	if (text.length > limit) text = text.replace(/:.*$/, '.');
	for (const sentence of sentences) {
		if (`${text} ${sentence}`.length > limit) break;
		text = `${text} ${sentence}`;
	}
	return `${text} ${suffix}`.length <= limit ? `${text} ${suffix}` : text;
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
		description: summary(body, 'A Laravel recipe with the full code and a Pest test.'),
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
	description: 'Upgrade Eloquent Viewable from one major version to the next: what changed, what broke and what to change in your Laravel application.',
	editUrl: `${repository}/edit/${branch}/UPGRADING.md`,
	tableOfContents: { maxHeadingLevel: 3 },
}, convert(upgrading, '', samples));

// The configuration reference: each top-level key of the published config, with
// the comments that document it, under the banner it carries in the file.
const configSections = {
	models: 'extending/view-model',
	recording: 'recording/recording-views',
	dimensions: 'viewers/dimensions',
	querying: 'counting/caching',
	retention: 'scaling/retention',
	milestones: 'counting/milestones',
	spikes: 'rankings/rising-and-spiking',
	visitor: 'recording/without-a-cookie',
	presence: 'rankings/presence',
	cooldown: 'recording/cooldowns',
	doctor: 'scaling/checking-your-setup',
};

const banner = /^ {4}\/\*\n {4}\|-+\n {4}\| (.+)\n {4}\|-+\n((?: {4}\|.*\n)*) {4}\*\/\n/gm;
const config = fs.readFileSync(path.join(root, 'config/eloquent-viewable.php'), 'utf8');
const banners = [...config.matchAll(banner)];
const configBody = banners.map((match, i) => {
	const start = match.index + match[0].length;
	const end = banners[i + 1]?.index ?? config.lastIndexOf('];');
	const code = config.slice(start, end).replace(/^ {4}/gm, '').trim();
	const key = code.match(/^'(\w+)'/)?.[1];
	if (!key || !(key in configSections)) {
		throw new Error(`config/eloquent-viewable.php: no page for the \`${key}\` section, add it to configSections.`);
	}
	const intro = match[2].replace(/^ {4}\| ?/gm, '').trim().replace(/\s*\n\s*/g, ' ');
	return [
		`## ${match[1]}`,
		intro,
		`See [${match[1]}](${base}/${configSections[key]}/) for how to use it.`,
		`\`\`\`php title="config/eloquent-viewable.php"\n${code}\n\`\`\``,
	].filter(Boolean).join('\n\n');
});

write(path.join(content, 'reference/configuration.md'), {
	title: 'Configuration',
	description: 'Every option of config/eloquent-viewable.php, with its default and the comment that explains it, grouped the way the published file is.',
	sidebar: { order: 1 },
	editUrl: `${repository}/edit/${branch}/config/eloquent-viewable.php`,
}, `Publish the config file to change an option. Everything left out keeps the default shown here.

\`\`\`bash
php artisan vendor:publish --provider="CyrildeWit\\EloquentViewable\\EloquentViewableServiceProvider" --tag="config"
\`\`\`

${configBody.join('\n\n')}`);

// The Artisan commands, read from the $signature and $description of every
// command class in src/.
const commandPages = {
	'views:anonymise': 'scaling/retention',
	'views:detect-spikes': 'rankings/rising-and-spiking',
	'views:dimensions': 'viewers/dimensions',
	'views:doctor': 'scaling/checking-your-setup',
	'views:flush': 'scaling/redis-buffer',
	'views:forget-viewer': 'viewers/erasing-views',
	'views:forget-visitor': 'viewers/erasing-views',
	'views:maintain': 'scaling/retention',
	'views:pairs': 'scaling/pairs-table',
	'views:prune': 'scaling/retention',
	'views:purge-bots': 'scaling/purging-bots',
	'views:recount': 'scaling/counts-table',
	'views:rollup': 'scaling/rollups',
	'views:seed-milestones': 'counting/milestones',
};

const phpString = (source, property) => {
	const literal = source.match(new RegExp(`protected \\$${property} = '((?:[^'\\\\]|\\\\.)*)';`))?.[1];
	return literal?.replace(/\\(['\\])/g, '$1');
};

// Splits a signature into its {…} parameters, minding the braces a
// description may hold, such as `context->{name}`.
function parameters(signature) {
	const found = [];
	let depth = 0;
	let current = '';
	for (const char of signature) {
		if (char === '{' && depth++ === 0) continue;
		if (char === '}' && --depth === 0) {
			found.push(current.trim());
			current = '';
			continue;
		}
		if (depth > 0) current += char;
	}
	return found.map((parameter) => {
		const [, name, description = ''] = parameter.match(/^([^:]+?)\s*(?::\s*([\s\S]*))?$/);
		const option = name.startsWith('--');
		const [flag, fallback] = name.replace(/^--/, '').split('=');
		const optional = flag.endsWith('?');
		return {
			usage: option ? `--${flag}${fallback !== undefined ? '=' : ''}` : flag.replace('?', ''),
			option,
			optional,
			fallback: fallback === '*' ? 'many' : fallback || undefined,
			description: description.replace(/\s+/g, ' '),
		};
	});
}

const commandFiles = fs.globSync('src/**/Console/*Command.php', { cwd: root }).sort();
const commands = commandFiles.flatMap((file) => {
	const source = fs.readFileSync(path.join(root, file), 'utf8');
	const signature = phpString(source, 'signature');
	// An abstract base command has no signature of its own.
	if (signature === undefined) return [];
	const name = signature.match(/^[\w:-]+/)[0];
	if (!(name in commandPages)) {
		throw new Error(`${file}: no page for \`${name}\`, add it to commandPages.`);
	}
	return { file, name, description: phpString(source, 'description'), parameters: parameters(signature.slice(name.length)) };
}).sort((a, b) => a.name.localeCompare(b.name));

const cell = (text) => text.replace(/\|/g, '\\|');
const commandBody = commands.map(({ file, name, description, parameters }) => {
	const args = parameters.filter((p) => !p.option);
	const usage = [name, ...args.map((a) => (a.optional ? `[${a.usage}]` : `<${a.usage}>`))].join(' ');
	const rows = parameters.map((p) => {
		const note = [p.fallback === 'many' ? 'Repeatable.' : p.fallback ? `Default \`${p.fallback}\`.` : '', p.optional ? 'Optional.' : '']
			.filter(Boolean).join(' ');
		return `| \`${p.usage}\` | ${cell(p.description)}${note ? ` ${note}` : ''} |`;
	});
	return [
		`## \`${name}\``,
		`${description}. See [the docs](${base}/${commandPages[name]}/) and [the source](${repository}/blob/${branch}/${file}).`,
		`\`\`\`bash\nphp artisan ${usage}\n\`\`\``,
		rows.length ? `| Argument or option | Description |\n|---|---|\n${rows.join('\n')}` : '',
	].filter(Boolean).join('\n\n');
});

write(path.join(content, 'reference/commands.md'), {
	title: 'Artisan commands',
	description: 'Every views:* Artisan command Eloquent Viewable ships, with its arguments and options and a link to the page that explains when to run it.',
	sidebar: { order: 2 },
}, `Most applications schedule one command, \`views:maintain\`, which rolls up, anonymises and deletes old views and
recounts counter columns as the config asks. Add \`views:flush\` with the [Redis buffer](${base}/scaling/redis-buffer/),
\`views:pairs\` with the [pairs table](${base}/scaling/pairs-table/) and \`views:detect-spikes\` with
[spike alerts](${base}/rankings/rising-and-spiking/#spike-alerts).

\`\`\`php title="routes/console.php"
Schedule::command('views:maintain')->hourly()->onOneServer();
\`\`\`

${commandBody.join('\n\n')}`);

console.log(`Synced ${samples.length} recipes, the upgrade guide, ${banners.length} config sections and ${commands.length} commands.`);
