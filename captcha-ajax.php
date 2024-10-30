<?php
/*
Plugin Name: Captcha Ajax
Plugin URI: https://captcha-ajax.eu
Description: Captcha anti-spam. Login form, registration form, lost password form. The "Ajax" method allows the "Captcha" to work fine even if a page cache is active.
Version: 1.10.0
Author: Alessandro Lin
License: GPL-2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: captcha-ajax
Domain Path: /languages
*/


/**
 * Copyright 2024 Alessandro Lin
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace CaptAjx;

use \WP_Error;
use WPCF7_TagGenerator;
use WPCF7_FormTagsManager;
use WPForms;

if (!defined('ABSPATH')) { exit; }

final class Ser {
	static $checkFail = '';
	static $ip = '';
}

/* 
 * Disable the plugin in exceptional cases:
 * Administrative page impossible to reach, in processing etc. etc.
 * Unlock only the login page accessible from / wp-admin /
 * It also works if inserted in wp-config.php
 */
// define( 'CAPTCHAJAX_STOP', true );

\add_action( 'init', function() {
        \load_plugin_textdomain('captcha-ajax', false, dirname(\plugin_basename(__FILE__)).'/languages');
    }
);

\add_action( 'admin_menu', function() {
    \add_options_page(
        \esc_html__('Captcha-Ajax Settings' , 'captcha-ajax'),
        \esc_html__('Captcha-Ajax Settings', 'captcha-ajax' ),
        'manage_options',
		'captcha-ajax',
        'CaptAjx\captcha_admin');

	if(\current_user_can('manage_options')){
		\add_filter( 'plugin_action_links_'. \plugin_basename(__FILE__), 'CaptAjx\add_link_plugin', 10, 4 );
	}
});

\add_action( 'activated_plugin', function() {
	    if( empty( \esc_html( \get_option('wpCap_login') )) ){ \update_option( 'wpCap_login', 'yes' ); }
	    if( empty( \esc_html( \get_option('wpCap_register') )) ){ \update_option( 'wpCap_register', 'yes' ); }
	    if( empty( \esc_html( \get_option('wpCap_image') )) ){ \update_option( 'wpCap_image', 'DE' ); }	
    }
);

\add_action( 'login_form', 'CaptAjx\captcha_Login' );
\add_filter( 'login_form_middle','CaptAjx\captcha_wp_login', 20, 1 );
\add_filter( 'authenticate', 'CaptAjx\captcha_authenticate', 20, 1 );
\add_action( 'register_form', 'CaptAjx\captcha_Register_form' );
\add_filter( 'registration_errors','CaptAjx\captcha_Register_validate' );
\add_action( 'lostpassword_form', 'CaptAjx\captcha_lostpassword' );
\add_filter( 'lostpassword_post', 'CaptAjx\captcha_lostpassword_validate', 10, 1 );
\add_action( 'comment_form', 'CaptAjx\captcha_comment' );
\add_filter( 'preprocess_comment', 'CaptAjx\captcha_comment_validate', 10, 1 );
\add_action( 'wpcf7_admin_init', 'CaptAjx\captcha_cf7_button', 90 );
\add_action( 'wpcf7_init', 'CaptAjx\add_shortcode_captchajax_for_cf7' );
\add_filter('wpcf7_validate','CaptAjx\captcha_cf7_validate', 90, 1);
\add_filter( 'wpforms_display_submit_before','CaptAjx\captcha_wpf_display');
\add_action( 'wpforms_process', 'CaptAjx\captcha_wpf_validate', 10, 3 );
\add_filter( 'forminator_render_button_markup', 'CaptAjx\captcha_forminator_display', 10 );
\add_filter('forminator_custom_form_submit_errors', 'CaptAjx\captcha_forminator_validate' );
\add_action( 'wp_ajax_nopriv_capaction', 'CaptAjx\get_captchAjax' );
\add_action( 'wp_ajax_capaction', 'CaptAjx\get_captchAjax' );
\add_action( 'wp_head', 'CaptAjx\icons_style', 5);
\add_action( 'login_head', 'CaptAjx\icons_style', 5);
\add_action( 'wp_head' ,'CaptAjx\head_style', 5 );
\add_action( 'login_head', 'CaptAjx\head_style', 5 );
\add_action( 'admin_head', function(){
	?>
	<style type='text/css'>
		td.cp75w select { width:75px;margin:0; }
		tr th.px260 { width:260px; }
		fieldset span.cpInpImm { display: block; margin-bottom:20px; }
		fieldset label span.cpLabImm { display:inline-block; width:120px; }
		fieldset label img.cpImgImm { width:200px; vertical-align:middle; }
	</style>
	<?php
}, 5 );

\add_action( 'rest_api_init', function () {
	\register_rest_route( 'captcha-ajax/v1', 'transients_expired', array(
	  'methods' => \WP_REST_Server::READABLE,
	  'callback' => 'CaptAjx\cap_Rest_transients',
	  'permission_callback' => '__return_true'
	) );
} );

function add_link_plugin(array $links, $plugin_file, $plugin_data, $context){
	$mylink = array('<a href="' . \admin_url( 'options-general.php?page=captcha-ajax' ) . '">' . \esc_html__( 'Settings', 'captcha-ajax' ) . '</a>');
	return array_merge( $links, $mylink );
}

function cap_Rest_transients(){
	if( \esc_html( \get_transient('wpCap_transients_expired') !== '1' )){
		\delete_expired_transients();
		\set_transient('wpCap_transients_expired', '1', 7200);
		return \rest_ensure_response( ['Cleaned expired transients' => 'yes'] );
	}
	return \rest_ensure_response( ['Cleaned expired transients' => 'none', 'Expired transients were cleaned less than 2 hours ago' => ''] );
}

function captcha_Login() {
	if( \esc_html( \get_option('wpCap_login') == 'yes' )){
		echo htmlBefore();
		echo htmlAfter();
	}
}

function captcha_wp_login( $content ){
	if( \esc_html( \get_option('wpCap_login') == 'yes' )){
		$content .= htmlBefore();
		$content .= htmlAfter();
	}
	return $content;
}

function captcha_authenticate( $user ){
	if (empty( $user )) { return; }

	if( defined('CAPTCHAJAX_STOP')  && ( !empty( constant('CAPTCHAJAX_STOP')) ) ){ return $user; }

	if( \esc_html( \get_option( 'wpCap_login') == 'yes' )){

		!null === 'SEL21' ? '' : define('SEL21', '<label id="capt_err" class="bookmarkClass" for="captcha_code_error">');

		if( \esc_html( \get_option( 'wpCap_failBan') == 'yes' )){

			!null === 'LOGINO_16' ? '' : define('LOGINO_16', 'Sorry. Login unavailable at this time');
		
			Ser::$checkFail = '1';
		
			$firewallIP = captcha_firewall_ip();
		
			switch ($firewallIP) {
				case ' #1':
					return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(LOGINO_16, 'captcha-ajax') . ' #1' . '</label>' );
					break;
				case ' #2':
					return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(LOGINO_16, 'captcha-ajax') . ' #2' . '</label>' );
					break;
				case ' #9':
					break;
				default:
					return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(LOGINO_16, 'captcha-ajax') . ' EFip' . '</label>' );
			}
				
		}
		
		if ( empty($_POST['captcha_code']) ) {
			!null === 'ABSENT_16' ? '' : define('ABSENT_16', 'Captcha absent error!');

			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);

				switch ($firewallCode) {
					case ' #5':
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(LOGINO_16, 'captcha-ajax') . $firewallCode .'</label>' );
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(ABSENT_16, 'captcha-ajax') . $firewallCode .'</label>' );
						break;
					default:
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(ABSENT_16, 'captcha-ajax') . ' #EF' . '</label>' );
				}
			}

			return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(ABSENT_16, 'captcha-ajax') . '</label>' );
		}

		if( !empty($_POST['hidCapUniq']) ){
			$nameTransient = 'wpCap_code' . \sanitize_text_field( $_POST['hidCapUniq'] );
		} else {
			!null === 'TOKENERR_16' ? '' : define('TOKENERR_16', 'Captcha token error!');

			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);	

				switch ($firewallCode) {
					case ' #5':
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(LOGINO_16, 'captcha-ajax') . $firewallCode .'</label>' );
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(TOKENERR_16, 'captcha-ajax') . $firewallCode .'</label>' );
						break;
					default:
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(TOKENERR_16, 'captcha-ajax') . ' #EFt' .'</label>' );
				}
			}
			return  new \WP_Error( 'captchaErr', SEL21 . \esc_html__(TOKENERR_16, 'captcha-ajax').'</label>' );
		}

		if(\esc_html( \get_option('wpCap_image') ) === 'IC'){
			if ( isset( $_POST['captcha_code'] )){
				$postT = explode( '@',  (\sanitize_text_field( $_POST['captcha_code'] )) );
				$trans = ( \get_transient($nameTransient) );
				if( !$trans ){ return  new \WP_Error( 'captchaErr', SEL21 . \esc_html__('Captcha error or captcha image expired!', 'captcha-ajax').'</label>' ); }
                
				if(( $postT[0] == \esc_html(html_entity_decode($trans[0])) )  && ( $postT[1] == \esc_html(html_entity_decode($trans[1])) )){
					return $user;
				}else {
					!null === 'IMAGERRic_16' ? '' : define('IMAGERRic_16', 'Captcha confirmation error!');

					if (Ser::$checkFail === '1'){
						$firewallCode = captcha_firewall_counter(Ser::$ip);

						switch ($firewallCode) {
							case ' #5':
								return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(IMAGERRic_16, 'captcha-ajax') . $firewallCode .'</label>' );
								break;
							case ' #4':
							case ' #6':
							case ' #7':
							case ' #8':
								return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(IMAGERRic_16, 'captcha-ajax') . $firewallCode .'</label>' );
								break;
							default:
								return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(IMAGERRic_16, 'captcha-ajax') . ' #EFic	' .'</label>' );
						}
					}
					return  new \WP_Error( 'captchaErr', SEL21 . \esc_html__(IMAGERRic_16, 'captcha-ajax').'</label>' );
				}
			}
		}

		if ( isset( $_POST['captcha_code'] ) && ( \esc_html(html_entity_decode( \get_transient($nameTransient) ) == \sanitize_text_field( $_POST['captcha_code'] ) ))) {
			return $user;
		} else {
			!null === 'IMAGERR_16' ? '' : define('IMAGERR_16', 'Captcha confirmation error!');	 // ver 1.9.0

			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);

				switch ($firewallCode) {
					case ' #5':
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(IMAGERR_16, 'captcha-ajax') . $firewallCode .'</label>' );
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(IMAGERR_16, 'captcha-ajax') . $firewallCode .'</label>' );
						break;
					default:
						return new \WP_Error( 'captchaErr', SEL21 . \esc_html__(IMAGERR_16, 'captcha-ajax') . ' #EFi	' .'</label>' );
				}
			}
			return  new \WP_Error( 'captchaErr', SEL21 . \esc_html__(IMAGERR_16, 'captcha-ajax').'</label>' );
		}
	}
	return $user;
}

function captcha_firewall_ip(){
	$ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
	if ($ip == (bool)false){ return ' #1'; }
	Ser::$ip = $ip;
	$searchBanned = []; 
	$searchBanned = \get_option('wpCap_Banned',['176.57.71.204' => ['banned' => 'N', 'expire'=> (int)0, 'counter'=> (int)3, 'expireCounter'=> (int)1714236139 ]]);
	$searchBanned = cap_sanitize_keys($searchBanned);	
	if(array_key_exists(Ser::$ip, $searchBanned)){
	 	if( \esc_html($searchBanned[Ser::$ip]['banned']) == 'Y'){
	 		if( (int) $searchBanned[Ser::$ip]['expire'] > time()){
	 			return ' #2';
	 		} else {
	 			unset($searchBanned[Ser::$ip]);
	 			\update_option( 'wpCap_Banned', $searchBanned );
	 		}
	 	}
	}
	return ' #9';
}

function captcha_firewall_counter($ip) {
	$Cleaner = function( array $searchBanned){
		if(count($searchBanned) > 10){
			$counter = (int) 0; $arrService = [];
			foreach($searchBanned as $key => $value){
				$counter += (int) 1; if ($counter > (int)10){ break;}
				switch ($value['banned']){
					case 'Y':
					case 'N':
						if ( (int)$value['expireCounter'] < time()){
							$arrService[] = $key;
						}
						break;
				}
				if ( count($arrService) > 2 ){ break; }
			}
			foreach($arrService as $val){ unset($searchBanned[$val]); }
		}
		return $searchBanned;
	};

	$searchBanned = [];
	$searchBanned = \get_option('wpCap_Banned',['176.57.71.204' => ['banned' => 'N', 'expire'=> (int)0, 'counter'=> (int)3, 'expireCounter'=> (int)1714236139 ]]);
	$searchBanned = cap_sanitize_keys($searchBanned);
				
	if( array_key_exists( $ip, $searchBanned ) ){
		switch ($searchBanned[$ip]['banned']){
			case 'N':
				if( (int)$searchBanned[$ip]['expireCounter'] < time() ){
					$searchBanned[$ip]['counter'] = 1;
					(int)$searchBanned[$ip]['expireCounter'] = (time() + 60);
					$searchBanned = $Cleaner($searchBanned);
					\update_option( 'wpCap_Banned', $searchBanned );
					return ' #4';
				} else {
					$searchBanned[$ip]['counter'] += (int)1;
					if( $searchBanned[$ip]['counter'] > (int)7){
						$searchBanned[$ip]['banned'] = 'Y';
						$ora = time();
						$searchBanned[$ip]['expire'] = ($ora + 1200);
						$searchBanned[$ip]['counter'] = 0;
						$searchBanned[$ip]['expireCounter'] = 0;
						\update_option( 'wpCap_Banned', $searchBanned );

						$ban_logs = [];
						$ban_logs = \get_option('wpCap_Ban_History', []);
						$ban_logs = cap_sanitize_keys($ban_logs);
						if( array_key_exists( $ip, $ban_logs ) ){
							array_push($ban_logs[$ip], $ora);
						} else {
							$ban_logs = array_merge($ban_logs, [$ip => [$ora]]);
						}
						\update_option('wpCap_Ban_History', $ban_logs);
		
						return ' #5';
					} else {
						\update_option( 'wpCap_Banned', $searchBanned );
						return ' #6';
					}
				}
				break;
			case 'Y':
			default:
				return ' #8';
		}
	} else {
		$searchBanned = array_merge($searchBanned, [$ip => ['banned' => 'N', 'expire'=> (int)0, 'counter'=> (int)1, 'expireCounter'=> time() + (int)60 ]]);
		$searchBanned = $Cleaner($searchBanned);
		\update_option( 'wpCap_Banned', $searchBanned );
		return ' #7';
	}
}

