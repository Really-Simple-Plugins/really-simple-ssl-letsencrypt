<?php
defined('ABSPATH') or die();
/**
 * If shell functions exist, add an action to handle it.
 * @param $steps
 *
 * @return mixed
 */
function rsssl_shell_add_condition_actions($steps){

	if ( !isset($steps['lets-encrypt'])){
		return $steps;
	}

	$installation_index = array_search( 'installation', array_column( $steps['lets-encrypt'], 'id' ) );
	$installation_index ++;

	if ( rsssl_is_cpanel() ) {
		if ( function_exists('shell_exec') || function_exists('system') || function_exists('passthru') || function_exists('exec') ) {
			$steps['lets-encrypt'][ $installation_index ]['actions'][]
				= array(
				'description' => __( "Attempting to install certificate using shell...", "really-simple-ssl-shell" ),
				'action'      => 'rsssl_shell_installSSL',
				'attempts'    => 1,
				'status'      => 'inactive',
			);
		}
	}

	return $steps;
}
add_filter( 'rsssl_steps', 'rsssl_shell_add_condition_actions' );

/**
 * Install SSL using Shell, if possible
 *
 * @return RSSSL_RESPONSE
 */
function rsssl_shell_installSSL(){

	if ( rsssl_is_ready_for('installation') ) {
		$domains = RSSSL_LE()->letsencrypt_handler->get_subjects();
		if ( function_exists('shell_exec') || function_exists('system') || function_exists('passthru') || function_exists('exec') ) {
			if ( is_array($domains) && count($domains)>0 ) {
				foreach( $domains as $domain ) {
					$response_item = rsssl_shell_installSSLPerDomain($domain);
					//set on first iteration
					if ( !$response ) {
						$response = $response_item;
					}

					//override if not successfull, to always get the error.
					if ( $response->status !== 'success' ) {
						$response = $response_item;
					}
				}
			}

			if ( !$response ) {
				$response = new RSSSL_RESPONSE('error', 'stop', __("No valid list of domains.", "really-simple-ssl-shell"));
			}

			if ( $response->status === 'success' ) {
				update_option('rsssl_le_certificate_installed_by_rsssl', 'cpanel:shell');
			}

			return $response;
		} else {
			$status = 'error';
			$action = 'skip';
			$message = rsssl_get_manual_instructions_text(RSSSL_LE()->letsencrypt_handler->ssl_installation_url);
			return new RSSSL_RESPONSE($status, $action, $message);
		}

	} else {
		$status = 'error';
		$action = 'stop';
		$message = __("The system is not ready for the installation yet. Please run the wizard again.", "really-simple-ssl-shell");
		return new RSSSL_RESPONSE($status, $action, $message);
	}
}

/**
 * Attempt shell installation of SSL for one domain
 * @param $domain
 *
 * @return RSSSL_RESPONSE
 */

function rsssl_shell_installSSLPerDomain($domain){
	$key_file = get_option('rsssl_private_key_path');
	$cert_file = get_option('rsssl_certificate_path');
	$cabundle_file = get_option('rsssl_intermediate_path');

	$cert = file_get_contents($cert_file);
	$key = file_get_contents($key_file);
	$cabundle = file_get_contents($cabundle_file);

	if (function_exists('escapeshellarg')) {
		$enc_cert = escapeshellarg(urlencode(str_replace("\r\n", "\n", $cert)));
		$enc_key = escapeshellarg(urlencode(str_replace("\r\n", "\n", $key)));
		$enc_cacert = escapeshellarg(urlencode(str_replace("\r\n", "\n", $cabundle)));
	} else {
		$enc_cert = urlencode(str_replace("\r\n", "\n", $cert));
		$enc_key = urlencode(str_replace("\r\n", "\n", $key));
		$enc_cacert = urlencode(str_replace("\r\n", "\n", $cabundle));
	}

	if ( function_exists('shell_exec') ) {
		$shell = shell_exec("uapi SSL install_ssl domain=$domain cert=$enc_cert key=$enc_key cabundle=$enc_cacert");
	} else if (function_exists('system')) {
		ob_start();
		system("uapi SSL install_ssl domain=$domain cert=$enc_cert key=$enc_key cabundle=$enc_cacert", $var);
		$shell = ob_get_contents();
		ob_end_clean();
	} else if (function_exists('passthru')) {
		ob_start();
		passthru("uapi SSL install_ssl domain=$domain cert=$enc_cert key=$enc_key cabundle=$enc_cacert", $var);
		$shell = ob_get_contents();
		ob_end_clean();
	} else if (function_exists('exec')) {
		exec("uapi SSL install_ssl domain=$domain cert=$enc_cert key=$enc_key cabundle=$enc_cacert", $output, $var);
		$shell = implode(',', $output);
	}

	if ( empty($shell) ) {
		$message = rsssl_get_manual_instructions_text(RSSSL_LE()->letsencrypt_handler->ssl_installation_url);
		return new RSSSL_RESPONSE('error', 'skip', $message );
	}

	$shell = str_ireplace(array('<br>', '<br />', '<b>', '</b>', '\n'), array('', '', '', '', ''), $shell);
	$fbr = stripos(htmlentities($shell), 'domain:');
	$finalshell = substr(htmlentities($shell), $fbr);
	$line_explode = explode("\n", $finalshell);

	$res_arr = array();
	foreach ($line_explode as $item) {
		$res_param = explode(":", $item);
		$res_arr[trim($res_param[0])] = isset($res_param[1]) ? $res_param[1] : '';
	}

	if ($res_arr['status'] == 1) {
		$message = sprintf(__("SSL successfully installed on %s","really-simple-ssl-shell"), $domain);
		return new RSSSL_RESPONSE('success', 'continue', $message );
	} else {
		$message = rsssl_get_manual_instructions_text(RSSSL_LE()->letsencrypt_handler->ssl_installation_url);
		return new RSSSL_RESPONSE('error', 'skip', $message );
	}

}