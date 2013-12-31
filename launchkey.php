<?php
/*
  Plugin Name: LaunchKey
  Plugin URI: https://wordpress.org/plugins/launchkey/
  Description:  LaunchKey eliminates the need and liability of passwords by letting you log in and out of WordPress with your smartphone or tablet.
  Version: 0.3.2
  Author: LaunchKey, Inc.
  Author URI: https://launchkey.com
  License: GPLv2 Copyright (c) 2013 LaunchKey, Inc.
 */

define( 'LAUNCHKEY_SSLVERIFY', 1 ); //Only modify to 0 if SSL certificates are broken on your server and you don't have permission to fix them properly!

class LaunchKey {

	/**
	 * __construct
	 *
	 */
	public function __construct() {
		add_action( 'login_form', array( &$this, 'launchkey_form' ) );
		add_action( 'wp_login', array( &$this, 'launchkey_pair' ), 1, 2 );
		add_action( 'wp_logout', array( &$this, 'launchkey_logout' ), 1, 2 );
		add_shortcode( 'launchkey_login', array( $this, 'launchkey_shortcode') );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'launchkey_plugin_page' ) );
			add_action( 'admin_init', array( $this, 'launchkey_page_init' ) );
			add_action( 'wp_ajax_launchkey-callback', array( $this, 'launchkey_callback' ) );
			add_action( 'wp_ajax_nopriv_launchkey-callback', array( $this, 'launchkey_callback' ) );
			add_action( 'profile_personal_options', array( $this, 'launchkey_personal_options' ) );
		}
	} //end __construct

	/**
	 * check_option - used by launchkey_page_init
	 *
	 * @param $input
	 *
	 * @return array
	 */
	public function check_option( $input ) {
		if ( isset( $input['app_key'] ) ) {
			if ( is_numeric( $input['app_key'] ) || $input['app_key'] === '' ) {
				$app_key = trim( $input['app_key'] );
				if ( get_option( 'launchkey_app_key' ) === FALSE ) {
					add_option( 'launchkey_app_key', $app_key );
				}
				else {
					update_option( 'launchkey_app_key', $app_key );
				}
			}
			else {
				$app_key = '';
			}
		}
		else {
			$app_key = '';
		}

		if ( isset( $input['secret_key'] ) ) {
			if ( ctype_alnum( $input['secret_key'] ) || $input['secret_key'] === '' ) {
				$secret_key = trim( $input['secret_key'] );
				if ( get_option( 'launchkey_secret_key' ) === FALSE ) {
					add_option( 'launchkey_secret_key', $secret_key );
				}
				else {
					update_option( 'launchkey_secret_key', $secret_key );
				}
			}
			else {
				$secret_key = '';
			}
		}
		else {
			$secret_key = '';
		}

		$options = array( $app_key, $secret_key );
		return $options;
	} //end check_option

	/**
	 * create_admin_page - used by launchkey_plugin_page
	 */
	public function create_admin_page() {
		echo '<div class="wrap">';
		screen_icon();
		echo '    <h2>LaunchKey</h2>';
		echo '    <form method="post" action="options.php">';
		settings_fields( 'launchkey_option_group' );
		do_settings_sections( 'launchkey-setting-admin' );
		submit_button();
		echo '    </form>';
		echo '</div>';
	} //end create_admin_page

	/**
	 * create_app_key_field
	 */
	public function create_app_key_field() {
		echo '<input type="text" id="app_key" name="array_key[app_key]" value="' . get_option( 'launchkey_app_key' ) . '">';
	} //end create_app_key_field

	/**
	 * create_app_key_secret
	 */
	public function create_secret_key_field() {
		echo '<input type="text" id="secret_key" name="array_key[secret_key]" value="' . get_option( 'launchkey_secret_key' ) . '">';
	} //end create_app_key_secret

	/**
	 * launchkey_callback - handle the oauth callback and authenticate/pair. performed by wp_ajax*_callback action
	 *
	 */
	public function launchkey_callback() {
		if ( isset( $_GET['error'] ) ) {
			wp_redirect( wp_login_url() . "?launchkey_error=1" );
		}

		if ( isset( $_GET['code'] ) ) {
			if ( ctype_alnum( $_GET['code'] ) && strlen( $_GET['code'] ) === 64 ) {
				//prepare data for access token
				$data                  = array();
				$data['client_id']     = get_option( 'launchkey_app_key' );
				$data['client_secret'] = get_option( 'launchkey_secret_key' );
				$data['redirect_uri']  = admin_url();
				$data['code']          = $_GET['code'];
				$data['grant_type']    = "authorization_code";

				//make oauth call
				$params = http_build_query( $data );
				if ( LAUNCHKEY_SSLVERIFY ) {
					$oauth_get = wp_remote_get( "https://oauth.launchkey.com/access_token?" . $params );
				}
				else {
					$oauth_get = wp_remote_get( "https://oauth.launchkey.com/access_token?" . $params, array( 'sslverify' => false ) );
				}

				if ( ! is_wp_error( $oauth_get ) ) {
					$oauth_response = json_decode( $oauth_get['body'], true );
				}
				else {
					wp_redirect( wp_login_url() . "?launchkey_ssl_error=1" );
				}

				if ( isset( $oauth_response['user'] ) && isset( $oauth_response['access_token'] ) ) {
					//vars
					$launchkey_user          = $oauth_response['user'];
					$launchkey_access_token  = $oauth_response['access_token'];
					$launchkey_refresh_token = $oauth_response['refresh_token'];
					$launchkey_expires       = current_time( 'timestamp', true ) + $oauth_response['expires_in'];

					//Match existing user to LaunchKey user
					$meta_args      = array( 'meta_key' => 'launchkey_user', 'meta_value' => $launchkey_user );
					$wordpress_user = get_users( $meta_args );

					//Log the user in or send them to login form to pair their existing account.
					if ( isset( $wordpress_user[0]->ID ) && ! empty( $launchkey_user ) && ! empty( $launchkey_access_token ) ) {
						if ( is_numeric( $wordpress_user[0]->ID ) && $wordpress_user[0]->ID > 0 ) {
							//Set Auth Cookie and Redirect to Admin Dashboard
							wp_set_auth_cookie( $wordpress_user[0]->ID, false );
							setcookie( 'launchkey_access_token', $launchkey_access_token, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );
							setcookie( 'launchkey_refresh_token', $launchkey_refresh_token, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );
							setcookie( 'launchkey_expires', $launchkey_expires, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );
							wp_redirect( admin_url() );
						}
						else {
							wp_redirect( wp_login_url() . "?launchkey_error=1" );
						}
					}
					else {
						//First Time Pair
						setcookie( 'launchkey_user', $launchkey_user, time() + 300, COOKIEPATH, COOKIE_DOMAIN );
						setcookie( 'launchkey_access_token', $launchkey_access_token, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );
						setcookie( 'launchkey_refresh_token', $launchkey_refresh_token, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );
						setcookie( 'launchkey_expires', $launchkey_expires, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );

						if ( ! current_user_can( 'manage_options' ) ) {
							//not previously logged in
							wp_redirect( wp_login_url() . "?launchkey_pair=1" );
						}
						else {
							//previously authenticated
							wp_redirect( admin_url( "profile.php?launchkey_admin_pair=1&updated=1" ) );
						}
					}
				}
				else {
					wp_redirect( wp_login_url() . "?launchkey_error=1" );
				}
			}
			else {
				wp_redirect( wp_login_url() . "?launchkey_error=1" );
			}
		}
		else {
			wp_redirect( wp_login_url() . "?launchkey_error=1" );
		}
	} //end function launchkey_callback

	/**
	 * launchkey_form - login form for wp-login.php
	 *
	 * @access public
	 * @return void
	 */
	public function launchkey_form() {
		$app_key = get_option( 'launchkey_app_key' );
		//output sanitization
		if ( ! is_numeric( $app_key ) ) {
			$app_key = "";
		}

		$redirect = admin_url( 'admin-ajax.php?action=launchkey-callback' );
		if ( isset( $_GET['launchkey_error'] ) ) {
			echo '<div style="padding:10px;background-color:#FFDFDD;border:1px solid #ced9ea;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;"><p style="line-height:1.6em;"><strong>Error!</strong> The LaunchKey request was denied or an issue was detected during authentication. Please try again. </p></div><br>';
		}
		elseif ( isset( $_GET['launchkey_ssl_error'] ) ) {
			echo '<div style="padding:10px;background-color:#FFDFDD;border:1px solid #ced9ea;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;"><p style="line-height:1.6em;"><strong>Error!</strong> There was an error trying to request the LaunchKey servers. If this persists you may need to disable SSL verification. </p></div><br>';
		}
		elseif ( isset( $_GET['launchkey_security'] ) ) {
			echo '<div style="padding:10px;background-color:#FFDFDD;border:1px solid #ced9ea;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;"><p style="line-height:1.6em;"><strong>Error!</strong> There was a security issue detected and you have been logged out for your safety. Log back 0in to ensure a secure session.</p></div><br>';
		}

		if ( isset( $_GET['launchkey_pair'] ) ) {
			echo '<div style="padding:10px;background-color:#eef5ff;border:1px solid #ced9ea;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;"><p style="line-height:1.6em;"><strong>Almost finished!</strong> Log in with your WordPress username and password for the last time to finish the user pair process. After this you can login exclusively with LaunchKey!</p></div><br>';
		}
		else {
			$login_url = 'https://oauth.launchkey.com/authorize?client_id=' . $app_key . '&redirect_uri=' . urlencode($redirect);
			echo '
                <span onclick="window.location.href=\'' . $login_url . '\'" style="cursor:pointer;display:block;text-align:center;padding:0;background-color:#fcfcfc;border:1px solid #e5e5e5;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;">
                    <span style="display:inline-block;height:55px;line-height:55px;padding:0;margin:0;">
                        <span style="display:inline-block;padding:0;">
                            <span style="margin:0 5px -10px 0;display:inline-block;height:30px;width:28px;background-image: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNi4wLjQsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxhdW5jaEtleV9Mb2dvIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiDQoJIHk9IjBweCIgd2lkdGg9IjEwMDBweCIgaGVpZ2h0PSIxMTU5LjQycHgiIHZpZXdCb3g9IjAgMCAxMDAwIDExNTkuNDIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDEwMDAgMTE1OS40MiINCgkgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+DQo8Zz4NCgk8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZmlsbD0iIzQxNjhCMSIgZD0iTTUwMi4zNjQsMTE0My44MTJDMjYzLjk0LDExNTIuNTkxLDY2LjI4Myw5NjYuMzM3LDI2LjQwNSw3NDkuMDENCgkJQy01LjY3Myw1NzQuMTk1LDYxLjMzNiwzNzYuMDExLDIxNi41NTksMjY0LjE0OGMxMi44OS05LjI5MywyNi40ODEtMTcuNjczLDQwLjE5OC0yNS43MDFjMy42MS0yLjExMiw5Ljg0OC0zLjA1NCwxMy4xMzUtMS4yODYNCgkJYzIuNDI0LDEuMzAzLDMuMjE0LDguMDQzLDIuNjc1LDEyLjA4NmMtNy43NjMsNTguNjc4LTkuMTI4LDExNy4zMTcsMC40NzUsMTc1LjkxMmMxLjI4LDcuODItMy4yMTIsMTIuNTktNy4wNDUsMTguMTA0DQoJCWMtMTMuMjMxLDE5LjAzNi0yNy44MDEsMzcuMzM2LTM5LjIwMSw1Ny40MjljLTk2LjkwMywxNzAuODI4LTIwLjc2LDM3OC4yNSwxNjIuNzc2LDQ1Mi4wMjUNCgkJYzE4MS43NzksNzMuMDY2LDM5NC4yNjQtNDkuNjIsNDI2LjMzLTIzOS40MzFjMTUuMzMxLTkwLjc3LTUuODMyLTE3Mi45ODktNTkuMjQ1LTI0Ny4zNTZjLTYuNDUtOC45ODktMTMuODk4LTE3LjI5LTE5Ljg5NS0yNi41NDkNCgkJYy0yLjY3OC00LjEzMS00LjY4My0xMC4xMDktMy45NjYtMTQuNzkxYzguMjU3LTU0LjA2Miw3LjgxMi0xMDguMTQxLDEuNTA1LTE2Mi4yOTljLTAuNjIyLTUuMzYzLTEuNTIzLTEwLjY5NS0yLjA2Ny0xNi4wNjINCgkJYy0wLjk3LTkuNTgyLDUuMDYxLTE0LjI0LDEzLjM3NS05LjQwNmMxNC42NzIsOC41MjUsMjkuMzY3LDE3LjIyLDQzLjAyMSwyNy4yNTRjODguNjA1LDY1LjExMSwxNDYuNjg0LDE1MS4yMDksMTc4LjM5NiwyNTYuNDAzDQoJCWM1Ny4xMjIsMTg5LjQ1NC02LjA0NCw0MjcuMTI5LTIxMy41NzUsNTU2LjIzNUM2NzYuMDg3LDExMjQuODQ2LDYwNC43MjIsMTE0My43MDcsNTAyLjM2NCwxMTQzLjgxMnoiLz4NCgk8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZmlsbD0iIzQxNjhCMSIgZD0iTTU0MS4yNzQsNjUzLjMxMmMtMy41NzksMjkuMzkyLTE3LjU0OCw0NS41MDMtMzguNDUsNDUuMzU2DQoJCWMtMjAuODQzLTAuMTM4LTM0LjA4OS0xNS41NC0zOC4zMTQtNDUuMzc1Yy0xNi44NDIsMC44MzMtMzQuMTA5LTMuMDc3LTUwLjYyLDcuMDc2Yy0xOS4yNSwxMS44MjgtMzkuNzcyLDIxLjYtNTkuNzg4LDMyLjE4MQ0KCQljLTguMTUyLDQuMzExLTExLjA0NywyLjc4LTEyLjUyMi02LjI3NWMtMTEuNDk4LTcwLjU4MSwyMC4yMy0xMzcuMjUyLDgyLjY3Mi0xNzMuMjU2YzMuOTI0LTIuMjY3LDQuOTIzLTQuMjQ1LDQuMTc5LTguODM1DQoJCWMtNi41MDMtNDAuMTk0LTE0LjYyOS04MC4yNjEtMTguNDQtMTIwLjcyN2MtNi43MS03MS4zMTIsMS4zNzQtMTQxLjY2MSwxOS44OTgtMjEwLjg4Mw0KCQljMTIuMjQ0LTQ1Ljc3MSwyOC44NzMtODkuODI1LDQ5LjY3Ny0xMzIuMzMyYzMuNDIyLTYuOTkxLDcuMjIzLTEzLjk1OCwxMS45MDctMjAuMTI3YzcuNTI5LTkuOTEyLDE1LjQzOC05LjczNywyMy4xNiwwLjE2Ng0KCQljMy43ODMsNC44NTcsNy4wMSwxMC4yNzUsOS43MzcsMTUuODA2YzUwLjMyNCwxMDIuMjExLDc3LjA2MywyMDkuNzc0LDczLjQxNywzMjQuMjMyYy0xLjM2Myw0Mi44MDctOS4zMyw4NC42ODgtMTcuMDQzLDEyNi42MjMNCgkJYy0xLjMxNSw3LjE1Ny01LjMyNiwxNS4zOTUtMy4wNzIsMjEuMThjMi4xNDMsNS41MDUsMTAuOTUxLDguMzE5LDE2LjY2OSwxMi41MzNjNTYuNDY4LDQxLjY0LDc5LjcwOSw5Ny4yNTMsNjkuODIxLDE2Ni42NzQNCgkJYy0xLjEwNCw3Ljc1LTQuNTgzLDkuMzAyLTExLjg2Myw1LjQ2MWMtMjAuOTg3LTExLjA0OS00Mi4wNjQtMjEuOTQtNjIuNzcxLTMzLjUwMWMtNy44NDItNC4zODMtMTUuNjItNi4zMjItMjQuNTM2LTYuMDY3DQoJCUM1NTcuNDE4LDY1My40MzgsNTQ5LjQ1MSw2NTMuMzEyLDU0MS4yNzQsNjUzLjMxMnoiLz4NCjwvZz4NCjwvc3ZnPg0K);background-position:0 0;background-size:28px 30px;background-repeat:no-repeat;"></span>
                            </span>
                        <a href="' . $login_url . '" title="Log in with LaunchKey" style="text-decoration:none;">Log in with LaunchKey</a>
                    </span>
                </span><br>';
		}
	} //end launchkey_form

	/**
	 * launchkey_logout - performed during wp_logout action
	 *
	 * @access public
	 * @return void
	 */
	public function launchkey_logout() {
		if ( isset( $_COOKIE['launchkey_access_token'] ) ) {
			if ( LAUNCHKEY_SSLVERIFY ) {
				wp_remote_get( 'https://oauth.launchkey.com/logout?access_token=' . $_COOKIE['launchkey_access_token'] );
			}
			else {
				wp_remote_get( 'https://oauth.launchkey.com/logout?access_token=' . $_COOKIE['launchkey_access_token'], array( 'sslverify' => false ) );
			}
			setcookie( 'launchkey_user', '1', time() - 60, COOKIEPATH, COOKIE_DOMAIN );
			setcookie( 'launchkey_access_token', '1', time() - 60, COOKIEPATH, COOKIE_DOMAIN );
			setcookie( 'launchkey_refresh_token', '1', time() - 60, COOKIEPATH, COOKIE_DOMAIN );
			setcookie( 'launchkey_expires', '1', time() - 60, COOKIEPATH, COOKIE_DOMAIN );
		}
	} //end launchkey_logout

	/**
	 * launchkey_page_init - performed during admin_init action
	 *
	 */
	public function launchkey_page_init() {
		if ( isset( $_GET['launchkey_unpair'] ) ) {
			if ( isset( $_GET['launchkey_nonce'] ) ) {
				if ( ! wp_verify_nonce( $_GET['launchkey_nonce'], 'launchkey_unpair-remove-nonce' ) ) {
					wp_logout();
				}
				else {
					$user = wp_get_current_user();
					$this->launchkey_unpair( $user->data );
					wp_logout();
				}
			}
		}

		if ( isset( $_GET['launchkey_remove_password'] ) ) {
			if ( isset( $_GET['launchkey_nonce'] ) ) {
				if ( ! wp_verify_nonce( $_GET['launchkey_nonce'], 'launchkey_unpair-remove-nonce' ) ) {
					wp_logout();
				}
				else {
					$user = wp_get_current_user();
					if ( $user->data->ID > 0 ) {
						wp_update_user( array( 'ID' => $user->data->ID, 'user_pass' => '' ) );
						wp_logout();
					}
				}
			}
		}

		if ( isset( $_GET['launchkey_admin_pair'] ) ) {
			$user = wp_get_current_user();
			$this->launchkey_pair( "", $user->data );
		}

		//check status of oauth access token
		if ( isset( $_COOKIE['launchkey_access_token'] ) ) {
			if ( LAUNCHKEY_SSLVERIFY ) {
				$args = array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $_COOKIE['launchkey_access_token']
					)
				);
			}
			else {
				$args = array(
					'headers'      => array(
						'Authorization' => 'Bearer ' . $_COOKIE['launchkey_access_token']
					), 'sslverify' => false
				);
			}
			$oauth_response = wp_remote_request( "https://oauth.launchkey.com/resource/ping", $args );
			if ( $oauth_response['body'] != '{"message": "valid"}' ) {
				//refresh_token
				if ( isset( $_COOKIE['launchkey_refresh_token'] ) ) {
					//prepare data for access token
					$data                  = array();
					$data['client_id']     = get_option( 'launchkey_app_key' );
					$data['client_secret'] = get_option( 'launchkey_secret_key' );
					$data['redirect_uri']  = admin_url();
					$data['refresh_token'] = $_COOKIE['launchkey_refresh_token'];
					$data['grant_type']    = "refresh_token";

					//make oauth call
					$params = http_build_query( $data );
					if ( LAUNCHKEY_SSLVERIFY ) {
						$oauth_get = wp_remote_get( "https://oauth.launchkey.com/access_token?" . $params );
					}
					else {
						$oauth_get = wp_remote_get( "https://oauth.launchkey.com/access_token?" . $params, array( 'sslverify' => false ) );
					}

					if ( ! is_wp_error( $oauth_get ) ) {
						$oauth_response = json_decode( $oauth_get['body'], true );
					}
					else {
						wp_logout();
						wp_redirect( wp_login_url() . "?launchkey_ssl_error=1" );
					}

					if ( isset( $oauth_response['refresh_token'] ) && isset( $oauth_response['access_token'] ) ) {
						$launchkey_access_token  = $oauth_response['access_token'];
						$launchkey_refresh_token = $oauth_response['refresh_token'];
						$launchkey_expires       = current_time( 'timestamp', true ) + $oauth_response['expires_in'];
						setcookie( 'launchkey_access_token', $launchkey_access_token, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );
						setcookie( 'launchkey_refresh_token', $launchkey_refresh_token, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );
						setcookie( 'launchkey_expires', $launchkey_expires, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN );
					}
					else {
						wp_logout();
						wp_redirect( wp_login_url() . "?loggedout=1" );
					}
				}
				else {
					wp_logout();
					wp_redirect( wp_login_url() . "?loggedout=1" );
				}
			}
		}

		register_setting( 'launchkey_option_group', 'array_key', array( $this, 'check_option' ) );
		add_settings_section( 'setting_section_id',
			'Settings',
			array( $this, 'launchkey_section_info' ),
			'launchkey-setting-admin'
		);

		add_settings_field( 'app_key',
			'App Key',
			array( $this, 'create_app_key_field' ),
			'launchkey-setting-admin',
			'setting_section_id'
		);
		add_settings_field( 'secret_key',
			'Secret Key',
			array( $this, 'create_secret_key_field' ),
			'launchkey-setting-admin',
			'setting_section_id'
		);
	} //end function launchkey_page_init

	/**
	 * launchkey_pair - pair a launchkey user with the WordPress user. performed during wp_login.
	 *
	 * @param mixed $not_used - required
	 * @param mixed $user
	 *
	 * @access public
	 * @return void
	 */
	public function launchkey_pair( $not_used, $user ) {
		if ( isset( $_COOKIE['launchkey_user'] ) ) {
			if ( is_numeric( $user->ID ) && $user->ID > 0 && ctype_alnum( $_COOKIE['launchkey_user'] ) && strlen( $_COOKIE['launchkey_user'] ) > 10 ) {
				update_user_meta( $user->ID, "launchkey_user", $_COOKIE['launchkey_user'] );
			}
		}
	} //end launchkey_pair

	/**
	 * launchkey_personal_options
	 *
	 * @param $user
	 */
	public function launchkey_personal_options( $user ) {
		echo '<div class="wrap">';
		echo '    <h3>LaunchKey Options</h3>';
		$user_meta = get_user_meta( $user->data->ID );
		if ( array_key_exists( 'launchkey_user', $user_meta ) ) {
			//check if password is set before allowing unpair
			if ( ! empty( $user->data->user_pass ) ) {
				$nonce        = wp_create_nonce( 'launchkey_unpair-remove-nonce' );
				$url          = admin_url( '/profile.php?launchkey_unpair=1&launchkey_nonce=' . $nonce );
				$password_url = admin_url( '/profile.php?launchkey_remove_password=1&launchkey_nonce=' . $nonce );
				echo '<p><em>Note</em>: unpairing a device or removing your WP password will log you out of WordPress.</p><table class="form-table"><tr><th>Status: <em>paired</em></th><td><a href="' . $url . '" title="Click here to unpair your LaunchKey account with this WordPress account">Unpair</a></td></tr><tr><th>WP Password</th><td><a href="' . $password_url . ' " title="Click here to remove your WordPress password">Remove WP password</a></td></tr></table>';
			}
			else {
				echo '<table class="form-table"><tr><th>Status: <em>paired</em></th><td></td></tr><tr><th>WP Password</th><td><em>Removed</em>, use form below to add password</td></tr></table>';
			}
		}
		else {
			$app_key   = get_option( 'launchkey_app_key' );
			$redirect  = admin_url( 'admin-ajax.php?action=launchkey-callback&launchkey_admin_pair=1' );
			$login_url = 'https://oauth.launchkey.com/authorize?client_id=' . $app_key . '&redirect_uri=' . $redirect;
			echo '<table class="form-table"><tr><th>Status: <em>not paired</em></th><td><a href="' . $login_url . '" title="Click here to pair your LaunchKey account with this WordPress account">Click to pair</a></td></tr></table>';
		}
		echo '</div>';
	} //end launchkey_personal_options

	/**
	 * launchkey_plugin_page - performed by admin_menu action
	 *
	 */
	public function launchkey_plugin_page() {
		// This page will be under "Settings"
		add_options_page( 'LaunchKey', 'LaunchKey', 'manage_options', 'launchkey-setting-admin',
			array( $this, 'create_admin_page' ) );
	} //end launchkey_plugin_page

	/**
	 * launchkey_section_info - used by launchkey_page_init
	 */
	public function launchkey_section_info() {
		echo 'For Setup information please see the <a href="https://launchkey.com/docs/plugins/wordpress">LaunchKey WordPress Documentation</a>.';
	} //end function launchkey_section_info

	/**
	 * launchkey_unpair - unpair a launchkey user with the WordPress user.
	 *
	 * @param mixed $user
	 *
	 * @access public
	 * @return void
	 */
	public function launchkey_unpair( $user ) {
		if ( is_numeric( $user->ID ) && $user->ID > 0 && strlen( $user->user_pass ) > 0 ) {
			delete_user_meta( $user->ID, 'launchkey_user' );
		}
	} //end launchkey_unpair

	/**
	 * launchkey_shortcode - outputs a launchkey login button
	 *
	 * @access public
	 * @return void
	 */
	public function launchkey_shortcode() {
		$this->launchkey_form();
	} //end launchkey_shortcode

} //end class LaunchKey

$LaunchKey = new LaunchKey();
