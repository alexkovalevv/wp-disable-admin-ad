=== Disable Admin Ad ===
Contributors: alexkovalevv
Tags: admin, ads, hide, xpath, ui, selection, wordpress, multisite
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Disable advertising and unwanted blocks in the WordPress admin using XPath rules. Includes a visual selection mode, output buffering sanitizer, and fine-grained settings.

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
- Performance: buffering only when needed and only in admin.
- Localization-ready.

== Installation ==

1. Upload the plugin to wp-content/plugins/disable-admin-ad or clone/install it there.
2. Navigate to Plugins → Activate "Disable Admin Ad".
3. From Settings → Disable Admin Ad, review defaults and permissions.
4. Build frontend assets (if building from source):
   - npm install && npm run build
   - composer install (or composer require masterminds/html5:^2.8)

== Usage ==

- In the admin bar, click the "Selector" target icon.
- Follow the hint: hover to preview, click to select, then click "Скрыть блок" to save a rule.
- You will be prompted for a duration (hours). Enter 0 for "forever".
- The block disappears immediately. On subsequent loads, the block remains hidden/removed.
- Manage rules in Settings → Disable Admin Ad: edit label/XPath/expiry, delete rules, reset all.

== Settings ==

- Enabled: turn the plugin on/off.
- Rules table:
  - Active: rule on/off
  - XPath: the expression used to target elements
  - Label: optional label
  - Author: user who created the rule
  - Created: creation date
  - Actions: edit (inline), delete
- Access control: allowed roles for selection mode.
- UI customization: colors for hover/selected, dimming opacity, blur, hotkey.
- Mode: delete nodes (safe placeholders) or hide via CSS.
- Safe preview: show hidden blocks to admins temporarily.
- Export/Import (JSON): manage rules across sites (optional; can be added on demand).
- Logging: enable/disable, keep last N entries.

== Multisite ==

- By default, settings are per site.
- Optional network-wide mode (planned toggle in settings) stores options as a site option across network.

== Security ==

- REST endpoints require X-WP-Nonce (wp_rest action).
- Capability checks: manage_options for write operations.
- Sanitization/validation of XPath and input payloads.
- Safe DOM parsing: Masterminds HTML5 preferred.

== FAQ ==

= The page layout breaks after hiding a block =
- Switch to "hide via CSS" mode. Or keep delete mode on but rely on placeholders. The plugin uses an HTML5 parser and placeholders to minimize breakage.

= A block with CSS-in-JS classes is not hidden =
- The plugin relaxes dynamic classes server-side and filters them client-side. Try re-selecting the parent container or a node with a stable id/label. Data attributes (data-test/testid/qa) are preferred.

= How to reset all rules? =
- Settings → Disable Admin Ad → "Reset all rules".

== Changelog ==

= 1.0.1 =
- Visual overlay updated: blur/hatch on hover and selection, centered action button, instruction hint.
- Safe removal via HTML5 and placeholders.
- Dynamic class filtering in XPath builder and server-side relaxation.
- Rules: expiry support (duration on save).
- Settings: actions column, edit in place, author display, instruction.
- PHP 8.0 minimum.

== Upgrade Notice ==

= 1.0.1 =
After upgrade, rebuild assets if you develop from source (npm run build). Composer: composer require masterminds/html5:^2.8.
