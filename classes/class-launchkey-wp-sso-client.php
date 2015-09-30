<?php

/**
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2015 LaunchKey, Inc. See project license for usage.
 */
class LaunchKey_WP_SSO_Client implements LaunchKey_WP_Client {

	/**
	 * @since 1.1.0
	 */
	const CALLBACK_AJAX_ACTION = 'launchkey-sso-callback';

	/**
	 * @var LaunchKey_WP_Global_Facade
	 */
	private $wp_facade;

	/**
	 * @var LaunchKey_WP_Template
	 */
	private $template;

	/**
	 * @var string
	 */
	private $entity_id;

	/**
	 * @var string
	 */
	private $security_key;

	/**
	 * @var string
	 */
	private $login_url;

	/**
	 * @var string
	 */
	private $logout_url;

	/**
	 * @var string
	 */
	private $error_url;

	/**
	 * LaunchKey_WP_SSO_Client constructor.
	 *
	 * @param LaunchKey_WP_Global_Facade $wp_facade
	 * @param LaunchKey_WP_Template $template
	 * @param string $entity_id
	 * @param XMLSecurityKey $security_key Security key to validate response signatures
	 * @param string $login_url URL to send user when logging in in via SSO
	 * @param string $logout_url URL to send user after logout when logged in via SSO
	 * @param string $error_url URL to send user when a login/logout error occurs
	 */
	public function __construct( LaunchKey_WP_Global_Facade $wp_facade, LaunchKey_WP_Template $template, $entity_id, XMLSecurityKey $security_key, $login_url, $logout_url, $error_url ) {
		$this->wp_facade = $wp_facade;
		$this->template = $template;
		$this->entity_id = $entity_id;
		$this->security_key = $security_key;
		$this->login_url = $login_url;
		$this->logout_url = $logout_url;
		$this->error_url = $error_url;
	}


	/**
	 * Register actions and callbacks with WP Engine
	 */
	public function register_actions() {
		$this->wp_facade->add_shortcode( 'launchkey_login', array( $this, 'launchkey_shortcode' ) );
		$this->wp_facade->add_action( 'login_form', array( &$this, 'launchkey_form' ) );

		// Register the authentication controller as the first filter in the chain
		$this->wp_facade->add_filter( 'authenticate', array( $this, 'authenticate' ), 0, 3 );
		// Register logout handler
		$this->wp_facade->add_filter( 'wp_logout', array( $this, 'logout' ) );
	}


	/**
	 * Function to render a {@see launchkey_form} via a WordPress short code
	 *
	 * [id="id-value" class="class-value" style="style-value" hide="true" ]
	 *
	 *   A hide value of "true" or the user being logged in will not show the form
	 *
	 * @link https://codex.wordpress.org/Shortcode_API
	 * @since 1.1.0
	 *
	 * @param array $atts
	 */
	public function launchkey_shortcode( $atts ) {
		$class = isset( $atts['class'] ) ? addslashes( $atts['class'] ) : '';
		$id = isset( $atts['id'] ) ? addslashes( $atts['id'] ) : '';
		$style = isset( $atts['style'] ) ? addslashes( $atts['style'] ) : '';
		$hide = isset( $atts['hide'] ) ? $atts['hide'] : '';

		if ( $hide != 'true' && !$this->wp_facade->is_user_logged_in() ) {
			$this->launchkey_form( $class, $id, $style );
		}
	} //end launchkey_logout


