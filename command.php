<?php

namespace YaronMiro\WpProfile;

if ( ! class_exists( 'WP_CLI' ) ) {
  return;
}

use WP_CLI;
use WP_CLI_Command;
use Symfony\Component\Yaml\Parser;

// We only need to manually require `autoload.php` if the command is installed
// from a local path (Usually on development). The command is been required by a
// config file: e.g. '~/.wp-cli/config.yml' | 'wp-cli.local.yml' | 'wp-cli.yml'.
// The`autoload.php` is automatically required when installing this command from
// the package index list via the `wp package install`
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
  require_once( __DIR__ . '/vendor/autoload.php' );
} else {
  WP_CLI::error( 'Wp-profile command: Please, run composer install first' );
}

/**
 *
 * Base class for all the profile install sub commands.
 *
 */
abstract class Installer extends WP_CLI_Command {

  /**
   * @var string $file_path the config file path.
   */
  protected  $file_path = '';

  /**
   * @var array $allowed_file_types config file types.
   */
  private $allowed_file_types = array( 'yml', 'yaml' );

  /**
   * @var array $data_structure the file data structure.
   */
  protected $data_structure = array();

  /**
   * @var array $data the data from the file.
   */
  protected $data = array();

  /**
   * @var object $data_parser the data parser object.
   */
  private $data_parser = null;

  /**
   * Install a profile component via a unique command.
   *
   * Each sub-command process is unique. Therefore it is an abstract method
   * that must be implemented when a sub-command extends this class.
   */
  public abstract function execute_command();

  /**
   * Validate that the dependencies have been loaded.
   * Assert that the data_structure property was declared correctly.
   */
  public function __construct() {
    $this->load_dependencies();
    $this->assert_data_structure();
  }

  /**
   * Basic mandatory operations for a command execution.
   */
  public function __invoke( $args, $assoc_args ) {

    // Process the file path and validate it's existence.
    $this->process_file_path( reset( $args ) );

    // Get the data from the file.
    $this->parse_data_from_file();

    // Validate the file data structure.
    $this->validate_data_structure();

    // Install.
    $this->execute_command();
  }

  /**
   * Load the dependencies.
   *
   * If the dependencies can't be loaded, then exits the script with an
   * error message.
   */
  protected function load_dependencies() {
    // Load the file data parser.
    if ( ! class_exists('Symfony\Component\Yaml\Parser') || ! $this->data_parser = new Parser() ) {
      WP_CLI::error( 'Can\'t execute the command due to missing dependencies' );
    }
  }

  /**
   * Assert that the `data_structure` property was declared as expected.
   *
   * e.g array (
   *      'required => array(),
   *      'not_required => array(),
   *     );
   */
  protected function assert_data_structure() {

    // Mandatory keys.
    $keys = array(
      'allowed_properties',
      'required_properties',
    );

    $variables = array('@class' => get_class( $this ));
    foreach ( $keys as $key ) {
      if ( ! isset( $this->data_structure[$key] ) ) {
        $variables['@key'] = $key;
        WP_CLI::error(  strtr('\'@class:\' data_structure array is missing a key definition: \'@key\'', $variables) );
      }
    }
  }

  /**
   * Validate that the data does not contain any unknown properties.
   *
   * Compare the file data properties against the 'allowed data properties'.
   * In case the arrays do not match then exits the script with an
   * error message.
   */
  protected function validate_data_allowed_properties() {

    // Compare arrays properties.
    if ( $unauthorised_properties = array_diff( Utils::get_array_keys( $this->data ), $this->data_structure['allowed_properties'] ) ) {
      $variables = array(
        '@file' => $this->file_path,
        '@unauthorised_properties' => implode( ', ', $unauthorised_properties ),
        '@prop' => count( $unauthorised_properties ) > 1 ? 'properties' : 'property',
      );
      WP_CLI::error( strtr( 'Unauthorised @prop: \'@unauthorised_properties\' on file: \'@file\'.' , $variables ) );
    }
  }

  /**
   * Validating the file data structure.
   *
   */
  protected function validate_data_structure() {

    // Validate that the data does not contain any unknown properties.
    $this->validate_data_allowed_properties();
    $this->validate_data_required_properties($this->data_structure['required_properties'], $this->data);
  }

