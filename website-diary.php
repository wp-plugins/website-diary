<?php
/*
Plugin Name: Website Diary
Plugin URI: http://kahi.cz/wordpress/plugins/
Description: For keeping diary-type records connected to that site. Interface is located on dashboard in your administration.
Author: Peter Kahoun
Version: 0.9.1
Author URI: http://kahi.cz
*/

/*
@todo: 
- czech localization
- test uninstall method
- write readme
*/

class webDiary {
	
	const DEV = false;
	
	const VERSION = '0.9.1';

	// Required user levels/capabilities
	// see: http://codex.wordpress.org/Roles_and_Capabilities#User_Levels
	const USER_LEVEL_EDIT = 'level_10'; 
	const USER_LEVEL_READ = 'level_7'; 
	
	// Descr: full name. used on options-page, ...
	static $name = 'Website Diary';

	// Descr: short name. used in menu-item name, ...
	static $short_name = 'webDiary';

	// Descr: abbreviation. used in textdomain, ...
	// Descr: must be same as the name of the class
	static $abbr = 'webDiary';

	// Descr: path to this this file
	// filled automatically
	static $dir_name;

	// Descr: settings: names => default values
	// Descr: in db are these settings prefixed with abbr_
	// Required if using self::$settings!
	static $settings = array (
		'version' => false,
		
	);
	static $message;


	/**
	 * Initialization - filling main variables, preparing data, hooking into WP. Constructor replacement.
	 */
	public static function Init () {
		if (self::DEV) error_reporting(E_ALL);

		// set self::$dir_name
		// example: my-plugin
		$t = str_replace('\\', '/', dirname(__FILE__));
		self::$dir_name = trim(substr($t, strpos($t, '/plugins/')+9), '/');

		// load translation
		// @todo: generate and attach .pot (P3)
		load_plugin_textdomain(__CLASS__, 'wp-content/plugins/' . self::$dir_name . '/languages/');

		// prepare settings
		self::prepareSettings();
		
		// first use = installation
		if (self::$settings['version'] === false)
			var_dump(self::install());
		
		// hook: uninstall
		register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
		
		// hook: own actions and templates
		add_action('admin_init', array (__CLASS__, 'admin_init'));
		
		// hook: dashboard widget (template)
		add_action('wp_dashboard_setup', array (__CLASS__, 'wp_dashboard_setup'));

	}
	
	/**
	 * Adding dashboard item
	 * Hook: wp_dashboard_setup. Can't be added on init, since uses functions loaded after init-hook is triggered.
	 */
	public static function wp_dashboard_setup() {
		if (current_user_can(self::USER_LEVEL_READ))
			wp_add_dashboard_widget(__CLASS__, self::$name, array(__CLASS__, 'template_main'));
	}
	
	/**
	 * Installation, creates DB table
	 */
	public static function install () {
		
		global $wpdb;
		
		if (0 === $wpdb->query('CREATE TABLE `'.$wpdb->prefix.'webdiary` (`id` smallint NOT NULL AUTO_INCREMENT PRIMARY KEY, `date` date NOT NULL, `description` text COLLATE \'utf8_general_ci\' NOT NULL)'))  {
			update_option(__CLASS__.'_version', self::VERSION);
			return true;
		}
		
		return false;

		
	}
	
	
	/**
	 * Initial phase: handles eventual POST data (plugin's actions)
	 * Hook: admin_init
	 */
	public static function admin_init ($content) {
		
		global $wpdb;
		if (isset($_POST['webdiary'])) {
			
			// action: adding 
			if ($_POST['webdiary'] == 'add' AND check_admin_referer('webdiary-add')) {
				
				$data = array(
					'date' => $_POST['date'],
					'description' => $_POST['description']
					);
				
				$result = $wpdb->insert(
					$wpdb->prefix.'webdiary',
					$data);
				
				self::$message = 'add_'.(int)(bool)$result;
								
			}
			// action: edit
			elseif ($_POST['webdiary'] == 'edit_save' AND check_admin_referer('webdiary-edit')) {
			
				$data = array(
					'date' => $_POST['date'],
					'description' => $_POST['description']
					);
				
				if (!isset($_POST['id']) OR !is_numeric($_POST['id'])) 
					die ('ID od WebDiary entry not defined.');

				$wpdb->show_errors(); 
				$result = $wpdb->update(
					$wpdb->prefix.'webdiary',
					$data,
					array('id'=> $_POST['id']));
				
				$result = (false !== $result); // because 0 means "OK" in this crappy db layer
				
				self::$message = 'edit_'.(int)(bool)$result;
				
 				// $wpdb->print_error();
			}
			// action: remove
			elseif ($_POST['webdiary'] == 'remove' AND check_admin_referer('webdiary-remove')) {
							
				if (!isset($_POST['id']) OR !is_numeric($_POST['id'])) 
					die ('ID od WebDiary entry not defined.');

				$result = $wpdb->query("DELETE FROM {$wpdb->prefix}webdiary WHERE id = '{$_POST['id']}'");
				
				// $result = (false !== $result); // because 0 means "OK" in this crappy db layer
				
				self::$message = 'remove_'.(int)(bool)$result;
				
 				// $wpdb->print_error();
			}
			
		} 
		elseif (isset($_GET['webdiary'])) {
			if ($_GET['webdiary'] == 'export' AND current_user_can(self::USER_LEVEL_READ)) {
				self::template_export();
				exit;
			} 
			elseif ($_GET['webdiary'] == 'edit_load' AND current_user_can(self::USER_LEVEL_EDIT)) {
				self::template_edit();
				exit();
			}
			elseif ($_GET['webdiary'] == 'add' AND current_user_can(self::USER_LEVEL_EDIT)) {
				self::template_add();
				exit();
			}

		}
			
	}
	