	/**
	 * launchkey_form - login form for wp-login.php
	 *
	 * @since 1.1.0
	 *
	 * @param string $class A space separated list of classes to set on the "class" attribute of a containing DIV for the login button
	 * @param string $id The value to set on the "id" attribute of a containing DIV for the login button
	 * @param string $style A string of HTML style code tto set on the "style" attribute of a containing DIV for the login button
	 */
	public function launchkey_form( $class = '', $id = '', $style = '' ) {
		if ( isset( $_GET['launchkey_error'] ) ) {
			$this->wp_facade->_echo( $this->template->render_template( 'error', array(
				'error' => 'Error!',
				'message' => 'The LaunchKey request was denied or an issue was detected during authentication. Please try again.'
			) ) );
		} elseif ( isset( $_GET['launchkey_ssl_error'] ) ) {
			$this->wp_facade->_echo( $this->template->render_template( 'error', array(
				'error' => 'Error!',
				'message' => 'There was an error trying to request the LaunchKey servers. If this persists you may need to disable SSL verification.'
			) ) );
		} elseif ( isset( $_GET['launchkey_security'] ) ) {
			$this->wp_facade->_echo( $this->template->render_template( 'error', array(
				'error' => 'Error!',
				'message' => 'There was a security issue detected and you have been logged out for your safety. Log back in to ensure a secure session.'
			) ) );
		}


		$container = SAML2_Utils::getContainer();
		$request = new SAML2_AuthnRequest();
		$request->setId( $container->generateId() );
		//$request->setProviderName( parse_url( $this->wp_facade->home_url( '/' ), PHP_URL_HOST ) );
		$request->setDestination( $this->login_url );
		$request->setIssuer( $this->entity_id );
		$request->setRelayState( $this->wp_facade->admin_url() );
		$request->setAssertionConsumerServiceURL( $this->wp_facade->wp_login_url() );
		$request->setProtocolBinding( SAML2_Const::BINDING_HTTP_POST );
		$request->setIsPassive( false );
		$request->setNameIdPolicy( array(
			'Format' => SAML2_Const::NAMEID_PERSISTENT,
			'AllowCreate' => true
		) );
		// Send it off using the HTTP-Redirect binding
		$binding = new SAML2_HTTPRedirect();
		$binding->setDestination( $this->login_url );

		$this->wp_facade->_echo( $this->template->render_template( 'launchkey-form', array(
			'class' => $class,
			'id' => $id,
			'style' => $style,
			'login_url' => $binding->getRedirectURL( $request ),
			'login_text' => 'Log in with',
			'login_with_app_name' => 'LaunchKey',
			'size' => in_array( $this->wp_facade->get_locale(), array(
				'fr_FR',
				'es_ES'
			) ) ? 'small' : 'medium'
		) ) );
	}

	/**
	 * Front controller for LaunchKey Native/White Label authentication
	 *
	 *
	 * @param WP_User $user Unused parameter always passed first by authenticate filter
	 * @param string $username Username specified by the user in the login screen
	 * @param string $password Password specifiedby the user in the login screen
	 *
	 * @since 1.0.0
	 * @return WP_User
	 */
	public function authenticate( $user, $username, $password ) {
		if ( empty( $user ) && empty( $username ) && empty( $password ) && !empty( $_REQUEST['SAMLResponse'] ) ) {
			$response_element = SAML2_DOMDocumentFactory::fromString( base64_decode( $_REQUEST['SAMLResponse'] ) )->documentElement;
			$signature_info = SAML2_Utils::validateElement( $response_element );
			try {
				SAML2_Utils::validateSignature( $signature_info, $this->security_key );
				$response = SAML2_StatusResponse::fromXML( $response_element );
				/** @var SAML2_Assertion[] $assertions */
				$assertions = $response->getAssertions();
				if ( empty( $assertions ) ) {
					throw new Exception( "No assertions in SAML response" );
				}

				$assertion = $assertions[0];
				$name_id = $assertion->getNameId();
				$username = $name_id['Value'];
				$session_id = $assertion->getSessionIndex();

				// Find the user by login
				$user = $this->wp_facade->get_user_by( 'login', $username );

				// If we don't have a user, create one
				if ( !( $user instanceof WP_User ) ) {
					$attributes = $assertion->getAttributes();
					$user_data = array(
						'user_login' => $username,
						'user_pass' => '',
						'role' => empty( $attributes['role'] ) ? false : $this->translate_role( $attributes['role'][0] )
					);
					$user_id = $this->wp_facade->wp_insert_user( $user_data );
					// Unset the password - wp_insert_user always generates a hash - it's misleading
					$this->wp_facade->wp_update_user( array( 'ID' => $user_id, 'user_pass' => '' ) );
					$user = new WP_User($user_id);
				}

				// Set the SSO session so we know we are logged in via SSSO
				$this->wp_facade->update_user_meta( $user->ID, 'launchkey_sso_session', $session_id );

			} catch ( Exception $e ) {
				$this->wp_facade->wp_redirect( $this->error_url );
				exit;
			};
			return $user;
		}
	}

	/**
	 * Method to handle redirects for logout of the LaunchKey SSO service
	 *
	 * '@since 1.1.0
	 */
	public function logout() {
		if ( $user = $this->wp_facade->wp_get_current_user() ) {
			// And that user has logged in with LaunchKey SSO
			if ( !empty ( $user->launchkey_sso_session ) ) {
				// Reset the SSO session
				$this->wp_facade->update_user_meta( $user->ID, 'launchkey_sso_session', null );
				// Redirect to SSO logout
				$this->wp_facade->wp_redirect( $this->logout_url );
				exit;
			}
		}
	}

	private function translate_role( $role ) {
		static $role_synonyms = array( "admin" => "administrator" );
		return isset( $role_synonyms[ $role ] ) ? $role_synonyms[ $role ] : $role;
	}
}