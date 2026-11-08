// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import mermaid from 'astro-mermaid';
import starlightLinksValidator from 'starlight-links-validator';

const repository = 'https://github.com/cyrildewit/eloquent-viewable';

// Served from GitHub Pages under the repository name. Links in the content
// carry the base too, so a custom domain means changing both.
export default defineConfig({
	site: 'https://cyrildewit.github.io',
	base: '/eloquent-viewable',
	trailingSlash: 'always',
	integrations: [
		// Before Starlight, so its remark plugin sees the ```mermaid fences first.
		mermaid({ autoTheme: true }),
		starlight({
			title: 'Eloquent Viewable',
			description: 'Analytics for your Eloquent models, stored in your own database.',
			logo: { src: './src/assets/logo.svg' },
			favicon: '/favicon.svg',
			social: [{ icon: 'github', label: 'GitHub', href: repository }],
			editLink: { baseUrl: `${repository}/edit/9.x/docs/` },
			lastUpdated: true,
			customCss: ['./src/styles/custom.css'],
			components: { Hero: './src/components/Hero.astro' },
			plugins: [starlightLinksValidator()],
			sidebar: [
				{ label: 'Getting started', items: [{ autogenerate: { directory: 'getting-started' } }] },
				{ label: 'Recording', collapsed: true, items: [{ autogenerate: { directory: 'recording' } }] },
				{ label: 'Counting', collapsed: true, items: [{ autogenerate: { directory: 'counting' } }] },
				{ label: 'Rankings & recommendations', collapsed: true, items: [{ autogenerate: { directory: 'rankings' } }] },
				{ label: 'Viewers & dimensions', collapsed: true, items: [{ autogenerate: { directory: 'viewers' } }] },
				{ label: 'Testing', slug: 'testing' },
				{ label: 'Scaling', collapsed: true, items: [{ autogenerate: { directory: 'scaling' } }] },
				{ label: 'Extending', collapsed: true, items: [{ autogenerate: { directory: 'extending' } }] },
				{ label: 'Recipes', collapsed: true, items: [{ autogenerate: { directory: 'recipes' } }] },
				{ label: 'Reference', collapsed: true, items: [{ autogenerate: { directory: 'reference' } }] },
				{
					label: 'Project',
					collapsed: true,
					items: [
						{ label: 'Upgrade guide', slug: 'project/upgrading' },
						{ label: 'Changelog', link: `${repository}/blob/9.x/CHANGELOG.md`, attrs: { target: '_blank' } },
						{ label: 'Contributing', link: `${repository}/blob/9.x/CONTRIBUTING.md`, attrs: { target: '_blank' } },
						{ label: 'Security', link: `${repository}/blob/9.x/SECURITY.md`, attrs: { target: '_blank' } },
					],
				},
			],
		}),
	],
});
