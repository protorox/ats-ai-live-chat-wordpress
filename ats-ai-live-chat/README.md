# ATS AI Live Chat (Version 1.1.1 working)

Self-hosted live chat plugin for WordPress 6.x with:

- Frontend chat bubble/widget for anonymous visitors.
- Live visitors list in wp-admin with page URL/title, recent page views, and typing preview.
- Agent dashboard with conversation thread and near real-time polling.
- WooCommerce support for product search/product cards and cart context.
- AI modes: Off, Auto (when no agents online), Draft replies.
- Cookie consent notice toggle and retention cleanup via WP-Cron.

## Install

1. Copy folder `ats-ai-live-chat` into `wp-content/plugins/`.
2. In WordPress admin, activate **ATS AI Live Chat**.
3. Go to **Live Chat > Settings**:
   - Set AI mode (`Off`, `Auto`, or `Draft`).
   - Add OpenAI API key/model if using AI.
   - Configure retention and cookie notice text.
4. Open **Live Chat** in wp-admin in one browser tab (agent).
5. Open site frontend in another tab/incognito (visitor) to test chat.

## Local test checklist

1. Activate plugin and ensure chat bubble appears frontend.
2. Open **/wp-admin/admin.php?page=ats-chat-live-chat** as admin/agent.
3. Visit frontend page and confirm visitor appears in left panel with current URL.
4. Send visitor message and verify agent receives it in ~2 seconds.
5. Send agent reply and verify visitor receives it in ~2 seconds.
6. Type in visitor input and verify admin sees typing indicator.
7. If WooCommerce active:
   - Add item to cart as visitor.
   - Verify cart context appears in admin panel.
   - Use **Send Product** search and send product card.
   - Verify product card renders in visitor chat.
8. Set AI mode to `Auto`, close admin panel (no agents online), send visitor message.
   - Verify AI auto-reply (if API key configured).
9. Set AI mode to `Draft`, use **AI Draft** in admin panel and click **Use Draft**.

## Troubleshooting

- **REST errors / 404**: Go to `Settings > Permalinks` and click **Save Changes** to flush rewrite rules.
- **Nonce errors (403)**: Clear page cache/CDN cache; ensure the page is not serving stale nonce values.
- **No real-time updates**: Disable aggressive caching/minification for plugin JS and REST endpoints.
- **Visitors not showing**: Confirm admin dashboard tab remains open; agent online status is heartbeat-based.
- **Woo features missing**: Ensure WooCommerce is active and products are published.
- **AI not responding**: Verify API key/model in settings and server can make outbound HTTPS requests.
- **Cron cleanup not running**: Trigger WP-Cron via site traffic or set a real server cron hitting `wp-cron.php`.

## Notes

- v1.0 uses REST polling (no paid realtime service).
- Structure allows future WebSocket replacement of polling.
- API keys remain server-side and are never exposed to visitors.

## GitHub Auto Updates

This plugin now supports auto-updates from GitHub Releases.

1. Put this plugin code in a GitHub repo (public or private).
2. Configure WordPress with your repo in `wp-config.php`:

```php
define( 'ATS_CHAT_GITHUB_REPO', 'YOUR_GITHUB_USERNAME/YOUR_REPO_NAME' );
// Optional only for private repos:
define( 'ATS_CHAT_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx' );
```

3. Publish GitHub releases with tags matching plugin version (example `v1.1.0`).
4. Attach a zip asset named `ats-ai-live-chat.zip` that contains top-level folder `ats-ai-live-chat/`.
5. WordPress update checks will detect the new release and show update in Plugins screen.
