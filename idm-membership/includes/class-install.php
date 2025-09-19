<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) { exit; }

class Install {

    public static function activate_safe() {
        self::create_tables();
        self::maybe_upgrade(); // in case an older table exists
        if (function_exists('update_option')) {
            $version = defined('IDM_MEMBERSHIP_VERSION') ? IDM_MEMBERSHIP_VERSION : '1.0.0';
            update_option('idm_membership_version', $version);
        }
    }

    public static function activate() { self::activate_safe(); }

    public static function maybe_upgrade() {
        global $wpdb;
        $members = $wpdb->prefix . 'idm_members';
        $exists  = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $members) );
        if ($exists !== $members) {
            self::create_tables();
            return;
        }
        // Ensure required columns exist (hotfix for older installs)
        self::ensure_column($members, 'pass_hash', "VARCHAR(255) NOT NULL");
        self::ensure_column($members, 'status', "TINYINT(1) NOT NULL DEFAULT 1");
        // Optional safety: ensure name/role exist (older drafts)
        self::ensure_column($members, 'name', "VARCHAR(255) NOT NULL DEFAULT ''");
        self::ensure_column($members, 'role', "VARCHAR(50) NOT NULL DEFAULT 'member'");

        self::ensure_winners_table();
    }

    private static function ensure_column($table, $column, $definition) {
        global $wpdb;
        $col = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column) );
        if (!$col) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $members = $wpdb->prefix . 'idm_members';
        $sql_members = "CREATE TABLE {$members} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            email VARCHAR(190) NOT NULL,
            pass_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT '',
            status TINYINT(1) NOT NULL DEFAULT 1,
            role VARCHAR(50) NOT NULL DEFAULT 'member',
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset_collate};";

        $tokens = $wpdb->prefix . 'idm_member_tokens';
        $sql_tokens = "CREATE TABLE {$tokens} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT(20) UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            purpose VARCHAR(50) NOT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY member_id (member_id)
        ) {$charset_collate};";

        $tags = $wpdb->prefix . 'idm_member_tags';
        $sql_tags = "CREATE TABLE {$tags} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT(20) UNSIGNED NOT NULL,
            tag VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY tag (tag)
        ) {$charset_collate};";

        $joins = $wpdb->prefix . 'idm_campaign_joins';
        $sql_joins = "CREATE TABLE {$joins} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT(20) UNSIGNED NOT NULL,
            campaign_key VARCHAR(100) NOT NULL,
            tags VARCHAR(255) NOT NULL DEFAULT '',
            referrer VARCHAR(255) NOT NULL DEFAULT '',
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY campaign_key (campaign_key)
        ) {$charset_collate};";

        dbDelta($sql_members);
        dbDelta($sql_tokens);
        dbDelta($sql_tags);
        dbDelta($sql_joins);
        dbDelta(self::winners_table_sql($charset_collate));
    }

    private static function ensure_winners_table() {
        global $wpdb;
        $winners = $wpdb->prefix . 'idm_campaign_winners';
        $exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $winners));

        if ($exists !== $winners) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta(self::winners_table_sql($wpdb->get_charset_collate()));
            return;
        }

        self::ensure_column($winners, 'entry_id', "BIGINT(20) UNSIGNED NULL");
        self::ensure_column($winners, 'winner_name', "VARCHAR(255) NOT NULL DEFAULT ''");
        self::ensure_column($winners, 'winner_email', "VARCHAR(190) NOT NULL DEFAULT ''");
        self::ensure_column($winners, 'winner_weight', "INT(11) NOT NULL DEFAULT 0");
        self::ensure_column($winners, 'drawn_at', "DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00'");
        self::ensure_column($winners, 'drawn_at', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    private static function winners_table_sql($charset_collate) {
        global $wpdb;
        $winners = $wpdb->prefix . 'idm_campaign_winners';

        return "CREATE TABLE {$winners} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_key VARCHAR(100) NOT NULL,
            winner_member_id BIGINT(20) UNSIGNED NOT NULL,
            entry_id BIGINT(20) UNSIGNED NULL,
            winner_name VARCHAR(255) NOT NULL DEFAULT '',
            winner_email VARCHAR(190) NOT NULL DEFAULT '',
            winner_weight INT(11) NOT NULL DEFAULT 0,
            drawn_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            drawn_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY campaign_key (campaign_key),
            KEY winner_member_id (winner_member_id)
        ) {$charset_collate};";
    }
}
