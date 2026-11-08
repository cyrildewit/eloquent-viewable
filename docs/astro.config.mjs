// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import mermaid from 'astro-mermaid';
import starlightLinksValidator from 'starlight-links-validator';
import { branch } from './src/branch.mjs';

const repository = 'https://github.com/cyrildewit/eloquent-viewable';
const socialImage = 'https://www.cyrildewit.nl/eloquent-viewable/og.png';

// GitHub Pages serves the site under the repository name, on the custom domain
// of the account's user site, and cyrildewit.github.io redirects there. Links in
// the content include the base, so a change of base means changing those links.
export default defineConfig({
	site: 'https://www.cyrildewit.nl',
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
			editLink: { baseUrl: `${repository}/edit/${branch}/docs/` },
			lastUpdated: true,
			// Starlight sets twitter:card to summary_large_image, which shows nothing without an image.
			head: [
				{ tag: 'meta', attrs: { property: 'og:image', content: socialImage } },
				{ tag: 'meta', attrs: { property: 'og:image:width', content: '1200' } },
				{ tag: 'meta', attrs: { property: 'og:image:height', content: '630' } },
				{ tag: 'meta', attrs: { property: 'og:image:alt', content: 'Eloquent Viewable: view counts, trends and rankings for Eloquent models' } },
				{ tag: 'meta', attrs: { name: 'twitter:image', content: socialImage } },
			],
			// Code blocks sit just off the page background instead of standing out as panels.
			expressiveCode: {
				styleOverrides: {
					codeBackground: 'var(--ev-code-bg)',
					borderColor: 'var(--sl-color-gray-5)',
					frames: { frameBoxShadowCssValue: 'none' },
				},
			},
			customCss: ['@fontsource-variable/geist', '@fontsource-variable/geist-mono', './src/styles/custom.css'],
			components: {
				Hero: './src/components/Hero.astro',
				SiteTitle: './src/components/SiteTitle.astro',
				SocialIcons: './src/components/SocialIcons.astro',
			},
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
						{ label: 'Changelog', link: `${repository}/blob/${branch}/CHANGELOG.md`, attrs: { target: '_blank' } },
						{ label: 'Contributing', link: `${repository}/blob/${branch}/CONTRIBUTING.md`, attrs: { target: '_blank' } },
						{ label: 'Security', link: `${repository}/blob/${branch}/SECURITY.md`, attrs: { target: '_blank' } },
					],
				},
			],
		}),
	],
});
