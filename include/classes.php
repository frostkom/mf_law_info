<?php

class mf_law_info
{
	function __construct($id = 0)
	{
		if($id > 0)
		{
			$this->id = $id;
		}

		else
		{
			$this->id = check_var('intLawInfoID');
		}

		$this->is_updating = $this->id > 0;
	}

	function get_user_status($data = array())
	{
		global $wpdb, $obj_list, $obj_law, $obj_law_info;

		if(!isset($data['list_id'])){		$data['list_id'] = 0;}
		if(!isset($data['list_rights'])){	$data['list_rights'] = array('editor', 'author');}
		if(!isset($data['user_id'])){		$data['user_id'] = get_current_user_id();}

		if(!isset($obj_list))
		{
			$obj_list = new mf_list();
		}

		if(!isset($obj_law))
		{
			$obj_law = new mf_law();
		}

		if(!isset($obj_law_info))
		{
			$obj_law_info = new mf_law_info();
		}

		$this->arr_not_acknowledged = $this->arr_not_accepted = $this->arr_accepted = array();

		if(!isset($this->arr_laws_temp))
		{
			$tbl_group = new mf_law_table(array('ignore_search' => true));

			$tbl_group->select_data(array(
				'select' => $wpdb->prefix."law.lawID, lawNo, lawName, lawValidTo, lawUpdatedTo",
				'where' => "(lawValid = '0000-00-00' OR lawValid > NOW() AND lawValidTo = '0000-00-00' OR lawValidTo < NOW())",
				'sort_data' => true,
				//'debug' => ($_SERVER['REMOTE_ADDR'] == ""),
			));

			$this->arr_laws_temp = $tbl_group->data;
		}

		if(count($this->arr_laws_temp) > 0)
		{
			$query_where = "";

			if($data['list_id'] > 0)
			{
				$query_where .= " AND listID = '".esc_sql($data['list_id'])."'";
			}

			if(count($data['list_rights']) > 0)
			{
				$query_where .= " AND listRights IN('".implode("','", $data['list_rights'])."')";
			}

			$this->arr_list_temp = $wpdb->get_results($wpdb->prepare("SELECT listID, listName, lawID FROM ".$wpdb->prefix."list INNER JOIN ".$wpdb->prefix."law2list USING (listID) INNER JOIN ".$wpdb->prefix."list2user USING (listID) WHERE ".$wpdb->prefix."list2user.userID = '%d' AND lawPublished = '1'".$query_where." GROUP BY lawID", $data['user_id'])); //, listRights

			if(count($this->arr_list_temp) > 0)
			{
				$law_edit_base_url = $obj_law->get_base_url('edit');

				/*if($_SERVER['REMOTE_ADDR'] == "")
				{
					do_log("get_user_status: ".var_export($wpdb->last_query, true));
				}*/

				foreach($this->arr_laws_temp as $r)
				{
					$intLawID = $r['lawID'];
					$strLawNo = $r['lawNo'];
					$strLawName = $r['lawName'];
					$dteLawValidTo = $r['lawValidTo'];
					$dteLawUpdatedTo = $r['lawUpdatedTo'];

					$arr_law_data = array(
						'no' => $strLawNo,
						'name' => $strLawName,
					);

					foreach($this->arr_list_temp as $r)
					{
						if($r->lawID == $intLawID)
						{
							$obj_law_info->law_id = $r->lawID;
							$obj_law_info->list_id = $obj_list->id = $r->listID;
							$arr_law_data['list_name'] = $r->listName;
							//$arr_law_data['list_rights'] = $r->listRights;

							if($obj_law->has_been_revoked(array('id' => $intLawID, 'valid_to' => $dteLawValidTo, 'updated_to' => $dteLawUpdatedTo)) == false) //, 'list_id' => $obj_list->id // This should not be used here. The connection to a list does not matter // || (isset($this->show_old) && $this->show_old == 'yes' && (IS_EDITOR || $dteLawValidTo >= date("Y-m-d", strtotime("-3 month"))))
							{
								$dteLawPublishedDate = $obj_list->last_published(array('law_id' => $intLawID));

								$arr_law_data['link'] = $law_edit_base_url."&intLawID=".$intLawID."&intListID=".$obj_list->id;

								$dteListAcknowledgedDate = $obj_law_info->get_key_value(array('key' => 'acknowledged', 'output' => 'date'));
								$dteListAcceptedDate = $obj_law_info->get_key_value(array('key' => 'accepted', 'output' => 'date'));

								if(!($dteListAcknowledgedDate > DEFAULT_DATE))
								{
									$arr_law_data['published'] = $dteLawPublishedDate;

									$this->arr_not_acknowledged[] = $arr_law_data;
								}

								else if(!($dteListAcceptedDate > DEFAULT_DATE))
								{
									$arr_law_data['published'] = $dteLawPublishedDate;

									$this->arr_not_accepted[] = $arr_law_data;
								}

								else
								{
									$arr_law_data['published'] = $dteListAcceptedDate;

									$this->arr_accepted[] = $arr_law_data;
								}
							}
						}
					}
				}
			}
		}
	}

	/*function shortcode_law_accepted()
	{
		global $obj_base;

		if(!isset($obj_base))
		{
			$obj_base = new mf_base();
		}

		$out = "";

		$this->get_user_status();

		$count_temp = count($this->arr_not_acknowledged);
		$count_limit = 10;

		if($count_temp > 0)
		{
			$this->arr_not_acknowledged = $obj_base->array_sort(array('array' => $this->arr_not_acknowledged, 'on' => 'published', 'order' => 'asc'));

			$out .= "<li><h2>".__("Comply with requirements", 'lang_law_info')."</h2></li>";

			for($i = 0; $i < $count_temp && $i < $count_limit; $i++)
			{
				$not_acknowledged = $this->arr_not_acknowledged[$i];

				$out .= "<li><i class='fa fa-times fa-lg red'></i> ".format_date($not_acknowledged['published']).": <a href='".$not_acknowledged['link']."'>".$not_acknowledged['name']."</a> (".$not_acknowledged['list_name'].")</li>";
			}

			if($count_temp > $count_limit)
			{
				$out .= "<li>".sprintf(__("...and %d more", 'lang_law_info'), ($count_temp - $count_limit))."</li>";
			}
		}

		$count_temp = count($this->arr_not_accepted);
		$count_limit = 5;

		if($count_temp > 0)
		{
			$this->arr_not_accepted = $obj_base->array_sort(array('array' => $this->arr_not_accepted, 'on' => 'published', 'order' => 'asc'));

			$out .= "<li><h2>".__("Left for checking evaluation", 'lang_law_info')."</h2></li>";

			for($i = 0; $i < $count_temp && $i < $count_limit; $i++)
			{
				$not_accepted = $this->arr_not_accepted[$i];

				$out .= "<li><i class='fa fa-times fa-lg red'></i> ".format_date($not_accepted['published']).": <a href='".$not_accepted['link']."'>".$not_accepted['name']."</a> (".$not_accepted['list_name'].")</li>";
			}

			if($count_temp > $count_limit)
			{
				$out .= "<li>".sprintf(__("...and %d more", 'lang_law_info'), ($count_temp - $count_limit))."</li>";
			}
		}

		$count_temp = count($this->arr_accepted);
		$count_limit = 5;

		if($count_temp > 0)
		{
			$this->arr_accepted = $obj_base->array_sort(array('array' => $this->arr_accepted, 'on' => 'published', 'order' => 'desc'));

			$out .= "<li><h2>".__("Compliance with", 'lang_law_info')."</h2></li>";

			for($i = 0; $i < $count_temp && $i < $count_limit; $i++)
			{
				$accepted = $this->arr_accepted[$i];

				$out .= "<li><i class='fa fa-check fa-lg green'></i> ".format_date($accepted['published']).": <a href='".$accepted['link']."'>".$accepted['name']."</a> (".$accepted['list_name'].")</li>";
			}

			if($count_temp > $count_limit)
			{
				$out .= "<li>".sprintf(__("...and %d more", 'lang_law_info'), ($count_temp - $count_limit))."</li>";
			}
		}

		if($out != '')
		{
			$out = "<ul>".$out."</ul>";
		}

		return $out;
	}*/