function cap_sanitize_keys($search){
	$serB = [];
	foreach($search as $key => $value){
		$key = \esc_html($key);
		!empty($key) ? $serB[$key] = $value : '';
	}
	return $serB;
}

function captcha_Register_form() {
	if( \esc_html(\get_option('wpCap_register') == 'yes' )){
		echo htmlBefore();
		echo htmlAfter();
	}
}

function captcha_Register_validate( $errors ) {
	if( \esc_html(\get_option('wpCap_register') == 'yes' )){
		if( \esc_html( \get_option( 'wpCap_failBan') == 'yes' )){
			!null === 'LOGINO_16' ? '' : define('LOGINO_16', 'Sorry. Login unavailable at this time');
			Ser::$checkFail = '1';
			$firewallIP = captcha_firewall_ip();

			switch ($firewallIP) {
				case ' #1':
					$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(LOGINO_16, 'captcha-ajax') . ' #1');
					return $errors;
					break;
				case ' #2':
					$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(LOGINO_16, 'captcha-ajax') . ' #2');
					return $errors;
					break;
				case ' #9':
					break;
				default:
					$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(LOGINO_16, 'captcha-ajax') . ' #Er');
					return $errors;
			}
		}

		if ( empty( $_POST['captcha_code'] )) {
			!null === 'ABSENT_16' ? '' : define('ABSENT_16', 'Captcha absent error!');

			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);

				switch ($firewallCode) {
					case ' #5':
						$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(LOGINO_16, 'captcha-ajax') . $firewallCode);
						return $errors;
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(ABSENT_16, 'captcha-ajax') . $firewallCode);
						return $errors;
						break;
					default:
						$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(ABSENT_16, 'captcha-ajax') . ' #Era');
						return $errors;
				}
			}

			$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(ABSENT_16, 'captcha-ajax'));
			return $errors;
		}

		if(!empty( $_POST['hidCapUniq'] )){
			$nameTransient = 'wpCap_code' . \sanitize_text_field( $_POST['hidCapUniq'] );
		} else {
			!null === 'TOKENERR_16' ? '' : define('TOKENERR_16', 'Captcha token error!');
			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);

				switch ($firewallCode) {
					case ' #5':
						$errors->add('captcha_token', \esc_html__(LOGINO_16, 'captcha-ajax') . $firewallCode );
						return $errors;
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						$errors->add('captcha_token', \esc_html__(TOKENERR_16, 'captcha-ajax') . $firewallCode );
						return $errors;
						break;
					default:
						$errors->add('captcha_token', \esc_html__(TOKENERR_16, 'captcha-ajax') . ' #Ert' );
						return $errors;
				}
			}
			$errors->add('captcha_token', \esc_html__(TOKENERR_16, 'captcha-ajax') );
			return $errors;
		}

		if(\esc_html( \get_option('wpCap_image') ) === 'IC'){
			$postT = explode( '@',  (\sanitize_text_field( $_POST['captcha_code'] )) );
			$trans = ( \get_transient($nameTransient) );
			if( !$trans ){ $errors->add( 'captcha_wrong', \esc_html__( 'Captcha error or captcha image expired!', 'captcha-ajax' )); return $errors; }

			if(( $postT[0] == \esc_html(html_entity_decode($trans[0])) )  && ( $postT[1] == \esc_html(html_entity_decode($trans[1])) )){
				return $errors;
			}else {
				!null === 'IMAGERRic_16' ? '' : define('IMAGERRic_16', 'Captcha confirmation error!');
				if (Ser::$checkFail === '1'){
					$firewallCode = captcha_firewall_counter(Ser::$ip);

					switch ($firewallCode) {
						case ' #5':
							$errors->add( 'captcha_wrong', \esc_html__( LOGINO_16, 'captcha-ajax' ) . $firewallCode);
							return $errors;
							break;
						case ' #4':
						case ' #6':
						case ' #7':
						case ' #8':
							$errors->add( 'captcha_wrong', \esc_html__( IMAGERRic_16, 'captcha-ajax' ) . $firewallCode);
							return $errors;
							break;
						default:
							$errors->add( 'captcha_wrong', \esc_html__( IMAGERRic_16, 'captcha-ajax' ) . ' #Eri');
							return $errors;
					}
				}
				$errors->add( 'captcha_wrong', \esc_html__( IMAGERRic_16, 'captcha-ajax' ) );
				return $errors;
			}
		}

		if ( isset( $_POST['captcha_code'] ) && ( \esc_html(html_entity_decode(\get_transient($nameTransient)) == \sanitize_text_field( $_POST['captcha_code'] ) ) )) {
			return $errors;
		} else {
			!null === 'IMAGERR_16' ? '' : define('IMAGERR_16', 'Captcha confirmation error!');
			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);
				switch ($firewallCode) {
					case ' #5':
						$errors->add( 'captcha_wrong', \esc_html__( LOGINO_16, 'captcha-ajax' ) . $firewallCode);
						return $errors;
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						$errors->add( 'captcha_wrong', \esc_html__( IMAGERR_16, 'captcha-ajax' ) . $firewallCode);
						return $errors;
						break;
					default:
						$errors->add( 'captcha_wrong', \esc_html__( IMAGERR_16, 'captcha-ajax' ) . ' #Erc');
						return $errors;
				}
			}
			$errors->add( 'captcha_wrong', \esc_html__( IMAGERR_16, 'captcha-ajax' ) );
			return $errors;
		}
	}
  	return $errors;
}

function captcha_lostpassword() {
	if( \esc_html(\get_option('wpCap_lost') == 'yes' )){
		echo htmlBefore();
		echo htmlAfter();
	}
}

function captcha_lostpassword_validate( $errors ) {
	if( \esc_html(\get_option('wpCap_lost') == 'yes' )){
		if( isset( $_REQUEST['user_login'] ) && "" == \sanitize_text_field( $_REQUEST['user_login'] ) ){ return $errors; }

		if( \esc_html( \get_option( 'wpCap_failBan') == 'yes' )){
			!null === 'LOGINO_16' ? '' : define('LOGINO_16', 'Sorry. Login unavailable at this time');
			Ser::$checkFail = '1';
			$firewallIP = captcha_firewall_ip();
			switch ($firewallIP) {
				case ' #1':
					$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(LOGINO_16, 'captcha-ajax') . ' #1');
					return $errors;
					break;
				case ' #2':
					$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(LOGINO_16, 'captcha-ajax') . ' #2');
					return $errors;
					break;
				case ' #9':		// IP sbannato o assente. Tutto bene fin qui
					break;
				default:
					$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(LOGINO_16, 'captcha-ajax') . ' #Er');
					return $errors;
			}
		}
	
		if ( empty( $_POST['captcha_code'] ) ) {
			!null === 'ABSENT_16' ? '' : define('ABSENT_16', 'Captcha absent error!');

			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);
				switch ($firewallCode) {
					case ' #5':
						$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(LOGINO_16, 'captcha-ajax') . $firewallCode);
						return $errors;
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(ABSENT_16, 'captcha-ajax') . $firewallCode);
						return $errors;
						break;
					default:
						$errors->add('captcha_blank', '<strong>'.\esc_html__('ERROR', 'captcha-ajax').'</strong>: '.\esc_html__(ABSENT_16, 'captcha-ajax') . ' #El');
						return $errors;
				}
			}

			$errors->add('captcha_absent',\esc_html__( ABSENT_16, 'captcha-ajax' ));
			return $errors;
		}
		
		if( !empty($_POST['hidCapUniq']) ){
			$nameTransient = 'wpCap_code' . \sanitize_text_field( $_POST['hidCapUniq'] );
		} else {
			!null === 'TOKENERR_16' ? '' : define('TOKENERR_16', 'Captcha token error!');

			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);
				switch ($firewallCode) {
					case ' #5':
						$errors->add('captcha_token', \esc_html__(LOGINO_16, 'captcha-ajax') . $firewallCode );
						return $errors;
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						$errors->add('captcha_token', \esc_html__(TOKENERR_16, 'captcha-ajax') . $firewallCode );
						return $errors;
						break;
					default:
						$errors->add('captcha_token', \esc_html__(TOKENERR_16, 'captcha-ajax') . ' #Ert' );
						return $errors;
				}

			}

			$errors->add( 'captcha_token', \esc_html__(TOKENERR_16, 'captcha-ajax') );
			return $errors;
		}

		if(\esc_html( \get_option('wpCap_image') ) === 'IC'){ 
			$postT = explode( '@',  (\sanitize_text_field( $_POST['captcha_code'] )) );
			$trans = ( \get_transient($nameTransient) );
			if( !$trans ){ $errors->add( 'captcha_wrong', \esc_html__( 'Captcha error o captcha image expired!', 'captcha-ajax' )); return $errors; }

			if(( $postT[0] == \esc_html(html_entity_decode($trans[0])) )  && ( $postT[1] == \esc_html(html_entity_decode($trans[1])) )){
				return $errors;
			}else {
				!null === 'IMAGERRic_16' ? '' : define('IMAGERRic_16', 'Captcha confirmation error!');

				if (Ser::$checkFail === '1'){
					$firewallCode = captcha_firewall_counter(Ser::$ip);
					switch ($firewallCode) {
						case ' #5':
							$errors->add( 'captcha_wrong', \esc_html__( LOGINO_16, 'captcha-ajax' ) . $firewallCode);
							return $errors;
							break;
						case ' #4':
						case ' #6':
						case ' #7':
						case ' #8':
							$errors->add( 'captcha_wrong', \esc_html__( IMAGERRic_16, 'captcha-ajax' ) . $firewallCode);
							return $errors;
							break;
						default:
							$errors->add( 'captcha_wrong', \esc_html__( IMAGERRic_16, 'captcha-ajax' ) . ' #Eri');
							return $errors;
					}
				}

				$errors->add( 'captcha_wrong', \esc_html__( IMAGERRic_16, 'captcha-ajax' ) );
				return $errors;
			}
		}

		if ( isset( $_POST['captcha_code'] ) && ( \esc_html(html_entity_decode(\get_transient($nameTransient)) == \sanitize_text_field( $_POST['captcha_code'] ) ))) {
			return $errors;
		} else {
			!null === 'IMAGERR_16' ? '' : define('IMAGERR_16', 'Captcha confirmation error!');	 // ver 1.9.0

			if (Ser::$checkFail === '1'){
				$firewallCode = captcha_firewall_counter(Ser::$ip);
				switch ($firewallCode) {
					case ' #5':
						$errors->add( 'captcha_wrong', \esc_html__( LOGINO_16, 'captcha-ajax' ) . $firewallCode);
						return $errors;
						break;
					case ' #4':
					case ' #6':
					case ' #7':
					case ' #8':
						$errors->add( 'captcha_wrong', \esc_html__( IMAGERR_16, 'captcha-ajax' ) . $firewallCode);
						return $errors;
						break;
					default:
						$errors->add( 'captcha_wrong', \esc_html__( IMAGERR_16, 'captcha-ajax' ) . ' #Erc');
						return $errors;
				}
			}

			$errors->add( 'captcha_wrong', \esc_html__( IMAGERR_16, 'captcha-ajax' ) );
			return $errors;
		}
	}
		return $errors;
}

function captcha_comment() {
	if(\esc_html(\get_option('wpCap_comments') == 'yes')) {
		if ( is_user_logged_in() && \esc_html(\get_option('wpCap_registered') == 'yes')) return;
		echo htmlBefore();
		echo htmlAfter();
	}
}

function captcha_comment_validate($commentdata) {
	if(\esc_html(\get_option('wpCap_comments') == 'yes')) {

		!null === 'SEL22' ? '' : define( 'SEL22', "<br><br><a href='javascript:history.back()'> << back</a>" );

		if (is_user_logged_in() && \esc_html(\get_option('wpCap_registered') == 'yes')) {
			return $commentdata;
		}
	
		if ( $commentdata['comment_type'] != '' && $commentdata['comment_type'] != 'comment' ) {
			return $commentdata;
		}
	
		if(empty($_POST['captcha_code'])){
			$erMs = \esc_html__('CAPTCHA cannot be empty. ', 'captcha-ajax' ); $erMs .= SEL22;
			\wp_die( $erMs );
		}

		if(!empty($_POST['hidCapUniq'])){
			$nameTransient = 'wpCap_code' . \sanitize_text_field($_POST['hidCapUniq']);
		}
		else {
			$erMs = \esc_html__('Error: Token CAPTCHA absent!.', 'captcha-ajax'); $erMs .= SEL22;
			\wp_die($erMs);
		}

		if(\esc_html( \get_option('wpCap_image') ) === 'IC'){ 
			$postT = explode( '@',  (\sanitize_text_field( $_POST['captcha_code'] )) );
			$trans = ( \get_transient($nameTransient) );
			if( !$trans ){ $erMs = \esc_html__('Captcha error o captcha image expired!', 'captcha-ajax'); \wp_die($erMs); }

			if(( $postT[0] == \esc_html(html_entity_decode($trans[0])) )  && ( $postT[1] == \esc_html(html_entity_decode($trans[1])) )){
				return $commentdata;
			}else {
				$erMs = \esc_html__('Error: Incorrect CAPTCHA.', 'captcha-ajax'); $erMs .= SEL22;
			    \wp_die($erMs);
			}
		}

		if( \esc_html(html_entity_decode(\get_transient($nameTransient))) == \sanitize_text_field($_POST['captcha_code']) ){
			return $commentdata;
		}
		else {
			$erMs = \esc_html__('Error: Incorrect CAPTCHA.', 'captcha-ajax'); $erMs .= SEL22;
			\wp_die($erMs);
		}
	}
	return $commentdata;
}

function captcha_cf7_button(){
	if( \esc_html(\get_option('wpCap_cf7_ax') ) == 'yes'){
		$formButton = WPCF7_TagGenerator::get_instance();

		$formButton->add(
			'captchajax_for_cf7',
			\esc_html__('Captcha-Ajax', 'captcha-ajax'),
			'CaptAjx\cf7Button_Callback',
			['nameless' => 1]
		);
	}
}

function cf7Button_Callback(){
	?>
<div class="control-box">
	<fieldset>
		<legend> <a href="https://captcha-ajax.eu" target="_blank"> Captcha Ajax for Contact Form 7 </a> </legend>
	</fieldset>
</div>
<div class="insert-box">
	<input style="margin-bottom:10px;" type="text" name="captchajax_for_cf7" class="tag code" readonly="readonly" onfocus="this.select()"/>
	<div class="submitbox">
		<input type="button" class="button button-primary insert-tag" value=" <?php \esc_html_e( 'Insert Captcha Tag', 'captcha-ajax' )  ?> "/>
	</div>
</div>
	<?php
}

