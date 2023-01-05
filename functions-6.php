<?php
defined('ABSPATH') or die();
/**
 * If shell functions exist, add an action to handle it.
 * @param $steps
 *
 * @return mixed
 */
function rsssl_shell_add_condition_actions($fields){

	$installation_index = array_search( 'installation', array_column( $fields, 'id' ) );
	if ( rsssl_is_cpanel() ) {
		if ( function_exists('shell_exec') || function_exists('system') || function_exists('passthru') || function_exists('exec') ) {
			$fields[ $installation_index ]['actions'][] = [
				'description' => __( "Attempting to install certificate using shell...", "really-simple-ssl-shell" ),
				'action'      => 'rsssl_shell_installSSL',
				'attempts'    => 1,
				'status'      => 'inactive',
			];
		}
	}
	return $fields;
}
add_filter( 'rsssl_fields', 'rsssl_shell_add_condition_actions' );

/**
 * Run the shell install
 *
 * @param $data
 * @param $test
 * @param $request
 *
 * @return false|mixed|RSSSL_RESPONSE
 */
function rsssl_shell_run_shell_install($data, $test, $request){
	if ( ! current_user_can('manage_security') ) {
		return new RSSSL_RESPONSE(
			'error',
			'stop',
			__( "Permission denied.", 'really-simple-ssl' )
		);
	}

	if ($test === 'rsssl_shell_installSSL') {
		$data = rsssl_shell_installSSL();
	}
	return $data;
}
add_filter("rsssl_run_test", 'rsssl_shell_run_shell_install', 10, 3);

/**
 * Install SSL using Shell, if possible
 *
 * @return RSSSL_RESPONSE
 */
function rsssl_shell_installSSL(){

	if ( rsssl_is_ready_for('installation') ) {
		$domains = RSSSL_LE()->letsencrypt_handler->get_subjects();
		$response = false;
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
		}

		$status  = 'error';
		$action  = 'skip';
		$message = rsssl_get_manual_instructions_text(RSSSL_LE()->letsencrypt_handler->ssl_installation_url);

		return new RSSSL_RESPONSE($status, $action, $message);
	}

	$status  = 'error';
	$action  = 'stop';
	$message = __("The system is not ready for the installation yet. Please run the wizard again.", "really-simple-ssl-shell");

	return new RSSSL_RESPONSE($status, $action, $message);
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
		$shell = ob_get_clean();
	} else if (function_exists('passthru')) {
		ob_start();
		passthru("uapi SSL install_ssl domain=$domain cert=$enc_cert key=$enc_key cabundle=$enc_cacert", $var);
		$shell = ob_get_clean();
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
	}

	$message = rsssl_get_manual_instructions_text(RSSSL_LE()->letsencrypt_handler->ssl_installation_url);

	return new RSSSL_RESPONSE('error', 'skip', $message );

}