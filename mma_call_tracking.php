<?php if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Access denied.');

/**
 * @package MMA Call Tracking
 *
 * Copyright 2015 Message Metric (support@messagemetric.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 * of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public
 * License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

/*
Plugin Name: MMA Call Tracking
Description: Track your calls with Message Metric.
Author: Message Metric
Version: 2.3.0
Author URI: http://www.messagemetric.com
*/

if (!class_exists("MessageMetricAssistant")) :

if (!defined('MMA_SIMPLIFIED_TRACKING')) define('MMA_SIMPLIFIED_TRACKING', true);
if (!defined('MSGMETRIC_SERVER_URL'))    define('MSGMETRIC_SERVER_URL', 'https://app.messagemetric.com/');


class MessageMetricAssistant {
	var $plugin_name = 'MMA Call Tracking';
	var $plugin_plug = 'mma_call_tracking';
	var $options = array();

	var $helper = null;

	var $post_error = '';
	var $post_message = '';

	var $phone_data = null;
	var $custom_urls = array();
	var $phone = '';


	function __construct() {
		$this->load_options();

		/* Initialize the session AFTER load_options */
		$this->init_session();

		if (!empty($_GET['mma']) && $_GET['mma'] == 'replace') {
			require 'mma_js.php';
			exit;
		} else if (!empty($_POST['mma_save_config']) || !empty($_POST['mma_save_phones'])) {
			$this->handle_config_options_page();
		}

		add_action('init', array(&$this, 'init_plugin'));
		if (is_admin()) {
			/* Dashboard initialization */
			add_action('admin_menu', array(&$this, 'add_menu_pages'));
		} else {
			if ($this->options['assist_mode'] == 'javascript') {
				add_action('wp_footer', array(&$this, MMA_SIMPLIFIED_TRACKING ? 'format_javascript_simple' : 'format_javascript'));
			}
		}

		add_shortcode('msgmetric_phone', array(&$this, 'shortcode_phone_fn'));

		register_activation_hook(__FILE__, array(&$this, 'activate_plugin'));
		register_deactivation_hook(__FILE__, array(&$this, 'deactivate_plugin'));
	}


	function activate_plugin() {
	}

	function add_menu_pages() {
		$config_name = $this->plugin_name . " " . __('Options', $this->plugin_plug);

		add_menu_page($this->plugin_name, $this->plugin_name, 'administrator', $this->plugin_plug.'_menu',
			array(&$this, 'config_options_page'), $this->plugin_dir_url(__FILE__).'icon.png');
		add_submenu_page($this->plugin_plug.'_menu', 'Settings', 'Settings', 'administrator', $this->plugin_plug.'_menu',
			array(&$this, 'config_options_page'));
		add_submenu_page($this->plugin_plug.'_menu', 'Phone Numbers', 'Phone Numbers', 'administrator', $this->plugin_plug.'_menu2',
			array(&$this, 'config_phones_page'));

		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filter_plugin_actions'), 10, 2);
	}

	function admin_notice_config_errors() {
		echo '<div class="mma-admin-error error">';
		echo  '<h3>MMA Call Tracking</h3>';
		echo  '<p>One or more potential configuration errors have been detected:</p>';
		echo  '<style>.mma-admin-error li { margin-left:1em;margin-bottom:0; }</style>';
		echo  '<ul style="list-style-type:disc;margin:.5em 1em">';
		if (empty($this->options['customer_id'])) {
			echo '<li>Please set your AdWords Customer ID. Ad clicks cannot be converted without this value.</li>';
		}
		if (empty($this->options['conversion_name'])) {
			echo '<li>Please set your AdWords Conversion Type. Ad clicks cannot be converted unless this value is ';
			echo  'set to the same value in your AdWords account.</li>';
		}
		if (!empty($this->options['not_paid_errors'])) {
			echo '<li>'.$this->options['not_paid_errors'].' AdWords Ad click(s) have been received that could not be ';
			echo  'replaced with AdWords tracking numbers (numbers with Display When set to URL Params). Please make ';
			echo  'sure your phone settings are correct. Re-save your MMA Call Tracking settings to reset this value.';
			echo '</li>';
		}
		echo  '</ul>';
		echo '</div>';
	}

	function admin_notice_auth_invalid() {
		echo '<div class="error">';
		echo  '<h3>MMA Call Tracking</h3>';
		echo  '<p><strong>Error connecting to Message Metric. Please make your username name and password are configured correctly.</strong></p>';
		echo '</div>';
	}

	function deactivate_plugin() {
	}

	function filter_plugin_actions($links, $file) {
		$settings_link = '<a href="admin.php?page='.$this->plugin_plug.'_menu">'.__('Settings').'</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	function init_plugin() {
		if (!empty($this->options['username'])) {
			/* Notify the user of invalid MM credentials */
			if (!$this->options['auth_valid']) {
				add_action('admin_notices', array(&$this, 'admin_notice_auth_invalid'));
			}

			/* Notify the user of configuration problems */
			if (   empty($this->options['customer_id'])
			    || empty($this->options['conversion_name'])
			    || !empty($this->options['not_paid_errors'])) {
				add_action('admin_notices', array(&$this, 'admin_notice_config_errors'));
			}
		}
	}