function add_shortcode_captchajax_for_cf7(){
	if( \esc_html(\get_option('wpCap_cf7_ax') ) == 'yes'){
		\wpcf7_add_form_tag( 'captchajax_for_cf7', function(){
			return htmlBefore('cf7') . htmlAfter();
		});
	}
}

function captcha_cf7_validate($result){
	if( \esc_html(\get_option('wpCap_cf7_ax') ) == 'yes'){

		if(empty($_POST['captcha_code'])){
			$result->invalidate('','');
			return $result;
		}

		if(!empty($_POST['hidCapUniq'])){
			$nameTransient = 'wpCap_code' . \sanitize_text_field($_POST['hidCapUniq']);
		} else {
			$result->invalidate('','');
			return $result;
		}

		if(\esc_html( \get_option('wpCap_image') ) === 'IC'){
			$postT = explode( '@',  (\sanitize_text_field( $_POST['captcha_code'] )) );
			$trans = ( \get_transient($nameTransient) );
			if( !$trans ){ $result->invalidate('',''); return $result;}

			if(( $postT[0] == \esc_html(html_entity_decode($trans[0])) )  && ( $postT[1] == \esc_html(html_entity_decode($trans[1])) )){
				return $result;
			}else {
                $result->invalidate('','');
				return $result;
			}
		}

		if( \esc_html(html_entity_decode(\get_transient($nameTransient))) == \sanitize_text_field($_POST['captcha_code']) ){
			return $result;
		} else {
			$result->invalidate('','');
			return $result;
		}
	}
}

function captcha_wpf_display(){
	if( \esc_html( \get_option('wpCap_wpf_ax') ) == 'yes'){
		echo htmlBefore('wpf');
		echo htmlAfter();
	}
}

function captcha_wpf_validate( $fields, $entry, $form_data ){
    if( \esc_html( \get_option('wpCap_wpf_ax') ) == 'yes'){
		if( empty($_POST['captcha_code']) ){
			\wpforms()->process->errors[ $form_data[ 'id' ] ][ 'footer' ] = \esc_html__('CAPTCHA cannot be empty. ', 'captcha-ajax' );
			return;
		}

		if( !empty($_POST['hidCapUniq']) ){
			$nameTransient = 'wpCap_code' . \sanitize_text_field( $_POST['hidCapUniq'] );
		} else {
			\wpforms()->process->errors[ $form_data[ 'id' ] ][ 'footer' ] = \esc_html__('Error: Token CAPTCHA absent!.', 'captcha-ajax');
			return;
		}

		if(\esc_html( \get_option('wpCap_image') ) === 'IC'){
			$postT = explode( '@',  (\sanitize_text_field( $_POST['captcha_code'] )) );
			$trans = ( \get_transient($nameTransient) );
			if( !$trans ){ \wpforms()->process->errors[ $form_data[ 'id' ] ][ 'footer' ] = \esc_html__('Captcha error o captcha image expired!', 'captcha-ajax'); return;}

			if(( $postT[0] == \esc_html(html_entity_decode($trans[0])) )  && ( $postT[1] == \esc_html(html_entity_decode($trans[1])) )){
				return;
			}else {
                \wpforms()->process->errors[ $form_data[ 'id' ] ][ 'footer' ] = \esc_html__('Error: Incorrect CAPTCHA.', 'captcha-ajax');
				return;
			}
		}

		if( \esc_html(html_entity_decode(\get_transient($nameTransient))) == \sanitize_text_field( $_POST['captcha_code'] )){} 
		else {
			\wpforms()->process->errors[ $form_data[ 'id' ] ][ 'footer' ] = \esc_html__('Error: Incorrect CAPTCHA.', 'captcha-ajax');
		}
	}
}

function captcha_forminator_display($html){
	if( \esc_html(\get_option('wpCap_forminator_ax') ) == 'yes'){
		return $html . htmlBefore('fnator') . htmlAfter();
	}
}

function captcha_forminator_validate(){
	if( \esc_html(\get_option('wpCap_forminator_ax') ) == 'yes'){
		if(empty($_POST['captcha_code'])){
			throw new \Exception( \esc_html__('CAPTCHA cannot be empty. ', 'captcha-ajax' ) );
			return;
		}

		if(!empty($_POST['hidCapUniq'])){
			$nameTransient = 'wpCap_code' . \sanitize_text_field( $_POST['hidCapUniq'] );
		} else {
			throw new \Exception( \esc_html__('Error: Token CAPTCHA absent!.', 'captcha-ajax' ) );
			return;
		}

		if(\esc_html( \get_option('wpCap_image') ) === 'IC'){
			$postT = explode( '@',  (\sanitize_text_field( $_POST['captcha_code'] )) );
			$trans = ( \get_transient($nameTransient) );
			if( !$trans ){ throw new \Exception( \esc_html__('Captcha error o captcha image expired!', 'captcha-ajax' ) ); return;}

			if(( $postT[0] == \esc_html(html_entity_decode($trans[0])) )  && ( $postT[1] == \esc_html(html_entity_decode($trans[1])) )){
				return;
			}else {
                throw new \Exception( \esc_html__('Error: Incorrect CAPTCHA.', 'captcha-ajax' ) );
				return;
			}
		}

		if( \esc_html(html_entity_decode(\get_transient($nameTransient))) == \sanitize_text_field( $_POST['captcha_code'] )){
			return;
		} else {
			throw new \Exception( \esc_html__('Error: Incorrect CAPTCHA.', 'captcha-ajax' ) );
			return;
		}
	}
}

function htmlBefore($flag = ''){
	if( defined('CAPTCHAJAX_STOP')  && ( !empty( constant('CAPTCHAJAX_STOP')) ) ){ return; }

	if(\esc_html( \get_option('wpCap_image') ) === 'IC'){
		return htmlBefore_icons($flag);
	}
	if(\esc_html( \get_option('wpCap_image') ) === 'AR'){ !null === 'CAPTX' ? '' : define('CAPTX', 'Type the RESULT displayed above'); } else { !null === 'CAPTX' ? '' : define('CAPTX', 'Type the text displayed above'); }
	$ajaxURL = \esc_url_raw(admin_url( 'admin-ajax.php') . '?action=capaction');

	if( $flag === 'wpf'){
		return '<div class="login-form-captcha">
		<div style="display:block; text-align:left;"><b>' . \esc_html__( 'Captcha', 'captcha-ajax' )  . '</b>
			<span class="required">*</span>
			<button class="btnNewCapt" type="button" form="NothingForm" onclick="capAjaxInit_589()"> new Captcha</button>
			<div id="mpLoader" class="mploader_inactive"></div>
		</div>
		<div id="CapAjx_pl" style="border: 1px solid aqua;height: 50px; text-align:center; max-width:300px;" ></div>
		<div id="CapAjx_token" ></div>
		<div style="display:block; text-align:left;">' . \esc_html__(CAPTX) . ':</div>
		<input style="min-height: 40px; max-width: 300px;" id="captcha_code" name="captcha_code" type="text" />
		<input id="ajxURL" name="ajxURL" type="hidden" value="' .$ajaxURL . '" />
		</div>';
	}

	return '<div class="login-form-captcha">
			<div><b>' . \esc_html__( 'Captcha', 'captcha-ajax' )  . '</b>
				<span class="required">*</span>
				<button class="btnNewCapt" type="button" form="NothingForm" onclick="capAjaxInit_589()"> new Captcha</button>
				<div id="mpLoader" class="mploader_inactive"></div>
			</div>
			<div id="CapAjx_pl" style="border: 1px solid aqua;height: 50px; text-align:center; max-width:300px;" ></div>
			<div id="CapAjx_token" ></div>
			<div style="display: block;">' . \esc_html__(CAPTX) . ':</div>
			<input style="min-height: 40px; max-width: 300px;" id="captcha_code" name="captcha_code" type="text" />
			<input id="ajxURL" name="ajxURL" type="hidden" value="' .$ajaxURL . '" />
			</div>';
}

function htmlBefore_icons($flag = ''){
	$soloForms = '';
	if($flag === 'cf7' || $flag === 'wpf' || $flag === 'fnator'){
		$soloForms = '<div id="soloForms"></div>';
	}
	$ajaxURL = \esc_url_raw(admin_url( 'admin-ajax.php') . '?action=capaction');
	
	if( $flag === 'wpf'){
		return '<div class="login-form-captcha">
	<div style="display:block; text-align:left;"><b>' . \esc_html__( 'Captcha', 'captcha-ajax' )  . '</b>
		<span class="required">*</span>
		<button class="btnNewCapt" type="button" form="NothingForm" onclick="iconsAjaxInit_589()"> new Captcha</button>
		<div id="mpLoader" class="mploader_inactive"></div>
	</div>
	<div id="CapAjx_pl"></div>
	<div id="CapAjx_token" ></div>' . $soloForms .
	'<div class="labClickOnIcon" style="display:block; text-align:left;"><b>Click on icon <span id="nameValidIcon"></span></b></div>
	<div style="min-height: 40px; max-width: 300px;"></div>
	<input id="captcha_code" name="captcha_code" type="hidden" />
	<input id="ajxURL" name="ajxURL" type="hidden" value="' .$ajaxURL . '" />
</div>';
	}

	return '<div class="login-form-captcha">
	<div><b>' . \esc_html__( 'Captcha', 'captcha-ajax' )  . '</b>
		<span class="required">*</span>
		<button class="btnNewCapt" type="button" form="NothingForm" onclick="iconsAjaxInit_589()"> new Captcha</button>
		<div id="mpLoader" class="mploader_inactive"></div>
	</div>
	<div id="CapAjx_pl"></div>
	<div id="CapAjx_token" ></div>' . $soloForms .
	'<div class="labClickOnIcon"><b>Click on icon <span id="nameValidIcon"></span></b></div>
	<div style="min-height: 40px; max-width: 300px;"></div>
	<input id="captcha_code" name="captcha_code" type="hidden" />
	<input id="ajxURL" name="ajxURL" type="hidden" value="' .$ajaxURL . '" />
</div>';
}

function htmlAfter(){
	if( defined('CAPTCHAJAX_STOP')  && ( !empty( constant('CAPTCHAJAX_STOP')) ) ){ return; }

	if(\esc_html( \get_option('wpCap_image') ) === 'IC'){
		return htmlAfter_icons();
	}

	return '<script id="capAjaxAfter">function sp_589(){mpLoader.classList.toggle("mpLoader");mpLoader.classList.toggle("mploader_inactive");}
	function capAjaxInit_589(){sp_589();let url=ajxURL.value;fetch(url).then(response=>response.json()).then(data=>{sp_589();CapAjx_token.innerHTML=data.input;CapAjx_pl.innerHTML=data.image}).catch(err=>console.warn(err));}
	(capAjaxInit_589)();</script>';
}

function htmlAfter_icons(){
    return '<script id="icons_ajax">function sp_589(){mpLoader.classList.toggle("mpLoader");mpLoader.classList.toggle("mploader_inactive");}
	{function iconsAjaxInit_589(){sp_589();let url=ajxURL.value;fetch(url).then(response=>response.json()).then(data=>{sp_589();CapAjx_pl.innerHTML="";CapAjx_token.innerHTML=data.input;data.icons.forEach(element=>{let img=document.createElement("img");img.id=element[1];img.className="unselectedIcon";img.src=element[0];img.onclick=function(){const allIcons=document.querySelectorAll("div#CapAjx_pl img");allIcons.forEach(elem=>{if(elem.id!=this.id){elem.classList.remove("iconChoice");elem.classList.add("unselectedIcon");}
	else{elem.classList.remove("unselectedIcon");elem.classList.add("iconChoice");}});captcha_code.value=nameValidIcon.innerHTML+"@"+this.id;};CapAjx_pl.append(img);});nameValidIcon.innerHTML=data.keyValidCaptcha}).catch(err=>console.warn(err));}
	(iconsAjaxInit_589)();}</script><script id="subNewCaptcha">{if(document.querySelector("#soloForms")){const closestDiv=CapAjx_pl.closest("form");if(closestDiv){closestDiv.addEventListener("submit",iconsAjaxInit_589);}}}</script>';
}

