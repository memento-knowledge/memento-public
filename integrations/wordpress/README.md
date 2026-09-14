# Memento WordPress integration — customer-side artifacts

Files a customer installs into **their own** WordPress site so Memento's
`wordpress_self_hosted` integration can connect. Memento-authored code that
runs inside the customer's system, reviewed and installed by the customer.

## Layout

```
integrations/wordpress/
└── mu-plugins/
    └── memento-application-passwords.php
```

## `mu-plugins/memento-application-passwords.php`

**When it is needed:** only when the customer's WordPress site sits behind
*hosting-level* HTTP Basic Auth (Kinsta password protection / htpasswd, WP Engine
or WordPress VIP or Pantheon environment locks, a plain nginx `auth_basic`). Sites
without such a gate do not need it — Application Passwords work out of the box.

**The problem it solves:** WordPress Application Passwords are transmitted as
`Authorization: Basic …` — the same header the hosting gate consumes. WordPress
core detects the gate and hides the Application Passwords form (core changeset
49752, Trac #51939), so the customer cannot create a credential for Memento.

**What it does** (35 lines of code, 118 total with comments; no settings screen,
no external code, no network calls):

1. **On REST API requests only** (paths under `/wp-json`; admin screens, login
   and front-end requests are untouched): keeps the hosting-gate credentials
   from being read by WordPress as WordPress credentials, and accepts the
   WordPress credential from a second header,
   `X-WP-Authorization: Basic base64(user:application-password)`, handing it to
   core's unmodified Application Password authentication.
2. **States the Basic-Auth override explicitly** through the filter core
   provides for exactly this case (`wp_is_site_protected_by_basic_auth` — core
   changeset 50006: *"a site that uses Basic Auth … can still use the
   Application Passwords feature"*). This restores the creation form.
3. **Restricts Application Passwords to a single configured account**
   (`MEMENTO_WP_API_USER`) via core's `wp_is_application_passwords_available_for_user`
   filter — no other account can create or use one — **and refuses
   admin-capable accounts outright**: if the configured account holds
   `manage_options` (administrator), the channel disables itself rather than
   inherit admin power.

**Install:**

1. The line `define( 'MEMENTO_WP_API_USER', 'memento' );` near the top of the
   plugin file names the one account allowed to use an Application Password —
   it defaults to `memento`; change it only if the account created for Memento
   has a different login name. The account should be an **Editor** (never an
   administrator — the plugin refuses admin accounts by design).
2. Upload the file to `wp-content/mu-plugins/` (create the folder if absent —
   on Kinsta it already exists). No activation step; it appears under
   Plugins → *Must-Use*. Delete the file to undo everything.

**Deployment scope:** deciding *which site* this file belongs on is the
installing administrator's responsibility, exactly like any other mu-plugin —
the file deliberately contains no environment detection. Code cannot police
its own deployment: anyone able to place files on a site can equally alter
such a check, and a self-disabling file would fail silently instead of
loudly. Install it only on the site it is intended for.

**Operational notes:**

- The `X-WP-Authorization` header carries a Base64 credential; confirm with the
  hosting provider that access logs do not record custom request headers
  (default nginx log formats do not).
- The account name in `MEMENTO_WP_API_USER` may be anything; pick a
  non-obvious login name if username predictability is a concern. Knowing the
  name without the 24-character Application Password grants nothing.

**Verification:** tested end-to-end on a Kinsta-hosted WordPress site with
hosting-level password protection enabled (2026-08-18) — confirmed the
Application Passwords creation form reappears, that REST API requests
authenticate correctly through both credential layers, and that no account
other than the configured one can create or use an Application Password. The
behavior relied on is documented in WordPress core itself and cited directly
in the plugin file's header comment (`wp-includes/user.php`,
`wp-includes/load.php`, `wp-admin/user-edit.php`, and the core changesets
referenced above). The 2026-09 hardening changes (REST-only scoping,
admin-account refusal, empty-name guard, /wp-json segment boundary) were
re-validated end-to-end on the same fixture on 2026-09-14 (version 1.2.0).

## License

MIT — see [LICENSE](../../LICENSE) in the repository root.
