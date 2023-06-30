<?php
/*
Plugin Name: MF Law Info
Plugin URI: 
Description: 
Version: 4.9.28
Author: Martin Fors
Author URI: http://martinfors.se
Text Domain: lang_law_info
Domain Path: /lang

Depends: MF Base, MF Law
*/

if(is_plugin_active("mf_base/index.php"))
{
	include_once("include/classes.php");

	load_plugin_textdomain('lang_law_info', false, dirname(plugin_basename(__FILE__)).'/lang/');

	$obj_law_info = new mf_law_info();

	add_action('cron_base', 'activate_law_info', mt_rand(1, 10));

	if(is_admin())
	{
		register_activation_hook(__FILE__, 'activate_law_info');
		register_uninstall_hook(__FILE__, 'uninstall_law_info');

		add_action('admin_init', array($obj_law_info, 'settings_law_info'));
		add_action('admin_init', array($obj_law_info, 'admin_init'), 0);
		add_action('admin_menu', array($obj_law_info, 'admin_menu'));

		add_filter('manage_users_columns', array($obj_law_info, 'manage_users_columns'), 5);
		add_action('manage_users_custom_column', array($obj_law_info, 'manage_users_custom_column'), 5, 3);

		add_action('deleted_user', array($obj_law_info, 'deleted_user'));

		//add_shortcode('mf_law_accepted', array($obj_law_info, 'shortcode_law_accepted'));
	}

	function activate_law_info()
	{
		global $wpdb;

		require_plugin("mf_law/index.php", "MF Law");

		$default_charset = (DB_CHARSET != '' ? DB_CHARSET : 'utf8');

		$arr_add_column = $arr_update_column = $arr_add_index = array();

		$wpdb->query("CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."law_info (
			lawInfoID INT UNSIGNED NOT NULL AUTO_INCREMENT,
			lawID INT UNSIGNED,
			listID INT UNSIGNED,
			lawInfoKey VARCHAR(100),
			lawInfoValue TEXT,
			lawInfoCreated DATETIME,
			lawInfoUpdated DATETIME,
			userID INT UNSIGNED DEFAULT '0',
			lawInfoArchived ENUM('0', '1') NOT NULL DEFAULT '0',
			lawInfoDeleted ENUM('0', '1') NOT NULL DEFAULT '0',
			lawInfoDeletedDate DATETIME DEFAULT NULL,
			lawInfoDeletedID INT UNSIGNED DEFAULT '0',
			PRIMARY KEY (lawInfoID),
			KEY lawID (lawID),
			KEY listID (listID),
			KEY lawInfoKey (lawInfoKey),
			KEY lawInfoArchived (lawInfoArchived),
			KEY lawInfoDeleted (lawInfoDeleted)
		) DEFAULT CHARSET=".$default_charset);

		$arr_add_column[$wpdb->prefix."law_info"] = array(
			//'lawInfoUpdated' => "ALTER TABLE [table] ADD [column] DATETIME AFTER lawInfoCreated",
		);

		$arr_update_column[$wpdb->prefix."law_info"] = array(
			//'lawTypeID' => "ALTER TABLE [table] DROP [column]",
		);

		$arr_add_index[$wpdb->prefix."law_info"] = array(
			//'lawInfoArchived' => "ALTER TABLE [table] ADD INDEX [column] ([column])",
		);

		add_columns($arr_add_column);
		update_columns($arr_update_column);
		add_index($arr_add_index);

		delete_base(array(
			'table' => "law_info",
			'field_prefix' => "lawInfo",
		));
	}

	function uninstall_law_info()
	{
		mf_uninstall_plugin(array(
			'tables' => array('law_info'),
		));
	}
}