=== AdsDestroyer - disable admin ad & adblocker ===
Contributors: wpaifactory, alexkovalevv
Author: wpaifactory
Author URI: https://wp-aifactory.com
Tags: disable, ads, notices, ad, block
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.20
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hide ads and unwanted blocks in WP Admin with XPath rules, a visual selector, safe output filtering, and flexible settings.

== Description ==

Disable Admin Ad hides or removes arbitrary HTML blocks in the WordPress admin using user-defined XPath rules.

Key features:
- Visual selection mode: pick elements from the admin UI via a toolbar button (“Selector”).
- Safe output filtering: HTML5 parsing (Masterminds HTML5) with placeholder removal to prevent broken markup.
- XPath engine with dynamic class relaxation to better target CSS-in-JS elements.
- Per-rule activation, expiration (duration), and labeling.
- Settings page: manage rules (CRUD), reset all, network-aware options (per-site by default), logging.
- Multisite support: per-site options with optional network-wide.
- Security: REST nonce validation, capability checks, sanitization, no dangerous eval.
- Performance: output buffering only when needed and only in admin area (never on frontend).
- Localization-ready.

== Installation ==

1. Upload the plugin to wp-content/plugins/ads-destroyer or clone/install it there.
2. Navigate to Plugins → Activate "AdsDestroyer - disable admin ad & adblocker".
3. From Settings → AdsDestroyer - disable admin ad & adblocker, review defaults and permissions.

== Usage ==

- In the admin bar, click the "Selector" target icon.
- Follow the hint: hover to preview, click to select, then click "Скрыть блок" to save a rule.
- You will be prompted for a duration (hours). Enter 0 for "forever".
- The block disappears immediately. On subsequent loads, the block remains hidden/removed.
- Manage rules in Settings → AdsDestroyer - disable admin ad & adblocker: edit label/XPath/expiry, delete rules, reset all.

== Multisite ==

- By default, settings are per site.
- Optional network-wide mode (planned toggle in settings) stores options as a site option across network.

== FAQ ==

= The page layout breaks after hiding a block =
- Switch to "hide via CSS" mode. Or keep delete mode on but rely on placeholders. The plugin uses an HTML5 parser and placeholders to minimize breakage.

= A block with CSS-in-JS classes is not hidden =
- The plugin relaxes dynamic classes server-side and filters them client-side. Try re-selecting the parent container or a node with a stable id/label. Data attributes (data-test/testid/qa) are preferred.

= How to reset all rules? =
- Settings → AdsDestroyer - disable admin ad & adblocker → "Reset all rules".

== Output Buffering ==

This plugin uses PHP output buffering (ob_start) to process HTML content in the WordPress admin panel. The buffering is **only active in the admin area** and **never affects the frontend** of your website.

**Important for hosting providers:**
- The plugin does NOT use output buffering on frontend pages
- Admin panel pages are rarely cached by hosting providers due to their personalized nature
- If you have admin panel caching enabled on your hosting, it may cause conflicts with the plugin's functionality
- If you have problems with Wordpress admin panel caching. We recommend disabling admin panel caching when using this plugin

**Technical details:**
- The buffering is used to apply XPath rules to hide unwanted elements in the admin interface
- No impact on frontend performance or caching

== Development & Build Instructions ==

This plugin uses modern build tools for JavaScript and CSS compilation. To build the plugin from source:

**Prerequisites:**
- Node.js (version 16 or higher)
- npm (comes with Node.js)

**Build Commands:**
- `npm install` - Install dependencies
- `npm run build` - Build production assets and create distribution package
- `npm run build:dev` - Build development assets (with source maps)
- `npm run dev` - Build development assets (alias for build:dev)

**Build Process:**
1. JavaScript files are compiled using Vite from `assets/js/` directory
2. SCSS files are compiled to CSS from `assets/scss/` directory  
3. Compiled assets are output to `build/` directory
4. Final distribution package is created as `build/compiled/ads-destroyer-[version].zip`

**Source Files:**
- JavaScript: `assets/js/main.js`, `assets/js/admin-settings.js`, and related modules
- SCSS: `assets/scss/index.scss`, `assets/scss/admin-settings.scss`, and component files
- Build configuration: `vite.config.js`, `package.json`

**Output Structure:**
- `build/js/` - Compiled JavaScript files
- `build/css/` - Compiled CSS files
- `build/compiled/` - Distribution packages

== Changelog ==

= 1.0.20 =
- Added comprehensive build instructions to readme.txt
- Updated development documentation with Node.js build process
- Improved plugin documentation for developers

= 1.0.19 =
- Updated all plugin prefixes from AIDAD/aidad to ADSD/adsd for WordPress.org compliance
- Added output buffering documentation for hosting providers
- Improved admin-only buffering with clear frontend exclusion

= 1.0.18 =
- Visual overlay updated: blur/hatch on hover and selection, centered action button, instruction hint.
- Safe removal via HTML5 and placeholders.
- Dynamic class filtering in XPath builder and server-side relaxation.
- Rules: expiry support (duration on save).
- Settings: actions column, edit in place, author display, instruction.
- PHP 8.0 minimum.
