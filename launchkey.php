<?php
/*
  Plugin Name: LaunchKey
  Plugin URI: https://wordpress.org/plugins/launchkey/
  Description:  LaunchKey eliminates the need and liability of passwords by letting you log in and out of WordPress with your smartphone or tablet.
  Version: 1.1.0
  Author: LaunchKey, Inc.
  Text Domain: launchkey
  Author URI: https://launchkey.com
  License: GPLv2 Copyright (c) 2014 LaunchKey, Inc.
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Initialize LaunchKey WordPress Plugin
 *
 * This function will perform the entire initializaiton for the plugin.  The initialization is encapsulated into
 * a funciton to protect against global variable collision.
 *
 * @since 1.0.0
 * Enclose plug-in initialization to protect against global variable corruption
 */
function launchkey_plugin_init() {

	/**
	 * Language domain for the plugin
	 */
	$language_domain = 'launchkey';

	/**
	 * Register plugin text domain with language files
	 *
	 * @see load_plugin_textdomain
	 * @link https://developer.wordpress.org/reference/hooks/plugins_loaded/
	 */
	add_action( 'plugins_loaded', function () use ( $language_domain ) {
		load_plugin_textdomain( $language_domain, false, plugin_basename( __FILE__ ) . '/languages/' );
	} );

	/**
	 * Get the WP global facade
	 * @see LaunchKey_WP_Global_Facade
	 */
	$facade = new LaunchKey_WP_Global_Facade();

	/**
	 * Create an AES encryption class for encryption/decryption of the secret options
	 * @link https://docs.launchkey.com/glossary.html#term-aes
	 */
	$crypt_aes = new Crypt_AES();
	/**
	 * Use an MD5 hash of the auth key as the crypto key.  The crypto key is used as it would normally affect all auth
	 * procedures as it is used as a salt for passwords.  An md5 hash is used as it will be a constant value based on
	 * the AUTH_KEY but guaranteed to be exactly thirty-two (32) characters as is needed by AES encryption.
	 */
	$crypt_aes->setKey( md5( AUTH_KEY ) );

	// Create an options handler that will encrypt and decrypt the plugin options as necessary
	$options_handler = new LaunchKey_WP_Options( $crypt_aes );

	/**
	 * The pre_update_option_launchkey filter will process the "launchkey" option directly
	 * before updating the data in the database.
	 *
	 * @since 1.0.0
	 * @link https://developer.wordpress.org/reference/hooks/pre_update_option_option/
	 * @see LaunchKey_WP_Options::pre_update_option_filter
	 */
	add_filter( 'pre_update_option_launchkey', array( $options_handler, 'pre_update_option_filter' ) );

	/**
	 * The pre_update_option_filter filter will process the "launchkey" option directly
	 * before adding the data in the database.
	 *
	 * @since 1.0.0
	 * @link https://developer.wordpress.org/reference/hooks/pre_update_option_option/
	 * @see LaunchKey_WP_Options::pre_update_option_filter
	 */
	add_filter( 'pre_add_option_launchkey', array( $options_handler, 'pre_update_option_filter' ) );

	/**
	 * The option_launchkey filter will process the "launchkey" option directly
	 * after retrieving the data from the database.
	 *
	 * @since 1.0.0
	 * @link https://developer.wordpress.org/reference/hooks/option_option/
	 * @see LaunchKey_WP_Options::post_get_option_filter
	 */
	add_filter( 'option_launchkey', array( $options_handler, 'post_get_option_filter' ) );

	/**
	 * If the pre-1.0.0 option style was already used, create a 1.0.0 option and remove the old options.  They are
	 * removed as the secret_key was stored plain text in the database.
	 *
	 * @since 1.0.0
	 */
	if ( get_option( 'launchkey_app_key' ) || get_option( 'launchkey_secret_key' ) ) {
		$launchkey_options[LaunchKey_WP_Options::OPTION_ROCKET_KEY] = get_option( 'launchkey_app_key' );
		$launchkey_options[LaunchKey_WP_Options::OPTION_SECRET_KEY] = get_option( 'launchkey_secret_key' );
		$launchkey_options[LaunchKey_WP_Options::OPTION_SSL_VERIFY] = ( defined( 'LAUNCHKEY_SSLVERIFY' ) && LAUNCHKEY_SSLVERIFY ) || true;
		$launchkey_options[LaunchKey_WP_Options::OPTION_IMPLEMENTATION_TYPE] = LaunchKey_WP_Implementation_Type::OAUTH;
		$launchkey_options[LaunchKey_WP_Options::OPTION_LEGACY_OAUTH] = true;

		if ( update_option( LaunchKey_WP_Admin::OPTION_KEY, $launchkey_options ) ) {
			delete_option( 'launchkey_app_key' );
			delete_option( 'launchkey_secret_key' );
		} else {
			throw new RuntimeException( 'Unable to upgrade LaunchKey meta-data.  Failed to save setting ' . LaunchKey_WP_Admin::OPTION_KEY );
		}
	} elseif ( !get_option( LaunchKey_WP_Admin::OPTION_KEY ) ) {
		add_option( LaunchKey_WP_Admin::OPTION_KEY, array() );
	}

	/**
	 * Create a templating object and point it at the correct directory for template files.
	 *
	 * @see LaunchKey_WP_Template
	 */
	$template = new LaunchKey_WP_Template( __DIR__ . '/templates', $facade, $language_domain );

	// Prevent XXE Processing Vulnerability
	libxml_disable_entity_loader( true );

	// Get the plugin options to determine which authentication implementation should be utilized
	$options = get_option( LaunchKey_WP_Admin::OPTION_KEY );
	$logger = new LaunchKey_WP_Logger( $facade );
	$launchkey_client = null;
	$client = null;

	// Only register the pieces that need to interact with LaunchKey if it's been configured
	if ( LaunchKey_WP_Implementation_Type::SSO === $options[LaunchKey_WP_Options::OPTION_IMPLEMENTATION_TYPE] && !empty( $options[LaunchKey_WP_Options::OPTION_SSO_ENTITY_ID] ) ) {

		$container = new LaunchKey_WP_SAML2_Container( $logger );
		SAML2_Compat_ContainerSingleton::setContainer( $container );
		$securityKey = new XMLSecurityKey( XMLSecurityKey::RSA_SHA1, array( 'type' => 'public' ) );
		$securityKey->loadKey( $options[LaunchKey_WP_Options::OPTION_SSO_CERTIFICATE], false, true );
		$client = new LaunchKey_WP_SSO_Client(
			$facade,
			$template,
			$options[LaunchKey_WP_Options::OPTION_SSO_ENTITY_ID],
			$securityKey,
			$options[LaunchKey_WP_Options::OPTION_SSO_LOGIN_URL],
			$options[LaunchKey_WP_Options::OPTION_SSO_LOGOUT_URL],
			$options[LaunchKey_WP_Options::OPTION_SSO_ERROR_URL]
		);

	} elseif ( LaunchKey_WP_Implementation_Type::OAUTH === $options[LaunchKey_WP_Options::OPTION_IMPLEMENTATION_TYPE] && !empty( $options[LaunchKey_WP_Options::OPTION_SECRET_KEY] ) ) {
		/**
		 * If the implementation type is OAuth, use the OAuth client
		 * @see LaunchKey_WP_OAuth_Client
		 */
		$client = new LaunchKey_WP_OAuth_Client( $facade, $template );

	} elseif ( !empty( $options[LaunchKey_WP_Options::OPTION_SECRET_KEY] ) ) {

		$launchkey_client = \LaunchKey\SDK\Client::wpFactory(
			$options[LaunchKey_WP_Options::OPTION_ROCKET_KEY],
			$options[LaunchKey_WP_Options::OPTION_SECRET_KEY],
			$options[LaunchKey_WP_Options::OPTION_PRIVATE_KEY],
			$options[LaunchKey_WP_Options::OPTION_SSL_VERIFY]
		);

		$client = new LaunchKey_WP_Native_Client( $launchkey_client, $facade, $template, $language_domain );

		add_filter( 'init', function () use ( $facade ) {
			wp_enqueue_script(
				'launchkey-script',
				plugins_url( '/public/launchkey-login.js', __FILE__ ),
				array( 'jquery' ),
				'1.0.0',
				true
			);
		} );
	}

	if ( $client ) {

		/**
		 * Register the non-admin actions for authentication client.  These actions will handle all of the
		 * authentication work for the plugin.
		 *
		 * @see LaunchKey_WP_Client::register_actions
		 * @see LaunchKey_WP_OAuth_Client::register_actions
		 * @see LaunchKey_WP_Native_Client::register_actions
		 */
		$client->register_actions();

		/**
		 * Create the a user profile object and register its actions.  These actions will handle all functionality
		 * related to a user customizing their authentication related options.
		 *
		 * @see LaunchKey_WP_User_Profile
		 */
		$profile = new LaunchKey_WP_User_Profile( $facade, $template, $language_domain, $options[LaunchKey_WP_Options::OPTION_IMPLEMENTATION_TYPE] );
		$profile->register_actions();

		/**
		 * Hideous workaround for the wp-login.php page not printing styles in the header like it should.
		 *
		 * @since 1.0.0
		 */
		if ( !has_action( 'login_enqueue_scripts', 'wp_print_styles' ) ) {
			add_action( 'login_enqueue_scripts', 'wp_print_styles', 11 );
		}
	}

	if ( is_admin() ) {
		/**
		 * If we are in the admin, create am admin object and register its actions.  These actions
		 * will manage setting of options and user management for the plugin.
		 *
		 * @see is_admin
		 * @see LaunchKey_WP_Admin
		 */
		$launchkey_admin = new LaunchKey_WP_Admin( $facade, $template, $language_domain );
		$launchkey_admin->register_actions();

		$config_wizard = new LaunchKey_WP_Configuration_Wizard(
			$facade, $launchkey_admin, $launchkey_client
		);
		$config_wizard->register_actions();
	}

	/**
	 * Add a filter to enqueue styles for the plugin
	 *
	 * @since 1.0.0
	 *
	 * @see add_filter
	 * @see wp_enqueue_style
	 * @link https://developer.wordpress.org/reference/functions/add_filter/
	 * @link https://developer.wordpress.org/reference/functions/wp_enqueue_style/
	 */
	add_filter( 'init', function () use ( $facade ) {
		wp_enqueue_style(
			'launchkey-style',
			plugins_url( '/public/launchkey.css', __FILE__ ),
			array(),
			'1.0.0',
			false
		);
	} );

}

// Run the initialization function above
launchkey_plugin_init();