	/**
	 * Main template: contains main diary interface (date-entry table), def. on dashboard
	 */
	function template_main () {
		global $wpdb;

		$messages = array(
			'add_0' => __('New diary entry adding failed!', __CLASS__),
			'add_1' => __('New diary entry added.', __CLASS__),
			'edit_0' => __('Diary entry update failed!', __CLASS__),
			'edit_1' => __('Diary entry updated.', __CLASS__),
			'remove_0' => __('Diary entry removing failed!', __CLASS__),
			'remove_1' => __('Diary entry removed.', __CLASS__));
?>

	<div id="webDiary_main">

		<?php 
		if (self::$message AND array_key_exists(self::$message, $messages)) echo '<p class="message">'. $messages[self::$message] .'</p>'; ?>

		<p><a href="?webdiary=add" class="thickbox"><?php _e('Add entry', __CLASS__) ?></a></p>

		<table>
			<?php $data = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'webdiary ORDER BY date DESC', ARRAY_A);
			foreach ($data as $row) { ?>
			<tr>
				<th><span><?php echo $row['date'] ?></span></th>
				<td><?php echo htmlSpecialChars(stripcslashes($row['description'])) ?>
					<?php if (current_user_can(self::USER_LEVEL_EDIT)) { ?>
						<a href="?webdiary=edit_load&amp;id=<?php echo $row['id'] ?>" class="thickbox"><?php _e('edit') ?></a>
					<?php } ?>
				</td>
			</tr>

			<?php 
			} ?>

		</table>

		<p class="export"><a href="?webdiary=export&amp;TB_iframe=1" class="thickbox"><?php _e('Export entries as text', __CLASS__) ?></a></p>
	</div>

	<style type="text/css" media="screen">
#webDiary_main {
	max-height:300px; overflow-y:auto;
}


#webDiary_main .message {
	padding:.67em;
	background-color:#eee;
	
	 -moz-border-radius: 4px; /* FF1+ */
  -webkit-border-radius: 4px; /* Saf3+, Chrome */
          border-radius: 4px; /* Opera 10.5, IE 9 */
}



#webDiary_main form {
	margin-bottom:1em;
}

	#webDiary_main input[name=date] {
		width:90px; font-size:11px
	}

	#webDiary_main input[name=description] {
		width:270px;
	}

#webDiary_main table {
	border-collapse: collapse;
}

#webDiary_main th,
#webDiary_main td {
	padding: 3px;
	border-bottom: 1px dotted #999;
}

#webDiary_main th {
	width:100px; padding:3px 10px 3px 0; 
	vertical-align:top; font-weight:bold; font-size:11px; text-align: left;
}

	#webDiary_main th span {
		background-color:#EFA60B; color:#fff; padding:3px;
	}

#webDiary_main th + td {
	/*width:260px;*/ padding:3px 10px 5px 0; 
	vertical-align:top; font-size:12px; line-height:1.4;
}


#webDiary_main tr:hover > * {
	background-color:#f8f8f8;
}

#webDiary_main td a {
	display: none;
	margin-left:1em;
	font-size:smaller;
}

#webDiary_main td:hover a {
	display: inline;
}


#webDiary_main .export a {
	color:#888 !important;
}



#webDiary_edit {
	text-align: center;
	font-size:13px;
}

#webDiary_edit input,
#webDiary_edit textarea {
	display: block; margin: 0 auto 1em;
	font-size:13px;
}

#webDiary_edit input[type=text] {
	width:140px;
	text-align: center;
}

#webDiary_edit textarea {
	width:400px;
}

