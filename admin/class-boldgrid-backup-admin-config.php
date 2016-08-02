<?php
/**
 * The admin-specific configuration class for the plugin
 *
 * @link http://www.boldgrid.com
 * @since 1.0
 *
 * @package Boldgrid_Backup
 * @subpackage Boldgrid_Backup/admin
 * @copyright BoldGrid.com
 * @version $Id$
 * @author BoldGrid.com <wpb@boldgrid.com>
 */

/**
 * BoldGrid Backup admin configuration class.
 *
 * @since 1.0
 */
class Boldgrid_Backup_Admin_Config {
	/**
	 * The core class object.
	 *
	 * @since 1.0
	 * @access private
	 * @var Boldgrid_Backup_Admin_Core
	 */
	private $core;

	/**
	 * User home directory.
	 *
	 * @since 1.0
	 * @access private
	 * @var string
	 */
	private $home_dir;

	/**
	 * Backup directory.
	 *
	 * @since 1.0
	 * @access private
	 * @var string
	 */
	private $backup_directory;

	/**
	 * Available compressors.
	 *
	 * @since 1.0
	 * @access private
	 * @var array
	 */
	private $available_compressors = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 *
	 * @param Boldgrid_Backup_Admin_Config $core Config class object.
	 */
	public function __construct( $core ) {
		// Save the Boldgrid_Backup_Admin_Core object as a class property.
		$this->core = $core;
	}

	/**
	 * Get the user home directory.
	 *
	 * @since 1.0
	 *
	 * @return string The path to the user home directory.
	 */
	public function get_home_directory() {
		// If home directory was already set, then return it.
		if ( false === empty( $this->home_dir ) ) {
			return $this->home_dir;
		}

		// For Windows and Linux.
		if ( true === $this->core->test->is_windows() ) {
			// Windows.
			$home_drive = ( false === empty( $_SERVER['HOMEDRIVE'] ) ? $_SERVER['HOMEDRIVE'] : null );
			$home_path = ( false === empty( $_SERVER['HOMEPATH'] ) ? $_SERVER['HOMEPATH'] : null );

			if ( false === ( empty( $home_drive ) || empty( $home_path ) ) ) {
				$home_dir = $home_drive . $home_path;
			}

			// If still unknown, then try getenv USERPROFILE.
			if ( true === empty( $home_dir ) ) {
				$home_dir = getenv( 'USERPROFILE' );
			}
		} else {
			// Linux.
			$home_dir = getenv( 'HOME' );

			if ( true === empty( $home_dir ) ) {
				$home_dir = ( false === empty( $_SERVER['HOME'] ) ? $_SERVER['HOME'] : null );
			}
		}

		// If still unknown, then try posix_getpwuid and posix_getuid.
		if ( true === empty( $home_dir ) && function_exists( 'posix_getuid' ) &&
			function_exists( 'posix_getpwuid' ) ) {
				$user = posix_getpwuid( posix_getuid() );

				$home_dir = ( false === empty( $user['dir'] ) ? $user['dir'] : null );
		}

		// Could not find the user home directory, so use the WordPress root directory.
		if ( true === empty( $home_dir ) ) {
			$home_dir = ABSPATH;
		}

		// Use rtrim the $home_dir to strip any trailing slashes.
		$home_dir = rtrim( $home_dir, '\\/' );

		// Record the home directory.
		$this->home_dir = $home_dir;

		// Return the directory path.
		return $home_dir;
	}

	/**
	 * Get the mode (last 3 characters of the octal number) of the home directory.
	 *
	 * @global WP_Filesystem $wp_filesystem The WordPress Filesystem API global object.
	 *
	 * @return string The mode of the home directory.
	 */
	public function get_home_mode() {
		// Get the user home directory.
		$home_dir = $this->get_home_directory();

		// Connect to the WordPress Filesystem API.
		global $wp_filesystem;

		// Get the mode of the directory.
		$home_dir_mode = $wp_filesystem->getchmod( $home_dir );

		return $home_dir_mode;
	}

	/**
	 * Get and return the backup directory path.
	 *
	 * @since 1.0
	 *
	 * @return string|bool The backup directory path, or FALSE on error.
	 */
	public function get_backup_directory() {
		// If home directory is not set, then set it.
		if ( true === empty( $this->backup_directory ) ) {
			// Initialize $backup_directory.
			$backup_directory = '';

			// Get settings.
			$settings = $this->core->settings->get_settings();

			// If the backup directory was saved in the settings, then use it.
			if ( false === empty( $settings['backup_directory'] ) ) {
				$backup_directory = $settings['backup_directory'];
			}

			$is_directory_set = $this->set_backup_directory( $backup_directory );

			// The backup directory could not be set.
			if ( false === $is_directory_set ) {
				return false;
			}
		}

		// Backup directory was set, so return the path.
		return $this->backup_directory;
	}

