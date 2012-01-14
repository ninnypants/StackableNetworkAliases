<?php
/*
Plugin Name: Stackable Network Alias Creator
Plugin URI: http://ninnypants.com/
Description: Auto creates aliases when a WordPress Miltisite site is created on a subdomain install using Stackable's API.
Author: NINNYPANTS
Version: .7
Author URI: http://ninnypants.com/
*/

// remove and delete all setting
register_uninstall_hook(__FILE__, 'sna_uninstall');
function sna_uninstall(){
	delete_option('sna_settings');
}


add_action('network_admin_menu', 'sna_menu');
function sna_menu(){
	add_submenu_page('settings.php', 'Stackable Alias Settings', 'Stackable Alias Settings', 'manage_network', 'sna-settings', 'sna_settings');	
}

function sna_settings(){
	
	$sna_settings = get_site_option('sna_settings', array('account-id' => null, 'api-key-id' => null, 'api-key' => null, 'host-id' => null));

	if(isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'stackable-settings-update')){
		
		$sna_settings['api-key-id'] = $_POST['api-key-id'];
		$sna_settings['api-key'] = $_POST['api-key'];
		// $sna_settings['account-id'] = $_POST['account-id'];
		$sna_settings['host-id'] = empty($_POST['host-id']) ? sna_search_host_id($sna_settings['api-key-id'], $sna_settings['api-key']) : $_POST['host-id'];



		if(update_site_option('sna_settings', $sna_settings)){
			add_site_option('sna_settings', $sna_settings);
		}

	}

	?>
	<style type="text/css">
	div.postbox div.inside{
		margin: 10px;
	}
	</style>
	<div class="wrap metabox-holder">
		<h2>Stackable Aliases</h2>
		
		<form action="" method="post">
		<div class="postbox">
			<h3>API Settings</h3>
			<div class="inside">
			<p>API Key ID<br />
			<input type="text" name="api-key-id" id="api-key-id" value="<?php echo esc_attr($sna_settings['api-key-id']); ?>" /></p>
			<p>API Key<br />
			<input type="password" name="api-key" id="api-key" value="<?php echo esc_attr($sna_settings['api-key']); ?>" /></p>
			<p>Host ID<br />
			<input type="text" name="host-id" id="host-id" value="<?php echo esc_attr($sna_settings['host-id']); ?>" /></p>
			</div>
		</div>
		
		<input type="submit" name="submit" value="Update" class="button-primary" style="margin-bottom: 20px;" />
		<?php wp_nonce_field('stackable-settings-update'); ?>
		</form>
		

	</div>

	<?php
}


function sna_search_host_id($api_key_id, $api_key){
	$ch = curl_init('https://api.stackable.com/Site/find');
	$json = '{"hostname": "'.$_SERVER['HTTP_HOST'].'"}';
	$date = date('c');
	$signature = base64_encode(hash_hmac('sha1', $date.$json, $api_key, true));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-type: application/json',
		'Customer-Key-ID: '.$api_key_id,
		'Signature: '.$signature,
		'Signature-Version: 1',
		'Signature-Method: HMAC/SHA1',
		'Signature-Created: '.$date,
	));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSLVERSION, 3);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	$ret = curl_exec($ch);
	curl_close($ch);

	$j = json_decode($ret);
	return $j->result[0]->id;
}

add_action('wpmu_new_blog', 'sna_create_alias', 0, 6);
function sna_create_alias($blog_id, $user_id, $domain, $path, $site_id, $meta){
	if(!is_subdomain_install()){
		return;
	}
	$sna_settings = get_site_option('sna_settings');
	$slug = str_replace(array('http://', '/'), '', $domain);
	$json = '{"id": '.$sna_settings['host-id'].', "aliases": ["'.$slug.'"], "createDNS": true}';
	$date = date('c');
	$signature = base64_encode(hash_hmac('sha1', $date.$json, $api_key, true));
	$ch = curl_init('https://api.stackable.com/Site/addAlias');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-type: application/json',
		'Customer-Key-ID: '.$api_key_id,
		'Signature: '.$signature,
		'Signature-Version: 1',
		'Signature-Method: HMAC/SHA1',
		'Signature-Created: '.$date,
	));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSLVERSION, 3);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	$ret = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
}