# AGENTS.md

This repository is the canonical source for the Kenzi Chat WordPress plugin.

## Ownership

- Package: `kenzi-chat/wordpress-plugin`
- WordPress plugin slug: `kenzi-chat`
- Primary launch channel: GitHub release ZIP
- PHP floor: 8.1

Do not route changes through `kenzi-chat/web/platforms/wordpress`. That path is
historical after the polyrepo split.

## Compatibility Rules

- Keep `Version:` in `kenzi-chat.php`, `KENZI_CHAT_VERSION`, and `Stable tag:`
  in `readme.txt` aligned.
- This plugin is the base plugin required by `kenzi-commerce`.
- If a change affects WooCommerce compatibility, coordinate the matching
  `kenzi-commerce` release and demo refs.

## Development Commands

Run commands from the repository root.

```bash
composer install
composer validate --strict
composer lint
composer analyze
composer test
bash bin/build-zip.sh
```

## Release Policy

Customer releases are native `v*` tags from this repository. Release automation
must validate the exact tag commit before publishing a GitHub release.

For each release, the tag version without the `v` prefix must match:

- `Version:` in `kenzi-chat.php`
- `KENZI_CHAT_VERSION` in `kenzi-chat.php`
- `Stable tag:` in `readme.txt`

GitHub release ZIPs are the initial install channel. They do not provide native
WordPress auto-update. If a WordPress.org listing is approved later, add SVN
release automation before using that channel.
