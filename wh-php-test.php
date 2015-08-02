<?php
/*
 * Plugin Name: WH PHP Test
 * Version: 0.0.1
 * Description: A simple autocomplete plugin.
 * Author: subhansanjaya
 */
if(! defined( 'ABSPATH' )) exit; // Exit if accessed directly

class WH_PHP_test {

	//default settings
	private $defaults = array(
	'version' => '0.0.1',
	'configuration' => array(
		'deactivation_delete' => false,
		'min_length' => 3,
	));

	private $options = array();
	private $tabs = array();

	public function __construct() {

		register_activation_hook(__FILE__, array(&$this, 'activation'));
		register_deactivation_hook(__FILE__, array(&$this, 'deactivation'));

		//define plugin path
		define( 'WHPT__PLUGIN_PATH', plugin_dir_path(__FILE__) );

		//define template path
		define( 'WHPT_TEMPLATE_DIRECTORY_NAME', 'templates' );
		define( 'WHPT_TEMPLATE_DIRECTORY_PATH', WHPT__PLUGIN_PATH .WHPT_TEMPLATE_DIRECTORY_NAME. DIRECTORY_SEPARATOR );

		//Add admin option
		add_action('admin_menu', array(&$this, 'admin_menu_options'));
		add_action('admin_init', array(&$this, 'register_settings'));
		add_action('admin_init', array(&$this, 'install_data'));
		add_action('admin_init', array(&$this, 'whpt_hide_notice'));

		//add text domain for localization
		add_action('plugins_loaded', array(&$this, 'load_textdomain'));

		//load defaults
		add_action('plugins_loaded', array(&$this, 'load_defaults'));

		//update plugin version
		update_option('whpt_version', $this->defaults['version'], '', 'no');
		$this->options['configuration'] = array_merge($this->defaults['configuration'], (($array = get_option('whpt_configuration')) === FALSE ? array() : $array));

		//insert js and css files
		add_action('wp_enqueue_scripts', array(&$this, 'whpt_load_scripts'));

		//settings link
		add_filter('plugin_action_links', array(&$this, 'show_settings_link'), 2, 2);

		//add shortcode
		add_shortcode( 'whpt', array(&$this, 'display_autocomplete'));

		//admin notices to install data
		add_action('admin_notices', array(&$this, 'whpt_admin_notices'));
		add_action('admin_init', array(&$this, 'whpt_ignore_notices'));

		//ajax 
		add_action('wp_ajax_nopriv_whpt_action', array(&$this, 'whpt_action_callback'));
		add_action('wp_ajax_whpt_action',  array(&$this, 'whpt_action_callback'));
	}

