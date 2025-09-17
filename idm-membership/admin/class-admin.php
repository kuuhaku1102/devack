<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    public static function register_menu() {
        // Slug must be a string identifier (not a URL)
        $slug = 'idm-membership';

        add_menu_page(
            __('独自会員', 'idm-membership'),
            __('独自会員', 'idm-membership'),
            'manage_options',
            $slug,
            [__CLASS__, 'render_dashboard'],
            'dashicons-groups',
            58
        );

        // Make the first submenu point to the same slug so both links open dashboard
        add_submenu_page(
            $slug,
            __('ダッシュボード', 'idm-membership'),
            __('ダッシュボード', 'idm-membership'),
            'manage_options',
            $slug,
            [__CLASS__, 'render_dashboard']
        );
    }

    public static function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', 'idm-membership'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('独自会員 ダッシュボード', 'idm-membership') . '</h1>';
        echo '<p>' . esc_html__('ここに企画ダッシュボードを表示します。', 'idm-membership') . '</p>';
        // Placeholder container for existing template include if needed:
        do_action('idm_membership_admin_dashboard');
        echo '</div>';
    }
}

// Auto-init
Admin::init();
