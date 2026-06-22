=== ABS AI Meta Generator ===
Contributors: standexscientific
Tags: seo, yoast, meta description, focus keyword, ai, openai, anthropic, woocommerce, product
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered meta description and focus keyword generator for WooCommerce products. Scans the live product URL and writes SEO-optimized Yoast metadata.

== Description ==

ABS AI Meta Generator uses OpenAI (GPT-4o-mini) or Anthropic (Claude) to scan a live product page URL, extract the real on-page content, and produce two things in a single API call:

1. A **160-character Yoast meta description** that accurately reflects the page.
2. A **Yoast focus keyword** (focus keyphrase) based on the product's primary SEO term.

Because it scans the **live rendered URL** rather than the raw `post_content`, it works with page builders, dynamic blocks, and WooCommerce product templates that inject content at render time.

= Key Features =

* **Per-product panel** - A side meta box on every WooCommerce product edit screen with Generate, Apply Meta Only, Apply Focus Only, and Apply Both buttons.
* **Batch processor** - Scan your entire catalog for products missing meta descriptions, focus keywords, or both, then process in configurable batches (1-10 at a time) with configurable delays.
* **Editable before apply** - Tweak the AI's suggestion in the UI before pushing it into Yoast.
* **Safe by default** - Batch mode only fills empty fields unless you explicitly check "Overwrite existing values."
* **Yoast compatibility** - Multiple fallback methods to inject values into every Yoast version (classic, React, Premium), plus a direct post-meta write as the reliable fallback.
* **Custom instructions** - Append your own prompt guidance (e.g., industry focus, compliance language) in the settings page.
* **Provider choice** - Switch between OpenAI and Anthropic at any time.

= How It Works =

1. The plugin fetches the live product URL via `wp_remote_get`.
2. It parses the HTML using `DOMDocument` and XPath to extract the product title, description, feature lists, specification tables, and main content.
3. The extracted content plus your optional custom instructions are sent to the chosen AI provider.
4. The AI returns a JSON object containing both the meta description and focus keyword.
5. Results are written to Yoast's `_yoast_wpseo_metadesc` and `_yoast_wpseo_focuskw` post meta, and optionally injected live into the editor UI.

== Installation ==

1. Upload the `abs-ai-meta-generator` folder to `/wp-content/plugins/`, or install the .zip via Plugins -> Add New -> Upload Plugin.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **Settings -> AI Meta Generator** and:
   - Choose your AI provider (OpenAI or Anthropic)
   - Enter your API key
   - Optionally add custom prompt instructions
4. Edit any WooCommerce product - you will see the "AI Meta Description Generator" box in the sidebar.
5. For bulk work, go to **Products -> Batch Meta Generator**.

== Frequently Asked Questions ==

= Which AI models does it use? =

OpenAI: `gpt-4o-mini`. Anthropic: `claude-opus-4-8` (Claude Opus 4.8 alias). These can be swapped in the source if you need different models.

= Will it overwrite my existing meta descriptions or focus keywords? =

No, not by default. In the batch tool only empty fields are filled unless you check the "Overwrite existing values" box. On the per-product panel, Apply buttons always write the value currently in the preview field.

= Does it support non-WooCommerce posts? =

The meta box and batch scanner are scoped to the `product` post type (WooCommerce). The core scan + generate logic is post-type agnostic and can be extended.

= Does it require Yoast SEO? =

Yes. It reads and writes Yoast's post meta keys (`_yoast_wpseo_metadesc` and `_yoast_wpseo_focuskw`) and attempts to update the Yoast UI directly.

= Is my API key exposed? =

No. The key is stored in `wp_options` and only sent server-side to the AI provider. It is never exposed to the browser.

= What about rate limits? =

The batch tool lets you control both batch size (1-10 at a time) and the delay between batches (1-5 seconds) so you can stay under provider rate limits.

== Screenshots ==

1. Per-product meta box with Generate, preview, and Apply buttons.
2. Batch scanner results showing Meta/Focus badges for missing fields.
3. Batch processing progress with live log.
4. Settings page for provider selection and API key.

== Changelog ==

= 1.3.2 =
* Update Anthropic model to the `claude-opus-4-8` alias (Claude Opus 4.8).

= 1.3.1 =
* Update Anthropic model to the `claude-opus-4-6` alias (Claude Opus 4.6).

= 1.3.0 =
* Add official `readme.txt`.
* Package as a distributable WordPress plugin zip.
* No functional code changes over 1.2.0.

= 1.2.0 =
* Add Yoast focus keyword (`_yoast_wpseo_focuskw`) generation alongside meta descriptions.
* Single AI call now returns both fields as JSON for efficiency.
* Meta box: new editable Focus Keyword field; Apply Both / Apply Meta Only / Apply Focus Only buttons; live character counter; editable meta description.
* Batch scanner: scan mode radios (either / meta only / focus only / missing both); update mode radios; overwrite checkbox; per-row Missing column with Meta/Focus badges.
* admin.js: 4 fallback methods for injecting the focus keyword into Yoast (data dispatch, classic input, hidden input, React native value setter).
* Safe by default: batch processor preserves existing Yoast values unless overwrite is explicitly enabled.

= 1.1.1 =
* Improved Yoast meta description injection with 7 fallback methods.
* PHP 8.2+ compatible HTML parsing.

= 1.0.0 =
* Initial release: AI meta description generation from live URL scans.

== Upgrade Notice ==

= 1.3.2 =
Updates the Anthropic model to `claude-opus-4-8` (Claude Opus 4.8 alias).

= 1.3.1 =
Updates the Anthropic model to `claude-opus-4-6` (Claude Opus 4.6 alias).

= 1.3.0 =
Documentation and packaging release. Upgrade is recommended but not required if already running 1.2.0.

= 1.2.0 =
Adds focus keyword generation. Existing meta descriptions are preserved.