function head_style(){
    ?>
    <style id="captcha-ajax-style" type='text/css'>
	div#mpLoader{display:inline-block}
.mpLoader{position:absolute;border:3px solid #e3efee;border-radius:50%;border-top:3px solid blue;width:40px;height:40px;-webkit-animation:spin 1s linear infinite;animation:spin 1s linear infinite}
@-webkit-keyframes spin{0%{-webkit-transform:rotate(0deg)}100%{-webkit-transform:rotate(360deg)}}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
.mploader_inactive{width:0}
.login-form-captcha{clear:both}
button.btnNewCapt{margin-left:20px!important;border:none!important;background:none!important;color:#2271b1!important;cursor:pointer!important;text-decoration:underline!important;font-size:small!important}
</style>
    <?php
}

function icons_style(){
	if(\esc_html( \get_option('wpCap_image') === 'IC' )) {
	?>
	<style id="captcha-ajax-icons">
	    div#CapAjx_pl{background-color:rgb(160,219,248);border:1px solid aqua;display:flex;justify-content:center;height:50px;max-width:298px}
div#CapAjx_pl img{margin:auto;padding:2px;border-radius:3px;max-width:38px;max-height:38px}
div#CapAjx_pl img.unselectedIcon:hover{background-color:whitesmoke;border:1px solid green;cursor:pointer;padding:1px}
div.labClickOnIcon{display:block;margin-top:10px}
.iconChoice{background-color:whitesmoke!important;box-shadow:1px 1px 3px chocolate!important;cursor:auto}
</style>
	<?php
	}
}

function get_captchAjax(){
	if ( !\wp_doing_ajax() ){ \wp_die( \esc_html__('Err no ajax call!', 'captcha-ajax' ) ); }

	$captchaAjaxTransient519 = uniqid();
	$nameTransient = 'wpCap_code' . \esc_html($captchaAjaxTransient519);

	$numberChars = \esc_html(\get_option( 'wpCap_no_chars' ));
	if( $numberChars < 3 || $numberChars > 6 ) { $numberChars = 3; }

	$captchaType= \esc_html(\get_option( 'wpCap_type' ));
	$captchaChars = \esc_html(\get_option( 'wpCap_letters' ));

	if( !empty($captchaType) ){
		if($captchaType== 'alphanumeric'){
			switch($captchaChars) {
				case 'maiusc':
					$allchars = '23456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
					break;
				case 'minusc':
					$allchars = '23456789bcdfghjkmnpqrstvwxyz';
					break;
				case 'maiusc_minusc':
					$allchars = '23456789bcdfghjkmnpqrstvwxyzABCEFGHJKMNPRSTVWXYZ';
					break;
				default:
					$allchars = '23456789bcdfghjkmnpqrstvwxyz';
					break;
			}
		}

		if( $captchaType== 'alphabets' ){
			switch($captchaChars){
				case 'maiusc':
					$allchars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
					break;
				case 'minusc':
					$allchars = 'bcdfghjkmnpqrstvwxyz';
					break;
				case 'maiusc_minusc':
					$allchars = 'bcdfghjkmnpqrstvwxyzABCEFGHJKMNPRSTVWXYZ';
					break;
				default:
					$allchars = 'abcdefghijklmnopqrstuvwxyz';
					break;
			}
		}

		if( $captchaType== 'numbers' ){
			$allchars = '0123456789';
		}
	} else {
		$allchars = '23456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	}

	$wpCapImage = \esc_html( \get_option('wpCap_image') );

	if( $wpCapImage === 'BW' ){

        $BWvars = new \stdClass;
		$BWvars->captchaAjaxTransient519 = $captchaAjaxTransient519;
	    $BWvars->nameTransient = $nameTransient;
	    $BWvars->numberChars = $numberChars;
	    $BWvars->allchars = $allchars;

		$hex2rgb = function($hexstr){
			$int = hexdec($hexstr);

			return [ "red" => 0xFF & ($int >> 0x10), "green" => 0xFF & ($int >> 0x8), "blue" => 0xFF & $int ];
	    };

		$image_width = 200;
		$image_height = 48;
		$random_dots = 0;
		$random_lines = 20;
	    $captcha_text_color="0x142864";
	    $captcha_noice_color = "0x142864";

		$font = __DIR__ . '/assets/monofont.ttf';

	    $code = '';

		$i = 0;
		while ($i < $BWvars->numberChars) {
			$code .= substr($BWvars->allchars, mt_rand(0, strlen($BWvars->allchars)-1), 1);
			$i++;
		}

		$font_size = $image_height * 0.75;
		$image = \imagecreate($image_width, $image_height);

		\imagecolorallocate($image, 255, 255, 255);

		$arr_text_color = $hex2rgb($captcha_text_color);
		$text_color = \imagecolorallocate($image, $arr_text_color['red'], $arr_text_color['green'], $arr_text_color['blue']);

		$arr_noice_color = $hex2rgb($captcha_noice_color);
		$image_noise_color = \imagecolorallocate($image, $arr_noice_color['red'], $arr_noice_color['green'], $arr_noice_color['blue']);

	    for( $i=0; $i<$random_dots; $i++ ) {
		    \imagefilledellipse($image, mt_rand(0,$image_width), mt_rand(0,$image_height), 2, 3, $image_noise_color);
        }

	    for( $i=0; $i<$random_lines; $i++ ) {
		    \imageline($image, mt_rand(0,$image_width), mt_rand(0,$image_height), mt_rand(0,$image_width), mt_rand(0,$image_height), $image_noise_color);
	    }

	    $textbox = \imagettfbbox($font_size, 0, $font, $code);
	    $x = ($image_width - $textbox[4])/2;
	    $y = ($image_height - $textbox[5])/2;

		\imagefttext($image, $font_size, 0, $x, $y, $text_color, $font , $code);

		\set_transient(htmlentities($BWvars->nameTransient), htmlentities($code), 900);

		$inputToken = '<input type="hidden" id="hidCapUniq" name="hidCapUniq" value="'. \esc_html( $BWvars->captchaAjaxTransient519 ) . '">';

		ob_start();
		\imagepng($image);
		$htmlImage = sprintf( '<img style="display:inline !important;" src="data:image/png;base64,%s" alt="O6AWp0Lm">', base64_encode(ob_get_clean()) );
		\imagedestroy($image);

		echo json_encode(['input' => $inputToken, 'image' => $htmlImage]);
		\wp_die();
	}

	if(  $wpCapImage === 'MC' ){
		$MCvars = new \stdClass;
	    $MCvars->numberChars = $numberChars;
	    $MCvars->allchars = $allchars;

		$image_width = 200;
		$image_height = 50;
		$font_size = (int) $image_height * 0.50;
		$font = __DIR__ . '/assets/FasterOne-Regular.ttf';
		$chars_length = $MCvars->numberChars;
		$captcha_characters = $MCvars->allchars;

		$image = \imagecreatetruecolor($image_width, $image_height);
		$bg_color = \imagecolorallocate($image, 245, 235, 235);
		\imagefilledrectangle($image, 0, 0, $image_width, $image_height, $bg_color);

		$color = \imagecolorallocate($image, 255, 255, 255);
		$total = round($image_width/5);
		for ($i = 0; $i < $total; $i++) { 
			$x = rand(0, $image_width);
			$y = rand(0, $image_height);
			$w = rand(5, 10);
			\imagerectangle($image, $x, $y, $x+$w, $y+$w, $color);
		}

		$xw = (int) ($image_width/$chars_length);
		$x = 0;

		$token = '';
		for($i = 0; $i < $chars_length; $i++) {
			$letter = $captcha_characters[rand(0, strlen($captcha_characters)-1)];
			$token .= $letter;
			$font_color = \imagecolorallocate($image, rand(0,255), rand(0,255), rand(0,255));
			$x = ($i == 0 ? 0 : $xw * $i);
			\imagettftext($image, $font_size, rand(-20,20), $x, rand(23, $image_height-4), $font_color, $font, $letter);
		}

		\set_transient(htmlentities($nameTransient), htmlentities($token), 900);

		$inputToken = '<input type="hidden" id="hidCapUniq" name="hidCapUniq" value="'. \esc_html( $captchaAjaxTransient519 ) . '">';

		$typeImages = []; $typeImages = \gd_info();
		if( !empty($typeImages['WebP Support']) ){
			ob_start();
			\imagewebp($image);
			$htmlImage = sprintf( '<img style="display:inline !important;" src="data:image/webp;base64,%s" alt="O6AWp0Lm">', base64_encode(ob_get_clean()) );
			\imagedestroy($image);
		
			echo json_encode(['input' => $inputToken, 'image' => $htmlImage]);
			\wp_die();
		}

		ob_start();
		\imagepng($image);
		$htmlImage = \sprintf( '<img style="display:inline !important;" src="data:image/png;base64,%s" alt="O6AWp0Lm">', base64_encode(ob_get_clean()) );
		\imagedestroy($image);

		echo \json_encode(['input' => $inputToken, 'image' => $htmlImage]);
		\wp_die();
	}

	if( $wpCapImage === 'IC' ){

		$ICvars = new \stdClass;
		$ICvars->nameTransient = $nameTransient;
		$ICvars->numberChars = $numberChars;
		
		$ICvars->allbase64 = [
			'anchor'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1NzYgNTEyIj48cGF0aCBkPSJNMzIwIDk2YTMyIDMyIDAgMSAxIC02NCAwIDMyIDMyIDAgMSAxIDY0IDB6bTIxLjEgODBDMzY3IDE1OC44IDM4NCAxMjkuNCAzODQgOTZjMC01My00My05Ni05Ni05NnMtOTYgNDMtOTYgOTZjMCAzMy40IDE3IDYyLjggNDIuOSA4MEgyMjRjLTE3LjcgMC0zMiAxNC4zLTMyIDMyczE0LjMgMzIgMzIgMzJoMzJWNDQ4SDIwOGMtNTMgMC05Ni00My05Ni05NnYtNi4xbDcgN2M5LjQgOS40IDI0LjYgOS40IDMzLjkgMHM5LjQtMjQuNiAwLTMzLjlMOTcgMjYzYy05LjQtOS40LTI0LjYtOS40LTMzLjkgMEw3IDMxOWMtOS40IDkuNC05LjQgMjQuNiAwIDMzLjlzMjQuNiA5LjQgMzMuOSAwbDctN1YzNTJjMCA4OC40IDcxLjYgMTYwIDE2MCAxNjBoODAgODBjODguNCAwIDE2MC03MS42IDE2MC0xNjB2LTYuMWw3IDdjOS40IDkuNCAyNC42IDkuNCAzMy45IDBzOS40LTI0LjYgMC0zMy45bC01Ni01NmMtOS40LTkuNC0yNC42LTkuNC0zMy45IDBsLTU2IDU2Yy05LjQgOS40LTkuNCAyNC42IDAgMzMuOXMyNC42IDkuNCAzMy45IDBsNy03VjM1MmMwIDUzLTQzIDk2LTk2IDk2SDMyMFYyNDBoMzJjMTcuNyAwIDMyLTE0LjMgMzItMzJzLTE0LjMtMzItMzItMzJIMzQxLjF6Ii8+PC9zdmc+',
			'bridge'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1NzYgNTEyIj48cGF0aCBkPSJNMzIgMzJDMTQuMyAzMiAwIDQ2LjMgMCA2NFMxNC4zIDk2IDMyIDk2SDcydjY0SDBWMjg4YzUzIDAgOTYgNDMgOTYgOTZ2NjRjMCAxNy43IDE0LjMgMzIgMzIgMzJoMzJjMTcuNyAwIDMyLTE0LjMgMzItMzJWMzg0YzAtNTMgNDMtOTYgOTYtOTZzOTYgNDMgOTYgOTZ2NjRjMCAxNy43IDE0LjMgMzIgMzIgMzJoMzJjMTcuNyAwIDMyLTE0LjMgMzItMzJWMzg0YzAtNTMgNDMtOTYgOTYtOTZWMTYwSDUwNFY5Nmg0MGMxNy43IDAgMzItMTQuMyAzMi0zMnMtMTQuMy0zMi0zMi0zMkgzMnpNNDU2IDk2djY0SDM3NlY5Nmg4MHpNMzI4IDk2djY0SDI0OFY5Nmg4MHpNMjAwIDk2djY0SDEyMFY5Nmg4MHoiLz48L3N2Zz4=',
			'car'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNMTM1LjIgMTE3LjRMMTA5LjEgMTkySDQwMi45bC0yNi4xLTc0LjZDMzcyLjMgMTA0LjYgMzYwLjIgOTYgMzQ2LjYgOTZIMTY1LjRjLTEzLjYgMC0yNS43IDguNi0zMC4yIDIxLjR6TTM5LjYgMTk2LjhMNzQuOCA5Ni4zQzg4LjMgNTcuOCAxMjQuNiAzMiAxNjUuNCAzMkgzNDYuNmM0MC44IDAgNzcuMSAyNS44IDkwLjYgNjQuM2wzNS4yIDEwMC41YzIzLjIgOS42IDM5LjYgMzIuNSAzOS42IDU5LjJWNDAwdjQ4YzAgMTcuNy0xNC4zIDMyLTMyIDMySDQ0OGMtMTcuNyAwLTMyLTE0LjMtMzItMzJWNDAwSDk2djQ4YzAgMTcuNy0xNC4zIDMyLTMyIDMySDMyYy0xNy43IDAtMzItMTQuMy0zMi0zMlY0MDAgMjU2YzAtMjYuNyAxNi40LTQ5LjYgMzkuNi01OS4yek0xMjggMjg4YTMyIDMyIDAgMSAwIC02NCAwIDMyIDMyIDAgMSAwIDY0IDB6bTI4OCAzMmEzMiAzMiAwIDEgMCAwLTY0IDMyIDMyIDAgMSAwIDAgNjR6Ii8+PC9zdmc+',
			'caravan'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NDAgNTEyIj48cGF0aCBkPSJNMCAxMTJDMCA2Ny44IDM1LjggMzIgODAgMzJINDE2Yzg4LjQgMCAxNjAgNzEuNiAxNjAgMTYwVjM1MmgzMmMxNy43IDAgMzIgMTQuMyAzMiAzMnMtMTQuMyAzMi0zMiAzMmwtMzIgMEgyODhjMCA1My00MyA5Ni05NiA5NnMtOTYtNDMtOTYtOTZIODBjLTQ0LjIgMC04MC0zNS44LTgwLTgwVjExMnpNMzIwIDM1Mkg0NDhWMjU2SDQxNmMtOC44IDAtMTYtNy4yLTE2LTE2czcuMi0xNiAxNi0xNmgzMlYxNjBjMC0xNy43LTE0LjMtMzItMzItMzJIMzUyYy0xNy43IDAtMzIgMTQuMy0zMiAzMlYzNTJ6TTk2IDEyOGMtMTcuNyAwLTMyIDE0LjMtMzIgMzJ2NjRjMCAxNy43IDE0LjMgMzIgMzIgMzJIMjI0YzE3LjcgMCAzMi0xNC4zIDMyLTMyVjE2MGMwLTE3LjctMTQuMy0zMi0zMi0zMkg5NnptOTYgMzM2YTQ4IDQ4IDAgMSAwIDAtOTYgNDggNDggMCAxIDAgMCA5NnoiLz48L3N2Zz4=',
			'cup'=>'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIGFyaWEtaGlkZGVuPSJ0cnVlIiByb2xlPSJpbWciIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDY0MCA1MTIiPjxwYXRoIGZpbGw9ImN1cnJlbnRDb2xvciIgZD0iTTE5MiAzODRoMTkyYzUzIDAgOTYtNDMgOTYtOTZoMzJhMTI4IDEyOCAwIDAwMC0yNTZIMTIwYy0xMyAwLTI0IDExLTI0IDI0djIzMmMwIDUzIDQzIDk2IDk2IDk2ek01MTIgOTZhNjQgNjQgMCAwMTAgMTI4aC0zMlY5NmgzMnptNDggMzg0SDQ4Yy00NyAwLTYxLTY0LTM2LTY0aDU4NGMyNSAwIDExIDY0LTM2IDY0eiIvPjwvc3ZnPg==',
			'dice'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NDAgNTEyIj48cGF0aCBkPSJNMjc0LjkgMzQuM2MtMjguMS0yOC4xLTczLjctMjguMS0xMDEuOCAwTDM0LjMgMTczLjFjLTI4LjEgMjguMS0yOC4xIDczLjcgMCAxMDEuOEwxNzMuMSA0MTMuN2MyOC4xIDI4LjEgNzMuNyAyOC4xIDEwMS44IDBMNDEzLjcgMjc0LjljMjguMS0yOC4xIDI4LjEtNzMuNyAwLTEwMS44TDI3NC45IDM0LjN6TTIwMCAyMjRhMjQgMjQgMCAxIDEgNDggMCAyNCAyNCAwIDEgMSAtNDggMHpNOTYgMjAwYTI0IDI0IDAgMSAxIDAgNDggMjQgMjQgMCAxIDEgMC00OHpNMjI0IDM3NmEyNCAyNCAwIDEgMSAwLTQ4IDI0IDI0IDAgMSAxIDAgNDh6TTM1MiAyMDBhMjQgMjQgMCAxIDEgMCA0OCAyNCAyNCAwIDEgMSAwLTQ4ek0yMjQgMTIwYTI0IDI0IDAgMSAxIDAtNDggMjQgMjQgMCAxIDEgMCA0OHptOTYgMzI4YzAgMzUuMyAyOC43IDY0IDY0IDY0SDU3NmMzNS4zIDAgNjQtMjguNyA2NC02NFYyNTZjMC0zNS4zLTI4LjctNjQtNjQtNjRINDYxLjdjMTEuNiAzNiAzLjEgNzctMjUuNCAxMDUuNUwzMjAgNDEzLjhWNDQ4ek00ODAgMzI4YTI0IDI0IDAgMSAxIDAgNDggMjQgMjQgMCAxIDEgMC00OHoiLz48L3N2Zz4=',
			'dog'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1NzYgNTEyIj48cGF0aCBkPSJNMzA5LjYgMTU4LjVMMzMyLjcgMTkuOEMzMzQuNiA4LjQgMzQ0LjUgMCAzNTYuMSAwYzcuNSAwIDE0LjUgMy41IDE5IDkuNUwzOTIgMzJoNTIuMWMxMi43IDAgMjQuOSA1LjEgMzMuOSAxNC4xTDQ5NiA2NGg1NmMxMy4zIDAgMjQgMTAuNyAyNCAyNHYyNGMwIDQ0LjItMzUuOCA4MC04MCA4MEg0NjQgNDQ4IDQyNi43bC01LjEgMzAuNS0xMTItNjR6TTQxNiAyNTYuMUw0MTYgNDgwYzAgMTcuNy0xNC4zIDMyLTMyIDMySDM1MmMtMTcuNyAwLTMyLTE0LjMtMzItMzJWMzY0LjhjLTI0IDEyLjMtNTEuMiAxOS4yLTgwIDE5LjJzLTU2LTYuOS04MC0xOS4yVjQ4MGMwIDE3LjctMTQuMyAzMi0zMiAzMkg5NmMtMTcuNyAwLTMyLTE0LjMtMzItMzJWMjQ5LjhjLTI4LjgtMTAuOS01MS40LTM1LjMtNTkuMi02Ni41TDEgMTY3LjhjLTQuMy0xNy4xIDYuMS0zNC41IDIzLjMtMzguOHMzNC41IDYuMSAzOC44IDIzLjNsMy45IDE1LjVDNzAuNSAxODIgODMuMyAxOTIgOTggMTkyaDMwIDE2SDMwMy44TDQxNiAyNTYuMXpNNDY0IDgwYTE2IDE2IDAgMSAwIC0zMiAwIDE2IDE2IDAgMSAwIDMyIDB6Ii8+PC9zdmc+',
			'envelope'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNNDggNjRDMjEuNSA2NCAwIDg1LjUgMCAxMTJjMCAxNS4xIDcuMSAyOS4zIDE5LjIgMzguNEwyMzYuOCAzMTMuNmMxMS40IDguNSAyNyA4LjUgMzguNCAwTDQ5Mi44IDE1MC40YzEyLjEtOS4xIDE5LjItMjMuMyAxOS4yLTM4LjRjMC0yNi41LTIxLjUtNDgtNDgtNDhINDh6TTAgMTc2VjM4NGMwIDM1LjMgMjguNyA2NCA2NCA2NEg0NDhjMzUuMyAwIDY0LTI4LjcgNjQtNjRWMTc2TDI5NC40IDMzOS4yYy0yMi44IDE3LjEtNTQgMTcuMS03Ni44IDBMMCAxNzZ6Ii8+PC9zdmc+',
			'euro_sign'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMjAgNTEyIj48cGF0aCBkPSJNNDguMSAyNDBjLS4xIDIuNy0uMSA1LjMtLjEgOHYxNmMwIDIuNyAwIDUuMyAuMSA4SDMyYy0xNy43IDAtMzIgMTQuMy0zMiAzMnMxNC4zIDMyIDMyIDMySDYwLjNDODkuOSA0MTkuOSAxNzAgNDgwIDI2NCA0ODBoMjRjMTcuNyAwIDMyLTE0LjMgMzItMzJzLTE0LjMtMzItMzItMzJIMjY0Yy01Ny45IDAtMTA4LjItMzIuNC0xMzMuOS04MEgyNTZjMTcuNyAwIDMyLTE0LjMgMzItMzJzLTE0LjMtMzItMzItMzJIMTEyLjJjLS4xLTIuNi0uMi01LjMtLjItOFYyNDhjMC0yLjcgLjEtNS40IC4yLThIMjU2YzE3LjcgMCAzMi0xNC4zIDMyLTMycy0xNC4zLTMyLTMyLTMySDEzMC4xYzI1LjctNDcuNiA3Ni04MCAxMzMuOS04MGgyNGMxNy43IDAgMzItMTQuMyAzMi0zMnMtMTQuMy0zMi0zMi0zMkgyNjRDMTcwIDMyIDg5LjkgOTIuMSA2MC4zIDE3NkgzMmMtMTcuNyAwLTMyIDE0LjMtMzIgMzJzMTQuMyAzMiAzMiAzMkg0OC4xeiIvPjwvc3ZnPg==',
			'faucet'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNMTkyIDk2djEyTDk2IDk2Yy0xNy43IDAtMzIgMTQuMy0zMiAzMnMxNC4zIDMyIDMyIDMybDk2LTEyIDMxLTMuOSAxLS4xIDEgLjEgMzEgMy45IDk2IDEyYzE3LjcgMCAzMi0xNC4zIDMyLTMycy0xNC4zLTMyLTMyLTMybC05NiAxMlY5NmMwLTE3LjctMTQuMy0zMi0zMi0zMnMtMzIgMTQuMy0zMiAzMnpNMzIgMjU2Yy0xNy43IDAtMzIgMTQuMy0zMiAzMnY2NGMwIDE3LjcgMTQuMyAzMiAzMiAzMkgxMzIuMWMyMC4yIDI5IDUzLjkgNDggOTEuOSA0OHM3MS43LTE5IDkxLjktNDhIMzUyYzE3LjcgMCAzMiAxNC4zIDMyIDMyczE0LjMgMzIgMzIgMzJoNjRjMTcuNyAwIDMyLTE0LjMgMzItMzJjMC04OC40LTcxLjYtMTYwLTE2MC0xNjBIMzIwbC0yMi42LTIyLjZjLTYtNi0xNC4xLTkuNC0yMi42LTkuNEgyNTZWMTgwLjJsLTMyLTQtMzIgNFYyMjRIMTczLjNjLTguNSAwLTE2LjYgMy40LTIyLjYgOS40TDEyOCAyNTZIMzJ6Ii8+PC9zdmc+',
			'flag'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0NDggNTEyIj48cGF0aCBkPSJNNjQgMzJDNjQgMTQuMyA0OS43IDAgMzIgMFMwIDE0LjMgMCAzMlY2NCAzNjggNDgwYzAgMTcuNyAxNC4zIDMyIDMyIDMyczMyLTE0LjMgMzItMzJWMzUybDY0LjMtMTYuMWM0MS4xLTEwLjMgODQuNi01LjUgMTIyLjUgMTMuNGM0NC4yIDIyLjEgOTUuNSAyNC44IDE0MS43IDcuNGwzNC43LTEzYzEyLjUtNC43IDIwLjgtMTYuNiAyMC44LTMwVjY2LjFjMC0yMy0yNC4yLTM4LTQ0LjgtMjcuN2wtOS42IDQuOGMtNDYuMyAyMy4yLTEwMC44IDIzLjItMTQ3LjEgMGMtMzUuMS0xNy42LTc1LjQtMjItMTEzLjUtMTIuNUw2NCA0OFYzMnoiLz48L3N2Zz4=',
			'guitar'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNNDY1IDdjLTkuNC05LjQtMjQuNi05LjQtMzMuOSAwTDM4MyA1NWMtMi40IDIuNC00LjMgNS4zLTUuNSA4LjVsLTE1LjQgNDEtNzcuNSA3Ny42Yy00NS4xLTI5LjQtOTkuMy0zMC4yLTEzMSAxLjZjLTExIDExLTE4IDI0LjYtMjEuNCAzOS42Yy0zLjcgMTYuNi0xOS4xIDMwLjctMzYuMSAzMS42Yy0yNS42IDEuMy00OS4zIDEwLjctNjcuMyAyOC42Qy0xNiAzMjguNC03LjYgNDA5LjQgNDcuNSA0NjQuNXMxMzYuMSA2My41IDE4MC45IDE4LjdjMTcuOS0xNy45IDI3LjQtNDEuNyAyOC42LTY3LjNjLjktMTcgMTUtMzIuMyAzMS42LTM2LjFjMTUtMy40IDI4LjYtMTAuNSAzOS42LTIxLjRjMzEuOC0zMS44IDMxLTg1LjkgMS42LTEzMWw3Ny42LTc3LjYgNDEtMTUuNGMzLjItMS4yIDYuMS0zLjEgOC41LTUuNWw0OC00OGM5LjQtOS40IDkuNC0yNC42IDAtMzMuOUw0NjUgN3pNMjA4IDI1NmE0OCA0OCAwIDEgMSAwIDk2IDQ4IDQ4IDAgMSAxIDAtOTZ6Ii8+PC9zdmc+',
			'heart'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNNDcuNiAzMDAuNEwyMjguMyA0NjkuMWM3LjUgNyAxNy40IDEwLjkgMjcuNyAxMC45czIwLjItMy45IDI3LjctMTAuOUw0NjQuNCAzMDAuNGMzMC40LTI4LjMgNDcuNi02OCA0Ny42LTEwOS41di01LjhjMC02OS45LTUwLjUtMTI5LjUtMTE5LjQtMTQxQzM0NyAzNi41IDMwMC42IDUxLjQgMjY4IDg0TDI1NiA5NiAyNDQgODRjLTMyLjYtMzIuNi03OS00Ny41LTEyNC42LTM5LjlDNTAuNSA1NS42IDAgMTE1LjIgMCAxODUuMXY1LjhjMCA0MS41IDE3LjIgODEuMiA0Ny42IDEwOS41eiIvPjwvc3ZnPg==',
			'horse_head'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NDAgNTEyIj48cGF0aCBkPSJNNjQgNDY0VjMxNi45YzAtMTA4LjQgNjguMy0yMDUuMSAxNzAuNS0yNDEuM0w0MDQuMiAxNS41QzQyNS42IDcuOSA0NDggMjMuOCA0NDggNDYuNGMwIDExLTUuNSAyMS4yLTE0LjYgMjcuM0w0MDAgOTZjNDguMSAwIDkxLjIgMjkuOCAxMDguMSA3NC45bDQ4LjYgMTI5LjVjMTEuOCAzMS40IDQuMSA2Ni44LTE5LjYgOTAuNWMtMTYgMTYtMzcuOCAyNS4xLTYwLjUgMjUuMWgtMy40Yy0yNi4xIDAtNTAuOS0xMS42LTY3LjYtMzEuN2wtMzIuMy0zOC43Yy0xMS43IDQuMS0yNC4yIDYuNC0zNy4zIDYuNGwtLjEgMCAwIDBjLTYuMyAwLTEyLjUtLjUtMTguNi0xLjVjLTMuNi0uNi03LjItMS40LTEwLjctMi4zbDAgMGMtMjguOS03LjgtNTMuMS0yNi44LTY3LjgtNTIuMmMtNC40LTcuNi0xNC4yLTEwLjMtMjEuOS01LjhzLTEwLjMgMTQuMi01LjggMjEuOWMyNCA0MS41IDY4LjMgNzAgMTE5LjMgNzEuOWw0Ny4yIDcwLjhjNCA2LjEgNi4yIDEzLjIgNi4yIDIwLjRjMCAyMC4zLTE2LjUgMzYuOC0zNi44IDM2LjhIMTEyYy0yNi41IDAtNDgtMjEuNS00OC00OHpNMzkyIDIyNGEyNCAyNCAwIDEgMCAwLTQ4IDI0IDI0IDAgMSAwIDAgNDh6Ii8+PC9zdmc+',
			'house'=>'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIGFyaWEtaGlkZGVuPSJ0cnVlIiByb2xlPSJpbWciIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDU3NiA1MTIiPjxwYXRoIGZpbGw9ImN1cnJlbnRDb2xvciIgZD0iTTQ4OCAzMTN2MTQzYzAgMTMtMTEgMjQtMjQgMjRIMzQ4Yy03IDAtMTItNS0xMi0xMlYzNTZjMC03LTUtMTItMTItMTJoLTcyYy03IDAtMTIgNS0xMiAxMnYxMTJjMCA3LTUgMTItMTIgMTJIMTEyYy0xMyAwLTI0LTExLTI0LTI0VjMxM2MwLTQgMi03IDQtMTBsMTg4LTE1NGM1LTQgMTEtNCAxNiAwbDE4OCAxNTRjMiAzIDQgNiA0IDEwem04NC02MWwtODQtNjlWNDRjMC02LTUtMTItMTItMTJoLTU2Yy03IDAtMTIgNi0xMiAxMnY3M2wtODktNzRhNDggNDggMCAwMC02MSAwTDQgMjUyYy01IDQtNSAxMi0xIDE3bDI1IDMxYzUgNSAxMiA1IDE3IDFsMjM1LTE5M2M1LTQgMTEtNCAxNiAwbDIzNSAxOTNjNSA1IDEzIDQgMTctMWwyNS0zMWM0LTYgNC0xMy0xLTE3eiIvPjwvc3ZnPg==',
			'jet_fighter'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NDAgNTEyIj48cGF0aCBkPSJNMTYwIDI0YzAtMTMuMyAxMC43LTI0IDI0LTI0SDI5NmMxMy4zIDAgMjQgMTAuNyAyNCAyNHMtMTAuNyAyNC0yNCAyNEgyODBMMzg0IDE5Mkg1MDAuNGM3LjcgMCAxNS4zIDEuNCAyMi41IDQuMUw2MjUgMjM0LjRjOSAzLjQgMTUgMTIgMTUgMjEuNnMtNiAxOC4yLTE1IDIxLjZMNTIyLjkgMzE1LjljLTcuMiAyLjctMTQuOCA0LjEtMjIuNSA0LjFIMzg0TDI4MCA0NjRoMTZjMTMuMyAwIDI0IDEwLjcgMjQgMjRzLTEwLjcgMjQtMjQgMjRIMTg0Yy0xMy4zIDAtMjQtMTAuNy0yNC0yNHMxMC43LTI0IDI0LTI0aDhWMzIwSDE2MGwtNTQuNiA1NC42Yy02IDYtMTQuMSA5LjQtMjIuNiA5LjRINjRjLTE3LjcgMC0zMi0xNC4zLTMyLTMyVjI4OGMtMTcuNyAwLTMyLTE0LjMtMzItMzJzMTQuMy0zMiAzMi0zMlYxNjBjMC0xNy43IDE0LjMtMzIgMzItMzJIODIuN2M4LjUgMCAxNi42IDMuNCAyMi42IDkuNEwxNjAgMTkyaDMyVjQ4aC04Yy0xMy4zIDAtMjQtMTAuNy0yNC0yNHpNODAgMjQwYy04LjggMC0xNiA3LjItMTYgMTZzNy4yIDE2IDE2IDE2aDY0YzguOCAwIDE2LTcuMiAxNi0xNnMtNy4yLTE2LTE2LTE2SDgweiIvPjwvc3ZnPg==',
			'key'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNMzM2IDM1MmM5Ny4yIDAgMTc2LTc4LjggMTc2LTE3NlM0MzMuMiAwIDMzNiAwUzE2MCA3OC44IDE2MCAxNzZjMCAxOC43IDIuOSAzNi44IDguMyA1My43TDcgMzkxYy00LjUgNC41LTcgMTAuNi03IDE3djgwYzAgMTMuMyAxMC43IDI0IDI0IDI0aDgwYzEzLjMgMCAyNC0xMC43IDI0LTI0VjQ0OGg0MGMxMy4zIDAgMjQtMTAuNyAyNC0yNFYzODRoNDBjNi40IDAgMTIuNS0yLjUgMTctN2wzMy4zLTMzLjNjMTYuOSA1LjQgMzUgOC4zIDUzLjcgOC4zek0zNzYgOTZhNDAgNDAgMCAxIDEgMCA4MCA0MCA0MCAwIDEgMSAwLTgweiIvPjwvc3ZnPg==',
			'locust'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1NzYgNTEyIj48cGF0aCBkPSJNMzEyIDMyYy0xMy4zIDAtMjQgMTAuNy0yNCAyNHMxMC43IDI0IDI0IDI0aDE2Yzk4LjcgMCAxODAuNiA3MS40IDE5NyAxNjUuNGMtOS0zLjUtMTguOC01LjQtMjktNS40SDQzMS44bC00MS44LTk3LjVjLTMuNC03LjktMTAuOC0xMy40LTE5LjMtMTQuNHMtMTcgMi43LTIyLjEgOS42bC00MC45IDU1LjUtMjEuNy01MC43Yy0zLjMtNy44LTEwLjUtMTMuMi0xOC45LTE0LjNzLTE2LjcgMi4zLTIyIDguOWwtMjQwIDMwNGMtOC4yIDEwLjQtNi40IDI1LjUgNCAzMy43czI1LjUgNi40IDMzLjctNGw3OS40LTEwMC41IDQzIDE2LjQtNDAuNSA1NWMtNy45IDEwLjctNS42IDI1LjcgNS4xIDMzLjZzMjUuNyA1LjYgMzMuNi01LjFMMjE1LjEgNDAwaDc0LjVsLTI5LjMgNDIuM2MtNy41IDEwLjktNC44IDI1LjggNi4xIDMzLjRzMjUuOCA0LjggMzMuNC02LjFMMzQ4IDQwMGg4MC40bDM4LjggNjcuOWM2LjYgMTEuNSAyMS4yIDE1LjUgMzIuNyA4LjlzMTUuNS0yMS4yIDguOS0zMi43TDQ4My42IDQwMEg0OTZjNDQuMSAwIDc5LjgtMzUuNyA4MC03OS43YzAtLjEgMC0uMiAwLS4zVjI4MEM1NzYgMTQzIDQ2NSAzMiAzMjggMzJIMzEyem01MC41IDE2OGwxNy4xIDQwSDMzM2wyOS41LTQwem0tODcuNyAzOC4xbC0xLjQgMS45SDIyNS4xbDMyLjctNDEuNSAxNi45IDM5LjV6TTg4LjggMjQwQzU3LjQgMjQwIDMyIDI2NS40IDMyIDI5Ni44YzAgMTUuNSA2LjMgMzAgMTYuOSA0MC40TDEyNi43IDI0MEg4OC44ek00OTYgMjg4YTE2IDE2IDAgMSAxIDAgMzIgMTYgMTYgMCAxIDEgMC0zMnoiLz48L3N2Zz4=',
			'moon'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzODQgNTEyIj48cGF0aCBkPSJNMjIzLjUgMzJDMTAwIDMyIDAgMTMyLjMgMCAyNTZTMTAwIDQ4MCAyMjMuNSA0ODBjNjAuNiAwIDExNS41LTI0LjIgMTU1LjgtNjMuNGM1LTQuOSA2LjMtMTIuNSAzLjEtMTguN3MtMTAuMS05LjctMTctOC41Yy05LjggMS43LTE5LjggMi42LTMwLjEgMi42Yy05Ni45IDAtMTc1LjUtNzguOC0xNzUuNS0xNzZjMC02NS44IDM2LTEyMy4xIDg5LjMtMTUzLjNjNi4xLTMuNSA5LjItMTAuNSA3LjctMTcuM3MtNy4zLTExLjktMTQuMy0xMi41Yy02LjMtLjUtMTIuNi0uOC0xOS0uOHoiLz48L3N2Zz4=',
			'motorcycle'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NDAgNTEyIj48cGF0aCBkPSJNMjgwIDMyYy0xMy4zIDAtMjQgMTAuNy0yNCAyNHMxMC43IDI0IDI0IDI0aDU3LjdsMTYuNCAzMC4zTDI1NiAxOTJsLTQ1LjMtNDUuM2MtMTItMTItMjguMy0xOC43LTQ1LjMtMTguN0g2NGMtMTcuNyAwLTMyIDE0LjMtMzIgMzJ2MzJoOTZjODguNCAwIDE2MCA3MS42IDE2MCAxNjBjMCAxMS0xLjEgMjEuNy0zLjIgMzJoNzAuNGMtMi4xLTEwLjMtMy4yLTIxLTMuMi0zMmMwLTUyLjIgMjUtOTguNiA2My43LTEyNy44bDE1LjQgMjguNkM0MDIuNCAyNzYuMyAzODQgMzEyIDM4NCAzNTJjMCA3MC43IDU3LjMgMTI4IDEyOCAxMjhzMTI4LTU3LjMgMTI4LTEyOHMtNTcuMy0xMjgtMTI4LTEyOGMtMTMuNSAwLTI2LjUgMi4xLTM4LjcgNkw0MTguMiAxMjhINDgwYzE3LjcgMCAzMi0xNC4zIDMyLTMyVjY0YzAtMTcuNy0xNC4zLTMyLTMyLTMySDQ1OS42Yy03LjUgMC0xNC43IDIuNi0yMC41IDcuNEwzOTEuNyA3OC45bC0xNC0yNmMtNy0xMi45LTIwLjUtMjEtMzUuMi0yMUgyODB6TTQ2Mi43IDMxMS4ybDI4LjIgNTIuMmM2LjMgMTEuNyAyMC45IDE2IDMyLjUgOS43czE2LTIwLjkgOS43LTMyLjVsLTI4LjItNTIuMmMyLjMtLjMgNC43LS40IDcuMS0uNGMzNS4zIDAgNjQgMjguNyA2NCA2NHMtMjguNyA2NC02NCA2NHMtNjQtMjguNy02NC02NGMwLTE1LjUgNS41LTI5LjcgMTQuNy00MC44ek0xODcuMyAzNzZjLTkuNSAyMy41LTMyLjUgNDAtNTkuMyA0MGMtMzUuMyAwLTY0LTI4LjctNjQtNjRzMjguNy02NCA2NC02NGMyNi45IDAgNDkuOSAxNi41IDU5LjMgNDBoNjYuNEMyNDIuNSAyNjguOCAxOTAuNSAyMjQgMTI4IDIyNEM1Ny4zIDIyNCAwIDI4MS4zIDAgMzUyczU3LjMgMTI4IDEyOCAxMjhjNjIuNSAwIDExNC41LTQ0LjggMTI1LjgtMTA0SDE4Ny4zek0xMjggMzg0YTMyIDMyIDAgMSAwIDAtNjQgMzIgMzIgMCAxIDAgMCA2NHoiLz48L3N2Zz4=',
			'paint_roller'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNMCA2NEMwIDI4LjcgMjguNyAwIDY0IDBIMzUyYzM1LjMgMCA2NCAyOC43IDY0IDY0djY0YzAgMzUuMy0yOC43IDY0LTY0IDY0SDY0Yy0zNS4zIDAtNjQtMjguNy02NC02NFY2NHpNMTYwIDM1MmMwLTE3LjcgMTQuMy0zMiAzMi0zMlYzMDRjMC00NC4yIDM1LjgtODAgODAtODBINDE2YzE3LjcgMCAzMi0xNC4zIDMyLTMyVjE2MCA2OS41YzM3LjMgMTMuMiA2NCA0OC43IDY0IDkwLjV2MzJjMCA1My00MyA5Ni05NiA5NkgyNzJjLTguOCAwLTE2IDcuMi0xNiAxNnYxNmMxNy43IDAgMzIgMTQuMyAzMiAzMlY0ODBjMCAxNy43LTE0LjMgMzItMzIgMzJIMTkyYy0xNy43IDAtMzItMTQuMy0zMi0zMlYzNTJ6Ii8+PC9zdmc+',
			'paperclip'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0NDggNTEyIj48cGF0aCBkPSJNMzY0LjIgODMuOGMtMjQuNC0yNC40LTY0LTI0LjQtODguNCAwbC0xODQgMTg0Yy00Mi4xIDQyLjEtNDIuMSAxMTAuMyAwIDE1Mi40czExMC4zIDQyLjEgMTUyLjQgMGwxNTItMTUyYzEwLjktMTAuOSAyOC43LTEwLjkgMzkuNiAwczEwLjkgMjguNyAwIDM5LjZsLTE1MiAxNTJjLTY0IDY0LTE2Ny42IDY0LTIzMS42IDBzLTY0LTE2Ny42IDAtMjMxLjZsMTg0LTE4NGM0Ni4zLTQ2LjMgMTIxLjMtNDYuMyAxNjcuNiAwczQ2LjMgMTIxLjMgMCAxNjcuNmwtMTc2IDE3NmMtMjguNiAyOC42LTc1IDI4LjYtMTAzLjYgMHMtMjguNi03NSAwLTEwMy42bDE0NC0xNDRjMTAuOS0xMC45IDI4LjctMTAuOSAzOS42IDBzMTAuOSAyOC43IDAgMzkuNmwtMTQ0IDE0NGMtNi43IDYuNy02LjcgMTcuNyAwIDI0LjRzMTcuNyA2LjcgMjQuNCAwbDE3Ni0xNzZjMjQuNC0yNC40IDI0LjQtNjQgMC04OC40eiIvPjwvc3ZnPg==',
			'phone'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNMTY0LjkgMjQuNmMtNy43LTE4LjYtMjgtMjguNS00Ny40LTIzLjJsLTg4IDI0QzEyLjEgMzAuMiAwIDQ2IDAgNjRDMCAzMTEuNCAyMDAuNiA1MTIgNDQ4IDUxMmMxOCAwIDMzLjgtMTIuMSAzOC42LTI5LjVsMjQtODhjNS4zLTE5LjQtNC42LTM5LjctMjMuMi00Ny40bC05Ni00MGMtMTYuMy02LjgtMzUuMi0yLjEtNDYuMyAxMS42TDMwNC43IDM2OEMyMzQuMyAzMzQuNyAxNzcuMyAyNzcuNyAxNDQgMjA3LjNMMTkzLjMgMTY3YzEzLjctMTEuMiAxOC40LTMwIDExLjYtNDYuM2wtNDAtOTZ6Ii8+PC9zdmc+',
			'plane'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1NzYgNTEyIj48cGF0aCBkPSJNNDgyLjMgMTkyYzM0LjIgMCA5My43IDI5IDkzLjcgNjRjMCAzNi01OS41IDY0LTkzLjcgNjRsLTExNi42IDBMMjY1LjIgNDk1LjljLTUuNyAxMC0xNi4zIDE2LjEtMjcuOCAxNi4xbC01Ni4yIDBjLTEwLjYgMC0xOC4zLTEwLjItMTUuNC0yMC40bDQ5LTE3MS42TDExMiAzMjAgNjguOCAzNzcuNmMtMyA0LTcuOCA2LjQtMTIuOCA2LjRsLTQyIDBjLTcuOCAwLTE0LTYuMy0xNC0xNGMwLTEuMyAuMi0yLjYgLjUtMy45TDMyIDI1NiAuNSAxNDUuOWMtLjQtMS4zLS41LTIuNi0uNS0zLjljMC03LjggNi4zLTE0IDE0LTE0bDQyIDBjNSAwIDkuOCAyLjQgMTIuOCA2LjRMMTEyIDE5MmwxMDIuOSAwLTQ5LTE3MS42QzE2Mi45IDEwLjIgMTcwLjYgMCAxODEuMiAwbDU2LjIgMGMxMS41IDAgMjIuMSA2LjIgMjcuOCAxNi4xTDM2NS43IDE5MmwxMTYuNiAweiIvPjwvc3ZnPg==',
			'star'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1NzYgNTEyIj48cGF0aCBkPSJNMzE2LjkgMThDMzExLjYgNyAzMDAuNCAwIDI4OC4xIDBzLTIzLjQgNy0yOC44IDE4TDE5NSAxNTAuMyA1MS40IDE3MS41Yy0xMiAxLjgtMjIgMTAuMi0yNS43IDIxLjdzLS43IDI0LjIgNy45IDMyLjdMMTM3LjggMzI5IDExMy4yIDQ3NC43Yy0yIDEyIDMgMjQuMiAxMi45IDMxLjNzMjMgOCAzMy44IDIuM2wxMjguMy02OC41IDEyOC4zIDY4LjVjMTAuOCA1LjcgMjMuOSA0LjkgMzMuOC0yLjNzMTQuOS0xOS4zIDEyLjktMzEuM0w0MzguNSAzMjkgNTQyLjcgMjI1LjljOC42LTguNSAxMS43LTIxLjIgNy45LTMyLjdzLTEzLjctMTkuOS0yNS43LTIxLjdMMzgxLjIgMTUwLjMgMzE2LjkgMTh6Ii8+PC9zdmc+',
			'tree'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0NDggNTEyIj48cGF0aCBkPSJNMjEwLjYgNS45TDYyIDE2OS40Yy0zLjkgNC4yLTYgOS44LTYgMTUuNUM1NiAxOTcuNyA2Ni4zIDIwOCA3OS4xIDIwOEgxMDRMMzAuNiAyODEuNGMtNC4yIDQuMi02LjYgMTAtNi42IDE2QzI0IDMwOS45IDM0LjEgMzIwIDQ2LjYgMzIwSDgwTDUuNCA0MDkuNUMxLjkgNDEzLjcgMCA0MTkgMCA0MjQuNWMwIDEzIDEwLjUgMjMuNSAyMy41IDIzLjVIMTkydjMyYzAgMTcuNyAxNC4zIDMyIDMyIDMyczMyLTE0LjMgMzItMzJWNDQ4SDQyNC41YzEzIDAgMjMuNS0xMC41IDIzLjUtMjMuNWMwLTUuNS0xLjktMTAuOC01LjQtMTVMMzY4IDMyMGgzMy40YzEyLjUgMCAyMi42LTEwLjEgMjIuNi0yMi42YzAtNi0yLjQtMTEuOC02LjYtMTZMMzQ0IDIwOGgyNC45YzEyLjcgMCAyMy4xLTEwLjMgMjMuMS0yMy4xYzAtNS43LTIuMS0xMS4zLTYtMTUuNUwyMzcuNCA1LjlDMjM0IDIuMSAyMjkuMSAwIDIyNCAwcy0xMCAyLjEtMTMuNCA1Ljl6Ii8+PC9zdmc+',
			'truck'=>'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NDAgNTEyIj48cGF0aCBkPSJNNDggMEMyMS41IDAgMCAyMS41IDAgNDhWMzY4YzAgMjYuNSAyMS41IDQ4IDQ4IDQ4SDY0YzAgNTMgNDMgOTYgOTYgOTZzOTYtNDMgOTYtOTZIMzg0YzAgNTMgNDMgOTYgOTYgOTZzOTYtNDMgOTYtOTZoMzJjMTcuNyAwIDMyLTE0LjMgMzItMzJzLTE0LjMtMzItMzItMzJWMjg4IDI1NiAyMzcuM2MwLTE3LTYuNy0zMy4zLTE4LjctNDUuM0w1MTIgMTE0LjdjLTEyLTEyLTI4LjMtMTguNy00NS4zLTE4LjdINDE2VjQ4YzAtMjYuNS0yMS41LTQ4LTQ4LTQ4SDQ4ek00MTYgMTYwaDUwLjdMNTQ0IDIzNy4zVjI1Nkg0MTZWMTYwek0xMTIgNDE2YTQ4IDQ4IDAgMSAxIDk2IDAgNDggNDggMCAxIDEgLTk2IDB6bTM2OC00OGE0OCA0OCAwIDEgMSAwIDk2IDQ4IDQ4IDAgMSAxIDAtOTZ6Ii8+PC9zdmc+'
		];

		$randKeys = []; $randKeys = array_rand($ICvars->allbase64, $ICvars->numberChars);
        
		$randImages = [];
		foreach($randKeys as $key){
			$idImg = 'idImg_' . strval(random_int(101, 999));

			$randImages[$key] = [  $ICvars->allbase64[$key] , $idImg ];
		}
		
		$keyValidCaptcha = array_rand($randImages);
		
		$keyValidCaptchaCompleto = []; $keyValidCaptchaCompleto = $randImages[ $keyValidCaptcha ];

		$keyValidCaptchaTransient = []; $keyValidCaptchaTransient = [ $keyValidCaptcha, $keyValidCaptchaCompleto[1] ];

		\set_transient(htmlentities($ICvars->nameTransient), ($keyValidCaptchaTransient), 900);

		$inputToken = '<input type="hidden" id="hidCapUniq" name="hidCapUniq" value="'. \esc_html( $captchaAjaxTransient519 ) . '">';

		$imagesBase64HtmlId = [];

		$imagesBase64HtmlId = array_values($randImages);

		$toBrow = [
            'input' => $inputToken,
			'keyValidCaptcha' => $keyValidCaptcha,
            'icons' => $imagesBase64HtmlId
		];
		
		echo json_encode($toBrow);
		\wp_die();
	}

	if(  $wpCapImage === 'AR' ){
		/*	  https://geekthis.net/post/php-gradient-images-rectangle-gd/ 	*/
		$imageGradientRect = function (&$img,$x,$y,$x1,$y1,$start,$end) {
			if($x > $x1 || $y > $y1) {
				return false;
			}
			$s = array(
				hexdec(substr($start,0,2)),
				hexdec(substr($start,2,2)),
				hexdec(substr($start,4,2))
			);
			$e = array(
				hexdec(substr($end,0,2)),
				hexdec(substr($end,2,2)),
				hexdec(substr($end,4,2))
			);
			$steps = $y1 - $y;
			for($i = 0; $i < $steps; $i++) {
				$r = $s[0] - ((($s[0]-$e[0])/$steps)*$i);
				$g = $s[1] - ((($s[1]-$e[1])/$steps)*$i);
				$b = $s[2] - ((($s[2]-$e[2])/$steps)*$i);
				$color = \imagecolorallocate($img,$r,$g,$b);
				\imagefilledrectangle($img,$x,$y+$i,$x1,$y+$i+1,$color);
			}
			return true;
		};

		$imgText = function(){
			$all = '0123456789';
			$toS = [];
			$toS[1]=substr( $all, mt_rand(0, strlen($all)-1), 1 );
			$toS[3]=substr( $all, mt_rand(0, strlen($all)-1), 1 );
			$toS[5]=substr( $all, mt_rand(0, strlen($all)-1), 1 );

			$pm = '';
			if( ((int)$toS[5] + (int)$toS[3]) < (int)9){ $pm = '+'; } else { $pm = (rand() % 2) ? '+' : '-'; }			
			if($pm === '+'){ $r = (int)$toS[5] + (int)$toS[3] + (int)$toS[1]; } elseif($pm === '-'){ $r = (int)$toS[5] + (int)$toS[3] - (int)$toS[1]; }
			
			$rev = $toS[1]. $pm . $toS[3]. "+" .$toS[5];
			return ['txt' => $rev, 'result' => $r];
		};

		$quantolarg = [ '3' => 100, '4' => 130, '5' => 160, '6' => 190 ];
		$numberChar = 5;
		$imgWidth = $quantolarg[(string)$numberChar];
		$imgHeight = 48;

		$img = \imagecreatetruecolor($imgWidth,$imgHeight);

		if ( false === $imageGradientRect($img, 0, 0, $imgWidth, $imgHeight,'a0d0ff','ffd00a')){ \wp_die('Error Captcha Ajax. Deactivate this plugin'); }

		$linecolo = \imagecolorallocate( $img, (int)95, (int)156, (int)88 );

		$textandresult = $imgText(); $allchar = (string)$textandresult['txt']; $result = $textandresult['result'];
	
		for($i=0; $i < 9; $i++) {
			\imagesetthickness( $img, rand(1,5) );
			\imageline( $img, rand( 0,$quantolarg[(string)$numberChar] + 4 ), 0, rand( 0,$quantolarg[(string)$numberChar] + 4 ), 48, $linecolo );
		}

		$textcolo1 = \imagecolorallocate( $img, 0x00, 0x00, 0x00 );
		$textcolo2 = \imagecolorallocate( $img, 0xFF, 0xFF, 0xFF );

		$fonts = [];
		$fonts[] = __DIR__ . "/DejaVuSerif-Bold.ttf";
		$fonts[] = __DIR__ . "/DejaVuSans-Bold.ttf";
		$fonts[] = __DIR__ . "/DejaVuSansMono-Bold.ttf";

		$digit = '';$ii = strlen($allchar);
		for($x = 10; $x < $quantolarg[(string)$numberChar]; $x += 30) {
			$textcolor = (rand() % 2) ? $textcolo1 : $textcolo2;
			$ii -= 1;
			$digi = substr($allchar,  strlen($allchar)-(strlen($allchar) - $ii), 1);
			$digit .= $digi;
			\imagettftext( $img, 22, rand(-10,10), $x, rand(30, 38), $textcolor, $fonts[array_rand($fonts)], $digi );
		}

		\set_transient(htmlentities($nameTransient), htmlentities((string)$result), 900);

		$inputToken = '<input type="hidden" id="hidCapUniq" name="hidCapUniq" value="'. \esc_html( $captchaAjaxTransient519 ) . '">';

		ob_start();
		\imagepng($img);
		$htmlImage = sprintf( '<img style="display:inline !important;" src="data:image/png;base64,%s" alt="O6AWp0Lm">', base64_encode(ob_get_clean()) );
		\imagedestroy($img);

		echo json_encode(['input' => $inputToken, 'image' => $htmlImage]);
		\wp_die();
	}


	$quantolarga = [ '3' => 100, '4' => 130, '5' => 160, '6' => 190 ];

	$image = \imagecreatetruecolor( $quantolarga[(string)$numberChars] + 4, 49) or die( \esc_html__("Server Error 500: cannot Initialize new GD image stream") );

	$background = \imagecolorallocate( $image, 0x66, 0xCC, 0xFF );
	\imagefill( $image, 0, 0, $background );
	$linecolor = \imagecolorallocate( $image, 0x33, 0x99, 0xCC );
	$textcolor1 = \imagecolorallocate( $image, 0x00, 0x00, 0x00 );
	$textcolor2 = \imagecolorallocate( $image, 0xFF, 0xFF, 0xFF );

	for($i=0; $i < 9; $i++) {
		\imagesetthickness( $image, rand(1,4) );
		\imageline( $image, rand( 0,$quantolarga[(string)$numberChars] + 4 ), 0, rand( 0,$quantolarga[(string)$numberChars] + 4 ), 48, $linecolor );
	}

	$fonts = [];
	$fonts[] = __DIR__ . "/DejaVuSerif-Bold.ttf";
	$fonts[] = __DIR__ . "/DejaVuSans-Bold.ttf";
	$fonts[] = __DIR__ . "/DejaVuSansMono-Bold.ttf";

	$digit = '';
	for($x = 10; $x < $quantolarga[(string)$numberChars]; $x += 30) {
		$textcolor = (rand() % 2) ? $textcolor1 : $textcolor2;
		$digi = substr( $allchars, mt_rand(0, strlen($allchars)-1), 1 );
		$digit .= $digi;
		\imagettftext( $image, 22, rand(-30,30), $x, rand(30, 38), $textcolor, $fonts[array_rand($fonts)], $digi );
	}

	\set_transient(htmlentities($nameTransient), htmlentities($digit), 900);

	$inputToken = '<input type="hidden" id="hidCapUniq" name="hidCapUniq" value="'. \esc_html( $captchaAjaxTransient519 ) . '">';

	$typeImages = []; $typeImages = gd_info();
	if( !empty($typeImages['WebP Support']) ){

		ob_start();
		\imagewebp($image);
		$htmlImage = sprintf( '<img style="display:inline !important;" src="data:image/webp;base64,%s" alt="O6AWp0Lm">', base64_encode(ob_get_clean()) );
		\imagedestroy($image);
		
		echo json_encode(['input' => $inputToken, 'image' => $htmlImage]);
		\wp_die();

	}
	
	ob_start();
	\imagepng($image);
	$htmlImage = sprintf( '<img style="display:inline !important;" src="data:image/png;base64,%s" alt="O6AWp0Lm">', base64_encode(ob_get_clean()) );
	\imagedestroy($image);

	echo json_encode(['input' => $inputToken, 'image' => $htmlImage]);
	\wp_die();
}

function captcha_admin(){
	!null === 'SEL20' ? '' : define('SEL20', 'selected="selected"');
	?>
	<div class="wrap">
	    <h1><?php \esc_html_e( 'Captcha Ajax ver. 1.10.0', 'captcha-ajax' );?></h1>

        <?php
        if( isset($_POST['submit'])) {
            if( !isset( $_POST['captcha_nonce_name'] ) || !\wp_verify_nonce( \sanitize_text_field( $_POST['captcha_nonce_name'] ), 'captcha_nonce_action' )){
				?> <div id="message" class="updated fade"><p><strong><?php \esc_html_e( 'Sorry, your nonce did not verify.', 'captcha-ajax' ); ?></strong></p></div>	<?php
			} else {
				?> <div id="message" class="updated fade"><p><strong><?php \esc_html_e( 'Options saved.', 'captcha-ajax' ); ?></strong></p></div> <?php

                if( isset($_POST['captcha_login']) ) { \update_option( 'wpCap_login', \sanitize_text_field($_POST['captcha_login']) ); }
                if( isset($_POST['captcha_register']) ) { \update_option( 'wpCap_register', \sanitize_text_field($_POST['captcha_register']) ); }
                if( isset($_POST['captcha_lost']) ) { \update_option( 'wpCap_lost', \sanitize_text_field($_POST['captcha_lost']) ); }
				if( isset($_POST['captcha_comments']) ) { \update_option('wpCap_comments', \sanitize_text_field($_POST['captcha_comments'])); }
                if( isset($_POST['captcha_registered']) ) { \update_option('wpCap_registered', \sanitize_text_field($_POST['captcha_registered'])); }
				if( isset($_POST['captcha_cf7_ax']) ) { \update_option( 'wpCap_cf7_ax', \sanitize_text_field($_POST['captcha_cf7_ax']) );}
				if( isset($_POST['captcha_wpf_ax']) ) { \update_option( 'wpCap_wpf_ax', \sanitize_text_field($_POST['captcha_wpf_ax']) );}
				if( isset($_POST['captcha_forminator_ax']) ) { \update_option( 'wpCap_forminator_ax', \sanitize_text_field($_POST['captcha_forminator_ax']) );}
				if( isset($_POST['captcha_type']) ) { \update_option( 'wpCap_type', \sanitize_text_field($_POST['captcha_type']) ); }
                if( isset($_POST['captcha_letters']) ) { \update_option( 'wpCap_letters', \sanitize_text_field($_POST['captcha_letters']) ); }
                if( isset($_POST['total_no_of_characters']) ) { \update_option( 'wpCap_no_chars', \sanitize_text_field($_POST['total_no_of_characters']) ); }
				if( isset($_POST['cheImage']) ) { \update_option( 'wpCap_image', \sanitize_text_field($_POST['cheImage']) ); }
				if( isset($_POST['captcha_failban']) ) { \update_option( 'wpCap_failBan', \sanitize_text_field($_POST['captcha_failban']) ); }
				if( isset($_POST['check_trans']) && \sanitize_text_field($_POST['checkTrans'] == '1')) { \delete_expired_transients();}  
			}
		}

        $login = \esc_html( \get_option('wpCap_login') );
        $loginYes = null; $loginNo = null;
        if( !empty($login) && $login == 'yes' ){ $loginYes = SEL20; } else { $loginNo = SEL20; }

        $registra = \esc_html( \get_option('wpCap_register') );
        $registraYes = null; $registraNo = null;
        if(!empty($registra) && $registra == 'yes') { $registraYes = SEL20; } else{ $registraNo = SEL20; }

        $lostpass = \esc_html( \get_option('wpCap_lost') );
        $lostpassYes = null; $lostpassNo = null;
        if(!empty($lostpass) && $lostpass == 'yes'){ $lostpassYes = SEL20; } else{ $lostpassNo = SEL20; }

		$postComments = \esc_html( \get_option('wpCap_comments') );
        $postCommentsYes = null;	$postCommentsNo = null;
        if(!empty($postComments) && $postComments == 'yes') { $postCommentsYes = SEL20; } else { $postCommentsNo = SEL20; }

        $userRegistred = \esc_html( \get_option('wpCap_registered') );
        $userRegistredYes = null; $userRegistredNo = null;
        if(!empty($userRegistred) && $userRegistred == 'yes'){ $userRegistredYes = SEL20; } else { $userRegistredNo = SEL20; }

		$cf7 = \esc_html(\get_option( 'wpCap_cf7_ax' ));
		$cf7Yes = null; $cf7No = null;
		if(!empty($cf7) && $cf7 == 'yes'){ $cf7Yes = SEL20; } else { $cf7No = SEL20; }

		$wpf = \esc_html(\get_option( 'wpCap_wpf_ax' ));
		$wpfYes = null; $wpfNo = null;
		if(!empty($wpf) && $wpf == 'yes'){ $wpfYes = SEL20; } else { $wpfNo = SEL20; }

		$forminator = \esc_html(\get_option( 'wpCap_forminator_ax' ));
		$forminatorYes = null; $forminatorNo = null;
		if(!empty($forminator) && $forminator == 'yes'){ $forminatorYes = SEL20; } else { $forminatorNo = SEL20; }
 
        $numLetters = \esc_html( \get_option('wpCap_type') );
        $maiusMinus = \esc_html( \get_option('wpCap_letters') );
        $totalChars = \esc_html( \get_option('wpCap_no_chars') );

		$cheImageCk = \esc_html( \get_option('wpCap_image', 'DE') );

		$failBan = \esc_html( \get_option('wpCap_failBan', 'No') );
		$failBanYes = null; $failBanNo = null;
		if(!empty($failBan) && $failBan == 'yes'){ $failBanYes = SEL20; } else { $failBanNo = SEL20; }

        ?>
	<div class="option-page-wrap">
			<h2 class="nav-tab-wrapper">
                <a id="captchaSettings" class="nav-tab" href="#">General</a>
				<a id="captchaImages" class="nav-tab" href="#">Images</a>
				<a id="captchaTransients" class="nav-tab" href="#">Fail and Ban</a>
            </h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'captcha_nonce_action', 'captcha_nonce_name' ); ?>
		<section id="captcha_settings" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row" class="px260"><?php \esc_html_e( 'Select Captcha letters type', 'captcha-ajax' ); ?>:</th>
                    <td>
                        <select name="captcha_letters" style="margin:0;">
                            <option value="maiusc" <?php if($maiusMinus == 'maiusc') { echo SEL20; } ?>><?php \esc_html_e('Capital letters only', 'captcha-ajax');?></option>
                            <option value="minusc" <?php if($maiusMinus == 'minusc') { echo SEL20; } ?>><?php \esc_html_e('Small letters only', 'captcha-ajax');?></option>
                            <option value="maiusc_minusc" <?php if($maiusMinus == 'maiusc_minusc') { echo SEL20; } ?>><?php \esc_html_e('Capital & Small letters', 'captcha-ajax');?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php \esc_html_e('Select a Captcha type', 'captcha-ajax');?>: </th>
                    <td>
                        <select name="captcha_type" style="margin:0;">
                            <option value="alphanumeric" <?php if($numLetters == 'alphanumeric'){ echo SEL20;} ?>><?php \esc_html_e('Alphanumeric', 'captcha-ajax');?></option>
                            <option value="alphabets" <?php if($numLetters == 'alphabets') {echo SEL20;} ?>><?php \esc_html_e('Alphabets only', 'captcha-ajax');?></option>
                            <option value="numbers" <?php if($numLetters == 'numbers') {echo SEL20;} ?>><?php \esc_html_e('Numbers only', 'captcha-ajax');?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php \esc_html_e('Total number of Captcha Characters', 'captcha-ajax');?>: </th>
                    <td class="cp75w">
                        <select name="total_no_of_characters">
                        <?php
                            for($i=3; $i<=6; $i++){
                                print '<option value="'.$i.'" ';
                                if($totalChars == $i) echo SEL20;
                                print '>'.$i.'</option>';
                            }
                        ?>
                        </select>
                    </td>
                </tr>
            </table>
            <h3 style="color:brown"><?php \esc_html_e( 'Captcha display Options', 'captcha-ajax' );?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row" class="px260"><?php \esc_html_e( "Enable Captcha for Login form", "captcha-ajax" );?>: </th>
                    <td class="cp75w">
                        <select name="captcha_login">
                            <option value="yes" <?php echo \esc_html($loginYes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                            <option value="no" <?php echo \esc_html($loginNo);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                        <th scope="row"><?php \esc_html_e('Enable Captcha for Register form', 'captcha-ajax');?>: </th>
                        <td class="cp75w" >
                            <select name="captcha_register">
                                <option value="yes" <?php echo \esc_html($registraYes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                                <option value="no" <?php echo \esc_html($registraNo);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
                            </select>
                        </td>
                </tr>
                <tr>
                        <th scope="row"><?php \esc_html_e('Enable Captcha for Lost Password form', 'captcha-ajax');?>: </th>
                        <td class="cp75w">
                            <select name="captcha_lost">
                                <option value="yes" <?php echo \esc_html($lostpassYes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                                <option value="no" <?php echo \esc_html($lostpassNo);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
                            </select>
                        </td>
                </tr>
				<tr>
                        <th scope="row"><?php \esc_html_e('Enable Captcha for Comments form', 'captcha-ajax');?>: </th>
                        <td class="cp75w">
                            <select name="captcha_comments">
                                <option value="yes" <?php echo \esc_html($postCommentsYes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                                <option value="no" <?php echo \esc_html($postCommentsNo);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
                            </select>
                        </td>
                </tr>
                <tr>
                        <th scope="row"><?php \esc_html_e('Hide Captcha for logged in users', 'captcha-ajax');?>: </th>
                        <td class="cp75w">
                            <select name="captcha_registered">
                                <option value="yes" <?php echo \esc_html($userRegistredYes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                                <option value="no" <?php echo \esc_html($userRegistredNo);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
                            </select>
                        </td>
                </tr>
			</table>
			<h3 style="color:brown"><?php \esc_html_e( 'Captcha for Forms', 'captcha-ajax' );?></h3>
			<table class="form-table">
				<tr>
					<th scope="row" class="px260"><?php \esc_html_e('Captcha for Contact Form 7 plugin', 'captcha-ajax');?>: </th>
				<?php
				if(!empty( \is_plugin_active("contact-form-7/wp-contact-form-7.php") )){
				?>
					<td class="cp75w">
						<select name="captcha_cf7_ax">
							<option value="yes" <?php echo \esc_html($cf7Yes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                        	<option value="no" <?php echo \esc_html($cf7No);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
						</select>
					</td>
				<?php
				} else {
				?>
					<td>
						<p class="descriptions"><strong><?php  \esc_html_e('Contact Form 7 plugin not active', 'captcha-ajax');  ?> </strong></p>
					</td>
				<?php
				}
				?>
				</tr>
				<tr>
					<th scope="row" class="px260"><?php \esc_html_e('Captcha for WPForms plugin', 'captcha-ajax');	?>: </th>
				<?php
				if(!empty(\is_plugin_active("wpforms-lite/wpforms.php"))){
				?>
					<td class="cp75w">
						<select name="captcha_wpf_ax">
							<option value="yes" <?php echo \esc_html($wpfYes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                        	<option value="no" <?php echo \esc_html($wpfNo);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
						</select>
					</td>
				<?php
				} else {
				?>
					<td>
						<p class="descriptions"><strong><?php  \esc_html_e('WPForms plugin not active', 'captcha-ajax');  ?> </strong></p>
					</td>
				<?php
				}
				?>
				</tr>
				<tr>
					<th scope="row" class="px260"><?php \esc_html_e('Captcha for Forminator plugin', 'captcha-ajax'); ?>: </th>
				<?php
				if(!empty(\is_plugin_active("forminator/forminator.php"))){
				?>
					<td class="cp75w">
						<select name="captcha_forminator_ax">
							<option value="yes" <?php echo \esc_html($forminatorYes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                        	<option value="no" <?php echo \esc_html($forminatorNo);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
						</select>
					</td>
				<?php
				} else {
				?>
					<td>
						<p class="descriptions"><strong><?php  \esc_html_e('Forminator form plugin not active', 'captcha-ajax');  ?> </strong></p>
					</td>
				<?php
				}
				?>
				</tr>
            </table>
		</section>

		<?php
			global $plugin_page;
			$defI  = WP_PLUGIN_URL . "/$plugin_page" . '/assets' . '/Captcha_default_image.webp';
			$blWhI = WP_PLUGIN_URL . "/$plugin_page" . '/assets' . '/Captcha_minimal_image.webp';
			$mlCol = WP_PLUGIN_URL . "/$plugin_page" . '/assets' . '/Captcha_multicolor_image.webp';
			$icon  = WP_PLUGIN_URL . "/$plugin_page" . '/assets' . '/Captcha_icons_image.webp';
			$arit  = WP_PLUGIN_URL . "/$plugin_page" . '/assets' . '/Captcha_arithmetic_image.png';
		?>
		
		<section id="captcha_image" class="tab-content">
		<table class="form-table">
		<th scope="row" class="px260" style="vertical-align:middle;"><?php \esc_html_e( "Select Captcha Image", "captcha-ajax" );?>: </th>
		<td class="cp75w">
			<fieldset>
				<span class="cpInpImm">
					<input type="radio" name="cheImage" value="DE"  <?php if( \esc_html($cheImageCk) === 'DE'){ echo 'checked'; } ?>>
					<label>
						<span class="cpLabImm">Default</span>
						<img class="cpImgImm" src= <?php echo \esc_url( $defI ); ?> >
					</label>
				</span>
				<span class="cpInpImm">
					<input type="radio" name="cheImage" value="BW" <?php if( \esc_html($cheImageCk) === 'BW'){ echo 'checked'; } ?>>
					<label>
						<span class="cpLabImm" >Black and White</span>
						<img  class="cpImgImm" src= <?php echo \esc_url( $blWhI ); ?> >
					</label>
				</span>
				<span class="cpInpImm">
					<input type="radio" name="cheImage" value="MC" <?php if( \esc_html($cheImageCk) === 'MC'){ echo 'checked'; } ?>>
					<label>
						<span class="cpLabImm" >Multicolor</span>
						<img  class="cpImgImm" src= <?php echo \esc_url( $mlCol ); ?> >
					</label>
				</span>
				<span class="cpInpImm">
					<input type="radio" name="cheImage" value="IC" <?php if( \esc_html($cheImageCk) === 'IC'){ echo 'checked'; } ?>>
					<label>
						<span class="cpLabImm" >Icons</span>
						<img  class="cpImgImm" src= <?php echo \esc_url( $icon ); ?> >
					</label>
				</span>
				<span class="cpInpImm">
					<input type="radio" name="cheImage" value="AR" <?php if( \esc_html($cheImageCk) === 'AR'){ echo 'checked'; } ?>>
					<label>
						<span class="cpLabImm" >Arithmetics</span>
						<img  class="cpImgImm" src= <?php echo \esc_url( $arit ); ?> >
					</label>
				</span>
			</fieldset>
		</td>
		</table>
		</section>
		<section id="captcha_transients" class="tab-content">
		<table class="form-table">
			<th scope="row" class="px260" style="vertical-align:middle;">
			    <?php \esc_html_e( "Firewall limiting access attempts.", "captcha-ajax" );?><br> 
				<?php \esc_html_e( "maxretry = 7 ", "captcha-ajax" );?><br>
				<?php \esc_html_e( "findtime = 60 ", "captcha-ajax" );?><br>
				<?php \esc_html_e( "bantime = 1200 ", "captcha-ajax" );?>
		    </th>
			<td class="cp75w">
			    <select name="captcha_failban">
					<option value="yes" <?php echo \esc_html($failBanYes);?>><?php \esc_html_e('Yes', 'captcha-ajax');?></option>
                   	<option value="no" <?php echo \esc_html($failBanNo);?>><?php \esc_html_e('No', 'captcha-ajax');?></option>
				</select>
			</td>
		</table>
		<table class="form-table">
			<h3 style="color:brown"><?php \esc_html_e( 'Expired Transients', 'captcha-ajax' );?></h3>
			<tr>
			<th scope="row" class="px260" style="vertical-align:middle;"><?php \esc_html_e( "Check and delete transients options expired ( all options expired ) ", "captcha-ajax" );?>: </th>
			<td class="cp75w">
				<fieldset>
					<label for="check_trans"></label><input id="check_trans" name="check_trans" type="checkbox"><input id="checkTrans" name="checkTrans" type="hidden" value="0">
				</fieldset>
			</td>
			</tr>
			<tr>
				<td><br></td>
			</tr>
			<tr>
			<th scope="row" class="px260" style="vertical-align:middle;text-align: left;">
				<?php \esc_html_e( "You can also remove expired transients   ", "captcha-ajax" );?><br>
				<?php \esc_html_e( "with REST API. Use this URL", "captcha-ajax" );?>:
			</th>
			<td style="font-size:16px;font-weight: 500;">
				<?php echo \esc_url_raw( \get_home_url() ) . '/wp-json/captcha-ajax/v1/transients_expired';?>
			</td>
			</tr>
		</table>
		</section>
		<tr>
            <td><?php \submit_button();?></td>
            <td></td>
        </tr>
        </form>
	</div>
	</div>

	<script id="capAdmin_settings">{function CaptchaAdminSettings(){this.displayFirst=()=>{captcha_image.style.display='none';captcha_transients.style.display='none';captcha_settings.style.display='block';};this.displayImages=()=>{captcha_settings.style.display='none';captcha_transients.style.display='none';captcha_image.style.display='block';};this.displayTransients=()=>{captcha_settings.style.display='none';captcha_image.style.display='none';captcha_transients.style.display='block';};this.ChangeTransientBox=()=>{if(check_trans.checked==true){checkTrans.value='1';}
if(check_trans.checked==false){checkTrans.value='0';}};}
const CP5=new CaptchaAdminSettings;CP5.displayFirst();document.addEventListener('DOMContentLoaded',()=>{captchaSettings.addEventListener('click',function(){CP5.displayFirst();});captchaImages.addEventListener('click',function(){CP5.displayImages();});captchaTransients.addEventListener('click',function(){CP5.displayTransients();});check_trans.addEventListener('click',function(){CP5.ChangeTransientBox();});});}</script>
<?php
}
