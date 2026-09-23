# Rating Glance

A small WordPress plugin that shows your **Google** and **Tripadvisor** rating at a glance: score, number of reviews and a link to the review page. Made for restaurant and business footers, but it works anywhere.

```
[G] Google ★★★★★ 4.6  1,234 reviews     [owl] Tripadvisor ★★★★★ 4.5  312 reviews
```

- **Block**, **classic widget** and **`[rating_glance]` shortcode**, so it works with block themes, classic themes and page builders (Elementor, Divi, …).
- **Links to your listings**: each rating links to your Google Maps place and Tripadvisor page, with the Google and Tripadvisor logos in your text color or in brand colors.
- **Inherits your theme**: font, size and text color come from wherever you place it. Works on light and dark footers.
- **Fast**: ratings are fetched in the background via [SerpApi](https://serpapi.com) and cached. Visitors never wait on an external API.
- **Resilient**: if a fetch fails, the last known values stay visible. Manual override per source.
- **Easy setup**: built-in search finds your Google place ID and Tripadvisor page.
- **Accessible**: each rating is a single link with a descriptive screen-reader label.
- No dependencies, no build step.

## Installation

1. Download **[rating-glance.zip](https://github.com/visco-horeca/rating-glance/releases/latest/download/rating-glance.zip)** from the [latest release](https://github.com/visco-horeca/rating-glance/releases/latest) and upload it via **Plugins → Add New → Upload Plugin**. (Don't use GitHub's "Source code" zip; it has the wrong folder name.)
2. Go to **Settings → Rating Glance** and enter a [SerpApi key](https://serpapi.com/manage-api-key). The free plan is enough: a daily refresh uses about 60 searches a month.
3. Click **Find** next to Google and Tripadvisor, pick your business, and save.
4. Add the **Rating Glance** block, widget or `[rating_glance]` shortcode to your footer.

## Shortcode

```
[rating_glance sources="google,tripadvisor" display="stars" icon="mono" count="text" layout="inline" align="start" label="yes"]
```

| Attribute | Values | Default |
|---|---|---|
| `sources` | `google`, `tripadvisor`, comma-separated, in display order | both |
| `display` | `stars` (five stars + score), `compact` (one star + score), `text` (4.6/5) | `stars` |
| `icon` | `mono` (logo in text color), `color` (brand colors), `none` | `mono` |
| `count` | `text` ("250 reviews"), `number` ("(250)"), `none` | `text` |
| `layout` | `inline`, `stacked` | `inline` |
| `align` | `start`, `center`, `end` | `start` |
| `label` | `yes`, `no` (show source name) | `yes` |
| `new_tab` | `yes`, `no` | setting |
| `class` | extra CSS class | |

## Styling

The output uses `currentColor` and `font: inherit`, so it usually needs no CSS. To fine-tune, set these variables on `.rating-glance` or any parent:

| Variable | Default |
|---|---|
| `--rg-gap` | `0.5em 1.75em` |
| `--rg-inner-gap` | `0.4em` |
| `--rg-icon-size` | `1.15em` |
| `--rg-star-color` | `currentColor` |
| `--rg-star-empty-opacity` | `0.3` |
| `--rg-count-opacity` | `0.75` |

```css
/* Gold stars */
.rating-glance { --rg-star-color: #e0a526; }
```

## How it works

A WP-Cron event (twice daily, daily or weekly) calls SerpApi's `google_maps` (place) and `tripadvisor_place` endpoints, and stores rating, review count and link in a single option. Rendering only reads that option. Saving settings or clicking **Refresh now** triggers an immediate fetch.

On low-traffic sites WP-Cron only runs when someone visits. For exact timing, trigger `wp-cron.php` from a real cron job.

## Privacy

Requests to SerpApi are made from your server only, with your API key and the configured place ID or search text. No visitor data is sent. See SerpApi's [terms](https://serpapi.com/legal) and [privacy policy](https://serpapi.com/privacy-policy).

## Trademarks

Google and Tripadvisor names and logos are trademarks of their respective owners. They're used only to link to the business's own listing; this project isn't affiliated with or endorsed by either company. Icons: [Simple Icons](https://simpleicons.org) (CC0) and the Google "G" from Wikimedia Commons (public domain).

## Requirements

WordPress 6.2+, PHP 7.4+.

## Contributing

Issues and pull requests are welcome.

- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) (`fix: …`, `feat: …`, `feat!: …`). [release-please](https://github.com/googleapis/release-please) uses them to bump the version, write the changelog and publish releases.
- Build an installable zip locally with `./scripts/build-zip.sh` (needs `bash`, `rsync` and `zip`). The result is `dist/rating-glance.zip`.
- CI lints all PHP files on PHP 7.4 through 8.4.

## License

[MIT](LICENSE) © Visco Horeca
