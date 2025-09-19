<?php
// 危険: プラグイン削除時に会員テーブル・トークンテーブルをDROPします。
if (!defined('WP_UNINSTALL_PLUGIN')) { die; }
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}idm_member_tokens");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}idm_members");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}idm_campaign_joins");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}idm_member_tags");
delete_option('idm_membership_version');
