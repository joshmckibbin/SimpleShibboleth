<?php
/**
 * SimpleShib: Simple_Shib class
 *
 * The Simple_Shib class is comprised of methods to support Single Sign-On via Shibboleth.
 *
 * @link https://wordpress.org/plugins/simpleshib/ Old WordPress plugin page.
 * @link https://github.com/joshmckibbin/SimpleShibboleth New GitHub repository.
 *
 * @package SimpleShib
 * @since 1.0.0
 */

/**
 * Simple_Shib class
 *
 * The Simple_Shib class is comprised of methods to support Single Sign-On via Shibboleth.
 *
 * @since 1.0.0
 */
class Simple_Shib {

	/**
	 * Instance of the Simple_Shib class.
	 *
	 * @since 1.5.0
	 * @var Simple_Shib $instance
	 */
	private static $instance;

	/**
	 * Options
	 *
	 * @since 1.2.0
	 * @var array $options
	 */
	private $options;

	/**
	 * Array containing the default options.
	 *
	 * @since 1.2.0
	 * @var const array DEFAULT_OPTS
	 */
	private const DEFAULT_OPTS = array(
		'attr_email'         => 'mail',
		'attr_firstname'     => 'givenName',
		'attr_lastname'      => 'sn',
		'attr_username'      => 'uid',
		'autoprovision'      => false,
		'debug'              => false,
		'enabled'            => false,
		'pass_change_url'    => 'https://www.example.com/passchange',
		'pass_reset_url'     => 'https://www.example.com/passreset',
		'session_init_url'   => '/Shibboleth.sso/Login',
		'session_logout_url' => '/Shibboleth.sso/Logout',
	);


	/**
	 * Construct method.
	 *
	 * The construct of this class initializes options, adds the Shibboleth
	 * authentication handler, adds the settings page, and tweaks the user profile page.
	 *
	 * @since 1.0.0
	 *
	 * @see remove_all_filters()
	 * @see add_filter()
	 * @see add_action()
	 */
	public function __construct() {
		// Get the options.
		$this->options = self::get_options();

		// Enable WP-CLI commands.
		if ( self::is_wp_cli() ) {
			self::wp_cli_commands();
		}

		// var_dump( $this->options ); // Uncomment this line to debug the options.

		// If SSO is _not_ enabled, this plugin still does a few things (e.g. adding the settings menu),
		// but it doesn't add the actual authenticate and session validation filters/actions.
		if ( true === $this->options['enabled'] ) {
			// Replace all existing WordPress authentication methods with our Shib auth handling.
			remove_all_filters( 'authenticate' );
			add_filter( 'authenticate', array( $this, 'authenticate_or_redirect' ), 1, 3 );

			// Check for IdP sessions that have disappeared.
			add_action( 'init', array( $this, 'validate_shib_session' ), 1, 0 );

			// Bypass the logout confirmation and redirect to $session_logout_url defined above.
			add_action( 'login_form_logout', array( $this, 'shib_logout' ), 5, 0 );

			// Hide password related fields.
			add_filter( 'show_password_fields', '__return_false' );
			add_filter( 'allow_password_reset', '__return_false' );
			add_action( 'login_form_lostpassword', array( $this, 'lost_password' ) );
		}

		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_new_user_admin_bar_link' ) );

		// Add the settings menu page and handle POST options.
		if ( ! is_multisite() ) {
			add_action( 'admin_menu', array( $this, 'add_settings_menu' ), 10, 0 );
			add_action( 'admin_post_simpleshib_settings', array( $this, 'handle_post' ), 5, 0 );
		} else {
			add_action( 'network_admin_menu', array( $this, 'add_settings_menu' ), 10, 0 );
			add_action( 'network_admin_edit_simpleshib_settings', array( $this, 'handle_post' ), 5, 0 );
		}
	}


