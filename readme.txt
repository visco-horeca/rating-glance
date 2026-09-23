=== Rating Glance ===
Tags: reviews, rating, google reviews, tripadvisor, footer
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Show your Google and Tripadvisor rating at a glance: score, number of reviews and a link. Blends into any theme.

== Description ==

A small, dependency-free plugin that shows your Google and Tripadvisor rating at a glance, for example in your site footer:

Google ★★★★★ 4.6 · 1,234 reviews    Tripadvisor ★★★★★ 4.5 · 312 reviews

* Block (Site Editor / footer template part), classic widget and `[rating_glance]` shortcode.
* Inherits your theme's font, size and text color. Works on light and dark backgrounds.
* Ratings are fetched in the background via SerpApi (https://serpapi.com) and cached. Visitors never wait on an external API.
* If a fetch fails, the last known values stay visible.
* Manual override per source, if you prefer to type the numbers in yourself.
* Built-in search to find your Google place ID and Tripadvisor page.
* Accessible: each rating is one link with a descriptive label for screen readers.

= External service =

This plugin connects to SerpApi (https://serpapi.com) to read the public rating and review count of the places you configure. Requests are sent only from your server, on the schedule you pick (default: once a day), plus when you save settings, click "Refresh now" or use the search on the settings page. Each request includes your SerpApi key and the configured place ID or search text. No visitor data is sent. SerpApi terms: https://serpapi.com/legal, privacy policy: https://serpapi.com/privacy-policy.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Settings → Rating Glance and enter a SerpApi key (the free plan is enough: a daily refresh uses about 60 searches a month).
3. Use "Find" to look up your Google place and Tripadvisor page, then save.
4. Add the "Rating Glance" block, the "Rating Glance" widget or the `[rating_glance]` shortcode to your footer.

== Shortcode ==

`[rating_glance sources="google,tripadvisor" display="stars" count="text" layout="inline" align="start" label="yes"]`

* display: stars | compact | text
* count: text ("250 reviews") | number ("(250)") | none
* layout: inline | stacked
* align: start | center | end
* label: yes | no
* new_tab: yes | no
* class: extra CSS class

== Styling ==

The output uses `currentColor` and `font: inherit`. Fine-tune with CSS variables on `.rating-glance`:

`--rg-gap`, `--rg-inner-gap`, `--rg-star-color`, `--rg-star-empty-opacity`, `--rg-count-opacity`.

Example (gold stars): `.rating-glance { --rg-star-color: #e0a526; }`

== Changelog ==

= 1.0.0 =
* First release.