	function settings_law_info()
	{
		global $wpdb;

		$options_area_orig = $options_area = __FUNCTION__;

		add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

		$arr_settings = array(
			'setting_law_info_red_on_not_acknowledged' => __("Rights for displaying red on not acknowledged", 'lang_law_info'),
		);

		show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
	}

	function settings_law_info_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Laws", 'lang_law_info'));
	}

		function setting_law_info_red_on_not_acknowledged_callback()
		{
			global $obj_list;

			if(!isset($obj_list))
			{
				$obj_list = new mf_list();
			}

			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_Default($setting_key, array());

			echo show_select(array('data' => $obj_list->get_list_roles_for_select(), 'name' => $setting_key."[]", 'value' => $option));
		}

	function admin_init()
	{
		global $pagenow;

		$plugin_include_url = plugin_dir_url(__FILE__);
		$plugin_version = get_plugin_version(__FILE__);

		if($pagenow == 'admin.php')
		{
			$page = check_var('page');

			if($page == "mf_law/create/index.php")
			{
				mf_enqueue_script('script_law_info', $plugin_include_url."script_wp.js", $plugin_version);
			}
		}
	}

	function admin_menu()
	{
		$menu_root = 'mf_law_info/';
		$menu_start = $menu_root.'list/index.php';
		$menu_capability = 'upload_files';

		$menu_title = __("Info", 'lang_law_info');
		add_submenu_page("mf_law/index.php", $menu_title, $menu_title, $menu_capability, $menu_start);

		$menu_title = __("Add New", 'lang_law_info');
		add_submenu_page("mf_law/index.php", $menu_title, $menu_title, $menu_capability, $menu_root.'create/index.php');
	}

	function deleted_user($user_id)
	{
		global $wpdb;

		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."law_info SET userID = '%d' WHERE userID = '%d'", get_current_user_id(), $user_id));
	}

	function manage_users_columns($cols)
	{
		$cols['laws2handle'] = shorten_text(array('string' => __("Laws to Handle", 'lang_law_info'), 'limit' => 10, 'add_title' => true));

		return $cols;
	}

	function manage_users_custom_column($value, $col, $id)
	{
		switch($col)
		{
			case 'laws2handle':
				$this->get_user_status(array('user_id' => $id, 'list_rights' => array('editor', 'author')));

				$count_not_acknowledged = count($this->arr_not_acknowledged);
				$count_not_accepted = count($this->arr_not_accepted);
				$count_accepted = count($this->arr_accepted);

				if($count_not_acknowledged > 0 || $count_not_accepted > 0 || $count_accepted > 0)
				{
					return $count_not_acknowledged."/".$count_not_accepted."/".$count_accepted;
				}
			break;
		}
	}

	function fetch_request()
	{
		$this->law_id = check_var('intLawID');
		$this->list_id = check_var('intListID');
		$this->key = check_var('strLawInfoKey');
		$this->value = isset($_POST['strLawInfoValue']) ? $_POST['strLawInfoValue'] : "";
		$this->arr_value = check_var('arrLawInfoValue');
	}

	function save_data()
	{
		global $wpdb, $error_text, $done_text;

		$out = "";

		if(isset($_POST['btnLawInfoCreate']) && (isset($_POST['_wpnonce_law_info_create']) && wp_verify_nonce($_POST['_wpnonce_law_info_create'], 'law_info_create_'.$this->id) || isset($_POST['_wpnonce_info']) && wp_verify_nonce($_POST['_wpnonce_info'], 'law_info_create_'.$this->id)))
		{
			if(!($this->law_id > 0) || !($this->list_id > 0) || $this->key == '')
			{
				$error_text = __("Please, enter all required fields", 'lang_law_info');
			}

			else
			{
				if($this->id > 0)
				{
					$this->update();
				}

				else
				{
					if($this->get_key_id() > 0)
					{
						$error_text = __("The type is already in use, please edit that one instead", 'lang_law_info');
					}

					else
					{
						$this->create();
					}
				}

				if($this->id > 0)
				{
					$this->id = $this->key = $this->value = "";
					$this->arr_value = array();

					if($this->is_updating)
					{
						$done_text = __("The info was updated", 'lang_law_info');
					}

					else
					{
						$done_text = __("The info was created", 'lang_law_info');
					}
				}
			}
		}

		else if(isset($_GET['btnLawAcknowledge']) && wp_verify_nonce($_GET['_wpnonce_law_acknowledge'], 'law_acknowledge_'.$this->law_id.'_'.$this->list_id))
		{
			if($this->law_id > 0 && $this->list_id > 0)
			{
				$wpdb->get_var($wpdb->prepare("SELECT lawInfoID FROM ".$wpdb->prefix."law_info WHERE lawID = '%d' AND listID = '%d' AND lawInfoKey = %s AND lawInfoDeleted = '0' LIMIT 0, 1", $this->law_id, $this->list_id, 'acknowledged'));

				if($wpdb->num_rows == 0)
				{
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."law_info SET lawID = '%d', listID = '%d', lawInfoKey = %s, lawInfoValue = %s, lawInfoCreated = NOW(), userID = '%d'", $this->law_id, $this->list_id, 'acknowledged', $_SERVER['REMOTE_ADDR'], get_current_user_id()));

					$this->update_list();

					$done_text = __("The receipt was saved", 'lang_law_info');
				}

				else
				{
					$error_text = __("There already seams to exist a saved receipt", 'lang_law_info');
				}
			}

			else
			{
				$error_text = __("ID for law or list is missing", 'lang_law_info');
			}
		}

		else if(isset($_GET['btnLawAccept']) && wp_verify_nonce($_GET['_wpnonce_law_accept'], 'law_accept_'.$this->law_id.'_'.$this->list_id))
		{
			if($this->law_id > 0 && $this->list_id > 0)
			{
				$wpdb->get_var($wpdb->prepare("SELECT lawInfoID FROM ".$wpdb->prefix."law_info WHERE lawID = '%d' AND listID = '%d' AND lawInfoKey = %s AND lawInfoDeleted = '0' LIMIT 0, 1", $this->law_id, $this->list_id, 'accepted'));

				if($wpdb->num_rows == 0)
				{
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."law_info SET lawID = '%d', listID = '%d', lawInfoKey = %s, lawInfoValue = %s, lawInfoCreated = NOW(), userID = '%d'", $this->law_id, $this->list_id, 'accepted', $_SERVER['REMOTE_ADDR'], get_current_user_id()));

					$id = $this->get_key_id(array('key' => 'not_accepted'));

					if($id > 0)
					{
						$this->trash($id);
					}

					$this->update_list();

					$done_text = __("Requirements are now saved as met", 'lang_law_info');
				}

				else
				{
					$error_text = __("Requirements seams to have already been met", 'lang_law_info');
				}
			}

			else
			{
				$error_text = __("ID for law or list is missing", 'lang_law_info');
			}
		}

		else if(isset($_GET['btnLawNotAccept']) && wp_verify_nonce($_GET['_wpnonce_law_not_accept'], 'law_not_accept_'.$this->law_id.'_'.$this->list_id))
		{
			if($this->law_id > 0 && $this->list_id > 0)
			{
				$wpdb->get_var($wpdb->prepare("SELECT lawInfoID FROM ".$wpdb->prefix."law_info WHERE lawID = '%d' AND listID = '%d' AND lawInfoKey = %s AND lawInfoDeleted = '0' LIMIT 0, 1", $this->law_id, $this->list_id, 'not_accepted'));

				if($wpdb->num_rows == 0)
				{
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."law_info SET lawID = '%d', listID = '%d', lawInfoKey = %s, lawInfoValue = %s, lawInfoCreated = NOW(), userID = '%d'", $this->law_id, $this->list_id, 'not_accepted', $_SERVER['REMOTE_ADDR'], get_current_user_id()));

					$id = $this->get_key_id(array('key' => 'accepted'));

					if($id > 0)
					{
						$this->trash($id);
					}

					$this->update_list();

					$done_text = __("Requirements are now saved as NOT met", 'lang_law_info');
				}

				else
				{
					$error_text = __("Requirements seams to have already been NOT met", 'lang_law_info');
				}
			}

			else
			{
				$error_text = __("ID for law or list is missing", 'lang_law_info');
			}
		}

		else if(isset($_GET['btnLawInfoDelete']) && $this->id > 0 && wp_verify_nonce($_GET['_wpnonce_law_info_delete'], 'law_info_delete_'.$this->id))
		{
			$this->trash();

			$done_text = __("The information was deleted", 'lang_law_info');
			//mf_redirect(admin_url("admin.php?page=mf_law/create/index.php&intLawID=".$this->law_id."&intListID=".$this->list_id."&deleted"));
		}

		else if(isset($_GET['created']))
		{
			$done_text = __("The information was created", 'lang_law_info');
		}

		else if(isset($_GET['updated']))
		{
			$done_text = __("The information was updated", 'lang_law_info');
		}

		else if(isset($_GET['deleted']))
		{
			$done_text = __("The information was deleted", 'lang_law_info');
		}

		return $out;
	}

	function get_from_db()
	{
		global $wpdb;

		if(!isset($_POST['btnLawInfoCreate']))
		{
			if($this->id > 0)
			{
				if(isset($_GET['recover']))
				{
					$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."law_info SET lawInfoDeleted = '0' WHERE lawInfoID = '%d'", $this->id));

					$this->update_list();
				}

				$result = $wpdb->get_results($wpdb->prepare("SELECT lawID, listID, lawInfoKey, lawInfoValue FROM ".$wpdb->prefix."law_info WHERE lawInfoDeleted = '0' AND lawInfoID = '%d'", $this->id));

				foreach($result as $r)
				{
					$this->law_id = $r->lawID;
					$this->list_id = $r->listID;
					$this->key = $r->lawInfoKey;
					$this->value = $r->lawInfoValue;
				}

				switch($this->key)
				{
					case 'responsibility':
					//case 'responsibility_v2':
						$result = $wpdb->get_results($wpdb->prepare("SELECT lawInfoValue FROM ".$wpdb->prefix."law_info WHERE lawInfoDeleted = '0' AND lawID = '%d' AND listID = '%d' AND lawInfoKey = %s", $this->law_id, $this->list_id, $this->key));

						foreach($result as $r)
						{
							$this->arr_value[] = $r->lawInfoValue;
						}
					break;
				}
			}

			/* Get from archive */
			else if($this->law_id > 0 && $this->key != '' && $this->value == '' && count($this->arr_value) == 0)
			{
				$result = $wpdb->get_results($wpdb->prepare("SELECT listID, lawInfoKey, lawInfoValue FROM ".$wpdb->prefix."law_info WHERE lawInfoDeleted = '0' AND lawInfoArchived = '1' AND lawID = '%d' AND listID = '%d' AND lawInfoKey = %s AND lawInfoValue != '' ORDER BY lawInfoCreated DESC LIMIT 0, 1", $this->law_id, $this->list_id, $this->key));

				foreach($result as $r)
				{
					$this->key = $r->lawInfoKey;

					switch($this->key)
					{
						case 'responsibility':
						//case 'responsibility_v2':
							$this->arr_value[] = $r->lawInfoValue;
						break;

						default:
							$this->value = $r->lawInfoValue;
						break;
					}
				}

				/* Get from parent */
				if($this->value == '' && count($this->arr_value) == 0)
				{
					$result = $wpdb->get_results($wpdb->prepare("SELECT lawInfoValue FROM ".$wpdb->prefix."law_info INNER JOIN ".$wpdb->prefix."law2law ON ".$wpdb->prefix."law_info.lawID = ".$wpdb->prefix."law2law.lawID_parent WHERE lawInfoDeleted = '0' AND ".$wpdb->prefix."law2law.lawID = '%d' AND listID = '%d' AND lawInfoKey = %s AND lawInfoValue != '' ORDER BY lawInfoCreated DESC LIMIT 0, 1", $this->law_id, $this->list_id, $this->key));

					foreach($result as $r)
					{
						switch($this->key)
						{
							case 'responsibility':
							//case 'responsibility_v2':
								$this->arr_value[] = $r->lawInfoValue;
							break;

							default:
								$this->value = $r->lawInfoValue;
							break;
						}
					}
				}
			}
		}
	}

	function get_keys($data = array())
	{
		$out = array();

		if(!isset($data['show_hidden'])){	$data['show_hidden'] = false;}
		if(!isset($data['show_special'])){	$data['show_special'] = false;}
		if(!isset($data['exclude'])){		$data['exclude'] = array();}

		$out['effects_on_company'] = array(__("Effects on the company", 'lang_law_info'), 'fa fa-industry');
		$out['requirements_met'] = array(__("Requirements met", 'lang_law_info'), 'fa fa-thumbs-up');
		$out['referal_to_process'] = array(__("Referal to process", 'lang_law_info'), 'fa fa-hand-point-right');
		$out['link'] = array(__("Link", 'lang_law_info'), 'fa fa-link');
		$out['responsibility'] = array(__("Responsibility", 'lang_law_info'), 'fa fa-gavel');
		//$out['responsibility_v2'] = array(__("Responsibility", 'lang_law_info'), 'fa fa-gavel');

		//$out['title'] = array(__("Title", 'lang_law_info'), 'fa fa-graduation-cap');

		if($data['show_hidden'] == true)
		{
			$out['acknowledged'] = array(__("Receipt", 'lang_law_info'), 'fa fa-eye');
		}

		$out['evaluation'] = array(__("Evaluation", 'lang_law_info'), 'fa fa-comments');

		if($data['show_hidden'] == true)
		{
			$out['accepted'] = array(__("Check Evaluation", 'lang_law_info'), 'fa fa-check-square');
			//$out['file_id'] = array(__("File", 'lang_law_info'), 'fa fa-file-alt');
		}

		if($data['show_special'] == true)
		{
			$out['not_accepted'] = array(__("Requirements are considered compliant", 'lang_law_info'), 'fa fa-ban');
		}

		foreach($data['exclude'] as $exclude_key)
		{
			unset($out[$exclude_key]);
		}

		return $out;
	}

	function get_key_name($id)
	{
		$arr_keys = $this->get_keys(array('show_hidden' => true, 'show_special' => true));

		return $arr_keys[$id][0];
	}

	function get_key_value($data)
	{
		global $wpdb;

		if(!isset($data['law_id'])){		$data['law_id'] = $this->law_id;}
		if(!isset($data['list_id'])){		$data['list_id'] = $this->list_id;}
		if(!isset($data['output'])){		$data['output'] = "";}
		if(!isset($data['select'])){		$data['select'] = "";}
		if(!isset($data['get_archived'])){	$data['get_archived'] = false;}

		$out = "";

		if($data['select'] != '')
		{
			$query_select = $data['select']." AS lawInfoValue";
		}

		else
		{
			$query_select = (in_array($data['key'], array("acknowledged", "accepted")) ? "lawInfoValue, lawInfoCreated, userID" : "lawInfoValue");
		}

		if(!isset($data['law_id']) || !($data['law_id'] > 0))
		{
			do_log("LawID wasn't set (".var_export($data, true).")");
		}

		/*if(!isset($data['list_id']) || !($data['list_id'] > 0))
		{
			do_log("ListID wasn't set (".var_export($data, true).")");
		}*/

		$result = $wpdb->get_results($wpdb->prepare("SELECT ".$query_select." FROM ".$wpdb->prefix."law_info WHERE lawInfoDeleted = '0' AND lawID = '%d' AND listID = '%d' AND lawInfoKey = %s AND lawInfoArchived = '%d'", $data['law_id'], $data['list_id'], $data['key'], $data['get_archived']));

		if($wpdb->num_rows > 0)
		{
			switch($data['key'])
			{
				case 'acknowledged':
				case 'accepted':
					$r = $result[0];
					$strLawInfoValue = $r->lawInfoValue;
					$dteLawInfoCreated = $r->lawInfoCreated;
					$intUserID = $r->userID;

					if($data['output'] == 'date')
					{
						$out = $dteLawInfoCreated;
					}

					else
					{
						$out = format_date($dteLawInfoCreated)." ".__("by", 'lang_law_info')." ".get_user_info(array('id' => $intUserID))." (".$strLawInfoValue.")";
					}
				break;

				case 'responsibility':
					$obj_responsibility = new mf_responsibility();

					$responsibility_names = "";

					foreach($result as $r)
					{
						$responsibility_names .= ($responsibility_names != '' ? ", " : "").$obj_responsibility->get_name($r->lawInfoValue);
					}

					$out = $responsibility_names;
				break;

				/*case 'responsibility_v2':
					//echo $wpdb->last_query;

					//$responsibility_names = "";
					$arrLawInfoValue = array();

					foreach($result as $r)
					{
						$arrLawInfoValue[] = $strLawInfoValue = $r->lawInfoValue;

						//$responsibility_names .= ($responsibility_names != '' ? ", " : "").get_user_info(array('id' => $strLawInfoValue, 'type' => 'short_name'));
					}

					//$out = $responsibility_names;
					$out = $arrLawInfoValue;

					//$responsibility_names = "";
				break;*/

				default:
					foreach($result as $r)
					{
						$out = $r->lawInfoValue;
					}
				break;
			}
		}

		return $out;
	}

	function get_column_value($data)
	{
		global $wpdb;

		$out = "";

		switch($data['key'])
		{
			case 'created':
				$dteLawCreated = $wpdb->get_var($wpdb->prepare("SELECT lawCreated FROM ".$wpdb->prefix."law WHERE lawID = '%d'", $data['law_id']));

				$out = format_date($dteLawCreated);
			break;

			case 1:
				$out = $wpdb->get_var($wpdb->prepare("SELECT lawText FROM ".$wpdb->prefix."law WHERE lawID = '%d'", $data['law_id']));
			break;

			case 2:
			case 'effects_on_company':
				$out = $this->get_key_value(array('law_id' => $data['law_id'], 'list_id' => $data['list_id'], 'key' => 'effects_on_company'));
			break;

			case 'requirements_met':
				$out = $this->get_key_value(array('law_id' => $data['law_id'], 'list_id' => $data['list_id'], 'key' => 'requirements_met'));
			break;

			case 3:
			case 'evaluation':
				$out = $this->get_key_value(array('law_id' => $data['law_id'], 'list_id' => $data['list_id'], 'key' => 'evaluation'));
			break;

			case 4:
				$out = $this->get_acknowledged(array('law_id' => $data['law_id'], 'list_id' => $data['list_id'], 'law_valid' => $data['law_valid']));

				$this->has_column_acknowledged = true;
			break;

			case 5:
				$out = $this->get_accepted(array('law_id' => $data['law_id'], 'list_id' => $data['list_id'], 'law_valid' => $data['law_valid']));
			break;

			case 'replaced_by':
			case 'replaces':
				$i = 0;

				$query_join_law_column = ($data['key'] == 'replaced_by' ? "lawID" : "lawID_parent");
				$query_where_law_column = ($data['key'] == 'replaced_by' ? "lawID_parent" : "lawID");

				$result = $wpdb->get_results($wpdb->prepare("SELECT ".$wpdb->prefix."law.lawID, lawNo, lawName, lawUpdatedTo FROM ".$wpdb->prefix."law INNER JOIN ".$wpdb->prefix."law2law ON ".$wpdb->prefix."law.lawID = ".$wpdb->prefix."law2law.".$query_join_law_column." WHERE ".$wpdb->prefix."law2law.".$query_where_law_column." = '%d' GROUP BY lawID", $data['law_id']));

				foreach($result as $r)
				{
					$intLawID = $r->lawID;
					$strLawNo = $r->lawNo;
					$strLawName = $r->lawName;
					$strLawUpdatedTo = $r->lawUpdatedTo;

					$out .= (is_admin() && $i > 0 ? ", " : "")."<a href='".$data['edit_url']."&intLawID=".$intLawID."'>".$strLawNo." ".$strLawName.($strLawUpdatedTo != '' ? " ".$strLawUpdatedTo : "")."</a>"; // "#wrapper .wp-list-table tr td a" has display: block

					$i++;
				}
			break;
		}

		return $out;
	}

	function show_form($obj_law)
	{
		global $wpdb, $error_text, $done_text;

		$out = "";

		$obj_list = new mf_list();

		if($obj_law->list_id > 0)
		{
			$out .= "<h3 id='law_info_container'>".__("Customer specific information", 'lang_law_info')."</h3>
			<h4>".$obj_list->get_name(array('id' => $obj_law->list_id))."</h4>"
			.input_hidden(array('name' => 'intListID', 'value' => $obj_law->list_id));

			$this->fetch_request();
			$out .= $this->save_data();
			$this->get_from_db();

			$out .= get_notification();

			$this->has_list_permission = $obj_list->has_permission(array('rights' => array('editor', 'author')));

			$this->edit_html = '';

			if($this->has_list_permission && $this->key != '')
			{
				switch($this->key)
				{
					case 'link':
						$this->edit_html .= show_textfield(array('name' => 'strLawInfoValue', 'value' => $this->value, 'placeholder' => get_option('siteurl')));
					break;

					case 'responsibility':
						$arr_data = $obj_law->get_responsibilities_for_select();

						if(count($arr_data) > 0)
						{
							$this->edit_html .= show_form_alternatives(array('data' => $arr_data, 'name' => 'arrLawInfoValue[]', 'value' => $this->arr_value)); //, 'text' => __("Responsibility", 'lang_law_info')
						}
					break;

					/*case 'responsibility_v2':
						$obj_list->company_id = $obj_list->get_company_from_list();
						$obj_company = new mf_company($obj_list->company_id);
						$strCompanyDomain = $obj_company->get_name("companyDomain");

						if($strCompanyDomain != '')
						{
							$arr_data = get_users_for_select(array('add_choose_here' => false, 'callback' => array($obj_list, 'filter_user_domain_callback'), 'domain' => array_map('trim', explode(",", $strCompanyDomain))));

							$obj_list->get_users();
							$obj_list->list_users(array('type' => 'responsibility'));

							foreach($obj_list->responsibilities as $responsibility)
							{
								unset($arr_data[$responsibility]);
							}

							if(count($arr_data) > 0)
							{
								$this->edit_html .= show_form_alternatives(array('data' => $arr_data, 'name' => 'arrLawInfoValue[]', 'value' => $this->arr_value));
							}
						}
					break;*/

					default:
						$this->edit_html .= show_wp_editor(array(
							'name' => 'strLawInfoValue',
							'value' => stripslashes($this->value),
							'class' => "hide_media_button hide_tabs",
							'mini_toolbar' => true,
							'editor_height' => 200,
							//'statusbar' => false,
						));
					break;
				}

				$law_edit_base_url = $obj_law->get_base_url('edit');
				$law_edit_base_url .= "&intLawID=".$obj_law->id."&intListID=".$obj_law->list_id;

				$this->edit_html .= show_submit(array('name' => 'btnLawInfoCreate', 'text' => ($this->is_updating ? __("Update", 'lang_law_info') : __("Add", 'lang_law_info'))))
				."&nbsp;<a href='".$law_edit_base_url."' class='button'>".__("Cancel", 'lang_law_info')."</a>"
				.input_hidden(array('name' => 'intLawInfoID', 'value' => $this->id))
				.input_hidden(array('name' => 'strLawInfoKey', 'value' => $this->key))
				.wp_nonce_field('law_info_create_'.$this->id, '_wpnonce_info', true, false);
			}

			$out .= $this->get_info_table($obj_law, $obj_list);
		}

		else
		{
			$obj_law->lists = IS_EDITOR ? array_merge($obj_law->lists, $obj_law->lists_published) : $obj_law->lists_published;

			if(count($obj_law->lists) > 0)
			{
				$arr_data = $obj_list->get_for_select(array('where' => "listID IN ('".implode("','", $obj_law->lists)."')"));

				if(is_admin())
				{
					$out .= "<div class='postbox'>
						<h3 class='hndle'><span>".__("Choose list to view info", 'lang_law_info')."</span></h3>
						<div class='inside'>";
				}

				else
				{
					$out .= "<div class='meta_box'>
						<h2>".__("Choose list to view info", 'lang_law_info')."</h2>
						<div>";
				}

						$out .= "<div class='flex_flow tight'>"
							.show_select(array('data' => $arr_data, 'name' => 'intListID', 'value' => $obj_law->list_id))
							."<div class='form_button'>"
								.show_submit(array('name' => 'btnListChange', 'text' => __("Show", 'lang_law_info')))
							."</div>"
						."</div>
					</div>
				</div>";
			}
		}

		return $out;
	}

	function has_responsibilities($list_id)
	{
		global $obj_list;

		if(!isset($obj_list))
		{
			$obj_list = new mf_list();
		}

		$obj_list->id = $list_id;
		$obj_list->company_id = $obj_list->get_company_from_list();
		$obj_company = new mf_company($obj_list->company_id);
		$strCompanyDomain = $obj_company->get_name("companyDomain");

		$arr_data = get_users_for_select(array('add_choose_here' => false, 'callback' => array($obj_list, 'filter_user_domain_callback'), 'domain' => array_map('trim', explode(",", $strCompanyDomain))));

		$obj_list->get_users();
		$obj_list->list_users(array('type' => 'responsibility'));

		/*foreach($obj_list->responsibilities as $responsibility)
		{
			unset($arr_data[$responsibility]);
		}*/

		return (count($arr_data) > 0);
	}

	function get_info_table($obj_law, $obj_list)
	{
		global $wpdb;

		$out = "";

		$arr_keys = $this->get_keys(array('show_hidden' => true));

		$tbl_group = new mf_law_info_table();

		$tbl_group->select_data(array(
			'select' => "lawInfoID, lawInfoKey, lawInfoValue, lawInfoCreated, lawInfoUpdated, ".(IS_EDITOR ? "" : $wpdb->prefix."law_info.")."userID",
			'where' => "lawInfoDeleted = '0' AND lawInfoArchived = '0' AND lawID = '".$obj_law->id."' AND listID = '".$obj_law->list_id."'",
			//'debug' => true,
		));

		$out .= "<table class='wp-list-table widefat striped'>
			<tbody>";

				$has_evaluation = false;

				$law_edit_base_url = $obj_law->get_base_url('edit');
				$law_edit_base_url .= "&intLawID=".$obj_law->id."&intListID=".$obj_law->list_id;

				foreach($arr_keys as $strLawInfoKey => $arr_value)
				{
					$strLawInfoKeyName = $arr_value[0];
					$strLawInfoKeyIcon = $arr_value[1];

					$intLawInfoID = $intUserID = 0;
					$strLawInfoValue = $dteLawInfoCreated = $dteLawInfoUpdated = $row_actions = "";
					$arrLawInfoValue = array();

					foreach($tbl_group->data as $r)
					{
						if($r['lawInfoKey'] == $strLawInfoKey)
						{
							$intLawInfoID = $r['lawInfoID'];
							$dteLawInfoCreated = $r['lawInfoCreated'];
							$dteLawInfoUpdated = $r['lawInfoUpdated'];
							$intUserID = $r['userID'];

							if($r['lawInfoKey'] == 'responsibility') // || $r['lawInfoKey'] == 'responsibility_v2'
							{
								$arrLawInfoValue[] = $strLawInfoValue = $r['lawInfoValue'];
							}

							else
							{
								$strLawInfoValue = stripslashes($r['lawInfoValue']);

								break;
							}
						}
					}

					if($strLawInfoValue != '')
					{
						$post_edit_url = $law_edit_base_url."&intLawInfoID=".$intLawInfoID;

						$row_actions = "<span class='edit'><a href='".$post_edit_url."#law_info_edit'>Redigera</a></span>";

						if(is_admin())
						{
							$row_actions .= " | ";
						}

						$row_actions .= "<span class='delete'><a href='".wp_nonce_url($post_edit_url."&btnLawInfoDelete#law_info_container", 'law_info_delete_'.$intLawInfoID, '_wpnonce_law_info_delete')."' rel='confirm'>Radera</a></span>";

						switch($strLawInfoKey)
						{
							case 'link':
								$strLawInfoValue = "<a href='".validate_url($strLawInfoValue)."'>".$strLawInfoValue."</a>";
							break;

							case 'acknowledged':
								$row_actions = $strLawInfoValue;

								$strLawInfoValue = "<i class='fa fa-check fa-lg green'></i> ".get_user_info(array('id' => $intUserID));

								if(IS_ADMIN)
								{
									$strLawInfoValue .= " | <a href='".wp_nonce_url($post_edit_url."&btnLawInfoDelete#law_info_container", 'law_info_delete_'.$intLawInfoID, '_wpnonce_law_info_delete')."' rel='confirm'>".__("Delete", 'lang_law_info')."</a>";
								}
							break;

							case 'evaluation':
								$has_evaluation = true;

								$strLawInfoValue = apply_filters('the_content', $strLawInfoValue);

								// This will only display the same version. It should filter out the current version if Old Verions should be displayed here
								/*if($this->has_list_permission)
								{
									$strLawInfoValue .= $this->get_parent_info($obj_law, $strLawInfoKey);
								}*/
							break;

							case 'accepted':
								if($has_evaluation == true)
								{
									$not_accepted = $this->get_key_value(array('law_id' => $obj_law->id, 'list_id' => $obj_law->list_id, 'key' => 'not_accepted'));

									$strListName = $obj_list->get_name(array('id' => $obj_law->list_id));

									$row_actions = $strLawInfoValue;

									$strLawInfoValue = "<p><i class='fa fa-check fa-lg green'></i> ".sprintf(__("%s has marked this as met", 'lang_law_info'), get_user_info(array('id' => $intUserID)))."</p>";

									if($not_accepted == '')
									{
										$strLawInfoValue .= "<a href='".wp_nonce_url($law_edit_base_url."&btnLawNotAccept", 'law_not_accept_'.$obj_law->id.'_'.$obj_law->list_id, '_wpnonce_law_not_accept')."' class='button button-link-delete' rel='confirm'>".sprintf(__("Undo! Requirements are no longer met for %s", 'lang_law_info'), $strListName)."</a>";
									}
								}
							break;

							case 'responsibility':
								$obj_responsibility = new mf_responsibility();

								$responsibility_names = "";

								foreach($arrLawInfoValue as $responsibility_id)
								{
									$responsibility_names .= ($responsibility_names != '' ? ", " : "").$obj_responsibility->get_name($responsibility_id);
								}

								$strLawInfoValue = $responsibility_names;
							break;

							/*case 'responsibility_v2':
								$responsibility_names = "";

								foreach($arrLawInfoValue as $responsibility_id)
								{
									$responsibility_names .= ($responsibility_names != '' ? ", " : "").get_user_info(array('id' => $responsibility_id, 'type' => 'short_name'));
								}

								$strLawInfoValue = $responsibility_names;
							break;*/

							default:
								if($dteLawInfoCreated > DEFAULT_DATE || $dteLawInfoUpdated > DEFAULT_DATE)
								{
									// Do nothing
								}

								else
								{
									$strLawInfoValue = "<span class='grey italic'>".$strLawInfoValue."</span>";
								}

								$strLawInfoValue = apply_filters('the_content', $strLawInfoValue);
							break;
						}
					}

					else
					{
						if($this->has_list_permission)
						{
							$post_edit_url = $law_edit_base_url."&strLawInfoKey=".$strLawInfoKey."#law_info_edit";

							switch($strLawInfoKey)
							{
								case 'acknowledged':
									if(1 == 1) // || $obj_list->has_permission(array('allow_editors' => false, 'rights' => array('responsibility'))) || in_array(get_current_user_id(), $this->get_key_value(array('law_id' => $obj_law->id, 'list_id' => $obj_law->list_id, 'key' => 'responsibility_v2')))
									{
										$strLawInfoValue = "<a href='".wp_nonce_url($law_edit_base_url."&btnLawAcknowledge", 'law_acknowledge_'.$obj_law->id.'_'.$obj_law->list_id, '_wpnonce_law_acknowledge')."' class='button' rel='confirm'>".sprintf(__("Acknowledge for %s", 'lang_law_info'), $obj_list->get_name(array('id' => $obj_law->list_id)))."</a>";
									}

									else
									{
										$strLawInfoValue = "<em>".__("You do not have the rights to acknowledge this law", 'lang_law_info')."</em>";
									}

									$row_actions = __("No receipt yet", 'lang_law_info');
								break;

								case 'accepted':
									if($has_evaluation == true)
									{
										$accepted = $this->get_key_id(array('law_id' => $obj_law->id, 'list_id' => $obj_law->list_id, 'key' => 'accepted'));
										$not_accepted = $this->get_key_value(array('law_id' => $obj_law->id, 'list_id' => $obj_law->list_id, 'key' => 'not_accepted'));

										$strListName = $obj_list->get_name(array('id' => $obj_law->list_id));

										$strLawInfoValue = "";

										if($accepted == '')
										{
											$result = $wpdb->get_results($wpdb->prepare("SELECT userID, lawInfoCreated FROM ".$wpdb->prefix."law_info WHERE lawID = '%d' AND listID = '%d' AND lawInfoKey = 'not_accepted' AND lawInfoDeleted = '0' LIMIT 0, 1", $obj_law->id, $obj_law->list_id));

											foreach($result as $r)
											{
												$intUserID = $r->userID;
												$dteLawInfoCreated = $r->lawInfoCreated;

												$strLawInfoValue .= "<p><i class='fa fa-times fa-lg red'></i> ".sprintf(__("%s has marked this as NOT met", 'lang_law_info'), get_user_info(array('id' => $intUserID)))."</p>";

												$row_actions = $not_accepted;
											}

											$strLawInfoValue .= "<a href='".wp_nonce_url($law_edit_base_url."&btnLawAccept", 'law_accept_'.$obj_law->id.'_'.$obj_law->list_id, '_wpnonce_law_accept')."' class='button button-primary' rel='confirm'>".sprintf(__("The requirements are met for %s", 'lang_law_info'), $strListName)."</a>";
										}

										if($not_accepted == '')
										{
											$strLawInfoValue .= "<a href='".wp_nonce_url($law_edit_base_url."&btnLawNotAccept", 'law_not_accept_'.$obj_law->id.'_'.$obj_law->list_id, '_wpnonce_law_not_accept')."' class='button button-link-delete' rel='confirm'>".sprintf(__("The requirements are NOT met for %s", 'lang_law_info'), $strListName)."</a>";
										}
									}

									else
									{
										$strLawInfoKey = '';
									}
								break;

								case 'responsibility':
									$tbl_group_resp = new mf_responsibility_table();

									$tbl_group_resp->select_data(array(
										'select' => "responsibilityID",
										'where' => "listID = '".$obj_law->list_id."'",
										'amount' => 1,
									));

									if(count($tbl_group_resp->data) > 0)
									{
										$strLawInfoValue = "<a href='".$post_edit_url."'><i class='fa fa-plus-circle fa-lg'></i></a>";
									}

									else
									{
										$strLawInfoKey = '';
									}
								break;

								/*case 'responsibility_v2':
									if($this->has_responsibilities($obj_law->list_id))
									{
										$strLawInfoValue = "<a href='".$post_edit_url."'><i class='fa fa-plus-circle fa-lg'></i></a>";
									}

									else
									{
										$strLawInfoKey = '';
									}
								break;*/

								default:
									$strLawInfoValue = "<a href='".$post_edit_url."'><i class='fa fa-plus-circle fa-lg'></i></a>";

									$strLawInfoValue .= $this->get_parent_info($obj_law, $strLawInfoKey);
								break;
							}
						}

						else
						{
							switch($strLawInfoKey)
							{
								case 'acknowledged':
									$strLawInfoValue = __("No receipt yet", 'lang_law_info');
								break;

								case 'accepted':
									$strLawInfoValue = __("Requirements are not yet met", 'lang_law_info');

									$not_accepted = $this->get_key_value(array('law_id' => $obj_law->id, 'list_id' => $obj_law->list_id, 'key' => 'not_accepted'));

									$row_actions = $not_accepted != "" ? __("Reason", 'lang_law_info').": ".$not_accepted : "";
								break;

								default:
									$strLawInfoValue = __("There is no information at this point", 'lang_law_info');
								break;
							}

							$strLawInfoValue = "<em>".$strLawInfoValue."</em>";
						}
					}

					if($strLawInfoKey != '')
					{
						$out .= "<tr".($this->key == $strLawInfoKey ? " class='active'" : "")." rel='".$strLawInfoKey."'>
							<td class='strong'>
								<i class='".$strLawInfoKeyIcon." fa-lg'></i>&nbsp; "
								.$strLawInfoKeyName
							."</td>
							<td>";

								if($this->key == $strLawInfoKey && $this->edit_html != '')
								{
									$out .= "<div id='law_info_edit'".(is_admin() ? "" : " class='form_button'").">".$this->edit_html."</div>";
								}

								else
								{
									if($dteLawInfoUpdated > DEFAULT_DATE)
									{
										//$row_actions .= ($row_actions != '' ? " | " : "").format_date($dteLawInfoUpdated);
										$row_actions .= ($row_actions != '' ? " | " : "").sprintf(__("Updated %s by %s", 'lang_law_info'), format_date($dteLawInfoUpdated), get_user_info(array('id' => $intUserID)));
									}

									else if($dteLawInfoCreated > DEFAULT_DATE)
									{
										//$row_actions .= ($row_actions != '' ? " | " : "").format_date($dteLawInfoCreated);
										$row_actions .= ($row_actions != '' ? " | " : "").sprintf(__("Created %s by %s", 'lang_law_info'), format_date($dteLawInfoCreated), get_user_info(array('id' => $intUserID)));
									}

									$out .= $strLawInfoValue;

									if($row_actions != '')
									{
										$out .= "<div class='row-actions'>"
											.$row_actions
										."</div>";
									}
								}

							$out .= "</td>
						</tr>";
					}
				}

			$out .= "</tbody>
		</table>";

		return $out;
	}

	function get_parent_info($obj_law, $strLawInfoKey)
	{
		global $wpdb;

		if(!isset($obj_law->id_parents))
		{
			$obj_law->get_parents();
		}

		$out = "";

		if(IS_EDITOR || !in_array($strLawInfoKey, array('requirements_met')))
		{
			$result = $wpdb->get_results($wpdb->prepare("SELECT lawID, lawNo, lawName, lawInfoValue, lawCreated FROM ".$wpdb->prefix."law INNER JOIN ".$wpdb->prefix."law_info USING (lawID) WHERE listID = '%d' AND (lawID = '%d' AND lawInfoArchived = '1' OR lawID IN ('".implode("', '", $obj_law->id_parents)."')) AND lawInfoKey = %s AND lawInfoValue != '' ORDER BY lawCreated DESC", $obj_law->list_id, $obj_law->id, $strLawInfoKey));

			if($wpdb->num_rows > 0)
			{
				$out .= "<div class='law_info_old_versions'>"
					.get_toggler_container(array('type' => 'start', 'text' => __("Old versions", 'lang_law_info'), 'rel' => $strLawInfoKey))
						."<ul>";

							$strLawChanges_old = "";

							foreach($result as $r)
							{
								$intLawID = $r->lawID;
								$strLawNo = $r->lawNo;
								$strLawName = $r->lawName;
								$strLawChanges = $r->lawInfoValue;
								$dteLawCreated = $r->lawCreated;

								if($strLawChanges != $strLawChanges_old)
								{
									$post_edit_url = IS_ADMIN ? admin_url("admin.php?page=mf_law/create/index.php&intListID=".$obj_law->list_id."&intLawID=".$intLawID) : "#";

									$out .= "<li>"
										.apply_filters('the_content', $strLawChanges)
										."<span class='grey'><a href='".$post_edit_url."'>".$strLawNo." ".$strLawName."</a> | ".format_date($dteLawCreated)."</span>
									</li>";

									$strLawChanges_old = $strLawChanges;
								}
							}

						$out .= "</ul>"
					.get_toggler_container(array('type' => 'end'))
				."</div>";
			}
		}

		return $out;
	}

	function get_key_id($data = array())
	{
		global $wpdb;

		if(!isset($data['law_id'])){		$data['law_id'] = $this->law_id;}
		if(!isset($data['list_id'])){		$data['list_id'] = $this->list_id;}
		if(!isset($data['key'])){			$data['key'] = $this->key;}
		if(!isset($data['get_archived'])){	$data['get_archived'] = false;}

		$query_where = "";

		return $wpdb->get_var($wpdb->prepare("SELECT lawInfoID FROM ".$wpdb->prefix."law_info WHERE lawInfoDeleted = '0' AND lawID = '%d' AND listID = '%d' AND lawInfoKey = %s AND lawInfoArchived = '%d'".$query_where, $data['law_id'], $data['list_id'], $data['key'], $data['get_archived']));
	}

	function archive_key($data)
	{
		global $wpdb;

		if(!isset($data['do_archive'])){	$data['do_archive'] = true;}
		if(!isset($data['key'])){			$data['key'] = 'evaluation';}

		$this->law_id = $data['law_id'];
		$this->list_id = $data['list_id'];

		$intLawInfoID = $this->get_key_id(array('key' => $data['key']));

		if($intLawInfoID > 0)
		{
			if($data['do_archive'] == true)
			{
				$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."law_info SET lawInfoArchived = '1', lawInfoDeletedDate = NOW(), lawInfoDeletedID = '%d' WHERE lawInfoID = '%d' AND lawID = '%d' AND listID = '%d' AND lawInfoDeleted = '0'", get_current_user_id(), $intLawInfoID, $this->law_id, $this->list_id));
			}

			else
			{
				return $intLawInfoID;
			}
		}
	}

	function get_existing_keys($data)
	{
		global $wpdb, $tbl_group_resp;

		$this->law_id = $data['law_id'];
		$this->list_id = $data['list_id'];

		$out = "";
		$arr_exclude = array();

		if($this->list_id > 0)
		{
			if(!isset($tbl_group_resp))
			{
				$tbl_group_resp = new mf_responsibility_table();
			}

			$tbl_group_resp->select_data(array(
				'select' => "responsibilityID",
				'where' => "listID = '".$this->list_id."'",
				'amount' => 1,
			));

			if(count($tbl_group_resp->data) == 0)
			{
				$arr_exclude[] = 'responsibility';
			}

			/*if(!$this->has_responsibilities($this->list_id))
			{
				$arr_exclude[] = 'responsibility_v2';
			}*/
		}

		$arr_keys = $this->get_keys(array('show_hidden' => true, 'exclude' => $arr_exclude));

		foreach($arr_keys as $key => $arr_value)
		{
			$icon = $arr_value[1];
			$class = ($this->get_key_id(array('key' => $key)) > 0 ? "green" : "red");
			$title = $arr_value[0];

			if($key == 'evaluation' && $class == "green")
			{
				$dteLawInfoCreated = $this->get_key_value(array('key' => $key, 'select' => "lawInfoCreated"));

				if($dteLawInfoCreated > DEFAULT_DATE)
				{
					$title .= " (".format_date($dteLawInfoCreated).")";
				}
			}

			$out .= ($out != '' ? " " : "")."<i class='".$icon." ".$class."' title='".$title."'></i>";
		}

		return $out;
	}

	function create()
	{
		global $wpdb;

		switch($this->key)
		{
			case 'responsibility':
			//case 'responsibility_v2':
				foreach($this->arr_value as $responsibility_id)
				{
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."law_info SET lawID = '%d', listID = '%d', lawInfoKey = %s, lawInfoValue = %s, lawInfoCreated = NOW(), userID = '%d'", $this->law_id, $this->list_id, $this->key, $responsibility_id, get_current_user_id()));
				}
			break;

			default:
				if($this->value != '')
				{
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."law_info SET lawID = '%d', listID = '%d', lawInfoKey = %s, lawInfoValue = %s, lawInfoCreated = NOW(), userID = '%d'", $this->law_id, $this->list_id, $this->key, $this->value, get_current_user_id()));
				}
			break;
		}

		$this->id = $wpdb->insert_id;

		if($this->key == 'not_accepted')
		{
			$id = $this->get_key_id(array('key' => 'accepted'));

			if($id > 0)
			{
				$this->trash($id);
			}
		}

		$this->update_list();

		return $this->id;
	}

	function update()
	{
		global $wpdb;

		switch($this->key)
		{
			case 'responsibility':
			//case 'responsibility_v2':
				$this->trash_key();

				foreach($this->arr_value as $responsibility_id)
				{
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."law_info SET lawID = '%d', listID = '%d', lawInfoKey = %s, lawInfoValue = %s, lawInfoCreated = NOW(), userID = '%d'", $this->law_id, $this->list_id, $this->key, $responsibility_id, get_current_user_id()));
				}
			break;

			default:
				if($this->value != '')
				{
					$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."law_info SET lawInfoKey = %s, lawInfoValue = %s, lawInfoUpdated = NOW() WHERE lawInfoID = '%d'", $this->key, $this->value, $this->id));
				}

				else
				{
					$this->trash($this->id);
				}
			break;
		}

		$this->update_list();
	}

	function copy_law_info($data)
	{
		global $wpdb;

		if(!isset($data['law_id_from'])){	$data['law_id_from'] = 0;}
		if(!isset($data['law_id_to'])){		$data['law_id_to'] = 0;}

		if($data['law_id_from'] > 0)
		{
			$arr_exclude = array('requirements_met', 'acknowledged', 'accepted', 'not_accepted');

			$copy_fields = ", listID, lawInfoKey, lawInfoValue";

			$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."law_info (lawID".$copy_fields.") (SELECT '%d'".$copy_fields." FROM ".$wpdb->prefix."law_info WHERE lawID = '%d' AND lawInfoDeleted = '0' AND lawInfoArchived = '0' AND lawInfoKey NOT IN ('".implode("','", $arr_exclude)."'))", $data['law_id_to'], $data['law_id_from'])); //, lawInfoCreated, userID //, NOW(), '".get_current_user_id()."'
		}
	}

	function trash($id = 0)
	{
		global $wpdb;

		if(!($id > 0))
		{
			$id = $this->id;
		}

		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."law_info SET lawInfoDeleted = '1', lawInfoDeletedDate = NOW(), lawInfoDeletedID = '%d' WHERE lawInfoID = '%d'", get_current_user_id(), $id));

		$this->update_list();
	}

	function trash_key($data = array())
	{
		global $wpdb;

		if(!isset($data['law_id'])){	$data['law_id'] = '';}
		if(!isset($data['list_id'])){	$data['list_id'] = '';}
		if(!isset($data['key'])){		$data['key'] = '';}

		if($data['law_id'] != '')
		{
			$this->law_id = $data['law_id'];
		}

		if($data['list_id'] != '')
		{
			$this->list_id = $data['list_id'];
		}

		if($data['key'] != '')
		{
			$this->key = $data['key'];
		}

		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."law_info SET lawInfoDeleted = '1', lawInfoDeletedDate = NOW(), lawInfoDeletedID = '%d' WHERE lawID = '%d' AND listID = '%d' AND lawInfoKey = %s AND lawInfoArchived = '0'", get_current_user_id(), $this->law_id, $this->list_id, $this->key));

		$this->update_list();
	}

	/*function delete($id = 0)
	{
		global $wpdb;

		if(!($id > 0))
		{
			$id = $this->id;
		}

		$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."law_info WHERE lawInfoID = '%d'", $id));

		$this->update_list();
	}*/

	function update_list()
	{
		global $wpdb;

		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."list SET listUpdated = NOW() WHERE listID = '%d'", $this->list_id));
	}

	function is_new_version($data)
	{
		global $wpdb;

		$wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."law2law WHERE lawID = '%d' AND lawID_parent > 0", $data['law_id']));

		return ($wpdb->num_rows > 0);
	}

	function get_acknowledged($data)
	{
		global $wpdb, $obj_list;

		if(!isset($obj_list))
		{
			$obj_list = new mf_list();
		}

		if(!isset($data['list_id'])){	$data['list_id'] = 0;}

		$out = $query_where = "";
		$acknowledged = true;

		$this->law_id = $data['law_id'];

		if($data['list_id'] > 0)
		{
			$query_where .= " AND listID = '".$data['list_id']."'";
		}

		else
		{
			$arr_lists = $obj_list->get_for_select(array('add_choose_here' => false));

			$query_where .= " AND (";

				$i = 0;

				foreach($arr_lists as $key => $value)
				{
					if($i > 0)
					{
						$query_where .= " OR ";
					}

					$query_where .= "listID = '".$key."'";

					$i++;
				}

			$query_where .= ")";
		}

		$result = $wpdb->get_results($wpdb->prepare("SELECT listID FROM ".$wpdb->prefix."law2list INNER JOIN ".$wpdb->prefix."list2user USING (listID) WHERE lawID = '%d'".$query_where." GROUP BY listID", $this->law_id));

		foreach($result as $r)
		{
			$obj_list->id = $r->listID;

			$strListName = $obj_list->get_name();

			//$this->law_id = $this->law_id;
			$this->list_id = $obj_list->id;

			$strListAcknowledged = $this->get_key_value(array('key' => 'acknowledged', 'line4debug' => __LINE__));

			if($strListAcknowledged != '')
			{
				$out .= "<div title='".$strListAcknowledged."'><i class='fa fa-check fa-lg green'></i> ".$strListName."</div>";

				/*$out .= "<div class='row-actions'>" //grey italic
					.$strListAcknowledged
				."</div>";*/
			}

			else
			{
				$setting_law_info_red_on_not_acknowledged = get_option_or_default('setting_law_info_red_on_not_acknowledged', array());

				$has_permission = false;

				if(count($setting_law_info_red_on_not_acknowledged) > 0)
				{
					$has_permission = $obj_list->has_permission(array('rights' => $setting_law_info_red_on_not_acknowledged));
				}

				if($has_permission)
				{
					$acknowledged = false;
				}
			}
		}

		if($acknowledged == false)
		{
			$rel = '';

			if(!IS_EDITOR && $this->is_new_version(array('law_id' => $this->law_id)))
			{
				$rel = "red";
			}

			/*else if($data['law_valid'] > DEFAULT_DATE)
			{
				if(!($data['law_valid'] <= date("Y-m-d", strtotime("+1 month"))))
				{
					$rel = "red";
				}

				else if($data['law_valid'] <= date("Y-m-d", strtotime("+2 month")))
				{
					$rel = "yellow";
				}
			}*/

			if($rel != '')
			{
				$out .= "<i class='set_tr_color' rel='".$rel."' title='".$data['law_valid']."'></i>";
			}
		}

		return $out;
	}

	function get_accepted($data)
	{
		global $wpdb, $obj_list;

		if(!isset($data['list_id'])){	$data['list_id'] = 0;}

		$out = $query_where = "";

		$this->law_id = $data['law_id'];

		if($data['list_id'] > 0)
		{
			$query_where .= " AND listID = '".$data['list_id']."'";
		}

		else
		{
			if(!isset($obj_list))
			{
				$obj_list = new mf_list();
			}

			$arr_lists = $obj_list->get_for_select(array('add_choose_here' => false));

			$query_where .= " AND (";

				$i = 0;

				foreach($arr_lists as $key => $value)
				{
					if($i > 0)
					{
						$query_where .= " OR ";
					}

					$query_where .= "listID = '".$key."'";

					$i++;
				}

			$query_where .= ")";
		}

		$result = $wpdb->get_results($wpdb->prepare("SELECT listID, listName FROM ".$wpdb->prefix."list INNER JOIN ".$wpdb->prefix."law2list USING (listID) INNER JOIN ".$wpdb->prefix."list2user USING (listID) WHERE lawID = '%d'".$query_where." GROUP BY listID", $this->law_id));

		foreach($result as $r)
		{
			$this->list_id = $r->listID;

			$strListName = $r->listName;

			$accepted = $this->get_key_value(array('key' => 'accepted'));

			if($accepted != '')
			{
				$out .= "<div title='".$accepted."'><i class='fa fa-check fa-lg green'></i> ".$strListName."</div>";

				/*."<div class='row-actions'>"
					.$accepted
				."</div>";*/
			}

			else
			{
				$not_accepted = $this->get_key_value(array('key' => 'not_accepted'));

				if($not_accepted != '')
				{
					$out .= "<div title='".$not_accepted."'><i class='fa fa-times fa-lg red'></i> ".$strListName."</div>";

					/*."<div class='row-actions allow_wrap'>"
						.$not_accepted
					."</div>";*/
				}
			}
		}

		return $out;
	}
}

