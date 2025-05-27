# GF Post Clone Multisite

**Contributors:** GF  
**Tags:** multisite, post clone, duplicate post, ACF, attachments  
**Requires at least:** 5.0  
**Tested up to:** 6.5  
**Stable tag:** 1.6  
**Requires PHP:** 7.4  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

## Description

Adds a "Clone to another site" action for posts, allowing administrators to duplicate a post (including metadata, ACF fields, and attachments) to another site within the same WordPress multisite network.

## Features

- Clone posts to any other site in the network
- Supports metadata, featured image, and post attachments
- Deep integration with ACF fields (including image/file fields)
- Maintains attachment relationships and thumbnail references
- Provides admin bar shortcut and post list row action
- Displays a link back to the original post on cloned entries

## How It Works

- Adds a "Clone to..." action in the post list for all post types.
- Presents a simple interface listing all other sites with icons and names.
- Copies post data, metadata, ACF fields, and media to the selected target site.
- Maintains a `_cloned_from` meta reference.
- Offers a “View Original” link in the admin bar if the current post is a clone.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/gf-post-clone-multisite` directory, or install via the WordPress Plugin Directory.
2. Activate the plugin through the “Plugins” screen in WordPress.
3. Make sure the plugin is active on all subsites if using subsite-level activation.

## Usage

1. Go to any post list screen (Posts, Pages, or Custom Post Types).
2. Click the **“Clone to…”** action under a post.
3. Choose a target site from the displayed grid.
4. Click **Clone**.
5. You’ll be redirected to the newly created draft on the target site.

## Notes

- This plugin only works on multisite installations.
- It handles only posts and attachments; terms and user authors are not cloned.
- Requires ACF and ACF Pro if using advanced ACF fields (image, file, repeater, flexible content, etc).

## Changelog

### 1.6
- Added admin bar shortcut on post edit screen
- Display link to original post when viewing a clone
- Improved ACF media cloning handling

### 1.0
- Initial release

## License

This plugin is licensed under the GPLv2 or later.  
You are free to use, modify, and distribute it as per the license terms.
