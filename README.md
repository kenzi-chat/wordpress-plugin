# Kenzi Chat WordPress Plugin

Adds the Kenzi chat widget to WordPress sites and provides the base connection
flow used by the Kenzi Commerce WooCommerce add-on.

## Requirements

- PHP 8.1+
- WordPress 6.7+

## Installation

GitHub release ZIPs are the initial install channel.

1. Download the release ZIP from this repository.
2. Upload the ZIP in WordPress under Plugins > Add New > Upload Plugin.
3. Activate Kenzi Chat.
4. Go to Settings > Kenzi Chat and connect the site to Kenzi.

GitHub release ZIPs do not provide native WordPress auto-update.

## Development

Run commands from the repository root.

```bash
composer install
composer validate --strict
composer lint
composer analyze
composer test
bash bin/build-zip.sh
```

## Releases

Customer releases are native `v*` tags from this repository. The tag version
without the `v` prefix must match:

- `Version:` in `kenzi-chat.php`
- `KENZI_CHAT_VERSION` in `kenzi-chat.php`
- `Stable tag:` in `readme.txt`

If a WordPress.org listing is approved later, add SVN release automation before
using that channel.
