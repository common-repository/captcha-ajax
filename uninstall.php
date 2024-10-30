<?php

// Make sure that uninstall was called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN'))   { exit; }

delete_option('wpCap_login');
delete_option('wpCap_register');
delete_option('wpCap_lost');
delete_option('wpCap_comments');
delete_option('wpCap_registered');
delete_option('wpCap_cf7_ax');
delete_option('wpCap_wpf_ax');
delete_option('wpCap_forminator_ax');
delete_option('wpCap_type');
delete_option('wpCap_letters');
delete_option('wpCap_no_chars');
delete_option('wpCap_image');
delete_option('wpCap_failBan');
delete_option('wpCap_Banned');
delete_option('wpCap_Ban_History');
