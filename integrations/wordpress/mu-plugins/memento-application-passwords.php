<?php
/**
 * Plugin Name: Memento — Application Password behind hosting password protection
 * Description: Lets ONE dedicated WordPress account authenticate to the REST API
 *              with a WordPress Application Password while hosting-level HTTP
 *              Basic Auth password protection (e.g. Kinsta htpasswd, WP Engine /
 *              VIP / Pantheon environment locks, nginx auth_basic) stays enabled.
 *              22 lines of code (100 total with comments), no settings screen, no
 *              external code, no network calls. Delete this file to undo everything.
 * Author:      Memento
 * Version:     0.1.240
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * Versioning: this file carries the Memento platform version it was validated
 * against (0.1.240, end-to-end on a Kinsta site with password protection on,
 * 2026-08-18), not an independent version line. Bump it to the platform version
 * whenever the file changes and is re-validated.
 *
 * BACKGROUND (verified against WordPress core source, wp-includes/user.php,
 * wp-includes/load.php, wp-admin/user-edit.php):
 *
 * Web requests have one standard slot for credentials: the "Authorization"
 * header. The hosting-level password protection already uses that slot. The
 * web server verifies it BEFORE WordPress runs, then hands the same
 * username/password on to PHP as $_SERVER['PHP_AUTH_USER'] / ['PHP_AUTH_PW'].
 * WordPress core reads exactly those two values as the Application Password
 * credential (wp_validate_application_password). So on a password-protected
 * site WordPress sees the HOSTING password where it expects a WORDPRESS one:
 *   - it hides the Application Passwords form on the profile screen
 *     (wp_is_site_protected_by_basic_auth), and
 *   - once any Application Password exists, it would try to authenticate every
 *     anonymous REST request with the hosting password and fail it with 401.
 *
 * This file keeps the two credentials apart. Nothing about the hosting
 * protection changes: a request that does not pass it never reaches PHP.
 */

// ---------------------------------------------------------------------------
// 1. CONFIGURE — the ONE WordPress account allowed to use an Application
//    Password. Set this to the login name of the account created for Memento.
//    Every other account is denied both creating and using one (see step 4).
// ---------------------------------------------------------------------------
if ( ! defined( 'MEMENTO_WP_API_USER' ) ) {
	define( 'MEMENTO_WP_API_USER', 'memento' );
}

// ---------------------------------------------------------------------------
// 2. Never let the hosting-level credentials be seen by WordPress as
//    WordPress credentials. The web server has already verified them; core
//    itself documents that these two values are "only used by Application
//    Passwords" (wp-includes/load.php, wp_is_site_protected_by_basic_auth).
//    Requests WITHOUT the header in step 3 therefore reach WordPress with no
//    credential at all — exactly like a request to an unprotected site.
// ---------------------------------------------------------------------------
$memento_wp_auth_header = isset( $_SERVER['HTTP_X_WP_AUTHORIZATION'] ) ? $_SERVER['HTTP_X_WP_AUTHORIZATION'] : '';
unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );

// Also state it explicitly through the override WordPress core provides for
// exactly this case. Core changeset 50006 (WordPress 5.6.1): "This commit
// extracts the Basic Auth check into a reusable function,
// wp_is_site_protected_by_basic_auth(), which can be adjusted using a filter of
// the same name. This way, a site that uses Basic Auth ... can still use the
// Application Passwords feature." Redundant with the unset above today; kept so
// the intent is explicit and survives future changes to core's detection.
add_filter( 'wp_is_site_protected_by_basic_auth', '__return_false' );

// ---------------------------------------------------------------------------
// 3. Accept the WordPress credential from a second, separate header:
//        X-WP-Authorization: Basic base64( "username:application-password" )
//    and place it where core expects it. From here on core's own, unmodified
//    Application Password checks run (REST/XML-RPC only, hashed comparison,
//    usage recorded) — see wp_authenticate_application_password.
// ---------------------------------------------------------------------------
if ( '' !== $memento_wp_auth_header && 0 === stripos( $memento_wp_auth_header, 'Basic ' ) ) {
	$memento_wp_decoded = base64_decode( substr( $memento_wp_auth_header, 6 ), true );

	if ( false !== $memento_wp_decoded && false !== strpos( $memento_wp_decoded, ':' ) ) {
		list( $memento_wp_user, $memento_wp_pass ) = explode( ':', $memento_wp_decoded, 2 );

		$_SERVER['PHP_AUTH_USER'] = $memento_wp_user;
		$_SERVER['PHP_AUTH_PW']   = $memento_wp_pass;

		unset( $memento_wp_user, $memento_wp_pass );
	}
	unset( $memento_wp_decoded );
}
unset( $memento_wp_auth_header );

// ---------------------------------------------------------------------------
// 4. Least privilege: only the configured account may create or use an
//    Application Password. Core consults this filter both on the profile
//    screen (form shown or not) and at authentication time
//    (wp_authenticate_application_password -> "application_passwords_disabled_for_user").
//    Any other account — including an administrator — is denied.
// ---------------------------------------------------------------------------
function memento_restrict_application_passwords_to_api_user( $available, $user ) {
	return $available && ( $user instanceof WP_User ) && MEMENTO_WP_API_USER === $user->user_login;
}
add_filter( 'wp_is_application_passwords_available_for_user', 'memento_restrict_application_passwords_to_api_user', 10, 2 );
