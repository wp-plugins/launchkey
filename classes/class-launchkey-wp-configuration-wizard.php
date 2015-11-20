<?php

/**
 * Plugin Configuration Wizard
 *
 * Wizard to walk a WordPress administrator through configuring and verifying the LaunchKey
 * WordPress plugin.
 *
 * @package launchkey
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2015 LaunchKey, Inc. See project license for usage.
 * @since 1.0.0
 */
class LaunchKey_WP_Configuration_Wizard {
	/**
	 * Action name for AJAX callback used to verify native implementation configurations
	 *
	 * @since 1.0.0
	 */
	const VERIFY_CONFIG_AJAX_ACTION = 'launchkey-config-wizard-verify';

	/**
	 * Action name for AJAX callback used to submit config data for wizard
	 *
	 * @since 1.0.0
	 */
	const DATA_SUBMIT_AJAX_ACTION = 'launchkey-config-wizard-data-submit';

	/**
	 * Nonce key for the verifier
	 *
	 * @since 1.0.0
	 */
	const VERIFIER_NONCE_KEY = 'launchkey-config-verifier-nonce';

	/**
	 * Nonce key for the wizard
	 *
	 * @since 1.0.0
	 */
	const WIZARD_NONCE_KEY = 'launchkey-config-wizard-nonce';
	/**
	 * @var LaunchKey_WP_Admin
	 */
	public $admin;

	/**
	 * @var LaunchKey_WP_Global_Facade
	 */
	private $wp_facade;

	/**
	 * @var \LaunchKey\SDK\Client
	 */
	private $launchkey_client;

	/**
	 * @var bool Is the site a network installation
	 */
	private $is_multi_site;

	/**
	 * LaunchKey_WP_Configuration_Wizard constructor.
	 *
	 * @param LaunchKey_WP_Global_Facade $wp_facade
	 * @param LaunchKey_WP_Admin $admin
	 * @param \LaunchKey\SDK\Client $launchkey_client
	 * @param bool $is_multi_site
	 */
	public function __construct(
		LaunchKey_WP_Global_Facade $wp_facade,
		LaunchKey_WP_Admin $admin,
		$is_multi_site,
		\LaunchKey\SDK\Client $launchkey_client = null
	) {
		$this->wp_facade        = $wp_facade;
		$this->admin            = $admin;
		$this->launchkey_client = $launchkey_client;
		$this->is_multi_site    = $is_multi_site;
	}

	/**
	 * Register actions for the wizard with WordPress
	 *
	 * @since 1.0.0
	 */
	public function register_actions() {
		$this->wp_facade->add_action(
			'wp_ajax_' . static::VERIFY_CONFIG_AJAX_ACTION,
			array( $this, 'verify_configuration_callback' )
		);
		$this->wp_facade->add_action(
			'wp_ajax_' . static::DATA_SUBMIT_AJAX_ACTION,
			array( $this, 'wizard_submit_ajax' )
		);
		$this->wp_facade->add_filter( 'init', array( $this, 'enqueue_verify_configuration_script' ) );
		$this->wp_facade->add_filter( 'init', array( $this, 'enqueue_wizard_script' ) );
	}

	/**
	 * @since 1.0.0
	 */
	public function verify_configuration_callback() {
		if ( isset( $_REQUEST['nonce'] ) &&
		     $this->wp_facade->wp_verify_nonce( $_REQUEST['nonce'], static::VERIFIER_NONCE_KEY ) &&
		     $this->wp_facade->current_user_can( 'manage_options' )
		) {
			$user     = $this->wp_facade->wp_get_current_user();
			$response = array( 'nonce' => $this->wp_facade->wp_create_nonce( static::VERIFIER_NONCE_KEY ) );
			if ( stripos( $_SERVER['REQUEST_METHOD'], 'POST' ) !== false && isset( $_POST['verify_action'] ) &&
			     'pair' === $_POST['verify_action']
			) {
				try {
					$white_label_user        = $this->launchkey_client->whiteLabel()->createUser( $user->user_login );
					$response['qrcode_url']  = $white_label_user->getQrCodeUrl();
					$response['manual_code'] = $white_label_user->getCode();
				} catch ( Exception $e ) {
					$response['error'] = $e->getCode();
				}
			} elseif ( stripos( $_SERVER['REQUEST_METHOD'], 'POST' ) !== false ) {
				$response['completed'] = false;
				try {
					$username     = empty ( $_POST['username'] ) ? $user->user_login : $_POST['username'];
					$auth_request = $this->launchkey_client->auth()->authorize( $username );
					$this->wp_facade->update_user_meta( $user->ID, 'launchkey_username', $username );
					$this->wp_facade->update_user_meta( $user->ID, 'launchkey_auth',
						$auth_request->getAuthRequestId() );
					$this->wp_facade->update_user_meta( $user->ID, 'launchkey_authorized', null );
				} catch ( Exception $e ) {
					$response['error'] = $e->getCode();
				}
			} else {
				$db                    = $this->wp_facade->get_wpdb();
				$value                 =
					$db->get_var( $db->prepare( "SELECT meta_value FROM $db->usermeta WHERE user_id = %s AND meta_key = 'launchkey_authorized' LIMIT 1",
						$user->ID ) );
				$response['completed'] = ! empty( $value );
			}
			$this->wp_facade->wp_send_json( $response );
		}
	}

