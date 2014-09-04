<?php
/*
  Plugin Name: LaunchKey
  Plugin URI: https://wordpress.org/plugins/launchkey/
  Description:  LaunchKey eliminates the need and liability of passwords by letting you log in and out of WordPress with your smartphone or tablet.
  Version: 0.4.3
  Author: LaunchKey, Inc.
  Text Domain: launchkey
  Author URI: https://launchkey.com
  License: GPLv2 Copyright (c) 2014 LaunchKey, Inc.
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
		add_action( 'plugins_loaded', array( &$this, 'launchkey_plugins_loaded' ) );
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
	 * launchkey_plugins_loaded
	 */
	public function launchkey_plugins_loaded() {
		// Internationalization
		$dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
		$loaded = load_plugin_textdomain('launchkey', false, $dir);
	}

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
 	 * @param string $class
 	 * @param string $id
 	 * @param string $style
	 *
	 * @access public
	 * @return void
	 */
	public function launchkey_form($class = '', $id = '', $style = '') {
		$app_key = get_option( 'launchkey_app_key' );
		//output sanitization
		if ( ! is_numeric( $app_key ) ) {
			$app_key = "";
		}

		$redirect = admin_url( 'admin-ajax.php?action=launchkey-callback' );
		if ( isset( $_GET['launchkey_error'] ) ) {
			echo '<div style="padding:10px;background-color:#FFDFDD;border:1px solid #ced9ea;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;"><p style="line-height:1.6em;"><strong>';
			_e('Error!', 'launchkey');
			echo '</strong> ';
			_e('The LaunchKey request was denied or an issue was detected during authentication. Please try again. ', 'launchkey');
			echo '</p></div><br>';
		}
		elseif ( isset( $_GET['launchkey_ssl_error'] ) ) {
			echo '<div style="padding:10px;background-color:#FFDFDD;border:1px solid #ced9ea;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;"><p style="line-height:1.6em;"><strong>';
			_e('Error!', 'launchkey');
			echo '</strong>';
			_e('There was an error trying to request the LaunchKey servers. If this persists you may need to disable SSL verification.', 'launchkey');
			echo '</p></div><br>';
		}
		elseif ( isset( $_GET['launchkey_security'] ) ) {
			echo '<div style="padding:10px;background-color:#FFDFDD;border:1px solid #ced9ea;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;"><p style="line-height:1.6em;"><strong>';
			_e('Error!', 'launchkey');
			echo '</strong> ';
			_e('There was a security issue detected and you have been logged out for your safety. Log back 0in to ensure a secure session.', 'launchkey');
			echo '</p></div><br>';
		}

		if ( isset( $_GET['launchkey_pair'] ) ) {
			echo '<div style="padding:10px;background-color:#eef5ff;border:1px solid #ced9ea;border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;"><p style="line-height:1.6em;"><strong>';
			_e('Almost finished!','launchkey');
			echo '</strong> ';
			_e('Log in with your WordPress username and password for the last time to finish the user pair process. After this you can login exclusively with LaunchKey!','launchkey');
			echo '</p></div><br>';
		}
		else {
			$login_url = 'https://oauth.launchkey.com/authorize?client_id=' . $app_key . '&redirect_uri=' . urlencode($redirect);
      $login_text = __( 'Log in with LaunchKey', 'launchkey' );
			if(WPLANG == 'fr_FR' || WPLANG == 'es_ES') {
				$size = "small";
			} else {
				$size = "medium";
			}
			echo '
			<div class="'.$class.'" id="'.$id.'" style="'.$style.'">
			<div align="center"><link rel="stylesheet" href="https://launchkey.com/stylesheets/buttons2.css">
			<a href="' . $login_url . '" title="' . $login_text . '" class="lkloginbtn full light ' . $size . '">
			<span class="icon"></span><span class="text">' . $login_text . '</span></a></div></div><br>
			';
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

		$settings = __('Settings', 'launchkey');
		$app_key = __('App Key', 'launchkey');
		$secret_key = __('Secret Key', 'launchkey');
		register_setting( 'launchkey_option_group', 'array_key', array( $this, 'check_option' ) );
		add_settings_section( 'setting_section_id',
			$settings,
			array( $this, 'launchkey_section_info' ),
			'launchkey-setting-admin'
		);

		add_settings_field( 'app_key',
			$app_key,
			array( $this, 'create_app_key_field' ),
			'launchkey-setting-admin',
			'setting_section_id'
		);
		add_settings_field( 'secret_key',
			$secret_key,
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
		echo '    <h3>';
		_e('LaunchKey Options', 'launchkey');
		echo '</h3>';
		$user_meta = get_user_meta( $user->data->ID );
		if ( array_key_exists( 'launchkey_user', $user_meta ) ) {
			//check if password is set before allowing unpair
			if ( ! empty( $user->data->user_pass ) ) {
				$nonce        = wp_create_nonce( 'launchkey_unpair-remove-nonce' );
				$url          = admin_url( '/profile.php?launchkey_unpair=1&launchkey_nonce=' . $nonce );
				$password_url = admin_url( '/profile.php?launchkey_remove_password=1&launchkey_nonce=' . $nonce );
				echo '<p><em>';
				_e('Note' , 'launchkey');
				echo '</em>:';
				_e('unpairing a device or removing your WP password will log you out of WordPress.', 'launchkey' );
				echo '</p><table class="form-table"><tr><th>';
				_e('Status' , 'launchkey');
				echo ': <em>';
				_e( 'paired', 'launchkey');
				echo '</em></th><td><a href="' . $url . '" title="';
				_e( 'Click here to unpair your LaunchKey account with this WordPress account' , 'launchkey' );
				echo '">';
				_e('Unpair' , 'launchkey');
				echo '</a></td></tr><tr><th>';
				_e('WP Password', 'launchkey');
				echo '</th><td><a href="' . $password_url . ' " title="';
				_e('Click here to remove your WordPress password', 'launchkey');
				echo '">';
				_e('Remove WP password', 'launchkey');
				echo '</a></td></tr></table >';
			}
			else {
				echo '<table class="form-table"><tr><th>';
				_e('Status', 'launchkey');
				echo ': <em>';
				_e('paired', 'launchkey');
				echo '</em></th><td></td></tr><tr><th>';
				_e('WP Password', 'launchkey');
				echo '</th><td><em>';
				_e('Removed', 'launchkey');
				echo '</em>, ';
				_e('use form below to add password', 'launchkey');
				echo '</td></tr></table>';
			}
		}
		else {
			$app_key   = get_option( 'launchkey_app_key' );
			$redirect  = admin_url( 'admin-ajax.php?action=launchkey-callback&launchkey_admin_pair=1' );
			$login_url = 'https://oauth.launchkey.com/authorize?client_id=' . $app_key . '&redirect_uri=' . urlencode($redirect);
			echo '<table class="form-table"><tr><th>';
			_e('Status', 'launchkey');
			echo ': <em>';
			_e('not paired', 'launchkey');
			echo '</em></th><td><a href="' . $login_url . '" title="';
			_e('Click here to pair your LaunchKey account with this WordPress account', 'launchkey');
			echo '">';
			_e('Click to pair', 'launchkey');
			echo '</a></td></tr></table>';
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
		_e( 'For Setup information please see the', 'launchkey' );
		echo ' <a href="https://launchkey.com/docs/plugins/wordpress">';
		_e( 'LaunchKey WordPress Documentation', 'launchkey') ;
		echo '</a>';
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
	 * @param $atts
	 *
	 * @access public
	 * @return void
	 */
	public function launchkey_shortcode( $atts ) {
		extract( shortcode_atts(
				array(
					'class' => '',
					'id' => '',
					'style' => '',
					'hide' => ''
				), $atts )
		);

		$class = addslashes( $class );
		$id = addslashes( $id );
		$style = addslashes( $style );

		if ( $hide != 'true' && !is_user_logged_in() ) {
			$this->launchkey_form( $class, $id, $style );
		}
	} //end launchkey_shortcode

} //end class LaunchKey

$LaunchKey = new LaunchKey();
