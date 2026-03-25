# ACE Social Planner

ACE Social Planner is a WordPress plugin project from AceMedia focused on AI-assisted social content workflows inside WordPress.

The repository is intentionally early-stage and evolving. It currently contains the plugin bootstrap, initial REST API wiring, and a small admin UI foundation for future expansion.

This codebase is being developed as part of broader internal tooling and publishing experiments. Documentation is kept intentionally light while the architecture settles.

Maintainer: Shane Rounce  
Organisation: [AceMedia.ninja](https://acemedia.ninja)

## Social Connection Setup

### X (Twitter) OAuth setup

1. Open the X developer dashboard: https://developer.x.com/en/portal/dashboard
2. Create/select an app and enable OAuth 2.0 Authorization Code with PKCE.
3. Add the callback URL shown in the plugin Connections tab.
4. Copy the OAuth Client ID into the plugin (Connections -> X).
5. Save settings, click **Connect X**, approve, then send a test post.

Reference docs:
- https://developer.x.com/en/docs/authentication/oauth-2-0/authorization-code

### Facebook OAuth setup

1. Open Meta for Developers: https://developers.facebook.com/apps/
2. Create/select an app and configure Facebook Login with the callback URL shown in the plugin Connections tab.
3. Add permissions for pages access and publishing:
- https://developers.facebook.com/docs/permissions/reference/pages_show_list/
- https://developers.facebook.com/docs/permissions/reference/pages_manage_posts/
4. Copy App ID and App Secret into the plugin (Connections -> Facebook).
5. Save settings, click **Connect Facebook**, then choose a Page and send a test post.

Note: Facebook publishing in this plugin targets Pages. Personal profile publishing is not generally available via modern Facebook API permissions.
