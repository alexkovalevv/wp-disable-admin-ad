=== AdsDestroyer - disable admin ad & adblocker ===
Contributors: wpaifactory, alexkovalevv
Author: wpaifactory
Author URI: https://wp-aifactory.com
Tags: disable, notices, ad, focus, hide
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Clean up your WordPress admin panel. Easily remove intrusive ads, system notifications, confidential data, or any other elements using the power and precision of XPath selectors.

== Description ==

AdsDestroyer transforms your WordPress admin into a clean, distraction-free workspace by allowing you to hide or remove any unwanted elements with precision. Whether you want to focus on important tasks, remove distracting ads and notices, or customize your admin interface, this plugin provides the tools you need.

**Perfect for:**
- **Focus & Productivity**: Hide distracting admin notices, promotional banners, and unnecessary UI elements to concentrate on your work
- **Brand Customization**: Remove third-party branding, API keys, and sensitive information from admin screens
- **Clean Interface**: Eliminate clutter by hiding elements that can't be removed through standard WordPress settings
- **Client Management**: Create cleaner admin experiences for clients by hiding complex settings and technical information
- **Development Workflow**: Remove development notices, debug information, and testing elements from production admin

**Key Features:**
- **Visual Selection Mode**: Click the admin bar button and visually select any element to hide
- **Smart XPath Engine**: Automatically generates precise targeting rules with Chrome DevTools-style XPath generation
- **Flexible Duration**: Set rules to hide elements forever or for specific time periods (hours, days, weeks, months)
- **Safe Removal**: Uses HTML5 parsing with placeholder replacement to prevent broken layouts
- **Rule Management**: Full CRUD interface to edit, activate/deactivate, and delete rules
- **Conflict Resolution**: Automatically detects duplicate rules and offers to activate existing ones
- **Multisite Support**: Per-site or network-wide rule management
- **Performance Optimized**: Only processes admin pages, never affects frontend performance

**We drew inspiration from these plugins:**
- Admin Menu Editor Pro
- White Label CMS
- Adminimize
- Remove Admin Notices
- Admin Columns Pro

== Installation ==

1. Upload the plugin to wp-content/plugins/ads-destroyer or clone/install it there.
2. Navigate to Plugins → Activate "AdsDestroyer - disable admin ad & adblocker".
3. From Settings → AdsDestroyer - disable admin ad & adblocker, review defaults and permissions.

== Usage ==

**Quick Start:**
1. **Activate Selection Mode**: Click the "Disable ad" button in the WordPress admin bar
2. **Select Elements**: Hover over any element to preview it, then click to select
3. **Choose Duration**: Click "Hide block" and select how long to hide the element:
   - Hide forever (permanent)
   - For a day (24 hours)
   - For a week (7 days) 
   - For a month (30 days)
4. **Element Hidden**: The selected element disappears immediately and stays hidden based on your chosen duration

**Managing Rules:**
- **View All Rules**: Go to Settings → AdsDestroyer to see all your hidden elements
- **Edit Rules**: Click the edit button to modify XPath rules, add descriptions, or change expiration dates
- **Toggle Rules**: Activate/deactivate rules without deleting them
- **Delete Rules**: Remove rules you no longer need
- **Reset All**: Clear all rules at once if needed

**Advanced Features:**
- **Visual Selection**: The plugin automatically generates precise XPath rules using Chrome DevTools-style targeting
- **Conflict Detection**: If you try to hide an element that's already hidden, the plugin offers to activate the existing rule
- **Safe Removal**: Uses HTML5 parsing to prevent broken layouts when hiding elements
- **Page Information**: Automatically tracks which page each rule was created on for better organization

== Multisite ==

- By default, settings are per site.
- Optional network-wide mode (planned toggle in settings) stores options as a site option across network.

== FAQ ==

= The page layout breaks after hiding a block =
- The plugin uses HTML5 parsing with placeholder replacement to minimize layout issues. If you still experience problems, try selecting a parent container instead of the specific element, or use the "Reset all rules" option to start fresh.

= A block with dynamic CSS classes is not hidden =
- The plugin automatically handles dynamic classes and generates multiple XPath candidates. Try re-selecting the element or select a parent container with a stable ID. The plugin prioritizes elements with data attributes (data-test, data-testid, data-qa) for better targeting.

= How do I reset all rules? =
- Go to Settings → AdsDestroyer → "Reset all rules" button. This will remove all hidden elements and restore the original admin interface.

= Can I hide elements temporarily? =
- Yes! When creating a rule, you can choose from several duration options: hide forever, for a day, for a week, or for a month. Temporary rules automatically expire and restore the hidden elements.

= What if I accidentally hide something important? =
- Use the "Reset all rules" option in the settings page to restore all hidden elements. You can also edit individual rules to deactivate them without deleting them.

= Does this plugin affect my website's frontend? =
- No, this plugin only works in the WordPress admin area and never affects your website's frontend or public pages.

= Can I use this on a multisite network? =
- Yes, the plugin supports multisite installations with per-site rule management. Each site can have its own set of hidden elements.

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

== Screenshots ==

1. Plugin settings page with rules table
2. Visual selection mode with overlay
3. Rule creation interface
4. Admin interface with hidden elements

== Changelog ==

= 1.0.21 =
- Completely redesigned readme.txt with comprehensive use cases and examples
- Added detailed usage instructions with step-by-step guide
- Updated FAQ section with current plugin features
- Added inspiration credits for similar plugins
- Enhanced description with focus on productivity and customization benefits
- Improved documentation for better user understanding

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
