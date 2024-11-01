<?php
/**
 * Plugin Name: WCS Web Maintenance
 * Description: Add feature "Under Construction" or "Site closed for maintenance"
 * Author: COMUSYS
 * Author URI: https://www.comusys.com
 * Version: 0.1
 * Text Domain: wcs-web-maintenance
 * Domain Path: /languages/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0
 */

/**
 *	Copyright (C) 2017 COMUSYS - Javier Fernandez Viña <info@comusys.com>
 *
 *  Based on Site maintenance mode by Nikolay Samoylov
 *
 *	This program is free software; you can redistribute it and/or
 *	modify it under the terms of the GNU General Public License
 *	as published by the Free Software Foundation; either version 2
 *	of the License, or (at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program; if not, write to the Free Software
 *	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

if(!defined('ABSPATH')) exit;

if(!class_exists('wcs_web_maintenance')) {
  class wcs_web_maintenance {
    var $prefix = 'wcsmw_';
    var $lng_domain = 'wcs-web-maintenance';
	
    function __construct() {
      // Add link to settings in plugins page
      add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'add_settings_link'));
      
      // Add widget to WP dashboard
      add_action('admin_init', array($this, 'init_dashboard'), 10);
      
      // Setup default values on plugin first activate
      register_activation_hook(__FILE__, array($this, 'check_install'));
      
      // Register (add) settings page
      add_action('admin_menu', array($this, 'add_settings_page'), 5);
      
      // Add maintenance page hook
      add_action('template_redirect', array($this, 'redirect'));
      
      // Register uninstall hook
      register_uninstall_hook(__FILE__, array($this, 'uninstall'));
      
      // Load localization
      add_action('plugins_loaded', array($this, 'load_lang'));
	  
	  // Load Color Picker
	  add_action( 'admin_enqueue_scripts', array($this, 'cs_script_enqueue_color_picker') );
    }

    // Maintenance mode is enabled?
    function is_enabled() {
      return (bool) get_option($this->prefix.'enabled');
    }
    
    // Show login link?
    function is_showlogin() {
      return (bool) get_option($this->prefix.'login');
    }	
	
    // Return roles array
    function get_roles(){
      if(!function_exists('get_editable_roles')) require_once(ABSPATH.'/wp-admin/includes/user.php');
      $result = get_editable_roles(); // init roles array
      $result['nobody'] = array('name' => 'nobody'); // Add 'nobody' role
      unset($result['administrator']); // remove 'administrator' roles from list
      return $result;
    }
    
    // Return user role
    function get_active_role(){
      global $current_user;
      if(!empty($current_user->roles[0])) return $current_user->roles[0];
      return 'nobody';
    }

    // Add link to settings in plugins page
    function add_settings_link($links) {
      $links[] = '<a href="'.esc_url(get_admin_url(null, 'options-general.php?page='.basename(__FILE__))).'">'.__('Settings').'</a>';
      return $links;
    }

    // Load localization
    function load_lang() {
      load_plugin_textdomain($this->lng_domain, false, dirname(plugin_basename(__FILE__)).'/lang');
    }
    
    // Add widget to WP dashboard
    function add_dashboard_widget(){
      wp_add_dashboard_widget('dashboard_widget', __('Maintenance mode', $this->lng_domain), array($this, 'dashboard_widget_data'));
    }
    // Dashboard widget JavaScript
    function maintenance_mode_change_state_javascript() { ?>
      <script type="text/javascript">
        (function($){$(function(){
          var mm_button = $('#<?php echo($this->prefix); ?>activate'),
              mm_toolbar = $('#<?php echo($this->prefix); ?>toolbar_notify');
          mm_button.on('click', function(){
            mm_button.blur();
            $.post(ajaxurl, {'action': 'maintenance_mode_change_state', 'enabled': mm_button.attr('data-action')}, function(response){
              try {
                response = JSON.parse(response);
                console.log(response);
                if(response.success){
                  mm_button
                    .attr('value', '<?php _e('Turn off maintenance mode', $this->lng_domain); ?>')
                    .removeClass('button-secondary').addClass('button-primary')
                    .attr('data-action', 'false');
                  mm_toolbar.show();
                } else {
                  mm_button
                    .attr('value', '<?php _e('Turn on maintenance mode', $this->lng_domain); ?>')
                    .removeClass('button-primary').addClass('button-secondary')
                    .attr('data-action', 'true');
                  mm_toolbar.hide();
                }
                mm_button.blur();
              } catch (err) {
                mm_button.attr('value', '<?php _e('Invalid server response', $this->lng_domain); ?>').attr('disabled','disabled');
              }
            });
          });
        })}(jQuery));
      </script>
    <?php }
	// Show color picker
	function cs_script_enqueue_color_picker( $hook_suffix ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wcs-web-maintenance-script', plugins_url('scripts.js', __FILE__ ), array( 'wp-color-picker' ), false, true );
	}
    // Dashboard widget HTML code
    function dashboard_widget_data(){ 
      $enabled = $this->is_enabled();
      echo '<p align="center">'.
             '<input type="button" id="'.$this->prefix.'activate" class="'.($enabled ? 'button-primary' : 'button-secondary').'" value="'.($enabled ? __('Turn off maintenance mode', $this->lng_domain) : __('Turn on maintenance mode', $this->lng_domain)).'" data-action="'.($enabled ? 'false' : 'true').'" />'.
           '</p>';
    }
    // AJAX answer generate
    function maintenance_mode_change_state_callback(){
      $result = array();
      if(isset($_POST['enabled'])) {
        update_option($this->prefix.'enabled', ($_POST['enabled'] === 'true' ? true : false));
        $result['success'] = $this->is_enabled();
      }
      echo(json_encode($result)); wp_die();
    }
    // Show notify label in administrator bar
    function toolbar_notify() {
      global $wp_admin_bar; // TODO: change status
      $wp_admin_bar->add_node(array(
        'id' => $this->prefix.'toolbar_notify',
        'title' => '<div id="'.$this->prefix.'toolbar_notify" style="display:'.($this->is_enabled() ? 'block' : 'none').'; background-color:#f00; color:#fff; padding: 0 15px; cursor:default">'.__('Maintenance mode enabled', $this->lng_domain).'</div>',
      ));
    }
    // Init dashboard actions
    function init_dashboard(){
      if(current_user_can('administrator')) {
        add_action('admin_footer', array($this, 'maintenance_mode_change_state_javascript'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('wp_ajax_maintenance_mode_change_state', array($this, 'maintenance_mode_change_state_callback'));
      }
      add_action('admin_bar_menu', array($this, 'toolbar_notify'), 990);
    }

    // Setup default values
    function set_defaults(){
      // Generate css keyframes (Transform+rotate_Function)
      function tf($class_name, $from_value, $to_value) {
        $prefixes = array('-webkit-', '-moz-', '-o-', '');
        foreach($prefixes as $keyprefix) {
          $result .= '@'.$keyprefix.'keyframes '.$class_name.'{0%{';
          foreach($prefixes as $prefix) $result .= $prefix.'transform:rotate('.$from_value.');'; $result .= '}100%{';
          foreach($prefixes as $prefix) $result .= $prefix.'transform:rotate('.$to_value.');';   $result .= '}}';
        }
        return $result."\n";
      }
      // Array with default values
      $defaults = array(
        $this->prefix.'enabled'       => false,
        $this->prefix.'login'         => true,
        $this->prefix.'roles'         => 'subscriber,nobody',
        $this->prefix.'head_title'    => sanitize_text_field(__('Site closed for maintenance', $this->lng_domain)),
        $this->prefix.'body_title'    => sanitize_text_field(__('Site temporarily unavailable', $this->lng_domain)),
        $this->prefix.'body_subtitle' => sanitize_text_field(__('Currently on the site are technical works', $this->lng_domain)),
        $this->prefix.'body_content'  => sanitize_text_field(__('We apologise for any inconvenience', $this->lng_domain)),		
        $this->prefix.'color_bg'      => sanitize_hex_color('#e5d85a'),	
        $this->prefix.'color_text'    => sanitize_hex_color('#515151'),	
        $this->prefix.'color_link'    => sanitize_hex_color('#000000'),	
        $this->prefix.'color_link2'   => sanitize_hex_color('#767676'),
        $this->prefix.'color_icon'    => sanitize_hex_color('#d4c20e'),
      );
      // Setup values
      foreach($defaults as $key => $value) update_option($key, $value);
    }
    // Setup default values on plugin first activate
    function check_install(){
      if((get_option($this->prefix.'body_html') == '') || (get_option($this->prefix.'roles') == ''))
        $this->set_defaults();
    }

    // Settings page CSS styles
    function settings_page_styles(){ ?>
      <style type="text/css">
        form div.settings input[type='text']{width:99%;}
        div.settings-error{margin: 5px 10px 15px 0 !important;}
        dl.user_roles{margin-top: 6px; margin-bottom: 0;}
        dl.user_roles dd{margin-left: 0;}
        dl.user_roles input{margin-right: 6px;}
      </style>
    <?php }
    // Settings page data
    function settings_page(){
      if(isset($_POST['save'])){ // Save settings in page
	    // Check nounce & plugins privileges
		if (check_admin_referer('wcs_update_config') && current_user_can('activate_plugins')) {
			// Check roles
			$roles4save = array();
			foreach($this->get_roles() as $role => $role_data) if(isset($_POST['users_role_'.$role])) array_push($roles4save, $role);
        
			if(isset($_POST['enabled']))       update_option($this->prefix.'enabled',        ($_POST['enabled'] == 'true' ? true : false));
			if(isset($_POST['login']))         update_option($this->prefix.'login',          ($_POST['login'] == 'true' ? true : false));			
			if(isset($_POST['head_title']))    update_option($this->prefix.'head_title',     sanitize_text_field($_POST['head_title']));
			if(isset($_POST['body_title']))    update_option($this->prefix.'body_title',     sanitize_text_field($_POST['body_title']));
			if(isset($_POST['body_subtitle'])) update_option($this->prefix.'body_subtitle',  sanitize_text_field($_POST['body_subtitle']));
			if(isset($_POST['body_content']))  update_option($this->prefix.'body_content',   sanitize_text_field($_POST['body_content']));
			// Check & update colors
			if(isset($_POST['color_bg'])) {
				$color_bg = $_POST['color_bg'];
				if(!preg_match('/^#[a-f0-9]{6}$/i', $color_bg)) {
					$color_bg = sanitize_hex_color('#e5d85a');
				}
				update_option($this->prefix.'color_bg', sanitize_hex_color($color_bg));
			}
			if(isset($_POST['color_text'])) {
				$color_text = $_POST['color_text'];
				if(!preg_match('/^#[a-f0-9]{6}$/i', $color_text)) {
					$color_text = sanitize_hex_color('#515151');
				}
				update_option($this->prefix.'color_text', sanitize_hex_color($color_text));
			}
			if(isset($_POST['color_link'])) {
				$color_link = $_POST['color_link'];
				if(!preg_match('/^#[a-f0-9]{6}$/i', $color_link)) {
					$color_link = sanitize_hex_color('#000000');
				}
				update_option($this->prefix.'color_link', sanitize_hex_color($color_link));
			}
			if(isset($_POST['color_link2'])) {
				$color_link2 = $_POST['color_link2'];
				if(!preg_match('/^#[a-f0-9]{6}$/i', $color_link2)) {
					$color_link2 = sanitize_hex_color('#767676');
				}
				update_option($this->prefix.'color_link2', sanitize_hex_color($color_link2));
			}			
			if(isset($_POST['color_icon'])) {
				$color_icon = $_POST['color_icon'];
				if(!preg_match('/^#[a-f0-9]{6}$/i', $color_icon)) {
					$color_icon = sanitize_hex_color('#767676');
				}
				update_option($this->prefix.'color_icon', sanitize_hex_color($color_icon));
			}
			if(!empty($roles4save))            update_option($this->prefix.'roles',          implode(',', $roles4save));
        
			add_settings_error('settings_updated', esc_attr('settings_updated'), __('Settings saved', $this->lng_domain), 'updated');
		}
      }
      if(isset($_POST['reset'])){ // Maintenance mode
		// Check nounce
		if (check_admin_referer('wcs_update_config') && current_user_can('activate_plugins')) {
			$this->set_defaults();
			add_settings_error('settings_reseted', esc_attr('settings_reseted'), __('Settings reseted', $this->lng_domain), 'updated');
		}
      }
      ?>
    <div class="wrap">
      <h2><?php echo(esc_html(__('Settings').' "'.__('maintenance mode', $this->lng_domain).'"')); ?></h2>
      <?php settings_errors(); ?>
      <form method="post">
	    <?php wp_nonce_field( 'wcs_update_config' ); ?>
        <div class="settings">
          <table class="form-table">
            <tbody>
              <tr>
                <th scope="row">
                  <?php _e('Mode active', $this->lng_domain); ?>
                </th><td>
                  <label>
                    <input type="radio" name="enabled" <?php checked(true, get_option($this->prefix.'enabled')); ?> value="true" /><?php _e('Yes'); ?>
                  </label>&nbsp;&nbsp;&nbsp;
                  <label>
                    <input type="radio" name="enabled" <?php checked(false, get_option($this->prefix.'enabled')); ?> value="false" /><?php _e('No'); ?>
                  </label>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php _e('Title'); ?>
                </th><td>
                  <input name="head_title" type="text" id="head_title" value="<?php echo(esc_attr(get_option($this->prefix.'head_title'))); ?>" class="regular-text" />
                  <p class="description" id="tagline-description"><?php printf(__('This text will be specified in the tag %s', $this->lng_domain), '&lt;title&gt;'); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php _e('Main header'); ?>
                </th><td>
                  <input name="body_title" type="text" id="body_title" value="<?php echo(esc_attr(get_option($this->prefix.'body_title'))); ?>" class="regular-text" />
                  <p class="description" id="tagline-description"><?php printf(__('This text will be specified in the tag %s', $this->lng_domain), '&lt;h1&gt;'); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php _e('Secondary header'); ?>
                </th><td>
                  <input name="body_subtitle" type="text" id="body_subtitle" value="<?php echo(esc_attr(get_option($this->prefix.'body_subtitle'))); ?>" class="regular-text" />
                  <p class="description" id="tagline-description"><?php printf(__('This text will be specified in the tag %s', $this->lng_domain), '&lt;h2&gt;'); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php _e('Content'); ?>
                </th><td>
                  <input name="body_content" type="text" id="body_content" value="<?php echo(esc_attr(get_option($this->prefix.'body_content'))); ?>" class="regular-text" />
                  <p class="description" id="tagline-description"><?php printf(__('This text will be specified in the tag %s', $this->lng_domain), '&lt;p&gt;'); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php _e('Background color'); ?>
                </th><td>
                   	<input type="text" class="color-field-bg" id="color_bg" name="color_bg" data-default-color="#e5d85a" value="<?php echo(esc_attr(get_option($this->prefix.'color_bg'))); ?>" />
                </td>
              </tr>
			  <tr>
                <th scope="row">
                  <?php _e('Text color'); ?>
                </th><td>
                   	<input type="text" class="color-field-text" id="color_text" name="color_text" data-default-color="#515151" value="<?php echo(esc_attr(get_option($this->prefix.'color_text'))); ?>" />
                </td>
              </tr>
			  <tr>
                <th scope="row">
                  <?php _e('Icon color'); ?>
                </th><td>
                   	<input type="text" class="color-field-icon" id="color_icon" name="color_icon" data-default-color="#000000" value="<?php echo(esc_attr(get_option($this->prefix.'color_icon'))); ?>" />
                </td>
              </tr>
			  <tr>
                <th scope="row">
                  <?php _e('Link color'); ?>
                </th><td>
                   	<input type="text" class="color-field-link" id="color_link" name="color_link" data-default-color="#767676" value="<?php echo(esc_attr(get_option($this->prefix.'color_link'))); ?>" />
                </td>
              </tr>
			  <tr>
                <th scope="row">
                  <?php _e('Active link color'); ?>
                </th><td>
                   	<input type="text" class="color-field-link2" id="color_link2" name="color_link2" data-default-color="#d4c20e" value="<?php echo(esc_attr(get_option($this->prefix.'color_link2'))); ?>" />
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php _e('Show login', $this->lng_domain); ?>
                </th><td>
                  <label>
                    <input type="radio" name="login" <?php checked(true, get_option($this->prefix.'login')); ?> value="true" /><?php _e('Yes'); ?>
                  </label>&nbsp;&nbsp;&nbsp;
                  <label>
                    <input type="radio" name="login" <?php checked(false, get_option($this->prefix.'login')); ?> value="false" /><?php _e('No'); ?>
                  </label>
				  <p class="description" id="tagline-description"><?php printf(__('Show login link on maintenance mode website.')); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php _e('Close access to the site for', $this->lng_domain) ?>
                </th><td>
                  <dl class="user_roles">
                  <?php
                    $roles_setting = explode(',', get_option($this->prefix.'roles'));
                    foreach($this->get_roles() as $role => $role_data){
                  ?>
                    <dd><label for="users_role_<?php echo($role); ?>"><input name="users_role_<?php echo($role); ?>" type="checkbox" id="users_role_<?php echo($role); ?>" value="0" <?php checked('1', in_array($role, $roles_setting)); ?> /><?php
                      switch ($role){
                        case 'author':      _e('Authors', $this->lng_domain); break;
                        case 'contributor': _e('Contributors', $this->lng_domain); break;
                        case 'editor':      _e('Editors', $this->lng_domain); break;
                        case 'subscriber':  _e('Subscribers', $this->lng_domain); break;
                        case 'nobody':      _e('Visitors', $this->lng_domain); break;
                        default: if(!empty($role_data['name'])) _e($role_data['name']); else _e($role);
                      }
                    ?></label>
                    </dd>
                  <?php } ?>
                  </dl>
                  <p class="description" id="tagline-description"><?php _e('Select a users groups for which the maintenance mode will be displayed instead of the contents of the site', $this->lng_domain); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <input name="save" type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                </th><td>
                  <input name="reset" type="submit" class="button-secondary" value="<?php _e('Reset settings', $this->lng_domain); ?>" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </form>
    </div>
    <?php }
    // Register (add) settings page
    function add_settings_page(){
      add_options_page(__('Maintenance mode', $this->lng_domain), __('Maintenance mode', $this->lng_domain), 'manage_options', basename(__FILE__), array($this, 'settings_page'));
      add_action('admin_head', array($this, 'settings_page_styles'));
      return;
    }

    // Check - page is exception?
    function is_exception_page(){
      return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
    }
    // Add maintenance page hook
    function redirect(){
	  // If not enabled plugin or is exception page
      if($this->is_exception_page()) return;
      if(!$this->is_enabled()) return;
	  // Check role and privileges to show website
      if(in_array($this->get_active_role(), explode(',', get_option($this->prefix.'roles')))) {
		if ($this->is_showlogin()) { $login_link = '<p><a href="'.wp_login_url().'">'.__('Login for Administrators', $this->lng_domain).'</a></p>'; } else { $login_link = ''; }
        header('HTTP/1.1 503 Service Unavailable');
        exit('<!doctype html>
			  <html>
              <head>
               <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
               <style media="all" type="text/css">
			 		@import url(//fonts.googleapis.com/css?family=PT+Sans&subset=cyrillic-ext,latin);
			 		html,body{ background-color: '.esc_html(get_option($this->prefix.'color_bg')).'; margin:0; padding:0; color: '.esc_html(get_option($this->prefix.'color_text')).'; font-family: "PT Sans",Helvetica Neue,Helvetica,Arial,sans-serif; overflow: hidden }
					a{color: '.esc_html(get_option($this->prefix.'color_link')).'; text-decoration: none}
					a:hover{color: '.esc_html(get_option($this->prefix.'color_link2')).'; text-decoration: underline}
					.wrap{position:absolute;width:800px;height:300px;left:50%;top:50%;margin-left:-400px;margin-top:-150px}
					.wrap .texts{padding-right:330px;font-size:120%}
					.wrap .texts p{font-size:90%;letter-spacing:-1px}
					.gears i { font-size: 300px; color: '.esc_html(get_option($this->prefix.'color_icon')).'; }
					.gear{position:absolute;top:0;right:0;}
					@media screen and (max-width:820px){
						.wrap{position:relative; width:auto; height:auto; margin:0; padding: 0 30px; left: 0}
						.wrap .texts{padding-right: 0}
						.gears{display:none}
					}</style>
			   <script src="https://use.fontawesome.com/fdcb6d9bc7.js"></script>
               <title>'.esc_html(get_option($this->prefix.'head_title')).'</title>
              </head>
			  <body>
				<div class="wrap">
					<div class="texts">
						<h1>'.esc_html(get_option($this->prefix.'body_title')).'</h1>
						<h2>'.esc_html(get_option($this->prefix.'body_subtitle')).'</h2>
						<p>'.esc_html(get_option($this->prefix.'body_content')).'</p>'.$login_link.'
					</div>
					<div class="gears">
						<i class="fa fa-gears gear"></i>
					</div>
				</div>
			  </body>
			  </html>');
      }
      return;
    }

    // Uninstall function
    function uninstall(){
      delete_option($this->prefix.'enabled');
      delete_option($this->prefix.'login');
      delete_option($this->prefix.'roles');
      delete_option($this->prefix.'head_title');
      delete_option($this->prefix.'body_title');
      delete_option($this->prefix.'body_subtitle');
      delete_option($this->prefix.'body_content');
      delete_option($this->prefix.'color_bg');
      delete_option($this->prefix.'color_text');
      delete_option($this->prefix.'color_link');
      delete_option($this->prefix.'color_link2');
      delete_option($this->prefix.'color_icon');
    }
  }
  $GLOBALS['wcs_web_maintenance'] = new wcs_web_maintenance();
}