class mf_law_info_table extends mf_list_table
{
	function set_default()
	{
		global $wpdb;

		$this->arr_settings['query_from'] = $wpdb->prefix."law_info";
		$this->arr_settings['query_select_id'] = "lawInfoID";
		$this->arr_settings['query_all_id'] = "0";
		$this->arr_settings['query_trash_id'] = "1";
		$this->orderby_default = "lawID ASC, listID ASC, lawInfoKey, lawInfoCreated";
	}

	function init_fetch()
	{
		global $wpdb, $obj_law_info;

		$intLawID = check_var('intLawID');

		if($intLawID > 0)
		{
			$this->query_where .= ($this->query_where != '' ? " AND " : "")."lawID = '".$intLawID."'";
		}

		if($this->search != '')
		{
			$this->query_where .= ($this->query_where != '' ? " AND " : "")."(lawInfoValue LIKE '".$this->filter_search_before_like($this->search)."' OR lawInfoKey LIKE '".$this->filter_search_before_like($this->search)."' OR SOUNDEX(lawInfoValue) = SOUNDEX('".$this->search."'))";
		}

		if(!IS_EDITOR)
		{
			$this->query_join .= " INNER JOIN ".$wpdb->prefix."list2user USING (listID)";
			$this->query_where .= ($this->query_where != '' ? " AND " : "").$wpdb->prefix."list2user.userID = '".get_current_user_id()."'"; // AND lawInfoKey != 'acknowledged'
		}

		$this->set_views(array(
			'db_field' => 'lawInfoDeleted',
			'types' => array(
				'0' => __("All", 'lang_law_info'),
				'1' => __("Trash", 'lang_law_info')
			),
		));

		$arr_columns = array(
			//'cb' => '<input type="checkbox">',
			'listID' => __("List", 'lang_law_info'),
			'lawInfoKey' => __("Type", 'lang_law_info'),
			'lawInfoValue' => __("Text", 'lang_law_info'),
		);

		$this->set_columns($arr_columns);

		$this->set_sortable_columns(array(
			'lawInfoKey',
			'lawInfoValue',
		));
	}