#webDiary_edit form.remove {
	margin-top:30px;
}

#webDiary_edit form.remove button {
	background-color:#B70007; color:#fff;
}

	</style>


<?php
	}

	/**
	 * Template: Plain-text export of diary data
	 */
	function template_export () {
		global $wpdb;
		
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta charset="utf-8" />
	<title>Website Diary Export</title>
	
	<style>
		body {
			font-family:sans-serif;
		}

		textarea {
			width:100%;
		}
	</style>
</head>
<body>

<h1>Website Diary - text export</h1>

<textarea rows=30><?php

	$data = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'webdiary ORDER BY date ASC', ARRAY_A);
	foreach ((array) $data as $row) {
		echo $row['date'].': '.$row['description']."\n";
	} ?></textarea>

</body>
</html>
<?php
		
	}

	/**
	 * Template: editing (similar to template for adding) (contains entry-removing trigger)
	 */
	function template_edit () {
		global $wpdb;
		
		$id = (int)$_GET['id'];
		$data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}webdiary WHERE id = {$id}", ARRAY_A);
		
		if (!count($data)) die('Sorry, no data found.');
		
		$row = $data[0]; 
?>

	<div id="webDiary_edit">

		<form action="?" method="post">
			
			<h3>Edit Website Diary entry from <?php echo $row['date'] ?></h3>
			
			<input type="date" name="date" value="<?php echo $row['date'] ?>">
			<textarea type="text" name="description" rows=5 cols=50><?php echo htmlSpecialChars(stripcslashes($row['description'])) ?></textarea>
			<input type="hidden" name="webdiary" value="edit_save">
			<input type="hidden" name="id" value="<?php echo $id ?>">
			<?php wp_nonce_field('webdiary-edit') ?>
			<p class="submit"><input type="submit" value="<?php _e('Save changes') ?>"></p>			
		</form>
		
		
		<form action="?" method="post" class="remove">
			<input type="hidden" name="webdiary" value="remove">
			<input type="hidden" name="id" value="<?php echo $id ?>">
			<?php wp_nonce_field('webdiary-remove') ?>
			<button type="submit" onclick="return window.confirm('<?php _e('Really remove this diary entry?', __CLASS__) ?>');"><?php _e('Remove', __CLASS__) ?></button>
		</form>
		
	</div>

<?php
	}

	/**
	 * Template: adding
	 */
	function template_add () {

?>

	<div id="webDiary_edit">

		<form action="?" method="post">
			
			<h3>A Website Diary entry</h3>
			
			<input type="date" name="date" value="<?php echo date('Y-m-d') ?>">
			<textarea type="text" name="description" rows=5 cols=50 placeholder="<?php _e('type your diary-event description...', __CLASS__) ?>"></textarea>
			<input type="hidden" name="webdiary" value="add">
			<input type="hidden" name="id" value="<?php echo $id ?>">
			<?php wp_nonce_field('webdiary-add') ?>
			<p class="submit"><input type="submit" value="<?php _e('Save') ?>"></p>			
		</form>
		
		
	</div>

<?php
	}


	// ====================  WP-general code  ====================

	/**
	 * Loads settings from db (wp_options) and stores them to self::$settings[setting_name_without_plugin_prefix]
	 * Settings-names are in db prefixed with "{__CLASS__}_", keys in $settings aren't. Very reusable.
	 * @see self::$settings
	 * @return void
	 */
	public static function prepareSettings () {

		foreach (self::$settings as $name => $default_value) {
			if (false !== ($option = get_option(__CLASS__ . '_' . $name))) {
				self::$settings[$name] = $option;
			} else {
				// do nothing, let there be the default value
			}
		}

		// self::debug(self::$settings); //??

	}


	/**
	 * WP Hook: Uninstallation. Removes all plugin's settings. Very reusable.
	 * @return void
	 */
	public static function uninstall () {
		
		foreach (self::$settings as $name => $value) {
			delete_option(__CLASS__.'_'.$name);
		}
		
		$wpdb->query('DROP table `'.$wpdb->prefix.'webdiary`'); // @todo test!!
		
	}


	/**
	 * Outputs content given as first parameter. Enhanced replacement for var_dump().
	 * @param mixed Variable to output
	 * @param string (Optional) variable description
	 * @return void
	 */
	public static function debug($var, $descr = false) {

		if ($descr) echo '<p style="background:#666; color:#fff"><b>'.$descr.':</b></p>';

		echo '<pre style="max-height:300px; overflow-y:auto">'.htmlSpecialChars(var_export($var, true)).'</pre>';

	}


} // end of class


// ====================  Initialize the plugin  ====================
if (is_admin())
	webDiary::Init();