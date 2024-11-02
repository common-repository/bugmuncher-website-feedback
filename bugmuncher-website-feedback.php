<?php
/*
Plugin Name: Saber Feedback Button
Plugin URI:  https://www.saberfeedback.com
Description: Include Saber in your Wordpress site.
Version:     1.2.2.1
Author:      Saber
Author URI:  https://www.saberfeedback.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

class SaberFeedback
{  
  // Singleton instance
  private static $instance = null;
   
  // Saved options
  public $options;
    
  /**
   * Creates or returns an instance of this class.
   *
   * @return SaberFeedback A single instance of this class.
   */
  public static function get_instance() {
    if ( null == static::$instance ) {
      static::$instance = new self;
    }

    return static::$instance;
  }

  /**
   * Initializes the plugin
   */
  private function __construct() {
    // Get saved option
    $this->options = get_option('bugm_settings_options');

    // when migrating from old version, create the default all visibility
    if(!isset($this->options['visibility'])) {
      $this->options['visibility'] = 'all';
    }

    // Add the page to the admin menu
    add_action('admin_menu', array(&$this, 'add_page'));
     
    // Register page options
    add_action('admin_init', array(&$this, 'register_page_options'));    

    // Add Saber Javascript to Head
    add_action('wp_head', array(&$this, 'saber_javascript'));
     
    // redirect to plugin settings after actication
    register_activation_hook(__FILE__, array(&$this, 'plugin_activated'));
    add_action('admin_init', array(&$this, 'redirect_on_activation'));

    // Display warning
    add_action('admin_notices', array(&$this, 'warning_message'));
  }

  /** 
   * get the options and add the script
   * Will not add if API key not included.
   */
  public function saber_javascript() {
    if(is_admin()) return;

    $logged_in = is_user_logged_in();

    if($this->options['visibility'] == 'guests' && $logged_in) {
      return;
    }

    if($this->options['visibility'] == 'users' && !$logged_in) {
      return;
    }
        
    if(!$this->options || !isset($this->options['api_key']) || $this->options['api_key'] == '') return;

    ?>
      <script>
        (function(){
          window.Saber={com:[],do:function(){this.com.push(arguments)}};
          var e = document.createElement("script"); 
          e.setAttribute("type", "text/javascript"); 
          e.setAttribute("src", "//feedback.saberfeedback.com/feedback.js?api_key=<?php echo $this->options['api_key'] ?>&options=wordpress-plugin"); 
          document.getElementsByTagName("head")[0].appendChild(e);
        })();
      </script>
    <?php
  }

  /**
   * Flag for post-activation redirect on admin_init
   */
  public function plugin_activated() {
    add_option('saber_do_activation_redirect', true);
  }

  /**
   * Redirect to plugin options if flagged
   */
  function redirect_on_activation() {
    if (get_option('saber_do_activation_redirect', false)) {
      delete_option('saber_do_activation_redirect');
      exit( wp_redirect("options-general.php?page=saber"));
    }
  }
    
  /**
   * Settings Menu options
   */
  public function add_page() {
    add_options_page('Saber Configuration', 'Saber', 'manage_options', 'saber', array(&$this, 'display_page'));
  }
    
  /**
   * Function that will display the options page.
   */
  public function display_page() {
    if(isset($this->options['language'])): ?>
      <div id="legacy_options_warning" class="error notice"> 
        <p><strong>Saber settings are now managed from within the <a href="https://app.saberfeedback.com/">control panel</a>. Please make sure your feedback button is correctly configured in the control panel before clicking the Save button below.</strong></p>
      </div>
    <?php endif ?>

    <div class="wrap">
      <h2>Saber</h2>
      <p>Thanks for using Saber! This plug in requires a Saber account, if you don't yet have one, you can sign up for free at <a href="https://www.saberfeedback.com/">www.saberfeedback.com</a></p>

      <form method="post" action="options.php">
      <?php 
        settings_fields('bugm_settings');      
        do_settings_sections('bugm_settings');
        submit_button('Save');
      ?>
      </form>
      <h3>Form and Button Configuration</h3>
      <p>You can customise the feedback button and form by logging into the <a href="https://app.saberfeedback.com/">Saber control panel</a> and choosing <strong>Edit Website</strong> or <strong>Form Builder</strong> from the menu on the right.</p>
      <p>
        <a href="https://app.saberfeedback.com" class="button button-default">Go to Saber Control Panel</a>
      </p>
    </div> <!-- /wrap -->
    <?php    
  }
     
  /**
   * Function that will register admin page options.
   */
  public function register_page_options() {
    // Add Section for option fields
    add_settings_section('bugm_api_section', 'Saber Connection', array(&$this, 'display_api_section'), 'bugm_settings');
     
    // Add API key Field
    add_settings_field(
      'bugmuncher_api_key_field',            // id
      'Public API Key',                      // name
      array(&$this, 'render_api_key_field'), // display method ($this->render_api_key_field)
      'bugm_settings',                       // page
      'bugm_api_section'                     // section
    );

    // Add Visibility Field
    add_settings_field(
      'bugmuncher_visibility_field',            // id
      'Load Saber for',                    // name
      array(&$this, 'render_visibility_field'), // display method ($this->render_visibility_field)
      'bugm_settings',                          // page
      'bugm_api_section'                        // section
    );

    // Register Settings
    //                option group     option name             sanitize method
    register_setting('bugm_settings', 'bugm_settings_options', array(&$this, 'validate_options')); 
  }
      
  /**
   * Function that will validate all fields.
   */
  public function validate_options($fields) {        
    $valid_fields = array();
     
    // Validate API Key Field
    $api_key = strip_tags( stripslashes( trim( $fields['api_key'] )));
    if($api_key == ''){
      add_settings_error('bugm_settings_options', 'bugm_bg_error', 'Public API Key is required to use Saber Feedback', 'error');
    }
    elseif(preg_match('/^(?=[a-f0-9]*$)(?:.{20}|.{40})$/i', $api_key ) == 0){
      add_settings_error('bugm_settings_options', 'bugm_bg_error', 'API Key is not in the correct format. Please check and try again', 'error');
    }
        
    $valid_fields['api_key'] = $api_key;

    $visibility = strip_tags(stripslashes( trim( $fields['visibility'] )));
    $valid_fields['visibility'] = $visibility;
    
    return apply_filters('validate_options', $valid_fields, $fields);
  }
   
  /**
   * Callback function for settings section
   */
  public function display_api_section() {
    echo '<p>Enter your public API key to connect to Saber, you can find your public API key at the top of the screen below your website name when you log in to <a href="https://app.saberfeedback.com/">app.saberfeedback.com</a>.</p>';
  } 
   
  /**
   * Functions that display the fields.
   */
  public function render_api_key_field() {
    $val = isset($this->options['api_key']) ? $this->options['api_key'] : '';
    echo '<input type="text" name="bugm_settings_options[api_key]" value="' . $val . '" class="regular-text" />';
  }        

  public function render_visibility_field() {
    $html = '<select name="bugm_settings_options[visibility]">';

    $html .= '<option value="all"'.selected($this->options['visibility'], 'all', false).'>All Visitors</option>';
    $html .= '<option value="users"'.selected($this->options['visibility'], 'users', false).'>Logged in users only</option>';
    $html .= '<option value="guests"'.selected($this->options['visibility'], 'guests', false).'>Guests only</option>';

    $html .= '</select>';
       
    echo $html;
  }


  /** 
   * Display a warning message
   */
  public function warning_message() {
    $class = 'notice notice-warning';
    $message = __( 'The current version of the <strong>Saber Feedback Button</strong> plugin will not be supported anymore starting in 2021. A new and updated version will be available in the <a href="https://wordpress.org/plugins/saber-feedback-button/" target="blank">WordPress Plugin Directory</a>, please remove your current plugin and install the new one. <a href="mailto:info@saberfeedback.com">Get in touch</a> if you need assistance.', 'saber-feedback-button' );
 
    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message);
  }
}

SaberFeedback::get_instance();
