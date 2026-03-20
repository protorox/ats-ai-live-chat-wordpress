# ATS AI Live Chat for WordPress

A lightweight WordPress live chat plugin with:

- Floating front-end chat widget
- Admin settings page
- Business hours and online/offline logic
- Offline message capture
- Email notifications
- Avada-friendly styling and responsive behavior
- GitHub release-based updates for WordPress

## Install

1. Download the repository zip from GitHub, or clone it into your WordPress plugins directory.
2. Place the plugin in `wp-content/plugins/`.
3. Activate `ATS Live Chat` in WordPress.
4. Configure it from the `ATS Live Chat` admin menu.

## Main Plugin File

`ats-ai-live-chat.php`

## GitHub Updates

This plugin can update from GitHub releases when it is installed as the `ats-ai-live-chat-wordpress` plugin folder in WordPress.

How it works:

- WordPress checks the latest GitHub release for `protorox/ats-ai-live-chat-wordpress`.
- The plugin looks for a release asset named `ats-ai-live-chat-wordpress.zip`.
- If the release version is newer than the installed plugin version, WordPress can update it.

Release process:

1. Bump the `Version` in `ats-ai-live-chat.php`.
2. Commit and push to `main`.
3. Create and push a tag matching the plugin version, for example `v1.0.2`.
4. GitHub Actions builds `ats-ai-live-chat-wordpress.zip` and attaches it to the release.

Example:

```bash
git tag v1.0.2
git push origin main --tags
```

Optional private-repo support in `wp-config.php`:

```php
define( 'ATSLC_GITHUB_TOKEN', 'github_token_here' );
define( 'ATSLC_GITHUB_REPO_OVERRIDE', 'your-user/your-private-repo' );
```
