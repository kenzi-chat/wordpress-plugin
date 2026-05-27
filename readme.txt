=== Kenzi Chat ===
Contributors: kenzichat
Tags: customer messaging, live chat, support, widget
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add the Kenzi chat widget to your WordPress site for customer messaging.

== Description ==

Kenzi Chat connects your WordPress site to the Kenzi customer messaging platform. Add a chat widget to your site so customers can reach your support team instantly.

**Features:**

* One-click workspace connection via secure OAuth flow
* Chat widget injection on all pages
* Easy enable/disable toggle in Settings
* Developer-friendly base URL configuration for staging/local environments

== Installation ==

1. Upload the `kenzi-chat` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Settings > Kenzi Chat
4. Click "Connect to Kenzi" and select your workspace
5. Enable the widget toggle to show the chat on your site

== Frequently Asked Questions ==

= How do I connect my site? =

Go to Settings > Kenzi Chat and click "Connect to Kenzi". A popup will open where you can sign in and select your workspace.

= How do I change the Kenzi server URL for development? =

Set the `KENZI_APP_BASE` and `KENZI_STATIC_BASE` environment variables to point to your local or staging server. For internal development, source the shared `kenzi.sh` env file before starting your WordPress dev server so PHP can read the values via `getenv()`:

`source /path/to/kenzi.sh && wp server --port=8080`

The env file should export at minimum:

`export KENZI_APP_BASE="http://localhost:4000"`
`export KENZI_STATIC_BASE="http://localhost:4000"`

Without these, the plugin falls back to the production URLs (`https://app.kenzi.chat` and `https://static.kenzi.chat`).

== Changelog ==

= 1.0.0 =
* Initial release: OAuth connect flow, widget injection, WordPress settings integration
