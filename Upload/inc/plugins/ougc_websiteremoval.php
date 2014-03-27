<?php

/***************************************************************************
 *
 *	OUGC Website Removal plugin (/inc/plugins/ougc_websiteremoval.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2014 Omar Gonzalez
 *
 *	Website: http://omarg.me
 *
 *	Removes website links for users in profile and posts.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

// Run/Add Hooks
if(defined('IN_ADMINCP'))
{
	$plugins->add_hook('admin_config_settings_start', 'ougc_websiteremoval_lang_load');
	$plugins->add_hook('admin_config_settings_change', 'ougc_websiteremoval_settings_change');
}
else
{
	define('OUGC_WEBSITEREMOVAL_GROUPS', '');

	foreach(array('member_profile_end', 'postbit_prev', 'postbit_pm', 'postbit_announcement', 'postbit') as $hook)
	{
		$plugins->add_hook($hook, 'ougc_websiteremoval');
	}
}

// Plugin API
function ougc_websiteremoval_info()
{
	global $lang;
	ougc_websiteremoval_lang_load();

	return array(
		'name'			=> 'OUGC Website Removal',
		'description'	=> $lang->ougc_websiteremoval_desc,
		'website'		=> 'http://omarg.me',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://omarg.me',
		'version'		=> '0.9',
		'versioncode'	=> 0900,
		'compatibility'	=> '16*'
	);
}

// _activate() routine
function ougc_websiteremoval_activate()
{
	global $cache;
	ougc_websiteremoval_pl_check();

	// Add settings group
	$PL->settings('ougc_websiteremoval', $lang->setting_group_ougc_websiteremoval, $lang->setting_group_ougc_websiteremoval_desc, array(
		'groups'	=> array(
		   'title'			=> $lang->setting_ougc_websiteremoval_groups,
		   'description'	=> $lang->setting_ougc_websiteremoval_groups_desc,
		   'optionscode'	=> 'text',
			'value'			=>	'3,4,6',
		)
	));

	// Insert/update version into cache
	$plugins = $cache->read('ougc_plugins');
	if(!$plugins)
	{
		$plugins = array();
	}

	$info = ougc_websiteremoval_info();

	if(!isset($plugins['websiteremoval']))
	{
		$plugins['websiteremoval'] = $info['versioncode'];
	}

	/*~*~* RUN UPDATES START *~*~*/

	/*~*~* RUN UPDATES END *~*~*/

	$plugins['websiteremoval'] = $info['versioncode'];
	$cache->update('ougc_plugins', $plugins);
}

// _is_installed() routine
function ougc_websiteremoval_is_installed()
{
	global $cache;

	$plugins = (array)$cache->read('ougc_plugins');

	return !empty($plugins['websiteremoval']);
}

// _uninstall() routine
function ougc_websiteremoval_uninstall()
{
	global $PL, $cache;
	ougc_websiteremoval_pl_check();

	$PL->settings_delete('ougc_websiteremoval');

	// Delete version from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['websiteremoval']))
	{
		unset($plugins['websiteremoval']);
	}

	if(!empty($plugins))
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$PL->cache_delete('ougc_plugins');
	}
}

// Loads language strings
function ougc_websiteremoval_lang_load()
{
	global $lang;

	isset($lang->setting_group_ougc_websiteremoval) or $lang->load('ougc_websiteremoval');
}

// PluginLibrary dependency check & load
function ougc_websiteremoval_pl_check()
{
	global $lang;
	ougc_websiteremoval_lang_load();
	$info = ougc_websiteremoval_info();

	if(!file_exists(PLUGINLIBRARY))
	{
		flash_message($lang->sprintf($lang->ougc_websiteremoval_pl_required, $info['pl']['url'], $info['pl']['version']), 'error');
		admin_redirect('index.php?module=config-plugins');
		exit;
	}

	global $PL;

	$PL or require_once PLUGINLIBRARY;

	if($PL->version < $info['pl']['version'])
	{
		flash_message($lang->sprintf($lang->ougc_websiteremoval_pl_old, $info['pl']['url'], $info['pl']['version'], $PL->version), 'error');
		admin_redirect('index.php?module=config-plugins');
		exit;
	}
}

// Language support for settings
function ougc_websiteremoval_settings_change()
{
	global $db, $mybb;

	$query = $db->simple_select('settinggroups', 'name', 'gid=\''.(int)$mybb->input['gid'].'\'');
	$groupname = $db->fetch_field($query, 'name');
	if($groupname == 'ougc_websiteremoval')
	{
		global $plugins;
		ougc_websiteremoval_lang_load();

		if($mybb->request_method == 'post')
		{
			global $settings;

			$gids = '';
			if(isset($mybb->input['ougc_websiteremoval_groups']) && is_array($mybb->input['ougc_websiteremoval_groups']))
			{
				$gids = implode(',', (array)array_filter(array_map('intval', $mybb->input['ougc_websiteremoval_groups'])));
			}

			$mybb->input['upsetting']['ougc_websiteremoval_groups'] = $gids;

			return;
		}

		$plugins->add_hook('admin_formcontainer_output_row', 'ougc_websiteremoval_formcontainer_output_row');
	}
}

// Friendly settings
function ougc_websiteremoval_formcontainer_output_row(&$args)
{
	if($args['row_options']['id'] == 'row_setting_ougc_websiteremoval_groups')
	{
		global $form, $settings;

		$args['content'] = $form->generate_group_select('ougc_websiteremoval_groups[]', explode(',', $settings['ougc_websiteremoval_groups']), array('multiple' => true, 'size' => 5));
	}
}

// Dark Magic
function ougc_websiteremoval(&$post)
{
	global $website, $memprofile, $PL, $settings;
	$PL or require_once PLUGINLIBRARY;

	$var = 'memprofile';
	if(THIS_SCRIPT != 'member.php')
	{
		$var = 'post';
		$website = &$post['website'];
	}

	if(!$PL->is_member($settings['ougc_websiteremoval_groups'], array('usergroup' => ${$var}['usergroup'], 'additionalgroups' => ${$var}['additionalgroups'])))
	{
		$website = '';
	}
}