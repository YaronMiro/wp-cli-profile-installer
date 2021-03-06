<?php

namespace YaronMiro\WpProfile;

use WP_CLI;

/**
 * Command that installs the site plugins.
 *
 * ## EXAMPLE
 *
 *     # Download WordPress core
 *     $ wp profile-install core example/core.yml
 *     Success: WordPress downloaded successfully.
 *
 */
class Core extends Installer {

  /**
   * @var array $data_structure the file data structure.
   */
  protected $data_structure = array (
    'allowed_properties' => array(),
    'required_properties' => array(),
  );

  /**
   * Downloads WordPress core from a config Yaml file.
   *
   * ## OPTIONS
   *
   * <config-file>
   * : The config file relative path.
   *
   * ## EXAMPLES
   *
   * wp profile-install core example/core.yml
   *
   */
  public function __invoke( $args, $assoc_args ) {
    parent::__invoke( $args, $assoc_args );
  }

  /**
   * Download WordPress core.
   *
   */
  public function execute_command() {
    WP_CLI::success( 'WordPress downloaded successfully' );
  }

}