  /**
   * Validate that the required properties are declared as expected.
   *
   * @param array $required_data
   *  An array that defines the required properties.
   * @param $data
   *   The target data to validate against.
   * @param null $main_property
   *  The data may have some property that have sub-properties. For example:
   *  The main property is 'user' and it has sub-properties: 'name' & `password`
   * + ------------- +
   * +  user:        +
   * +    name:      +
   * +    password:  +
   * + ------------- +
   */
  protected function validate_data_required_properties($required_data, $data, $main_property = null) {

    // Iterate over the data properties and validate that the properties were
    // declared as expected.
    $error_message = 'Property: \'@property\' is required on file: \'@file\'.';
    foreach ($required_data as $key => $value) {

      $variables = array( '@file' => $this->file_path );
      // In case it's a simple key value per property.
      if ( ! is_array( $value ) && empty( $data[$value] ) ) {
        $variables['@property'] = $main_property ?  ( $main_property . ': ' . $value ) : $value;
        WP_CLI::error( strtr( $error_message, $variables ) );
      }
      // In case it's an array (property with sub-properties).
      /**
       * Check if numeric array or associative array
       *
       */
      if ( is_array( $value ) ) {

        // In case the main property is missing.
        if ( empty( $data[$key] ) ) {
          $variables['@property'] = $key;
          WP_CLI::error( strtr( $error_message,  $variables) );
        }

        // Validate the sub properties.
        $this->validate_data_required_properties($required_data[$key], $data[$key], $key);
      }

    }
  }

  /**
   * Parse the data from a given file.
   */
  protected function parse_data_from_file() {
    $this->data = $this->data_parser->parse( ( file_get_contents( $this->file_path ) ) );
  }

  /**
   * Process the file path from relative to absolute & validate it's integrity.
   *
   * @param $relative_file_path
   *  string
   *
   * Wrong file type or the file does not exists then,
   * exits the script with an error message.
   * File was found then, store the absolute file path.
   */
  private function process_file_path( $relative_file_path ) {

    // Get the absolute file path.
    $absolute_file_path = getcwd() . '/' . $relative_file_path;

    // In case the file type is incorrect.
    $file_extension = strtolower( pathinfo( $absolute_file_path, PATHINFO_EXTENSION ) );
    if ( ! in_array( $file_extension , $this->allowed_file_types ) ) {

      // Message placeholders.
      $variables = array(
        '@file_type' => $file_extension,
        '@allowed_file_types' => implode( ', ', $this->allowed_file_types ),
      );

      // Error message.
      $message = 'File type: \'@file_type\' is incorrect. allowed file type are: \'@allowed_file_types\'';
      WP_CLI::error( strtr( $message, $variables ) );
    }

    // In case the file does not exists.
    if ( ! file_exists( $absolute_file_path ) ) {
      WP_CLI::error( strtr( 'File: \'@file\' was not found!', array( '@file' => $absolute_file_path ) ) );
    }

    // Store the absolute path.
    $this->file_path = $absolute_file_path;
  }
}

// Info command.
WP_CLI::add_command( 'profile-install info', __NAMESPACE__ . '\\Info', array( 'when' => 'before_wp_load' ) );

// Database command.
WP_CLI::add_command( 'profile-install db', __NAMESPACE__ . '\\Database', array( 'when' => 'before_wp_load' ) );

// Plugins command.
WP_CLI::add_command( 'profile-install plugins', __NAMESPACE__ . '\\Plugins', array( 'when' => 'before_wp_load' ) );

// Themes command.
WP_CLI::add_command( 'profile-install themes', __NAMESPACE__ . '\\Themes' );

// Options command.
WP_CLI::add_command( 'profile-install options', __NAMESPACE__ . '\\Options' );

// Core command.
WP_CLI::add_command( 'profile-install core', __NAMESPACE__ . '\\Core', array( 'when' => 'before_wp_load' ) );

// Site command.
WP_CLI::add_command( 'profile-install site', __NAMESPACE__ . '\\Site', array( 'when' => 'before_wp_load' ) );
