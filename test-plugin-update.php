<?php
/*
 * Plugin Name: Test Plugin Update
 * Plugin URI: http://clark-technet.com
 * Description: Test plugin updates
 * Version: 0.8
 * Author: Jeremy Clark
 * Author URI: http://clark-technet.com
 * Update URI:  https://github.com/d2roth/automatic-theme-plugin-update/
*/


/*
// TEMP: Enable update check on every request. Normally you don't need this! This is for testing only!
// NOTE: The 
//  if (empty($checked_data->checked))
//    return $checked_data; 
// lines will need to be commented in the check_for_plugin_update function as well.

set_site_transient('update_plugins', null);

// TEMP: Show which variables are being requested when query plugin API
add_filter('plugins_api_result', 'aaa_result', 10, 3);
function aaa_result($res, $action, $args) {
  print_r($res);
  return $res;
}
// NOTE: All variables and functions will need to be prefixed properly to allow multiple plugins to be updated
*/

class TestPluginUpdate {
  private $api_url = 'http://url_to_api_server/';
  private $plugin_slug = 'test-plugin-update';

  function __construct() {
    // Take over the update check
    add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_plugin_update'] );

    // Take over the Plugin info screen
    add_filter('plugins_api', [$this, 'plugin_api_call'], 10, 3);
  }

  function check_for_plugin_update( $checked_data ) {
    global $wp_version;

    $api_url = $this->api_url;
    $plugin_slug = $this->plugin_slug;
    
    // Comment out these two lines during testing.
    if ( empty( $checked_data->checked ) ){
      return $checked_data;
    }
    
    // return early if this plugin does not have an update
    if( !isset( $checked_data->checked[$plugin_slug .'/'. $plugin_slug .'.php'] ) ){
      return $checked_data;
    }

    var_dump($plugin_slug, $api_url, $checked_data->checked);
    wp_die();
    $args = array(
      'slug' => $plugin_slug,
      'version' => $checked_data->checked[$plugin_slug .'/'. $plugin_slug .'.php'],
    );

    $request_string = array(
        'body' => array(
          'action' => 'basic_check', 
          'request' => serialize($args),
          'api-key' => md5(get_bloginfo('url'))
        ),
        'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
      );
    
    // Start checking for an update
    $raw_response = wp_remote_post($api_url, $request_string);
    
    if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200))
      $response = unserialize($raw_response['body']);
    
    if (!empty($response) && is_object($response)) // Feed the update data into WP updater
      $checked_data->response[$plugin_slug .'/'. $plugin_slug .'.php'] = $response;
    
    return $checked_data;
  }

  function plugin_api_call( $def, $action, $args ) {
    global $wp_version;
    
    $api_url = $this->api_url;
    $plugin_slug = $this->plugin_slug;

    if (!isset($args->slug) || ($args->slug != $plugin_slug))
      return false;
    
    // Get the current version
    $plugin_info = get_site_transient('update_plugins');
    $current_version = $plugin_info->checked[$plugin_slug .'/'. $plugin_slug .'.php'];
    $args->version = $current_version;
    
    $request_string = array(
        'body' => array(
          'action' => $action, 
          'request' => serialize($args),
          'api-key' => md5(get_bloginfo('url'))
        ),
        'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
      );
    
    $request = wp_remote_post($api_url, $request_string);
    
    if (is_wp_error($request)) {
      $res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
    } else {
      $res = unserialize($request['body']);
      
      if ($res === false)
        $res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
    }
    
    return $res;
  }
}