	/* multi site activation hook */
	public function activation($networkwide) {

		if(is_multisite() && $networkwide) {
			global $wpdb;

			$activated_blogs = array();
			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->activate_single();
				$activated_blogs[] = (int)$blog_id;
			}

			switch_to_blog($current_blog_id);
			update_site_option('whpt_activated_blogs', $activated_blogs, array());
		}
		else
			$this->activate_single();
	}

	function activate_single() {

		global $wpdb;

   		$table_name = $wpdb->prefix . "whpt_population"; 

		$charset_collate = $wpdb->get_charset_collate();

		//create table if not exist
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

		$sql = "CREATE TABLE $table_name (
			id int(11) unsigned NOT NULL AUTO_INCREMENT,
			location varchar(150) NOT NULL,
			slug varchar(150) NOT NULL,
			population int(10) unsigned NOT NULL,
			PRIMARY KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		}
		
		add_option('whpt_version', $this->defaults['version'], '', 'no');
		add_option('whpt_configuration', $this->defaults['configuration'], '', 'no');
	
	}

	/* copy data from CSV file */
	function install_data() {

		global $wpdb;

		$table_name = $wpdb->prefix . "whpt_population"; 

		$empty = $wpdb->get_col($wpdb->prepare("SELECT 1 FROM $table_name LIMIT 1" ) );

		if ( empty($empty) && isset($_POST['whpt_install_data']) ) {

		global $wpdb;

   		$table_name = $wpdb->prefix . "whpt_population"; 

		$file = plugins_url('data.csv', __FILE__ );

	    $handle = fopen($file,"r");

	    do { 

        if ($data[0]) { 

			preg_match_all('~^(.*?)(\d+)~m', $data[1], $matches);

			$location = str_replace(array("\n", "\r", "\r\n", "\n\r"), ',',  str_replace(array("\N"), '', $data[0]));
			$location = preg_replace('/\s+/', '', $location);

					$wpdb->insert($table_name , array(
						"location" => addslashes($matches[1][0]),
						"slug" => $location,
						"population" => addslashes($matches[2][0]) // ... and so on
					));

        		} 

   			} while ($data = fgetcsv($handle,54822,",","'")); 

		}			

	}

	/*  multi-site deactivation hook */
	function deactivation($networkwide) {

		if(is_multisite() && $networkwide) {
			global $wpdb;

			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			if(($activated_blogs = get_site_option('whpt_activated_blogs', FALSE, FALSE)) === FALSE)
				$activated_blogs = array();

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->deactivate_single(TRUE);

				if(in_array((int)$blog_id, $activated_blogs, TRUE))
					unset($activated_blogs[array_search($blog_id, $activated_blogs)]);
			}

			switch_to_blog($current_blog_id);
			update_site_option('whpt_activated_blogs', $activated_blogs);
		}
		else
			$this->deactivate_single();
	}

	public function deactivate_single($multi = FALSE) {

		if($multi === TRUE) {
			$options = get_option('whpt_configuration');
			$check = $options['deactivation_delete'];
		}
		else {
		$check = $this->options['configuration']['deactivation_delete'];
		
		if($check === TRUE) {

			global  $current_user;

        	$user_id = $current_user->ID;
			
			delete_option('whpt_version');
			delete_option('whpt_configuration');
			delete_user_meta($user_id, 'whpt_ignore_notice');
			
			global $wpdb;

	   		$table_name = $wpdb->prefix . "whpt_population"; 
			$wpdb->query( "DROP TABLE IF EXISTS $table_name " );

			}

		}
	}

	/* settings link in management screen */
	public function show_settings_link($actions, $file) {

		if(false !== strpos($file, 'wh-php-test'))
		 $actions['settings'] = '<a href="options-general.php?page=wh-php-test">Settings</a>';
		return $actions; 

	}

	/* display autocomplete */
	public function display_autocomplete() {

		$whpt_autocomplete = '';

		//include theme
		include $this->get_file_path('template');

		wp_reset_postdata();

		return $whpt_autocomplete;
	}

	/* view path for the theme files */
	public function get_file_path( $view_name, $is_php = true ) {
		
		$temp_path = get_stylesheet_directory().'/wh-php-test/templates/';

		if(file_exists($temp_path)) {

			if ( strpos( $view_name, '.php' ) === FALSE && $is_php )
		return $temp_path.'/'.$view_name.'/'.$view_name.'.php';
		return $temp_path . $view_name;

		} else {

			if ( strpos( $view_name, '.php' ) === FALSE && $is_php )
		return WHPT_TEMPLATE_DIRECTORY_PATH.'/'.$view_name.'.php';
		return WHPT_TEMPLATE_DIRECTORY_PATH . $view_name;
		}

	}


	/* insert css files js files */
	public function whpt_load_scripts($jquery_true) {

		wp_register_style('jquery-ui', plugins_url('/assets/css/jquery-ui.css',__FILE__));
		wp_enqueue_style('jquery-ui');

		wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-autocomplete');

		wp_register_style('whpt_styles', plugins_url('/assets/css/custom.css',__FILE__));
		wp_enqueue_style('whpt_styles');

	    $args_mtree = apply_filters('whpt_options', array(
		'min_length' =>   $this->options['configuration']['min_length'],
		'plugins_url' => plugins_url(),
		'url' => admin_url( 'admin-ajax.php')
		));

		wp_register_script('whpt_autocomplete',plugins_url('/assets/js/autocomplete.js', __FILE__),array('jquery'),'',false);
	    wp_enqueue_script('whpt_autocomplete'); 

	    wp_localize_script('whpt_autocomplete','whpt_options',$args_mtree);
	}

	/* load default settings */
	public function load_defaults(){
		
		$this->choices = array(
			'yes' => __('Enable', 'whpt_txt'),
			'no' => __('Disable', 'whpt_txt')
		);


		$this->tabs = array(
	
            'general-settings' => array(
                'name' => __('General', 'whpt_txt'),
                'key' => 'whpt_configuration',
                'submit' => 'save_whpt_configuration',
                'reset' => 'reset_whpt_configuration'
            )
		);
	}


	/* admin menu */
	public function admin_menu_options(){
		add_options_page(
			__('WH PHP Test', 'whpt_txt'),
			__('WH PHP Test', 'whpt_txt'),
			'manage_options',
			'wh-php-test',
			array(&$this, 'options_page')
		);
	}

	/* register setting for plugins page */
	public function register_settings() {

		//advance settings
		register_setting('whpt_configuration', 'whpt_configuration', array(&$this, 'validate_options'));

		add_settings_section('whpt_configuration', __('', 'whpt_txt'), '', 'whpt_configuration');
		add_settings_field('min_length', __('Autocomplete min length', 'whpt_txt'), array(&$this, 'min_length'), 'whpt_configuration', 'whpt_configuration');
		add_settings_field('deactivation_delete', __('Deactivation', 'whpt_txt'), array(&$this, 'deactivation_delete'), 'whpt_configuration', 'whpt_configuration');

	}

	/* min length */
	public function min_length() {

		echo '<div id="min_length">
		<input type="text"  value="'.esc_attr($this->options['configuration']['min_length']).'" name="whpt_configuration[min_length]" onkeypress="return event.charCode >= 48 && event.charCode <= 57"/>
		</div>';

	}

	/* deactivation on delete */
	public function deactivation_delete(){
		echo '
		<div id="deactivation_delete" class="wplikebtns">';
		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="whpt-deactivation-delete-'.$val.'" type="radio" name="whpt_configuration[deactivation_delete]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['deactivation_delete'], FALSE).' />
			<label for="whpt-deactivation-delete-'.$val.'">'.$trans.'</label>';
		}
		echo '
			<p class="description">'.__('Delete settings on plugin deactivation.', 'whpt_txt').'</p>
		</div>';
	}


	/* options page */
	public function options_page() {

		$tab_key = (isset($_GET['tab']) ? $_GET['tab'] : 'general-settings');
		echo '<div class="wrap">'.screen_icon().'
			<h2>'.__('WH PHP Test', 'wa_wcc_txt').'</h2>
			<h2 class="nav-tab-wrapper">';

		echo '<form action="options.php" method="post">';
	
		wp_nonce_field('update-options');
		
		settings_fields($this->tabs[$tab_key]['key']);
		
		do_settings_sections($this->tabs[$tab_key]['key']);
		
		echo '<p class="submit">';
		
		submit_button('', 'primary', $this->tabs[$tab_key]['submit'], FALSE);
	
		echo ' ';
		
		echo submit_button(__('Reset to defaults', 'wa_wcc_txt'), 'secondary', $this->tabs[$tab_key]['reset'], FALSE);
		
		echo '</p></form></div><div class="clear"></div></div>';
	}


	/* load text domain for localization */
	public function load_textdomain(){
		load_plugin_textdomain('whpt_txt', FALSE, dirname(plugin_basename(__FILE__)).'/lang/');
	}

	/* validate options and register settings */
	public function validate_options($input) {
	

	if(isset($_POST['reset_whpt_configuration'])) {

		$input = $this->defaults['configuration'];

			add_settings_error('reset_whpt_configuration', 'whpt_reset', __('Settings of were restored to defaults.', 'whpt_txt'), 'updated');

		} else if(isset($_POST['save_whpt_configuration'])) {

			$input['deactivation_delete'] = (isset($input['deactivation_delete'], $this->choices[$input['deactivation_delete']]) ? ($input['deactivation_delete'] === 'yes' ? true : false) : $this->defaults['configuration']['deactivation_delete']);	
			$input['min_length'] =isset($input['min_length']) ? $input['min_length'] : '3';
	
		}

		return $input;
	}

	/* Install data admin notice */
	public function whpt_admin_notices() {

		global $wpdb, $pagenow, $current_user ;

        $user_id = $current_user->ID;

		$table_name = $wpdb->prefix . "whpt_population"; 

		$empty = $wpdb->get_col($wpdb->prepare("SELECT 1 FROM $table_name LIMIT 1" ) );
		if ( empty($empty) ){
			if ( $pagenow == 'plugins.php' ) {
				?>
					<div class="error">
					<form action="#" method="post"><p><label>WH PHP Test | </label>
					<input name='whpt_install_data' type='submit' class="button" value='Copy data from CSV'></p>
					</form></div>
				<?php
			}
		}else {

			if ( ! get_user_meta($user_id, 'whpt_ignore_notice') ) {

				if ( $pagenow == 'plugins.php' ) {

					echo '<div class="updated"><p>'; 
					printf(__('WH PHP Test | Data was copied successfully. <a href="%1$s">Hide Notice</a>'), '?whpt_ignore_notice=0');
					echo "</p></div>";

					}
				}

		}
	}

	//ajax action call back
	public function whpt_action_callback() {

		global $wpdb;

		$table_name = $wpdb->prefix . "whpt_population"; 

		if( isset($_GET['term']) ) { 

			$keyword = '%'.mysql_real_escape_string($_GET['term']).'%'; 

			$results = $wpdb->get_results("SELECT slug,location,population FROM $table_name WHERE location LIKE '$keyword'  ORDER BY population DESC LIMIT 10");

			$a_json = array();
			$a_json_row = array();
	    
	       foreach ($results as $locationData) {

	       	$a_json_row["id"] = $locationData->slug;
	       	$a_json_row["value"] = $locationData->location;
	   		$a_json_row["label"] =  $locationData->location;


			array_push($a_json, $a_json_row);

			}

			echo json_encode($a_json);

			die(); // this is required to terminate immediately and return a proper response

		} 

	}

	public function whpt_hide_notice() {

    	global $current_user;

	        $user_id = $current_user->ID;

	        if ( isset($_GET['whpt_ignore_notice']) && '0' == $_GET['whpt_ignore_notice'] ) {
	             add_user_meta($user_id, 'whpt_ignore_notice', 'true', true);
	    }
	}

}
$WH_PHP_test = new WH_PHP_test();