	function init_session() {
		if (!session_id()) session_start();
		if (!empty($_REQUEST['mma_term']) && empty($_SESSION['MSGMETRIC_ASSISTANT_ADWORD_TERM'])) {
			$_SESSION['MSGMETRIC_ASSISTANT_ADWORD_TERM'] = $_REQUEST['mma_term'];
		}
		if (!empty($_REQUEST['gclid']) && empty($_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID'])) {
			$_SESSION['MSGMETRIC_ASSISTANT_DEBUG_DATA'] = array(
				'gclid'=>$_REQUEST['gclid'],
				'mma_term'=>$_REQUEST['mma_term'],
				'referrer'=>!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
				'user_agent'=>$_SERVER['HTTP_USER_AGENT'],
				);

			error_log('mma_call_tracking: ad click: mma_term='.$_REQUEST['mma_term'].', gclid='.$_REQUEST['gclid']);
			if (!empty($_SERVER['HTTP_REFERER'])) error_log('mma_call_tracking: ad click: referrer='.$_SERVER['HTTP_REFERER']);
			error_log('mma_call_tracking: ad click: user agent='.$_SERVER['HTTP_USER_AGENT']);

			$_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID'] = $_REQUEST['gclid'];

			if (!MMA_SIMPLIFIED_TRACKING) {
				$click_data = array(
					'customer_id'=>$this->options['customer_id'],
					'conversion'=>$this->options['conversion_name'],
					'gclid'=>$_REQUEST['gclid'],
					'tentative'=>1,
					);

				$this->send_server_request('record_ad_click', $click_data);
			}
		}
		$_SESSION['MSGMETRIC_ASSISTANT_PHONES'][''] = $this->get_msgmetric_phone();
	}

	function load_options() {
		$the_options = get_option($this->plugin_plug.'_options');

		if (empty($the_options['username'])) $the_options['username'] = '';
		if (empty($the_options['auth_key'])) $the_options['auth_key'] = '';
		if (empty($the_options['customer_id'])) $the_options['customer_id'] = '';
		if (empty($the_options['conversion_name'])) $the_options['conversion_name'] = '';
		if (empty($the_options['referral_urls'])) $the_options['referral_urls'] = array();
		if (empty($the_options['phones'])) $the_options['phones'] = array();
		if (empty($the_options['assist_mode'])) $the_options['assist_mode'] = 'shortcode';
		if (empty($the_options['replace_list'])) $the_options['replace_list'] = array();
		if (empty($the_options['phone_list'])) $the_options['phone_list'] = array();
		if (empty($the_options['phone_groups'])) $the_options['phone_groups'] = array();

		if (empty($the_options['notify_email'])) $the_options['notify_email'] = '';
		if (empty($the_options['notify_mmsupport'])) $the_options['notify_mmsupport'] = 0;
		if (empty($the_options['notify_mmsent'])) $the_options['notify_mmsent'] = false;
		if (empty($the_options['not_paid_errors'])) $the_options['not_paid_errors'] = 0;

		$this->options = $the_options;
	}

	function plugin_dir_url($file=__FILE__) {
		return get_option('siteurl') . '/' . PLUGINDIR . '/' .  basename(dirname($file)) . '/';
	}

	function save_options() {
		if (empty($this->options)) {
			delete_option($this->plugin_plug.'_options');
		} else {
			update_option($this->plugin_plug.'_options', $this->options);
		}
	}


	function compile_referral_urls() {
		$custom_urls = array();
		foreach ($this->options['phone_list'] as $phone=>$phone_data) {
			if ($phone_data['when'] != 'refurl') continue;
			$custom_urls[$phone_data['refurl']] = 1;
		}

		$this->custom_urls = array_keys($custom_urls);
	}

	/* config_options_page
	 *
	 * Settings configuration page
	 */
	function config_options_page() {
?>
<div class="wrap">
  <h2><?php echo $this->plugin_name ?></h2>
  <?php if (!empty($this->post_error)) { ?>
  <p style="color: #C00; margin: 4px 0px 4px 10px">Error: <?php echo $this->post_error ?></p>
  <?php } else if (!empty($this->post_message)) { ?>
  <p style="color: #0A0; margin: 4px 0px 4px 10px"><?php echo $this->post_message ?></p>
  <?php } ?>

  <form name="<?php echo $this->plugin_plug ?>'_options_form" method="post" autocomplete="off">
	<table class="form-table">
	<tr valign="top"><th scope="row" colspan="3"><h3 style="margin:0"><?php _e("Plugin Settings:", $this->plugin_plug); ?></h3></th></tr>

	<tr valign="top">
		<th scope="row" width="20%"><?php _e('Username:', $this->plugin_plug); ?></th>
		<td width="50%"><input type="text" name="username" value="<?php echo $this->options['username'] ?>" class="widefat" /></td>
		<td width="30%">(email address)</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('Authenication Key:', $this->plugin_plug); ?></th>
		<td><input type="auth_key" name="auth_key" value="<?php echo $this->options['auth_key'] ?>" class="widefat" /></td>
		<td>(e.g. xxxx-xxxxx-xxxxx-xxxxx-xxxxx)</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('AdWords Customer ID:', $this->plugin_plug); ?></th>
		<td><input type="text" name="customer_id" value="<?php echo $this->options['customer_id'] ?>" class="widefat" /></td>
		<td>(e.g. nnn-nnn-nnnn)</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('AdWords Conversion Type:', $this->plugin_plug); ?></th>
		<td>
			<input type="text" name="conversion_name" value="<?php echo $this->options['conversion_name'] ?>" class="widefat" />
			<em>This value <strong>must</strong> exactly match the name of a conversion defined in your AdWords account. Spaces between words
			  and the case of letters are important and <strong>must</strong> match exactly.  Please make sure the <strong>source</strong>  value of
			  conversion is set as "Import"; other values will not work.</em>
		</td>
		<td style="vertical-align:top">(e.g: Phone Call)</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('Additional Phones:', $this->plugin_plug); ?></th>
		<td><input type="text" name="phones" value="<?php echo implode(',', $this->options['phones']) ?>" class="widefat" /></td>
		<td>(comma separated list of additional phone numbers)</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('Assistant Mode:', $this->plugin_plug); ?></th>
		<td>
			<input type="radio" name="assist_mode" value="shortcode" <?php echo $this->options['assist_mode'] != 'javascript' ? 'checked' : '' ?> /> Shortcode
				<div style="margin:.5em 1.5em">Use a WordPress shortcode wherever you want to display a Message Metric phone number.
					This mode inserts the phone number before the page loads, but will not work with caching plugins  like W3
					Total Cache.</div>
			<input type="radio" name="assist_mode" value="javascript" <?php echo $this->options['assist_mode'] == 'javascript' ? 'checked' : '' ?> /> JavaScript
				<div style="margin:.5em 1.5em">Automatically replace phone numbers wherever they are found on your web site.  This
					mode works with caching plugins like W3 Total Cache but users may see the original phone number briefly before
					it is replaced with a Message Metric number.</div>
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('Troubleshooting:', $this->plugin_plug); ?></th>
		<td>
			<div><strong>Notification Email</strong> <em>(Send an email when configuration problems are detected)</em></div>
			<div><input type="text" name="notify_email" value="<?php echo $this->options['notify_email'] ?>" placeholder="email address" class="widefat" /></div>
			<br/>
			<div><strong>Notification Message Metric Support</strong></div>
			<div><input type="checkbox" name="notify_mmsupport" value="1" <?php echo $this->options['notify_mmsupport'] ? 'checked' : '' ?> />
				Also send Message Metric Support a copy of any notices that are sent.</div>
			<div><em>Note: a copy of your configuration will also be included that Support may use to help resolve the problem.</em></div>
		</td>
		<td>&nbsp;</td>
	</tr>

	</table>
	<p class="submit">
		<input type="submit" class="button-primary" name="mma_save_config" value="<?php _e('Save Settings') ?>" />
	</p>

  </form>
</div>

<?php
		echo $this->format_settings_footer('options');
	}

	/* config_phones_page
	 *
	 * Phone Number configuration page
	 */
	function config_phones_page() {
		$data = $this->get_phone_data();
		if (is_array($data)) {
			$this->options['auth_valid'] = true;
			$this->options['referral_urls'] = isset($data['directory_list']) ? $data['directory_list'] : array();
			$this->options['phone_list'] = $this->merge_phone_data($data['phone_list']);
		}

		$this->compile_referral_urls();

		$var1List = $this->get_phone_variables('var1', $this->options['phone_list'], $this->options['replace_list']);
		$var2List = $this->get_phone_variables('var2', $this->options['phone_list'], $this->options['replace_list']);
?>
<style>
table.phone-list th { border-bottom: 1px solid #222; }
table.phone-list th, table.phone-list td { padding: 0; vertical-align: top; }
table.phone-list th { width: 10%; }
table.phone-list th.wide { width: 45%; }
table.phone-list th.example { width: 35%; }
table.phone-list input, table.phone-list select { width: 95%; }
table.phone-list select.setting-paid{ margin-right: 2px; width: 23%; }
</style>
<div class="wrap">
  <h2><?php echo $this->plugin_name ?></h2>
  <?php if (!empty($this->post_error)) { ?>
  <p style="color: #C00; margin: 4px 0px 4px 10px">Error: <?php echo $this->post_error ?></p>
  <?php } else if (!empty($this->post_message)) { ?>
  <p style="color: #0A0; margin: 4px 0px 4px 10px"><?php echo $this->post_message ?></p>
  <?php } ?>

  <form name="<?php echo $this->plugin_plug ?>_phones_form" class="phones-form" method="post" autocomplete="off">
	<table class="form-table">

	<?php if ($this->options['assist_mode'] == 'javascript') { ?>

	<tr valign="top"><th scope="row"><h3 style="margin:0">Phone Numbers:</h3></th></tr>
	<tr><td>Enter the phone numbers that you want to want to have replaced with Message Metric numbers.</td></tr>

	<tr><td>

	<table width="100%" class="replacement-list phone-list">
	<tr><th><strong>Phone #</strong></th><th><strong>Location</strong></th><th><strong>Split Test</strong></th><th>Action</th></tr>
	<?php
	$replace_list = !empty($this->options['replace_list']) ? $this->options['replace_list'] : array(''=>array('var1'=>'', 'var2'=>''));
	$replace_list['-TEMPLATE-'] = array('var1'=>'', 'var2'=>'');
	foreach ($replace_list as $phone=>$phone_data) {
		$style = $phone == '-TEMPLATE-' ? 'style="display:none"' : '';
		$phone = $phone == '-TEMPLATE-' ? '' : $phone;
		echo '<tr '.$style.'>';
		echo   '<td><input type="text" name="repl_phone[]" value="'.$phone.'" class="repl-phone widefat" /></td>';
		echo   '<td>'.$this->get_phone_variable_selector('var1', 'repl_var1', $var1List, '', $phone_data).'</td>';
		echo   '<td>'.$this->get_phone_variable_selector('var2', 'repl_var2', $var2List, '', $phone_data).'</td>';
		echo   '<td><input type="submit" class="remove-replacement button-secondary" value="Remove" style="width:6em" /></td>';
		echo '</tr>';
	}
	?>
	</table>
	<input type="submit" class="add-replacement button-secondary" value="Add Phone Number" style="width:12em" />

	</td></tr>

	<?php } ?>

	<tr valign="top"><th scope="row"><h3 style="margin:0">Phone Settings:</h3></th></tr>
	<tr><td>
	<?php
	if (!empty($this->options['phone_list'])) {
		echo '<table width="100%" class="phone-list">';

		echo '<tr>';
		echo   '<th><strong>Phone #</strong></th>';
		echo   '<th><strong>Title</strong></th>';
		echo   '<th><strong>Group</strong></th>';
		echo   '<th><strong>Display When</strong></th>';
		echo   '<th><strong>Location</strong></th>';
		echo   '<th><strong>Split Test</strong></th>';
		echo   '<th class="wide"><strong>Settings</strong></th>';
		echo '</tr>';

		$groupList = $this->get_phone_variables('group', $this->options['phone_list'], array());
		$var1List = $this->get_phone_variables('var1', $this->options['phone_list'], $this->options['replace_list']);
		$var2List = $this->get_phone_variables('var2', $this->options['phone_list'], $this->options['replace_list']);
		foreach ($this->options['phone_list'] as $phone=>$phone_data) {
			echo '<tr data-phone="'.$this->normalize_phone($phone).'">';
			echo   '<td class="phone-number">'.$phone.'</td>';
			echo   '<td>'.$phone_data['title'].'</td>';
			echo   '<td>'.$this->get_phone_variable_selector('group', 'group', $groupList, $phone, $phone_data).'</td>';
			echo   '<td>'.$this->get_phone_display_selector($phone, $phone_data).'</td>';
			echo   '<td>'.$this->get_phone_variable_selector('var1', 'var1', $var1List, $phone, $phone_data).'</td>';
			echo   '<td>'.$this->get_phone_variable_selector('var2', 'var2', $var2List, $phone, $phone_data).'</td>';
			echo   '<td>';
			echo     $this->get_phone_display_settings($phone, $phone_data);
			echo   '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}

	echo '</td></tr>';
	echo '</table>';
	if (!empty($this->options['phone_list'])) {
		echo '<p class="submit">';
		echo 	'<input type="submit" class="button-primary" name="mma_save_phones" value="Save Changes" />';
		echo '</p>';
	}

	echo $this->format_settings_footer('phones');
	?>

  </form>
</div>
<script type="text/javascript">
jQuery(function(){
	jQuery('.add-replacement').click(function(){
		var $tmpl = jQuery('.replacement-list tr:last-child');
		var $clone = $tmpl.clone();
		$clone.find('.repl-phone').val('');
		$clone.insertBefore($tmpl).show();
		return false;
	});
	jQuery('.replacement-list')
		.on('click', '.remove-replacement', function(){
			var $table = jQuery(this).closest('table');
			jQuery(this).closest('tr').remove();
			if ($table.find('tr').length < 3) jQuery('.add-replacement').click();
			return false;
		});
	jQuery('.phone-when').change(function(){
		var val = jQuery(this).val();
		jQuery(this).closest('tr').find('.phone-setting').hide();
		jQuery(this).closest('tr').find('.setting-'+val).show();
	});
	jQuery('.setting-refurl').change(function(){
		if (jQuery(this).val() === '') {
			var url = prompt('Please enter a URL to add:', '');
			if (url !== null) {
				jQuery('.setting-refurl').append('<option value="'+url+'">'+url+'</option>');
				jQuery(this).val(url);
			} else {
				jQuery(this).val('');
			}
		}

		return false;
	});
	jQuery('.phones-form').on('change', '.phone-group,.phone-var1,.phone-var2', function(){
		var $opt = jQuery(this).find('option:selected');
		var $parent = jQuery(this).closest('tr');
		var type = jQuery(this).data('type');
		var val = $opt.val();

		if (val === '-add-') {
			val = prompt('Please enter a '+(type=='group'?'group name':'value')+':', '');
			val !== null && (val = val.replace(/[^0-9A-Za-z _]+/, ''));
			if (val!== null && $opt.parent().find('option[value="'+val+'"]').length === 0) {
				var $opt = jQuery('<option value="'+val+'">'+val+'</option>');
				jQuery('.phone-'+type).append($opt);
				jQuery(this).val(val);
			} else {
				jQuery(this).val('');
			}
		}

		if (type == 'group') {
			if (val === '') {
				$parent.find('.mma-term').text($parent.data('phone'));
			} else {
				$parent.find('.mma-term').text(val);
			}
		}

		return false;
	});
	<?php if ($this->options['assist_mode'] != 'javascript') { ?>
	jQuery('input[name=mma_save_phones]').click(function(){
		var dfltPhone = false;
		jQuery('.phone-when').each(function(){
			if (jQuery(this).val() === 'default') {
				var $tr = jQuery(this).closest('tr');
				if ($tr.find('.phone-var1').val() === '' && $tr.find('.phone-var2').val() === '') {
					dfltPhone = true;
					return false;
				}
			}
		});
		if (!dfltPhone) {
			alert('At least one phone number must be have Display When set to "Default" and have no "Location" and no "Split Test" value.');
			return false;
		}
	});
	<?php } ?>
});
</script>
<?php
	}

	/* format_javascript
	 *
	 * This is the more advanced, W3 Total Cache friendly version of MMA javascript.  Theoretically this works, but it for some reason
	 * only ~50% of AdWords clicks are getting recorded.
	 *
	 * Replaced with format_javascript_simple for now.
	 */
	function format_javascript() {
		$url = str_replace(array('http:', 'https:'), array('', ''), get_site_url()).'/?mma=replace';
		$gclid = "&gclid='+\$mma('gclid')+'";
		$term = "&term='+\$mma('mma_term')+'";
		$refurl = "&refurl='+d.referrer+'";
		$key = '&key='.$this->options['auth_key'];
		foreach ($this->options['replace_list'] as $phone=>$phone_data) {
			$phone = $this->normalize_phone($phone);
			$phones[] = 'phone[]='.$phone.'-'.urlencode($phone_data['var1']).'-'.urlencode($phone_data['var2']);
		}
		$phones = is_array($phones) ? ('&'.implode('&', $phones)) : '';

		$js  = '<script>';
		$js .=  '!function(d,undefined){var $mma=function(e){e=e.replace(/[\[]/,"\[").replace(/[\]]/,"\]");';
		$js .=  'var t=new RegExp("[\?&]"+e+"=([^&#]*)"),n=t.exec(location.search);return n===null?"":decodeURIComponent(n[1].replace(/\+/g," "))};';
		$js .=  'var scriptTag = document.createElement("script");';
		$js .=  'scriptTag.type = "text/javascript";';
		$js .=  'scriptTag.async = true;';
		$js .=  'scriptTag.src = \''.$url.$gclid.$term.$refurl.$key.$phones.'\';';
		$js .=  'var s = document.getElementsByTagName("script")[0];';
		$js .=  's.parentNode.insertBefore(scriptTag, s);';
		$js .=  '}(document);';
		$js .= '</script>';

		echo $js;
	}

	function format_javascript_simple() {
		$term = $_SESSION['MSGMETRIC_ASSISTANT_ADWORD_TERM'];
		$refurl = $_SERVER['HTTP_REFERER'];
		$gclid = $_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID'];

		$rphones = array();
		foreach ($this->options['replace_list'] as $phone=>$phone_data) {
			$phone = $this->normalize_phone($phone);
			$rphones[$phone] = $this->normalize_phone($this->get_msgmetric_phone($term, $refurl, $gclid, $phone_data['var1'], $phone_data['var2']));
		}

		?><script>
		// Copyright (c) 2015 Message Metric

		// docReady: Copyright (c) 2014, John Friend, MIT License
		!function(t,e){"use strict";function n(){if(!a){a=!0;for(var t=0;t<o.length;t++)o[t].fn.call(window,o[t].ctx);o=[]}}function d(){"complete"===document.readyState&&n()}t=t||"docReady",e=e||window;var o=[],a=!1,c=!1;e[t]=function(t,e){return a?void setTimeout(function(){t(e)},1):(o.push({fn:t,ctx:e}),void("complete"===document.readyState||!document.attachEvent&&"interactive"===document.readyState?setTimeout(n,1):c||(document.addEventListener?(document.addEventListener("DOMContentLoaded",n,!1),window.addEventListener("load",n,!1)):(document.attachEvent("onreadystatechange",d),window.attachEvent("onload",n)),c=!0)))}}("docReady",window);

		docReady(function(){
		!function(e,t){"object"==typeof module&&module.exports?module.exports=t():"function"==typeof define&&define.amd?define(t):e.farDT=t()}(this,function(){function e(e){return String(e).replace(/([.*+?^=!:${}()|[\]\/\\])/g,"\\$1")}function t(){return n.apply(null,arguments)||r.apply(null,arguments)}function n(e,n,i,o,d){if(n&&!n.nodeType&&arguments.length<=2)return!1;var a="function"==typeof i;a&&(i=function(e){return function(t,n){return e(t.text,n.startIndex)}}(i));var s=r(n,{find:e,wrap:a?null:i,replace:a?i:"$"+(o||"&"),prepMatch:function(e,t){if(!e[0])throw"farDT cannot handle zero-length matches";if(o>0){var n=e[o];e.index+=e[0].indexOf(n),e[0]=n}return e.endIndex=e.index+e[0].length,e.startIndex=e.index,e.index=t,e},filterElements:d});return t.revert=function(){return s.revert()},!0}function r(e,t){return new i(e,t)}function i(e,n){var r=n.preset&&t.PRESETS[n.preset];if(n.portionMode=n.portionMode||o,r)for(var i in r)s.call(r,i)&&!s.call(n,i)&&(n[i]=r[i]);this.node=e,this.options=n,this.prepMatch=n.prepMatch||this.prepMatch,this.reverts=[],this.matches=this.search(),this.matches.length&&this.processMatches()}var o="retain",d="first",a=document,s=({}.toString,{}.hasOwnProperty);return t.NON_PROSE_ELEMENTS={br:1,hr:1,script:1,style:1,img:1,video:1,audio:1,canvas:1,svg:1,map:1,object:1,input:1,textarea:1,select:1,option:1,optgroup:1,button:1},t.NON_CONTIGUOUS_PROSE_ELEMENTS={address:1,article:1,aside:1,blockquote:1,dd:1,div:1,dl:1,fieldset:1,figcaption:1,figure:1,footer:1,form:1,h1:1,h2:1,h3:1,h4:1,h5:1,h6:1,header:1,hgroup:1,hr:1,main:1,nav:1,noscript:1,ol:1,output:1,p:1,pre:1,section:1,ul:1,br:1,li:1,summary:1,dt:1,details:1,rp:1,rt:1,rtc:1,script:1,style:1,img:1,video:1,audio:1,canvas:1,svg:1,map:1,object:1,input:1,textarea:1,select:1,option:1,optgroup:1,button:1,table:1,tbody:1,thead:1,th:1,tr:1,td:1,caption:1,col:1,tfoot:1,colgroup:1},t.NON_INLINE_PROSE=function(e){return s.call(t.NON_CONTIGUOUS_PROSE_ELEMENTS,e.nodeName.toLowerCase())},t.PRESETS={prose:{forceContext:t.NON_INLINE_PROSE,filterElements:function(e){return!s.call(t.NON_PROSE_ELEMENTS,e.nodeName.toLowerCase())}}},t.Finder=i,i.prototype={search:function(){function t(e){for(var d=0,p=e.length;p>d;++d){var h=e[d];if("string"==typeof h){if(o.global)for(;n=o.exec(h);)a.push(s.prepMatch(n,r++,i));else(n=h.match(o))&&a.push(s.prepMatch(n,0,i));i+=h.length}else t(h)}}var n,r=0,i=0,o=this.options.find,d=this.getAggregateText(),a=[],s=this;return o="string"==typeof o?RegExp(e(o),"g"):o,t(d),a},prepMatch:function(e,t,n){if(!e[0])throw new Error("farDT cannot handle zero-length matches");return e.endIndex=n+e.index+e[0].length,e.startIndex=n+e.index,e.index=t,e},getAggregateText:function(){function e(r,i){if(3===r.nodeType)return[r.data];if(t&&!t(r))return[];var i=[""],o=0;if(r=r.firstChild)do if(3!==r.nodeType){var d=e(r);n&&1===r.nodeType&&(n===!0||n(r))?(i[++o]=d,i[++o]=""):("string"==typeof d[0]&&(i[o]+=d.shift()),d.length&&(i[++o]=d,i[++o]=""))}else i[o]+=r.data;while(r=r.nextSibling);return i}var t=this.options.filterElements,n=this.options.forceContext;return e(this.node)},processMatches:function(){var e,t,n,r=this.matches,i=this.node,o=this.options.filterElements,d=[],a=i,s=r.shift(),p=0,h=0,l=0,c=[i];e:for(;;){if(3===a.nodeType&&(!t&&a.length+p>=s.endIndex?t={node:a,index:l++,text:a.data.substring(s.startIndex-p,s.endIndex-p),indexInMatch:p-s.startIndex,indexInNode:s.startIndex-p,endIndexInNode:s.endIndex-p,isEnd:!0}:e&&d.push({node:a,index:l++,text:a.data,indexInMatch:p-s.startIndex,indexInNode:0}),!e&&a.length+p>s.startIndex&&(e={node:a,index:l++,indexInMatch:0,indexInNode:s.startIndex-p,endIndexInNode:s.endIndex-p,text:a.data.substring(s.startIndex-p,s.endIndex-p)}),p+=a.data.length),n=1===a.nodeType&&o&&!o(a),e&&t){if(a=this.replaceMatch(s,e,d,t),p-=t.node.data.length-t.endIndexInNode,e=null,t=null,d=[],s=r.shift(),l=0,h++,!s)break}else if(!n&&(a.firstChild||a.nextSibling)){a.firstChild?(c.push(a),a=a.firstChild):a=a.nextSibling;continue}for(;;){if(a.nextSibling){a=a.nextSibling;break}if(a=c.pop(),a===i)break e}}},revert:function(){for(var e=this.reverts.length;e--;)this.reverts[e]();this.reverts=[]},prepareReplacementString:function(e,t,n){var r=this.options.portionMode;return r===d&&t.indexInMatch>0?"":(e=e.replace(/\$(\d+|&|`|\')/g,function(e,t){var r;switch(t){case"&":r=n[0];break;case"`":r=n.input.substring(0,n.startIndex);break;case"\'":r=n.input.substring(n.endIndex);break;default:r=n[+t]}return r}),r===d?e:t.isEnd?e.substring(t.indexInMatch):e.substring(t.indexInMatch,t.indexInMatch+t.text.length))},getPortionReplacementNode:function(e,t,n){var r=this.options.replace||"$&",i=this.options.wrap;if(i&&i.nodeType){var o=a.createElement("div");o.innerHTML=i.outerHTML||(new XMLSerializer).serializeToString(i),i=o.firstChild}if("function"==typeof r)return r=r(e,t,n),r&&r.nodeType?r:a.createTextNode(String(r));var d="string"==typeof i?a.createElement(i):i;return r=a.createTextNode(this.prepareReplacementString(r,e,t,n)),r.data&&d?(d.appendChild(r),d):r},replaceMatch:function(e,t,n,r){var i,o,d=t.node,s=r.node;if(d===s){var p=d;t.indexInNode>0&&(i=a.createTextNode(p.data.substring(0,t.indexInNode)),p.parentNode.insertBefore(i,p));var h=this.getPortionReplacementNode(r,e);return p.parentNode.insertBefore(h,p),r.endIndexInNode<p.length&&(o=a.createTextNode(p.data.substring(r.endIndexInNode)),p.parentNode.insertBefore(o,p)),p.parentNode.removeChild(p),this.reverts.push(function(){i===h.previousSibling&&i.parentNode.removeChild(i),o===h.nextSibling&&o.parentNode.removeChild(o),h.parentNode.replaceChild(p,h)}),h}i=a.createTextNode(d.data.substring(0,t.indexInNode)),o=a.createTextNode(s.data.substring(r.endIndexInNode));for(var l=this.getPortionReplacementNode(t,e),c=[],u=0,f=n.length;f>u;++u){var x=n[u],g=this.getPortionReplacementNode(x,e);x.node.parentNode.replaceChild(g,x.node),this.reverts.push(function(e,t){return function(){t.parentNode.replaceChild(e.node,t)}}(x,g)),c.push(g)}var N=this.getPortionReplacementNode(r,e);return d.parentNode.insertBefore(i,d),d.parentNode.insertBefore(l,d),d.parentNode.removeChild(d),s.parentNode.insertBefore(N,s),s.parentNode.insertBefore(o,s),s.parentNode.removeChild(s),this.reverts.push(function(){i.parentNode.removeChild(i),l.parentNode.replaceChild(d,l),o.parentNode.removeChild(o),N.parentNode.replaceChild(s,N)}),N}},t});
		var mob=(function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))check = true})(navigator.userAgent||navigator.vendor||window.opera);
		var fel=function(e){return (" "+e.className+" ").indexOf(" mma-noreplace ") === -1;};
		<?php
		$patternList = array('dddddddddd', 'ddd.ddd.dddd', 'ddd-ddd-dddd', '(ddd)ddd-dddd', '(ddd) ddd-dddd');
		foreach ($rphones as $rphone=>$phone) {
			$tphone = $this->format_phone($phone, '+1##########', false);
			foreach ($patternList as $pattern) {
				list($regex, $nphone) = $this->get_regex($pattern, "$rphone", $phone);
				echo 'farDT(document.body, { find: "'.$regex.'", replace: mob ? "<a href=\"tel:'.$tphone.'\">'.$nphone.'</a>" : "'.$nphone.'", filterElements: fel });'.PHP_EOL;
			}
		    echo 'var $a=document.querySelector("a[href^=\"tel:\"]"); if ($a) $a.href = $a.href.replace("'.$rphone.'", "'.$phone.'")'.PHP_EOL;
		}
		?>
		});
		</script>
		<?php
	}

	function format_phone($phone, $format=false, $normalize=true) {
		if ($normalize) $phone = $this->normalize_phone($phone);
		if (strlen($phone) != 10) return '';

		if (!$format) {
			$phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
		} else {
			$chrList = array();
			for ($i = 0, $pos = 0, $flen = strlen($format), $plen = strlen($phone); $i < $flen && $pos < $plen; $i++) {
				$chrList[] = $format[$i] == '#' ? $phone[$pos++] : $format[$i];
			}
			$phone = implode('', $chrList);
		}

		return $phone;
	}

	function format_settings_footer($page) {
		if ($this->options['assist_mode'] == 'shortcode') {
			$html  = '<p>Use the <code>[msgmetric_phone]</code> shortcode to display the correct phone number on your site.</p>';
			$html .= '<p>By default the <code>[msgmetric_phone]</code> displays the phone number with the format: (###) ###-####.  You may change the formatting of ';
			$html .=   'the number or add other text by using the <tt>format</tt> parameter. Use one # character for each digit of the phone number. ';
			$html .=   'other characters are displayed as entered.  For example, you could use the <code>[msgmetric_phone format="###-###-#### ext. 2"]</code> to ';
			$html .=   'display the number without parenthesis and with an dialing extension of 2.';
			$html .= '</p>';
			$html .= '<p>Use a <code>phone</code> (e.g. <code>[msgmetric_phone phone="5055551212"]</code>) value to load the page with another ';
			$html .=   'number - usually your primary contact number - before displaying your Message Metric number.  This can be helpful for SEO ';
			$html .=   'as this number will consistently be visible to search engines.</p>';
		}
		if ($page == 'phones') {
			$html .= '<p>Use the <code>location</code> and <code>test</code> values to restrict the phone numbers that are displayed. This may be useful, ';
			$html .=   'for example, if your business has multiple locations. In that case you could create a <code>location</code> value for each location. ';
			$html .=   '<code>location="Los Angeles"</code> could then be used to display only phone numbers associated ';
			$html .=   'with Los Angeles. <code>test</code> values may be used to further distinguish when phone numbers are displayed such as when ';
			$html .=   'your web site is set up for split testing. ';
			$html .=   'The optional <code>location</code> and <code>test</code> values tell Message Metric to only ';
			$html .=   'display the phone numbers matching the values specified. ';
			$html .= '</p>';
		}

		if (empty($this->options['phone_list'])) {
			$url = MSGMETRIC_SERVER_URL.'?msgmetric_login=1&login_username='.$this->options['username'];

			$html .= '<p>';
			$html .= 'Your account has no phone numbers associated with it. ';
			$html .=   '<a href="'.$url.'">Click here to log into your account and add a number &raquo;</a>';
			$html .= '</p>';
		}

		return $html;
	}

	function get_msgmetric_phone($term=false, $refurl=false, $gclid=false, $var1=false, $var2=false) {
		if (empty($this->options['phone_list'])) return false;

		$var1 = $var1 !== false ? $var1 : false;
		$var2 = $var2 !== false ? $var2 : false;
		$key = ($var1 !== false || $var2 !== false) ? "$var1:$var2" : '';
		if (!empty($_SESSION['MSGMETRIC_ASSISTANT_PHONES'][$key])) return $_SESSION['MSGMETRIC_ASSISTANT_PHONES'][$key];

		$match = false;
		$weight = 0;
		$defaults = array();
		$match_group = false;
		$groups = array();
		foreach ($this->options['phone_list'] as $phone=>$phone_data) {
			$group = false;

			/* For #s that are not full-default #s, skip where the variables don't match */
			if ($phone_data['when'] != 'default' || !empty($phone_data['var1']) || !empty($phone_data['var2'])) {
				if ($var1 !== false && $var1 != $phone_data['var1']) continue;
				if ($var2 !== false && $var2 != $phone_data['var2']) continue;
			}

			switch ($phone_data['when']) {
			case 'paid':
				if (empty($term)) break;

				if (!empty($phone_data['group'])) {
					/* Match the term against the group name */
					$regex = '/'.strtolower($phone_data['group']).'/';
				} else {
					/* Match the term against the phone number */
					$regex = '/'.$this->normalize_phone($phone).'/';
				}

				if (preg_match($regex, strtolower($term))) {
					if (!empty($phone_data['group'])) $group = $phone_data['group'];

					if ($weight < 20) {
						$weight = 20;
						$match = $phone;
						$match_group = $group ? $group : false;
					}
				}
				break;

			case 'default':
				if (!empty($phone_data['group'])) $group = $phone_data['group'];

				$new_weight = ($phone_data['var1']?2:0) + ($phone_data['var2']?1:0);
				if ($weight < $new_weight) {
					$weight = $new_weight;
					$match = $phone;
					$match_group = $group ? $group : false;
				}

				$defaults[] = $phone;
				if ($group && !$match_group) $match_group = $group;
				break;

			case 'refurl':
				$value = $phone_data['refurl'];
				if (!empty($refurl) && strpos($refurl, $value) !== false) {
					if (!empty($phone_data['group'])) $group = $phone_data['group'];

					if ($weight < 10) {
						$weight = 10;
						$match = $phone;
						$match_group = $group ? $group : false;
					}
				}
				break;

			default:
				/* do nothing */
				break;
			}

			if ($group) {
				if (!empty($groups[$group])) {
					$groups[$group][] = $phone;
				} else {
					$groups[$group] = array($phone);
				}
			}
		}

		if ($match_group) {
			$did_match = true;
			$match_id = isset($this->options['phone_groups'][$match_group])
				? (($this->options['phone_groups'][$match_group]+1) % count($groups[$match_group]))
				: 0;
			$match = $groups[$match_group][$match_id];

			$this->options['phone_groups'][$match_group] = $match_id;
			$this->save_options();
		} else if (!$match) {
			$did_match = false;
			if (!empty($defaults)) {
				/* Pick a default phone randomly */
				$val = rand(0, count($defaults)-1);
				$match = $defaults[rand(0, count($defaults)-1)];
			}
		} else {
			$did_match = true;
		}

		/* If there is a gclid, notify Message Metric of the click. */
		if (!empty($gclid)) {
			$paid = ($this->options['phone_list'][$match]['when'] == 'paid');

			$click_data = array(
				'customer_id'=>$this->options['customer_id'],
				'conversion'=>$this->options['conversion_name'],
				'gclid'=>$gclid,
				'phone'=>$match,
				'match'=>$did_match ? 1 : 0,
				'paid'=>$paid,
				'tentative'=>0,
				);

			$this->send_server_request('record_ad_click', $click_data);
			unset($_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID']);

			if (!empty($gclid) && !$paid && !empty($this->options['notify_email'])) {
				$this->options['not_paid_errors']++;
				$this->save_options();

				$this->send_notification('not_paid');
			}
		}

		$_SESSION['MSGMETRIC_ASSISTANT_PHONES'][$key] = $match;
		return $match;
	}

	function get_phone_data() {
		if ($this->phone_data === null) {
			$rc = $this->send_server_request('get_phone_data');
			$this->phone_data = ($rc != 'auth' && $rc != 'error') ? @json_decode($rc, true) : false;
		}

		return $this->phone_data;
	}

	function get_phone_display_selector($phone, $phone_data) {
		$when_list = array(
			''=>'-- never --',
			'default'=>'Default',
			'paid'=>'URL Params',
			'refurl'=>'Site Referral',
			);

		$html  = '<select name="phone_when['.$phone.']" class="phone-when">';
		foreach ($when_list as $when=>$title) {
			$selected = $when == $phone_data['when'] ? 'selected="selected"' : '';
			$html .= '<option value="'.$when.'" '.$selected.'>'.$title.'</option>';
		}
		$html .= '</select>';

		return $html;
	}

	function get_phone_display_settings($phone, $phone_data) {
		/* Paid Search
		 */
		$display = (empty($phone_data['when']) || $phone_data['when'] != 'paid') ? 'style="display:none"' : '';
		$ex_term = !empty($phone_data['group']) ? $phone_data['group'] : $this->normalize_phone($phone);
		$html .= '<em class="phone-setting setting-paid" '.$display.'>(Example URL: '.get_bloginfo('url').'/?mma_term=<span class="mma-term">'.$ex_term.'</span>)</em>';

		/* Referral
		 */
		$hide = (empty($phone_data['when']) || $phone_data['when'] != 'refurl');
		$display = (!empty($phone_data['when']) && $phone_data['when'] == 'refurl') ? '' : 'style="display:none"';
		$value = (!empty($phone_data['when']) && $phone_data['when'] == 'refurl') ? $phone_data['refurl'] : '';
		$html .= $this->get_referral_list_selector($phone, $value, $hide);

		return $html;
	}

	function get_phone_variable_selector($var_type, $name, $variables, $phone, $phone_data) {
		$html  = '<select name="phone_'.$name.'['.$phone.']" class="phone-'.$var_type.'" data-type="'.$var_type.'">';

		$selected = empty($phone_data[$var_type]) ? 'selected="selected"' : '';
		$html .= '<option value="" '.$selected.'>-- '.($var_type=='group'?'no group':'all values').' --</option>';

		$html .= '<option value="-add-">-- add a '.($var_type=='group'?'group':'value').' --</option>';
		foreach ($variables as $var) {
			$selected = (!empty($phone_data[$var_type]) && $var == $phone_data[$var_type]) ? 'selected="selected"' : '';
			$html .= '<option value="'.$var.'" '.$selected.'>'.$var.'</option>';
		}
		$html .= '</select>';

		return $html;
	}

	function get_phone_variables($var_type, $phone_list, $replace_list) {
		$varList = array();
		foreach ($phone_list as $phone=>$phone_data) {
			if (!empty($phone_data[$var_type])) $varList[$phone_data[$var_type]] = 1;
		}
		foreach ($replace_list as $phone=>$phone_data) {
			if (!empty($phone_data[$var_type])) $varList[$phone_data[$var_type]] = 1;
		}

		return array_keys($varList);
	}

	function get_referral_list_selector($phone, $match, $hide=false) {
		$referral_urls = array_unique(array_merge($this->options['referral_urls'], $this->custom_urls));
		sort($referral_urls);

		$html  = '<select name="phone_setting['.$phone.'][refurl]" class="phone-setting setting-refurl" '.($hide?'style="display:none"':'').'>';
		$selected = $match == '' ? 'selected="selected"' : '';
		$html .= '<option value="" '.$selected.'>-- select url --</option>';
		$html .= '<option value="">-- add url --</option>';
		foreach ($referral_urls as $url) {
			$selected = $url == $match ? 'selected="selected"' : '';
			$html .= '<option value="'.$url.'" '.$selected.'>'.$url.'</option>';
		}
		$html .= '</select>';

		return $html;
	}

	function get_regex($pattern, $rphone, $phone) {
		$regex = '';
		$nphone = '';
		for ($i = 0, $j = 0; $i < strlen($pattern); $i++) {
			switch ($pattern[$i]) {
			case 'd':
				$regex .= isset($rphone[$j]) ? $rphone[$j] : '';
				$nphone .= isset($phone[$j]) ? $phone[$j] : '';
				$j++;
				break;
			case ' ':
				$regex .= ' ';
				$nphone .= ' ';
				break;
			case '.':
			case '-':
			case '(':
			case ')':
				$regex .= '\\'.$pattern[$i];
				$nphone .= $pattern[$i];
				break;
			}
		}
		return array($regex, $nphone);
	}

	function handle_config_options_page() {
		$err = false;

		if (!empty($_POST['mma_save_config'])) {
			$fields = array(
				'username', 'auth_key', 'customer_id', 'conversion_name', 'phones', 'assist_mode',
				'notify_email', 'notify_mmsupport',
				);
			foreach ($fields as $field) {
				$value = isset($_POST[$field]) ? $_POST[$field] : '';

				switch ($field) {
				case 'phones':
					$list = explode(',', $value);
					$value = array();
					foreach ($list as $phone) {
						$phone = substr(preg_replace('/\D/', '', $phone), -10);
						if (!empty($phone)) $value[] = '+1'.$phone;
					}
				}

				$this->options[$field] = $value;
			}

			$this->options['notify_mmsent'] = false;
			$this->options['not_paid_errors'] = 0;
		}

		$data = $this->get_phone_data();

		if (is_array($data)) {
			$this->options['auth_valid'] = true;
			$this->options['phone_list'] = $this->merge_phone_data($data['phone_list']);
		} else {
			$this->options['auth_valid'] = false;
		}

		$validDefault = false;
		if ($this->options['auth_valid'] && !empty($_POST['mma_save_phones'])) {
			$this->options['replace_list'] = array();
			for ($i = 0; $i < count($_POST['repl_phone']); $i++) {
				$phone = $this->format_phone($_POST['repl_phone'][$i], '+1##########');
				if (!$phone) continue;

				$this->options['replace_list'][$phone] = array(
					'var1'=>$_POST['phone_repl_var1'][$i],
					'var2'=>$_POST['phone_repl_var2'][$i],
					);
			}

			foreach ($_POST['phone_when'] as $phone=>$data) {
				$this->options['phone_list'][$phone]['group'] = $_POST['phone_group'][$phone];
				$this->options['phone_list'][$phone]['var1'] = $_POST['phone_var1'][$phone];
				$this->options['phone_list'][$phone]['var2'] = $_POST['phone_var2'][$phone];
				$this->options['phone_list'][$phone]['when'] = $_POST['phone_when'][$phone];
				$this->options['phone_list'][$phone]['refurl'] = $_POST['phone_setting'][$phone]['refurl'];

				if (   $this->options['phone_list'][$phone]['when'] == 'default'
				    && $this->options['phone_list'][$phone]['var1'] == ''
				    && $this->options['phone_list'][$phone]['var2'] == '') $validDefault = true;
			}

			$groups = $this->get_phone_variables('group', $this->options['phone_list'], array());
			foreach ($this->options['phone_groups'] as $group=>$group_data) {
				if (!in_array($group, $groups)) unset($this->options['phone_groups'][$group]);
			}

			if ($validDefault) {
				$this->save_phone_data();
			} else if ($this->options['assist_mode'] != 'javascript') {
				$err = 'no default phone number was defined.';
			}
		}


		if ($err) {
			$this->post_error = $err;
		} else {
			$this->save_options();
			$this->post_message = 'Your changes were sucessfully saved. ';
		}
	}

	function merge_phone_data($data) {
		$phone_list = $this->options['phone_list'];
		foreach ($data as $phone=>$phone_data) {
			if (isset($phone_list[$phone])) {
				$phone_list[$phone] = array_merge($phone_list[$phone], $phone_data);
			} else {
				$phone_list[$phone] = $phone_data;
			}
		}

		foreach ($phone_list as $phone=>$phone_data) {
			if (!isset($data[$phone]) && !in_array($phone, $this->options['phones'])) {
				unset($phone_list[$phone]);
			}
		}

		foreach ($this->options['phones'] as $phone) {
			if (!isset($phone_list[$phone])) {
				$phone_list[$phone] = array('title'=>'Phone '.$this->format_phone($phone));
			}
		}

		return $phone_list;
	}

	function normalize_phone($phone) {
		return substr(preg_replace('/\D/', '', $phone), -10);
	}

	function send_server_request($req_type, $params=array()) {
		$url = MSGMETRIC_SERVER_URL.'?msgmetric_worker='.$req_type;
		$auth_token = uniqid();
		$post_data = array_merge(array(
			'username'=>$this->options['username'],
			'auth_token'=>$auth_token,
			'auth_hash'=>md5($auth_token.$this->options['auth_key']),
			'version'=>1,
			), $params);
		$post_data = http_build_query($post_data);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
		$rc = curl_exec($ch);
		$info = curl_getinfo($ch);

		curl_close($ch);

		if (!in_array($info['http_code'], array(200, 201, 202, 204))) {
			error_log('mma_call_tracking: send_server_request: error('.$info['http_code'].'): '.$req_type.': '.var_export($params,1));
		}

		return $rc;
	}

	function save_phone_data() {
		$this->phone_data['phone_list'] = $this->options['phone_list'];

		$rc = $this->send_server_request('save_phone_data', $this->phone_data);
		return ($rc != 'auth' && $rc != 'error');
	}

	function send_notification($type) {
		switch ($type) {
		case 'not_paid':
			$explanation = 'Your web site received an AdWords ad click, but call tracking is not set up correctly to show an '.
				'AdWords tracking number.  Usually, this means you either do not have any phone numbers set up with the '.
				'"Display When: URL Params" option selected or you have an Ad that is not configured with the correct mma_term '.
				'parameter. Please check your call tracking settings to make sure they are correct.';
			break;
		}

		$message  = '<p>'.$explanation.'</p>';
		$message .= '<p>Details:';
		$message .=  '<div style="margin:.5em 1em">';
		$message .=   'GCLID: '.$_SESSION['MSGMETRIC_ASSISTANT_DEBUG_DATA']['gclid'].'<br/>';
		$message .=   'mma_term: '.$_SESSION['MSGMETRIC_ASSISTANT_DEBUG_DATA']['mma_term'].'<br/>';
		$message .=   'Referrer: '.$_SESSION['MSGMETRIC_ASSISTANT_DEBUG_DATA']['referrer'].'<br/>';
		$message .=   'User Agent: '.$_SESSION['MSGMETRIC_ASSISTANT_DEBUG_DATA']['user_agent'].'<br/>';
		if ($type == 'not_paid') {
			$message .= '# Clicks: '.$this->options['not_paid_errors'];
		}
		$message .=  '</div>';
		$message .= '</p>';

		wp_mail($this->options['notify_email'], 'MMA Call Tracking Notice', $message.'<p>Thanks for using Message Metric!</p>');

		if ($this->options['notify_mmsupport'] && !$this->options['notify_mmsent']) {
			$this->options['notify_mmsent'] = true;
			$this->save_options();

			$message .= '<br/>';
			$message .= '<div>Site: '.get_option('siteurl').'</div>';
			$message .= '<div>Admin: '.get_option('admin_email').'</div>';
			$message .= '<div>Config:</div>';
			$message .= '<pre>'.var_export($this->options,1).'</pre>';

			wp_mail('support@messagemetric.com', 'MMA Troubleshooting Notice', $message);
		}
	}

	/* shortcode_phone_fn
	 *
	 * Implements the [msgmetric_phone] shortcode.
	 */
	function shortcode_phone_fn($atts, $content=null) {
		$atts = shortcode_atts(array(
			'format'=>false,
			'location'=>false, /* Alias for 'var1' */
			'phone'=>false,
			'test'=>false, /* Alias for 'var2' */
			'var1'=>false,
			'var2'=>false,
			),
			$atts);

		$term = $_SESSION['MSGMETRIC_ASSISTANT_ADWORD_TERM'];
		$refurl = $_SERVER['HTTP_REFERER'];
		$gclid = $_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID'];

		if ($atts['location']) $atts['var1'] = $atts['location'];
		if ($atts['test']) $atts['var2'] = $atts['test'];

		$phone = $this->format_phone($this->get_msgmetric_phone($term, $refurl, $gclid, $atts['var1'], $atts['var2']));
		$alt_phone = $atts['phone'] ? $this->format_phone($atts['phone'], $atts['format']) : false;

		if ($alt_phone) {
			static $id = 1;
			$html  = '<span id="mma-phone-'.$id.'">'.$alt_phone.'</span>';
			$html .= '<script>document.getElementById("mma-phone-'.$id.'").innerHTML="'.$phone.'"</script>';
			$id++;
		} else {
			$html  = $this->format_phone($phone, $atts['format']);
		}

		return $html;
	}

}

endif;

global $mmAssistant;
$mmAssistant = new MessageMetricAssistant();

?>
