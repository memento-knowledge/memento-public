<?php
/**
 * Plugin Name: Memento — Application Password behind hosting password protection
 * Description: Lets ONE dedicated WordPress account authenticate to the REST API
 *              with a WordPress Application Password while hosting-level HTTP
 *              Basic Auth password protection (e.g. Kinsta htpasswd, WP Engine /
 *              VIP / Pantheon environment locks, nginx auth_basic) stays enabled.
 *              35 lines of code (121 total with comments), no settings screen, no external code, no
 *              network calls. Delete this file to undo everything.
 * Author:      Memento
 * Version:     0.1.240
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * Versioning: this file carries the Memento platform version it was validated
 * against, not an independent version line. Bump it to the platform version
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
//    The name may be anything — since this file is readable, prefer a
//    non-obvious login name if username predictability is a concern. Knowing
//    the name without the 24-character Application Password grants nothing.
// ---------------------------------------------------------------------------
if ( ! defined( 'MEMENTO_WP_API_USER' ) ) {
	define( 'MEMENTO_WP_API_USER', 'memento' );
}

// ---------------------------------------------------------------------------
// 2. On REST API requests ONLY (path contains /wp-json; the plain-permalink
//    ?rest_route= form is deliberately unsupported, as is XML-RPC — narrower
//    surface), keep the two credentials apart:
//      - never let the hosting-level credentials be seen by WordPress as
//        WordPress credentials. The web server has already verified them;
//        core documents these two values as "only used by Application
//        Passwords" (wp-includes/load.php).
//      - accept the WordPress credential from a second, separate header:
//            X-WP-Authorization: Basic base64( "username:application-password" )
//        and place it where core expects it. From here on core's own,
//        unmodified Application Password checks run (hashed comparison,
//        usage recorded) — see wp_authenticate_application_password.
//    Every other request — admin screens, login, front end — is untouched,
//    including its PHP_AUTH_* values.
// ---------------------------------------------------------------------------
$memento_wp_request_path = (string) parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );

if ( false !== strpos( $memento_wp_request_path, '/wp-json' ) ) {
	$memento_wp_auth_header = isset( $_SERVER['HTTP_X_WP_AUTHORIZATION'] ) ? $_SERVER['HTTP_X_WP_AUTHORIZATION'] : '';
	unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );

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
}
unset( $memento_wp_request_path );

// ---------------------------------------------------------------------------
// 3. Tell WordPress the Basic Auth conflict is handled, through the override
//    core provides for exactly this case. Core changeset 50006 (WordPress
//    5.6.1): "This commit extracts the Basic Auth check into a reusable
//    function, wp_is_site_protected_by_basic_auth(), which can be adjusted
//    using a filter of the same name. This way, a site that uses Basic Auth
//    ... can still use the Application Passwords feature." This is what
//    restores the Application Passwords form on the profile screen.
// ---------------------------------------------------------------------------
add_filter( 'wp_is_site_protected_by_basic_auth', '__return_false' );

// ---------------------------------------------------------------------------
// 4. Least privilege: only the configured account may create or use an
//    Application Password — and never an admin-capable one. Core consults
//    this filter both on the profile screen (form shown or not) and at
//    authentication time (wp_authenticate_application_password ->
//    "application_passwords_disabled_for_user"). So even if the configured
//    account is ever promoted to administrator, this API channel shuts off
//    by itself rather than inherit admin power.
// ---------------------------------------------------------------------------
function memento_restrict_application_passwords_to_api_user( $available, $user ) {
	if ( ! $available || ! ( $user instanceof WP_User ) ) {
		return false;
	}
	if ( '' === MEMENTO_WP_API_USER || MEMENTO_WP_API_USER !== $user->user_login ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return false;
	}
	return true;
}
add_filter( 'wp_is_application_passwords_available_for_user', 'memento_restrict_application_passwords_to_api_user', 10, 2 );
