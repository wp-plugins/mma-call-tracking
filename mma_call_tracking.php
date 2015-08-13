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
Version: 2.0.3
Author URI: http://www.messagemetric.com
*/

if (!class_exists("MessageMetricAssistant")) :

if (!defined('MSGMETRIC_SERVER_URL')) define('MSGMETRIC_SERVER_URL', 'https://app.messagemetric.com/');


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

		if (!empty($_POST['mma_save_config']) || !empty($_POST['mma_save_phones'])) $this->handle_config_options_page();

		$this->init_session();
		add_action('init', array(&$this, 'init_plugin'));
		if (is_admin()) {
			/* Dashboard initialization */
			add_action('admin_menu', array(&$this, 'add_menu_pages'));
		} else {
			add_action('parse_request', array(&$this, 'parse_request'));
			add_action('wp_footer', array(&$this, 'format_javascript'));
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

	function admin_notice_auth_invalid() {
		echo '<div class="error"><p><strong>Error connecting to Message Metric. Please make your username name and password are configured correctly.</strong></p></div>';
	}

	function deactivate_plugin() {
	}

	function filter_plugin_actions($links, $file) {
		$settings_link = '<a href="admin.php?page='.$this->plugin_plug.'_menu">'.__('Settings').'</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	function init_plugin() {
		if (!empty($this->options['username']) && !$this->options['auth_valid']) {
			add_action('admin_notices', array(&$this, 'admin_notice_auth_invalid'));
		}
	}

	function init_session() {
		if (!session_id()) session_start();
		if (!empty($_REQUEST['mma_term']) && empty($_SESSION['MSGMETRIC_ASSISTANT_ADWORD_TERM'])) {
			$_SESSION['MSGMETRIC_ASSISTANT_ADWORD_TERM'] = $_REQUEST['mma_term'];
		}
		if (!empty($_REQUEST['gclid']) && empty($_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID'])) {
			$_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID'] = $_REQUEST['gclid'];
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

	</table>
	<p class="submit">
		<input type="submit" class="button-primary" name="mma_save_config" value="<?php _e('Save Settings') ?>" />
	</p>

  </form>
</div>

<?php
		echo $this->format_settings_footer();
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

  <form name="<?php echo $this->plugin_plug ?>'_phones_form" method="post" autocomplete="off">
	<table class="form-table">

	<?php if ($this->options['assist_mode'] == 'javascript') { ?>

	<tr valign="top"><th scope="row"><h3 style="margin:0">Phone Numbers:</h3></th></tr>
	<tr><td>Enter the phone numbers that you want to want to have replaced with Message Metric numbers.</td></tr>

	<tr><td>

	<table width="100%" class="replacement-list phone-list">
	<tr><th><strong>Phone #</strong></th><th><strong>Var #1</strong></th><th><strong>Var #2</strong></th><th>Action</th></tr>
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
		echo   '<th><strong>Var #1</strong></th>';
		echo   '<th><strong>Var #2</strong></th>';
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

	echo $this->format_settings_footer();
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
	jQuery('.phone-group,.phone-var1,.phone-var2').change(function(){
		var $opt = jQuery(this).find('option:selected');
		var $parent = jQuery(this).closest('tr');
		var type = jQuery(this).data('type');
		var val = $opt.val();

		if (val === '-add-') {
			var vname = prompt('Please enter a '+(type=='group'?'group name':'value')+':', '');
			vname !== null && (vname = vname.replace(/[^0-9A-Za-z _]+/, ''));
			if (vname !== null && $opt.parent().find('option[value="'+vname+'"]').length === 0) {
				var $opt = jQuery('<option value="'+vname+'">'+vname+'</option>');
				jQuery('.phone-'+type).append($opt);
				jQuery(this).val(vname);
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
			alert('At least one phone number must be have Display When set to "Default" and have no Var #1 and no Var #2 value.');
			return false;
		}
	});
});
</script>
<?php
	}

	function format_javascript() {
		$url = str_replace(array('http:', 'https:'), array('', ''), get_site_url()).'/?mma=replace';
		$gclid = "&gclid='+\$mma('gclid')+'";
		$term = "&term='+\$mma('mma_term')+'";
		$refurl = "&refurl='+d.referrer+'";
		$key = '&key='.$this->options['auth_key'];
		foreach ($this->options['replace_list'] as $phone=>$phone_data) {
			$phone = $this->normalize_phone($phone);
			$phones[] = '&phone[]='.$phone.'-'.$phone_data['var1'].'-'.$phone_data['var2'];
		}
		$phones = is_array($phones) ? ('&'.implode('&', $phones)) : '';

		$js  = '<script>';
		$js .=  '!function(d,undefined){var $mma=function(e){e=e.replace(/[\[]/,"\[").replace(/[\]]/,"\]");';
		$js .=  'var t=new RegExp("[\?&]"+e+"=([^&#]*)"),n=t.exec(location.search);return n===null?"":decodeURIComponent(n[1].replace(/\+/g," "))};';
		$js .=  'd.write(\'\x3Cscript src="'.$url.$gclid.$term.$refurl.$key.$phones.'">\x3C/script>\');';
		$js .=  '}(document);';
		$js .= '</script>';

		echo $js;
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

	function format_settings_footer() {
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
		$html .= '<p>Use the <code>var1</code> and <code>var2</code> values to restrict the phone numbers that are displayed. This may be useful, ';
		$html .=   'for example, if your business has multiple locations. In that case you could create a <code>var1</code> value for each location. ';
		$html .=   '<code>var1="Los Angeles"</code> could then be used to display only phone numbers associated ';
		$html .=   'with Los Angeles. <code>var2</code> values may be used to further distinguish when phone numbers are displayed such as when ';
		$html .=   'your web site is set up for split testing. ';
		$html .=   'The optional <code>var1</code> and <code>var2</code> values tell Message Metric to only ';
		$html .=   'display the phone numbers matching the values identified. ';
		$html .= '</p>';

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

		/* If there is a gclid and the matched phone number is a paid number, record the click. */
		if ($this->options['phone_list'][$match]['when'] == 'paid' && !empty($gclid)) {
			$click_data = array(
				'customer_id'=>$this->options['customer_id'],
				'conversion'=>$this->options['conversion_name'],
				'gclid'=>$gclid,
				'phone'=>$match,
				'match'=>$did_match ? 1 : 0);

			if (!empty($this->options['customer_id'])) {
				$this->send_server_request('record_ad_click', $click_data);
			} else {
				error_log('mma_call_tracking: error recording adword click, missing customer id: '.
					var_export($click_data,1));
			}
			unset($_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID']);
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

	function handle_config_options_page() {
		$err = false;

		if (!empty($_POST['mma_save_config'])) {
			$fields = array(
				'username', 'auth_key', 'customer_id', 'conversion_name', 'phones', 'assist_mode',
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
			} else {
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

	function parse_request($wp) {
		if (!empty($_GET['mma']) && $_GET['mma'] == 'replace') {
			require 'mma_js.php';
			exit;
		}
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

		return $rc;
	}

	function save_phone_data() {
		$this->phone_data['phone_list'] = $this->options['phone_list'];

		$rc = $this->send_server_request('save_phone_data', $this->phone_data);
		return ($rc != 'auth' && $rc != 'error');
	}

	/* shortcode_phone_fn
	 *
	 * Implements the [msgmetric_phone] shortcode.
	 */
	function shortcode_phone_fn($atts, $content=null) {
		$atts = shortcode_atts(array(
			'format'=>false,
			'phone'=>false,
			'var1'=>false,
			'var2'=>false,
			),
			$atts);

		$term = $_SESSION['MSGMETRIC_ASSISTANT_ADWORD_TERM'];
		$refurl = $_SERVER['HTTP_REFERER'];
		$gclid = $_SESSION['MSGMETRIC_ASSISTANT_ADWORD_GCLID'];

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