	/**
	 * Set backup directory.
	 *
	 * @since 1.0.1
	 *
	 * @global WP_Filesystem $wp_filesystem The WordPress Filesystem API global object.
	 *
	 * @param string $backup_directory_path The backup directory path to be set/configured.
	 * @return bool
	 */
	public function set_backup_directory( $backup_directory_path = '' ) {
		// If a backup directory was not specified, then use the default.
		if ( true === empty( $backup_directory_path ) ) {
			// Get the user home directory.
			$home_dir = $this->get_home_directory();

			// Check if home directory is writable.
			$home_dir_writable = $this->core->test->is_homedir_writable();

			// If home directory is not writable, then abort.
			if ( false === $home_dir_writable ) {
				return false;
			}

			// Define the backup directory name, using the default.
			$backup_directory_path = $home_dir . '/boldgrid_backup';
		}

		// Connect to the WordPress Filesystem API.
		global $wp_filesystem;

		// Check if the backup directory exists.
		$backup_directory_exists = $wp_filesystem->exists( $backup_directory_path );

		// If the backup directory does not exist, then attempt to create it.
		if ( false === $backup_directory_exists ) {
			$backup_directory_created = $wp_filesystem->mkdir( $backup_directory_path, 0700 );

			// If mkdir failed, then notify and abort.
			if ( false === $backup_directory_created ) {
				// Create error message.
				$errormsg = sprintf(
					esc_html__( 'Could not create directory "%s".', 'boldgrid-backup' ),
					$backup_directory_path
				);

				// Trigger an admin notice.
				do_action( 'boldgrid_backup_notice', $errormsg, 'notice notice-error is-dismissible' );

				// Abort.
				return false;
			}
		}

		// Check if the backup directory is a directory.
		$backup_directory_isdir = $wp_filesystem->is_dir( $backup_directory_path );

		// If the backup directory is not a directory, then notify and abort.
		if ( false === $backup_directory_isdir ) {
			// Create error message.
			$errormsg = sprintf(
				esc_html__( 'Backup directory "%s" is not a directory.', 'boldgrid-backup' ),
				$backup_directory_path
			);

			// Trigger an admin notice.
			do_action( 'boldgrid_backup_notice', $errormsg, 'notice notice-error is-dismissible' );

			// Abort.
			return false;
		}

		// If the backup directory is not writable, then notify and abort.
		if ( false === $wp_filesystem->is_writable( $backup_directory_path ) ) {
			// Get the mode of the directory.
			$backup_directory_mode = $wp_filesystem->getchmod( $backup_directory_path );

			// Create error message.
			$errormsg = sprintf(
				esc_html__(
					'Backup directory "%s" (mode %s) is not writable.',
					'boldgrid-backup'
				),
				$backup_directory_path,
				$backup_directory_mode
			);

			// Trigger an admin notice.
			do_action( 'boldgrid_backup_notice', $errormsg, 'notice notice-error is-dismissible' );

			// Abort.
			return false;
		}

		// Record the backup directory path.
		$this->backup_directory = $backup_directory_path;

		// Return success.
		return true;
	}

	/**
	 * Get the WordPress admin email address.
	 *
	 * @since 1.0.1
	 *
	 * @return string|bool The admin email address, or FALSE on error.
	 */
	public function get_admin_email() {
		// Initialize $admin_email.
		$admin_email = null;

		// Get the site email address.
		// Try get_bloginfo.
		if ( true === function_exists( 'get_bloginfo' ) ) {
			$admin_email = get_bloginfo( 'admin_email' );
		}

		// If the email address is still needed, then try wp_get_current_user.
		if ( true === empty( $admin_email ) && true === function_exists( 'wp_get_current_user' ) ) {
			// Get the current user information.
			$current_user = wp_get_current_user();

			// Check if user information was retrieved, abort if not.
			if ( false === $current_user ) {
				return false;
			}

			// Get the current user email address.
			$admin_email = $current_user->user_email;
		}

		// If there is no email address found, then abort.
		if ( true === empty( $admin_email ) ) {
			return false;
		}

		// Return the admin email address.
		return $admin_email;
	}

	/**
	 * Add an archive compressor to the available list.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $compressor A name of a compressor.
	 * @return null
	 */
	private function add_compressor( $compressor = null ) {
		if ( false === empty( $compressor ) &&
			false === in_array( $compressor, $this->available_compressors, true )
		) {
			$this->available_compressors[] = $compressor;
		}

		return;
	}

	/**
	 * Is a specific archive compressor available?
	 *
	 * @since 1.0
	 *
	 * @param string $compressor A string to identify a compressor.
	 * @return bool
	 */
	public function is_compressor_available( $compressor = null ) {
		// If input parameter is empty, then fail.
		if ( true === empty( $compressor ) || true === empty( $this->available_compressors ) ) {
			return false;
		}

		// Check the array to see if the specified compressor is available.
		$is_available = in_array( $compressor, $this->available_compressors, true );

		return $is_available;
	}

	/**
	 * Get available compressors.
	 *
	 * Test for available archive compressors, add them to the array in a preferred order, and
	 * return the array.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_available_compressors() {
		// If at least one compressor is already configured, then return TRUE.
		if ( false === empty( $this->available_compressors ) ) {
			return $this->available_compressors;
		}

		// Initialize $this->available_compressors to an empty array.
		$this->available_compressors = array();

		// PHP zip (ZipArchive).
		if ( extension_loaded( 'zip' ) && class_exists( 'ZipArchive' ) ) {
			$this->add_compressor( 'php_zip' );
		}

		// PHP bz2 (Bzip2).
		if ( extension_loaded( 'bz2' ) && function_exists( 'bzcompress' ) ) {
			$this->add_compressor( 'php_bz2' );
		}

		// PHP zlib (Zlib).
		if ( extension_loaded( 'zlib' ) && function_exists( 'gzwrite' ) ) {
			$this->add_compressor( 'php_zlib' );
		}

		// PHP lzf (LZF).
		if ( function_exists( 'lzf_compress' ) ) {
			$this->add_compressor( 'php_lzf' );
		}

		// System tar.
		if ( file_exists( '/bin/tar' ) && is_executable( '/bin/tar' ) ) {
			$this->add_compressor( 'system_tar' );
		}

		// System zip.
		if ( file_exists( '/usr/bin/zip' ) && is_executable( '/usr/bin/zip' ) ) {
			$this->add_compressor( 'system_zip' );
		}

		return $this->available_compressors;
	}
}
