=== Kenzi Chat ===
Contributors: kenzichat
Tags: customer messaging, live chat, support, widget
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bring customer messages, order details, and conversation history together in one shared inbox.

== Description ==

Kenzi is a customer support platform for teams that want every customer conversation in one place. Bring together live chat, email, Facebook Messenger, and Instagram DMs in a shared Kenzi inbox so your team can see what needs attention, respond faster, and keep support organized.

The Kenzi Chat plugin connects your WordPress site to Kenzi and adds the live chat experience to your site, so visitors can reach your team from the same place they browse.

**Features:**

* Add Kenzi live chat to your WordPress website
* See customer support conversations from live chat, email, Facebook Messenger, and Instagram in one inbox
* View customer details, order info, and previous conversations alongside each chat
* Organize, filter, and save custom inbox views
* Work together with your support team in a shared workspace
* Install in about a minute with no code required
* As easy to use as texting

== External services ==

Kenzi Chat connects to the Kenzi customer messaging service at `https://app.kenzi.chat` when an administrator connects, configures, disconnects, or checks the integration status for this plugin.

When the site is connected to Kenzi and the widget setting is enabled, the plugin loads the chat widget loader script from `https://static.kenzi.chat/widget/loader.js` with the connected workspace ID in the request URL. This script is loaded on frontend pages to render the Kenzi chat widget and allow visitors to message the site's Kenzi workspace.

== Installation ==

1. Upload the `kenzi-chat` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Settings > Kenzi Chat
4. Click "Connect to Kenzi" and select your workspace
5. Enable the widget toggle to show the chat on your site

== Frequently Asked Questions ==

= How do I connect my site? =

Go to Settings > Kenzi Chat and click "Connect to Kenzi". A popup will open where you can sign in and select your workspace.

= Do I need a Kenzi account? =

Yes. This plugin connects your WordPress site to a Kenzi workspace, where your team manages customer conversations. Kenzi includes live chat, email, Facebook Messenger, and Instagram messaging in one shared inbox.

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