	function column_default($item, $column_name)
	{
		global $obj_law_info;

		$out = "";

		$intLawInfoID = $item['lawInfoID'];
		$strLawInfoKey = $item['lawInfoKey'];

		$obj_law_info = new mf_law_info($intLawInfoID);

		switch($column_name)
		{
			case 'listID':
				$intListID = $item['listID'];

				$obj_list = new mf_list(array('id' => $intListID));
				$out .= $obj_list->get_name();
			break;

			case 'lawInfoKey':
				$strLawInfoKeyName = $obj_law_info->get_key_name($strLawInfoKey);
				$intLawInfoDeleted = $item['lawInfoDeleted'];

				$post_edit_url = admin_url("admin.php?page=mf_law_info/create/index.php&intLawInfoID=".$intLawInfoID);

				$actions = array();

				if($intLawInfoDeleted == 0)
				{
					if(IS_ADMIN)
					{
						$actions['edit'] = "<a href='".$post_edit_url."'>".__("Edit", 'lang_law_info')."</a>";

						$actions['delete'] = "<a href='".wp_nonce_url(admin_url("admin.php?page=mf_law/create/index.php&btnLawInfoDelete&intLawInfoID=".$intLawInfoID), 'law_info_delete_'.$intLawInfoID, '_wpnonce_law_info_delete')."'>".__("Delete", 'lang_law_info')."</a>";
					}
				}

				else
				{
					$actions['recover'] = "<a href='".$post_edit_url."&recover'>".__("Recover", 'lang_law_info')."</a>";
				}

				$out .= "<a href='".$post_edit_url."'>"
					.$strLawInfoKeyName
				."</a>"
				.$this->row_actions($actions);
			break;

			case 'lawInfoValue':
				$item_value = $item['lawInfoValue'];

				switch($strLawInfoKey)
				{
					case 'file_id':
						$out .= $item_value;
					break;

					case 'link':
						$item_value = trim($item_value);

						$out .= "<a href='".validate_url($item_value)."'>".$item_value."</a>";
					break;

					case 'acknowledged':
					case 'accepted':
						$dteLawInfoCreated = $item['lawInfoCreated'];
						$intUserID = $item['userID'];

						$out .= $dteLawInfoCreated." ".__("by", 'lang_law_info')." ".get_user_info(array('id' => $intUserID))." (".$item_value.")";
					break;

					default:
						$out .= apply_filters('the_content', $item_value);
					break;
				}
			break;

			default:
				if(isset($item[$column_name]))
				{
					$out .= $item[$column_name];
				}
			break;
		}

		return $out;
	}
}