	/**
	 * Initialize the Simple_Shib class.
	 *
	 * @since 1.5.0
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Get the plugin options.
	 *
	 * This method will fetch the options from the database. If options do not
	 * exist, they will be added with appropriate default values. Note that
	 * FOO_site_option() functions are safe for both single-site and multi-site.
	 *
	 * @see get_site_option()
	 * @see add_site_option()
	 * @since 1.2.0
	 */
	private static function get_options() {
		$options = get_site_option( 'simpleshib_options', false );
		if ( false === $options || empty( $options ) ) {
			// The options don't exist in the DB. Add them with default values.
			$options = self::DEFAULT_OPTS;
			add_site_option( 'simpleshib_options', $options );
		}

		return $options;
	}

	/**
	 * WP-CLI check
	 */
	private static function is_wp_cli() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return false;
	}

	/**
	 * Add Custom WP-CLI commands.
	 */
	public static function wp_cli_commands() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		// Register the activate command.
		WP_CLI::add_command( 'sshib enable', array( __CLASS__, 'wp_cli_enable' ) );

		// Register the deactivate command.
		WP_CLI::add_command( 'sshib disable', array( __CLASS__, 'wp_cli_disable' ) );
	}

	/**
	 * Enable the plugin via WP-CLI.
	 */
	public static function wp_cli_enable() {
		if ( ! self::is_wp_cli() ) {
			return;
		}

		$options = self::get_options();
		if ( true === $options['enabled'] ) {
			WP_CLI::error( 'Simple Shibboleth SSO is already enabled.' );
		}
		$options['enabled'] = true;
		update_site_option( 'simpleshib_options', $options );

		WP_CLI::success( 'Simple Shibboleth SSO Enabled' );
	}

	/**
	 * Disable the plugin via WP-CLI.
	 */
	public static function wp_cli_disable() {
		if ( ! self::is_wp_cli() ) {
			return;
		}

		$options = self::get_options();
		if ( false === $options['enabled'] ) {
			WP_CLI::error( 'Simple Shibboleth SSO is already disabled.' );
		}
		$options['enabled'] = false;
		update_site_option( 'simpleshib_options', $options );

		WP_CLI::success( 'Simple Shibboleth SSO Disabled' );
	}

	/**
	 * Activates the plugin.
	 */
	public static function activate() {
		if ( ! self::is_wp_cli() ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_die( esc_html__( 'Permission denied.' ) );
			}

			$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : ''; // phpcs:ignore WordPress.Security -- check_admin_referer() handles this.
			check_admin_referer( "activate-plugin_{$plugin}" );
		}

		// Initialize the plugin options.
		$options = self::get_options();

		// Always disable SSO on activation.
		if ( true === $options['enabled'] ) {
			$options['enabled'] = false;
			update_site_option( 'simpleshib_options', $options );
		}

		// Hide password fields on profile.php and user-edit.php, and do not alow resets.
		add_action( 'admin_init', array( self::init(), 'admin_init' ) );
	}


	/**
	 * Deactivates the plugin.
	 */
	public static function deactivate() {
		if ( ! self::is_wp_cli() ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_die( esc_html__( 'Permission denied.' ) );
			}
			$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : ''; // phpcs:ignore WordPress.Security -- check_admin_referer() handles this.
			check_admin_referer( "deactivate-plugin_{$plugin}" );
		}

		// Disable SSO on deactivation.
		$options            = self::get_options();
		$options['enabled'] = false;
		update_site_option( 'simpleshib_options', $options );

		// Remove all shib filters and actions added by this plugin.
		remove_all_filters( 'authenticate' );
		remove_action( 'init', array( self::init(), 'validate_shib_session' ) );
		remove_action( 'login_form_logout', array( self::init(), 'shib_logout' ) );
		remove_action( 'admin_menu', array( self::init(), 'add_settings_menu' ) );
		remove_action( 'network_admin_menu', array( self::init(), 'add_settings_menu' ) );
		remove_action( 'wp_before_admin_bar_render', array( self::init(), 'remove_new_user_admin_bar_link' ) );
	}


	/**
	 * Uninstalls the plugin.
	 */
	public static function uninstall() {
		if ( ! self::is_wp_cli() ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_die( esc_html__( 'Permission denied.' ) );
			}

			// Check if the file is the one that was registered during the uninstall hook.
			if ( __FILE__ !== WP_UNINSTALL_PLUGIN ) {
				wp_die( esc_html__( 'Permission denied.' ) );
			}
		}

		// Remove the options from the database.
		delete_site_option( 'simpleshib_options' );
	}


	/**
	 * Sanitizes plugin options submitted via POST.
	 *
	 * Unknown keys will be unset. Invalid values will be replaced with defaults.
	 *
	 * @since 1.2.0
	 * @param array $given_opts Options submitted by the user.
	 * @return array Sanitized array of known options.
	 */
	public function sanitize_options( array $given_opts = array() ) {
		$clean_opts = self::DEFAULT_OPTS;

		if ( empty( $given_opts ) ) {
			return $clean_opts;
		}

		foreach ( $given_opts as $key => $value ) {
			switch ( $key ) {
				// Strings (non-URL).
				case 'attr_email':
				case 'attr_firstname':
				case 'attr_lastname':
				case 'attr_username':
					if ( sanitize_text_field( (string) $value ) ) {
						$clean_opts[ $key ] = $value;
					}
					continue 2;

				// Booleans.
				case 'autoprovision':
				case 'debug':
				case 'enabled':
					$validated = filter_var( $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE );
					if ( ! is_null( $validated ) ) {
						$clean_opts[ $key ] = (bool) $validated;
					}
					continue 2;

				// Full URL strings.
				case 'pass_change_url':
				case 'pass_reset_url':
					$sanitized = sanitize_url( $value, array( 'https' ) );
					if ( ! empty( $sanitized ) ) {
						$clean_opts[ $key ] = $sanitized;
					}
					continue 2;

				// Strings, but not full URLs (e.g. "/Shibboleth.sso/Login").
				case 'session_init_url':
				case 'session_logout_url':
					$sanitized = filter_var( (string) $value, FILTER_SANITIZE_URL );
					if ( ! empty( $sanitized ) ) {
						$clean_opts[ $key ] = $sanitized;
					}
					continue 2;
			}
		}

		return $clean_opts;
	}


	/**
	 * Authenticate or Redirect
	 *
	 * This method handles user authentication. It either returns an error object,
	 * a user object, or redirects the user to the homepage or SSO initiator URL.
	 * It is hooked on 'authenticate'.
	 *
	 * @since 1.0.0
	 *
	 * @see is_user_logged_in()
	 * @see is_shib_session_active()
	 * @see login_to_wordpress()
	 * @see wp_safe_redirect()
	 * @see get_initiator_url()
	 *
	 * @param WP_User $user WP_User if the user is authenticated. WP_Error or null otherwise.
	 * @param string  $username Username or email address.
	 * @param string  $password User password.
	 *
	 * @return WP_User Returns WP_User for successful authentication, otherwise WP_Error.
	 */
	public function authenticate_or_redirect( $user, $username, $password ) { //phpcs:ignore
		// Logged in at IdP and WP. Redirect to /.
		if ( true === is_user_logged_in() &&
		true === $this->is_shib_session_active() ) {
			$this->debug( 'Logged in at WP and IdP. Redirecting to /.' );
			wp_safe_redirect( get_site_url() );
			exit();
		}

		// Logged in at IdP but not WP. Login to WP.
		if ( false === is_user_logged_in() &&
			true === $this->is_shib_session_active() ) {
			$this->debug( 'Logged in at IdP but not WP.' );
			$login_obj = $this->login_to_wordpress();
			return $login_obj;
		}

		// Logged in nowhere. Redirect to IdP login page.
		$this->debug( 'Logged in nowhere!' );

		// The redirect_to parameter is rawurlencode()ed in get_initiator_url().
		// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_GET['redirect_to'] ) ) {
			wp_safe_redirect( $this->get_initiator_url( $_GET['redirect_to'] ) );
			// phpcs:enable
		} else {
			wp_safe_redirect( $this->get_initiator_url() );
		}

		exit();

		// The case of 'logged in at WP but not IdP' is handled in 'init' via validate_shib_session().
	}


	/**
	 * Admin init.
	 *
	 * Apply several actions on the user profile edit pages.
	 *
	 * @since 1.0.0
	 *
	 * @see add_action()
	 */
	public function admin_init() {
		// 'show_user_profile' fires after the "About Yourself" section when a user is editing their own profile.
		add_action( 'show_user_profile', array( $this, 'add_password_change_link' ) );

		// Add scripts to disable form fields.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Add a notice to the top of the profile page.
		add_action( 'admin_notices', array( $this, 'add_profile_notice' ) );

		// Don't just mark the HTML form fields readonly, but handle the POST data as well.
		add_action( 'personal_options_update', array( $this, 'disable_profile_fields_post' ) );
	}


	/**
	 * Adds a settings menu.
	 *
	 * Hooked on admin_menu and network_admin_menu.
	 *
	 * @since 1.2.0
	 * @see add_submenu_page()
	 */
	public function add_settings_menu() {
		if ( ! is_multisite() ) {
			$parent_slug = 'options-general.php'; // Single site admin page.
		} else {
			$parent_slug = 'settings.php'; // Network admin page.
		}

		add_submenu_page(
			$parent_slug,
			__( 'Simple Shibboleth Settings' ),
			__( 'Simple Shibboleth' ),
			'manage_options',
			'simpleshib_settings',
			array( $this, 'settings_menu_html' ),
			null
		);
	}


	/**
	 * Prints the HTML for the settings page.
	 *
	 * @since 1.2.0
	 */
	public function settings_menu_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Access denied.' );
		}

		// Determine the POST action URL.
		$post_url = add_query_arg( 'action', 'simpleshib_settings', admin_url( 'admin-post.php' ) );
		if ( is_multisite() ) {
			$post_url = add_query_arg( 'action', 'simpleshib_settings', network_admin_url( 'edit.php' ) );
		} ?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Simple Shibboleth Settings' ); ?></h1>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<?php wp_nonce_field( 'simple-shibboleth-' . SIMPLE_SHIBBOLETH_VERSION, 'simpleshib_options[_nonce]' ); ?>
				<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable SSO' ); ?></th>
					<td>
						<input type="checkbox" id="simple-shibboleth--enabled" name="simpleshib_options[enabled]" value="1"<?php echo ( true === $this->options['enabled'] ? ' checked' : '' ); ?> />
						<label for="simple-shibboleth--enabled"><?php esc_html_e( 'Enable and enforce SSO. Local account passwords will no longer be used.' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-shibboleth--attr_email"><?php esc_html_e( 'Email Attribute' ); ?></label></th>
					<td>
						<input type="text" id="simple-shibboleth--attr_email" name="simpleshib_options[attr_email]" size="40" value="<?php echo esc_attr( $this->options['attr_email'] ); ?>" required /><br>
						<?php esc_html_e( 'The SAML attribute released by the IdP containing the person\'s email address. Default:' ); ?> <code>mail</code>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-shibboleth--attr_firstname"><?php esc_html_e( 'First Name Attribute' ); ?></label></th>
					<td>
						<input type="text" id="simple-shibboleth--attr_firstname" name="simpleshib_options[attr_firstname]" size="40" value="<?php echo esc_attr( $this->options['attr_firstname'] ); ?>" required /><br>
						<?php esc_html_e( 'The SAML attribute released by the IdP containing the person\'s (preferred) first name. Default:' ); ?> <code>givenName</code>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-shibboleth--attr_lastname"><?php esc_html_e( 'Last Name Attribute' ); ?></label></th>
					<td>
						<input type="text" id="simple-shibboleth--attr_lastname" name="simpleshib_options[attr_lastname]" size="40" value="<?php echo esc_attr( $this->options['attr_lastname'] ); ?>" required /><br>
						<?php esc_html_e( 'The SAML attribute released by the IdP containing the person\'s (preferred) last name. Default:' ); ?> <code>sn</code>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-shibboleth--attr_username"><?php esc_html_e( 'Username Attribute' ); ?></label></th>
					<td>
						<input type="text" id="simple-shibboleth--attr_username" name="simpleshib_options[attr_usernam]" size="40" value="<?php echo esc_attr( $this->options['attr_username'] ); ?>" required /><br>
						<?php esc_html_e( 'The SAML attribute released by the IdP containing the person\'s local WordPress username. Default:' ); ?> <code>uid</code>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Autoprovision Accounts' ); ?></th>
					<td>
						<input type="checkbox" id="simple-shibboleth--autoprovision" name="simpleshib_options[autoprovision]" value="1"<?php echo ( true === $this->options['autoprovision'] ? ' checked' : '' ); ?> />
						<label for="simple-shibboleth--autoprovision"><?php esc_html_e( 'Automatically create local accounts (as needed) after authenticating at the IdP.' ); ?></label><br>
						<?php esc_html_e( 'If disabled, only users with pre-existing local accounts can login.' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-shibboleth--session_init_url"><?php esc_html_e( 'Session Initiation URL' ); ?></label></th>
					<td>
						<input type="text" id="simple-shibboleth--session_init_url" name="simpleshib_options[session_init_url]" size="70" value="<?php echo esc_attr( $this->options['session_init_url'] ); ?>" required /><br>
						<?php esc_html_e( 'This generally should not be changed. Default:' ); ?> <code>/Shibboleth.sso/Login</code>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-shibboleth--session_logout_url"><?php esc_html_e( 'Session Logout URL' ); ?></label></th>
					<td>
						<input type="text" id="simple-shibboleth--session_logout_url" name="simpleshib_options[session_logout_url]" size="70" value="<?php echo esc_attr( $this->options['session_logout_url'] ); ?>" required /><br>
						<?php esc_html_e( 'This generally should not be changed, but an optional return URL can be provided. Example:' ); ?><br>
						<code>/Shibboleth.sso/Logout?return=https://idp.example.com/idp/profile/Logout</code>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-shibboleth--pass_change_url"><?php esc_html_e( 'Password Change URL' ); ?></label></th>
					<td>
						<input type="text" id="simple-shibboleth--pass_change_url" name="simpleshib_options[pass_change_url]" size="70" value="<?php echo esc_attr( $this->options['pass_change_url'] ); ?>" required /><br>
						<?php esc_html_e( 'Full URL where users can change their SSO password.' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-shibboleth--pass_reset_url"><?php esc_html_e( 'Password Reset URL' ); ?></label></th>
					<td>
						<input type="text" id="simple-shibboleth--pass_reset_url" name="simpleshib_options[pass_reset_url]" size="70" value="<?php echo esc_attr( $this->options['pass_reset_url'] ); ?>" required /><br>
						<?php esc_html_e( 'Full URL where users can reset their forgotten/lost SSO password.' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug' ); ?></th>
					<td>
						<input type="checkbox" id="simple-shibboleth--debug" name="simpleshib_options[debug]" value="1"<?php echo ( true === $this->options['debug'] ? ' checked' : '' ); ?> />
						<label for="simple-shibboleth--debug"><?php esc_html_e( 'Log debugging messages to PHP\'s error log.' ); ?></label>
					</td>
				</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}


	/**
	 * Handle POST from settings form.
	 *
	 * Hooked on admin_post_simpleshib_settings.
	 *
	 * @since 1.2.0
	 * @see 'admin_post_$action'
	 * @see wp_verify_nonce()
	 * @see update_site_option()
	 */
	public function handle_post() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['simpleshib_options'] ) ) {
			wp_die( 'Request method isn\'t POST or post data is empty!' );
		}

		// Verify the security nonce value.
		if ( empty( $_POST['simpleshib_options']['_nonce'] ) || ! wp_verify_nonce( $_POST['simpleshib_options']['_nonce'], 'simple-shibboleth-' . SIMPLE_SHIBBOLETH_VERSION ) ) { // phpcs:ignore
			wp_die( 'Invalid nonce!' );
		}

		$new_options = array();
		foreach ( self::DEFAULT_OPTS as $key => $value ) {
			if ( empty( $_POST['simpleshib_options'][ $key ] ) ) {
				// Unchecked checkboxes are empty() in the POST data.
				$_POST['simpleshib_options'][ $key ] = false;
			}

			$new_options[ $key ] = $_POST['simpleshib_options'][ $key ];  // phpcs:ignore
		}

		$clean_options = $this->sanitize_options( $new_options );
		update_site_option( 'simpleshib_options', $clean_options );

		// Generate the return_to URL.
		$return_to_page = 'options-general.php';
		if ( is_multisite() ) {
			$return_to_page = 'settings.php';
		}
		$return_to = add_query_arg(
			array(
				'updated' => 'true',
				'page'    => 'simpleshib_settings',
			),
			network_admin_url( $return_to_page )
		);

		wp_safe_redirect( $return_to );
		die;
	}


	/**
	 * Validate Shibboleth IdP session.
	 *
	 * This method determines if a Shibboleth session is active at the IdP by checking
	 * for the shibboleth HTTP headers. These headers cannot be forged because they are
	 * generated locally by shibd via Apache's mod_shib. For example, if the user attempts
	 * to spoof the "mail" header, it shows up as HTTP_MAIL instead of "mail".
	 *
	 * @since 1.0.0
	 * @since 1.2.1 Added support for custom attributes.
	 * @return bool True if the IdP session is active, otherwise false.
	 */
	private function is_shib_session_active() {
		if ( isset( $_SERVER['AUTH_TYPE'] ) && 'shibboleth' === $_SERVER['AUTH_TYPE']
			&& ! empty( $_SERVER['Shib-Session-ID'] )
			&& ! empty( $_SERVER[ $this->options['attr_email'] ] )
			&& ! empty( $_SERVER[ $this->options['attr_firstname'] ] )
			&& ! empty( $_SERVER[ $this->options['attr_lastname'] ] )
			&& ! empty( $_SERVER[ $this->options['attr_username'] ] )
		) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Validate the Shibboleth IdP session
	 *
	 * This method validates the Shibboleth login session at the IdP.
	 * If the IdP's session disappears while the user is logged into WordPress
	 * locally, this will log them out.
	 * It is hooked on 'init'.
	 *
	 * @since 1.0.0
	 *
	 * @see is_user_logged_in()
	 * @see is_shib_session_active()
	 * @see wp_logout()
	 * @see wp_safe_redirect()
	 */
	public function validate_shib_session() {
		if ( true === is_user_logged_in() && false === $this->is_shib_session_active() ) {
			$this->debug( 'validate_shib_session(): Logged in at WP but not IdP. Logging out!' );
			wp_logout();
			wp_safe_redirect( get_site_url() );
			die;
		}
	}


	/**
	 * Generate the SSO initiator URL.
	 *
	 * This function generates the initiator URL for the Shibboleth session.
	 * In the case of multisite, the site that the user is logging in one is
	 * added as a return_to parameter to ensure they return to the same site.
	 *
	 * @since 1.0.0
	 *
	 * @see get_site_url()
	 * @see get_current_blog_id()
	 *
	 * @param string $redirect_to Optional. URL parameter from client. Null.
	 *
	 * @return string Full URL for SSO initialization.
	 */
	private function get_initiator_url( $redirect_to = null ) {
		// Get the login page URL.
		$return_to = get_site_url( get_current_blog_id(), 'wp-login.php', 'login' );

		if ( ! empty( $redirect_to ) ) {
			// Don't rawurlencode($RedirectTo) - we do this below.
			$return_to = add_query_arg( 'redirect_to', $redirect_to, $return_to );
		}

		$initiator_url = $this->options['session_init_url'] . '?target=' . rawurlencode( $return_to );

		return $initiator_url;
	}


	/**
	 * Log a user into WordPress.
	 *
	 * If $auto_account_provision is enabled, a WordPress account will be created if one
	 * does not exist. User data, including username, name, and email, are updated with
	 * values from the IdP during every user login.
	 *
	 * @since 1.0.0
	 *
	 * @see authenticate_or_redirect()
	 * @see get_user_by()
	 * @see wp_insert_user()
	 *
	 * @return WP_User Returns WP_User for successful authentication, otherwise WP_Error.
	 */
	private function login_to_wordpress() {
		// The headers have been confirmed to be ! empty() in is_shib_session_active() above.
		// phpcs:disable -- The data is coming from the IdP, not the user, so it is trustworthy.
		$shib['email']       = $_SERVER[ $this->options['attr_email'] ];
		$shib['firstName']   = $_SERVER[ $this->options['attr_firstname'] ];
		$shib['lastName']    = $_SERVER[ $this->options['attr_lastname'] ];
		$shib['username']    = $_SERVER[ $this->options['attr_username'] ];
		// phpcs:enable

		// Check to see if they exist locally.
		$user_obj = get_user_by( 'login', $shib['username'] );
		if ( false === $user_obj && false === $this->options['autoprovision'] ) {
			do_action( 'wp_login_failed', $shib['username'] ); // Fire any login-failed hooks.
			$error_obj = new WP_Error(
				'shib',
				'<strong>Access Denied.</strong> Your login credentials are correct, but you do not have authorization to access this site.'
			);
			return $error_obj;
		}

		// The user_pass is irrelevant since we removed all internal WP auth functions.
		// However, if SimpleShib is ever disabled, WP will revert back to using user_pass, so it has to be safe.
		$insert_user_data = array(
			'user_pass'     => sha1( microtime() ),
			'user_login'    => $shib['username'],
			'user_nicename' => $shib['username'],
			'user_email'    => $shib['email'],
			'display_name'  => $shib['firstName'] . ' ' . $shib['lastName'],
			'nickname'      => $shib['username'],
			'first_name'    => $shib['firstName'],
			'last_name'     => $shib['lastName'],
		);

		// If wp_insert_user() receives 'ID' in the array, it will update the
		// user data of an existing account instead of creating a new account.
		if ( false !== $user_obj && is_numeric( $user_obj->ID ) ) {
			$insert_user_data['ID'] = $user_obj->ID;
			$error_msg              = 'syncing';
		} else {
			$error_msg = 'creating';
		}

		$new_user = wp_insert_user( $insert_user_data );

		// wp_insert_user() returns either int of the userid or WP_Error object.
		if ( is_wp_error( $new_user ) || ! is_int( $new_user ) ) {
			do_action( 'wp_login_failed', $shib['username'] ); // Fire any login-failed hooks.

			// TODO: Add setting for support ticket URL.
			$error_obj = new WP_Error(
				'shib',
				'<strong>ERROR:</strong> credentials are correct, but an error occurred ' . $error_msg . ' the local account. Please open a support ticket with this error.'
			);
			return $error_obj;
		} else {
			// Created the user successfully.
			$user_obj = new WP_User( $new_user );

			return $user_obj;
		}
	}


	/**
	 * Remove new user menu item from the admin bar.
	 *
	 * This method removes the "Add New User" link from the admin bar.
	 * It is hooked on 'wp_before_admin_bar_render'.
	 *
	 * @since 1.5.0
	 */
	public function remove_new_user_admin_bar_link() {
		global $wp_admin_bar;
		$wp_admin_bar->remove_node( 'new-user' );
	}


	/**
	 * User logout handler.
	 *
	 * This method bypasses the "Are you sure?" prompt when logging out.
	 * It redirects the user directly to the SSO logout URL.
	 * It is hooked on 'login_form_logout'.
	 *
	 * @since 1.0.0
	 *
	 * @see wp_logout().
	 * @see wp_safe_redirect().
	 */
	public function shib_logout() {
		// TODO: Is this still needed to bypass the logout prompt?
		wp_logout();
		wp_safe_redirect( $this->options['session_logout_url'] );
		exit();
	}


	/**
	 * Lost password.
	 *
	 * This method redirects the user to the URL defined in the settings above.
	 * It is hooked on 'login_form_lostpassword'.
	 *
	 * @since 1.0.0
	 *
	 * @see wp_redirect()
	 */
	public function lost_password() {
		// wp_safe_redirect() is not used here because $lost_pass_url is set
		// in the plugin configuration (not provided by the user) and is likely
		// an external URL. The phpcs sniff is disabled to avoid a warning.
		// phpcs:disable WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $this->options['pass_reset_url'] );
		// phpcs:enable
		exit();
	}


	/**
	 * Add password change link.
	 *
	 * This method adds a row to the bottom of the user profile page that contains
	 * a password reset link pointing to the URL defined in the settings.
	 *
	 * @since 1.0.0
	 */
	public function add_password_change_link() {
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Change Password' ); ?></th>
				<td><a href="<?php echo esc_url( $this->options['pass_change_url'] ); ?>"><?php esc_html_e( 'Change your password' ); ?></a></td>
			</tr>
		</table>
		<?php
	}


	/**
	 * Add scripts for disabling profile fields.
	 *
	 * @since 1.3.0
	 */
	public function add_scripts() {
		// Make sure the profile screen is being displayed.
		$screen = get_current_screen();
		if ( ! $screen || 'profile' !== $screen->id ) {
			return;
		}

		wp_enqueue_script( 'simple-shibboleth', plugin_dir_url( __FILE__ ) . 'script.js', array( 'jquery' ), SIMPLE_SHIBBOLETH_VERSION, true );
	}


	/**
	 * Add notice to top of profile page about centrally managed fields.
	 *
	 * @since 1.3.0
	 */
	public function add_profile_notice() {
		// Make sure the profile screen is being displayed.
		$screen = get_current_screen();
		if ( ! $screen || 'profile' !== $screen->id ) {
			return;
		}
		?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Names and email addresses are centrally managed and cannot be changed from within WordPress.' ); ?></p></div>
		<?php
	}

	/**
	 * Disable profile fields POST data.
	 *
	 * This method disables the processing of POST data from the user profile form for
	 * first name, last name, nickname, and email address. This is necessary because a
	 * DOM editor can be used to re-enable the form fields manually.
	 *
	 * @since 1.0.0
	 *
	 * @see disable_profile_fields()
	 */
	public function disable_profile_fields_post() {
		add_filter(
			'pre_user_first_name',
			function () {
				$user_obj = wp_get_current_user();
				return $user_obj->first_name;
			}
		);

		add_filter(
			'pre_user_last_name',
			function () {
				$user_obj = wp_get_current_user();
				return $user_obj->last_name;
			}
		);

		add_filter(
			'pre_user_nickname',
			function () {
				$user_obj = wp_get_current_user();
				return $user_obj->user_nicename;
			}
		);

		// TODO
		// In my testing, I found problems with 'pre_user_email' not blocking email changes.
		// Since user data is updated from Shib upon every login, it really isn't a big deal.
		// This may be a WP core bug.
		add_filter(
			'pre_user_email',
			function () {
				$user_obj = wp_get_current_user();
				return $user_obj->user_email;
			}
		);
	}


	/**
	 * Logs debugging messages to PHP's error log.
	 *
	 * @since 1.2.0
	 * @param string $msg Debugging message.
	 */
	private function debug( $msg ) {
		if ( true === $this->options['debug'] && ! empty( $msg ) ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'SimpleShib-Debug: ' . $msg );
			// phpcs:enable
		}
	}
}