	/**
	 * @since 1.0.0
	 */
	public function enqueue_verify_configuration_script() {
		if ( $this->wp_facade->current_user_can( 'manage_options' ) ) {
			$options = $this->get_option();
			$this->wp_facade->wp_enqueue_script(
				'launchkey-config-verifier-native-script',
				$this->wp_facade->plugins_url( '/public/launchkey-config-verifier.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				'1.0.0',
				true
			);

			$this->wp_facade->wp_localize_script(
				'launchkey-config-verifier-native-script',
				'launchkey_verifier_config',
				array(
					'url'                 => $this->wp_facade->admin_url( 'admin-ajax.php?action=' .
					                                                      static::VERIFY_CONFIG_AJAX_ACTION ),
					'nonce'               => $this->wp_facade->wp_create_nonce( static::VERIFIER_NONCE_KEY ),
					'implementation_type' => $options[ LaunchKey_WP_Options::OPTION_IMPLEMENTATION_TYPE ],
					'is_configured'       => $this->is_plugin_configured( $options ),
				)
			);
		}
	}

	/**
	 * @since 1.0.0
	 */
	public function enqueue_wizard_script() {
		if ( $this->wp_facade->current_user_can( 'manage_options' ) ) {
			$options = $this->get_option();
			$this->wp_facade->wp_enqueue_script(
				'launchkey-wizard-script',
				$this->wp_facade->plugins_url( '/public/launchkey-wizard.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				'1.0.0',
				true
			);

			$this->wp_facade->wp_localize_script(
				'launchkey-wizard-script',
				'launchkey_wizard_config',
				array(
					'nonce'               => $this->wp_facade->wp_create_nonce( static::WIZARD_NONCE_KEY ),
					'is_configured'       => $this->is_plugin_configured( $options ),
					'implementation_type' => $options[ LaunchKey_WP_Options::OPTION_IMPLEMENTATION_TYPE ],
					'url'                 => $this->wp_facade->admin_url( 'admin-ajax.php?action=' .
					                                                      static::DATA_SUBMIT_AJAX_ACTION )
				)
			);
		}
	}

	public function wizard_submit_ajax() {
		if ( isset( $_POST['nonce'] ) ) {
			if ( $this->wp_facade->wp_verify_nonce( $_POST['nonce'], static::WIZARD_NONCE_KEY ) &&
			     $this->wp_facade->current_user_can( 'manage_options' )
			) {
				list( $options, $errors ) = $this->admin->check_option( $_POST );
				if ( $errors ) {
					$response["errors"] = $errors;
				} elseif ( $this->is_multi_site ) {
					$this->wp_facade->update_site_option( LaunchKey_WP_Admin::OPTION_KEY, $options );
				} else {
					$this->wp_facade->update_option( LaunchKey_WP_Admin::OPTION_KEY, $options );
				}
				$response['nonce'] = $this->wp_facade->wp_create_nonce( static::WIZARD_NONCE_KEY );
			} else {
				$response["errors"] =
					$this->wp_facade->__( "An error occurred submitting the page.  Please refresh the page and submit again." );
			}
			$this->wp_facade->wp_send_json( $response );
		}
	}

	private function is_plugin_configured( $options ) {
		$is_configured =
			( $options[ LaunchKey_WP_Options::OPTION_IMPLEMENTATION_TYPE ] === LaunchKey_WP_Implementation_Type::SSO
			  && ! empty( $options[ LaunchKey_WP_Options::OPTION_SSO_ENTITY_ID ] ) )
			|| ( $options[ LaunchKey_WP_Options::OPTION_IMPLEMENTATION_TYPE ] !== LaunchKey_WP_Implementation_Type::SSO
			     && ! empty( $options[ LaunchKey_WP_Options::OPTION_SECRET_KEY ] ) );

		return $is_configured;
	}

	/**
	 * @return mixed
	 */
	private function get_option() {
		return $this->is_multi_site ? $this->wp_facade->get_site_option( LaunchKey_WP_Admin::OPTION_KEY ) :
			$this->wp_facade->get_option( LaunchKey_WP_Admin::OPTION_KEY );
	}
}
