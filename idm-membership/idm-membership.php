<?php
/**
 * Plugin Name: IDM Membership (Independent)
 * Plugin URI:  https://example.com/
 * Description: 独自DBで会員登録・ログイン・ページ保護（Shortcodesのみ読み込み）
 * Version:     1.0.0
 * Author:      Sample
 * Text Domain: idm-membership
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) exit;

define('IDM_MEMBERSHIP_DIR', plugin_dir_path(__FILE__));
define('IDM_MEMBERSHIP_URL', plugin_dir_url(__FILE__));

/**
 * 本体ロード（短コードなど）
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('idm-membership', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    foreach ([
        'includes/class-auth.php',
        'includes/class-template.php',
        'includes/class-shortcodes.php',
    ] as $rel) {
        $file = IDM_MEMBERSHIP_DIR . $rel;
        if (is_readable($file)) {
            require_once $file;
        } else {
            error_log('[IDM] Missing include: ' . $file);
        }
    }

    if (class_exists('\\IDM\\Membership\\Shortcodes')) {
        (new \IDM\Membership\Shortcodes())->init();
        error_log('[IDM] Shortcodes initialized');
    } else {
        error_log('[IDM] Shortcodes class not found');
    }
}, 5);

/**
 * 管理画面：キャンペーン管理の読み込み（※ネストしない）
 */
add_action('plugins_loaded', function () {
    if (!is_admin()) return;

    $admin = IDM_MEMBERSHIP_DIR . 'admin/class-campaigns.php';
    if (is_readable($admin)) {
        require_once $admin;
        error_log('[IDM] campaigns file loaded');

        if (class_exists('\\IDM\\Membership\\Admin_Campaigns')) {
            \IDM\Membership\Admin_Campaigns::init();
            error_log('[IDM] Admin_Campaigns::init() executed');
        } elseif (class_exists('\\IDM\\Membership\\Campaigns_Admin')) {
            \IDM\Membership\Campaigns_Admin::init();
            error_log('[IDM] Campaigns_Admin::init() executed');
        } else {
            error_log('[IDM] Admin campaigns class not found in file');
        }
    } else {
        error_log('[IDM] campaigns file NOT readable: ' . $admin);
    }

    $dashboard = IDM_MEMBERSHIP_DIR . 'admin/class-admin.php';
    if (is_readable($dashboard)) {
        require_once $dashboard;
        error_log('[IDM] admin dashboard file loaded');

        if (class_exists('\\IDM\\Membership\\Admin')) {
            \IDM\Membership\Admin::init();
            error_log('[IDM] Admin::init() executed');
        } else {
            error_log('[IDM] Admin class not found in admin dashboard file');
        }
    } else {
        error_log('[IDM] admin dashboard file NOT readable: ' . $dashboard);
    }
}, 6);

/**
 * 動作確認ショートコード: 固定ページに [idm_ping] → "pong"
 */
add_action('init', function () {
    add_shortcode('idm_ping', function(){ return 'pong'; });
});

/**
 * メニュー系フック全体の健全性チェック用：テストページを必ず出す
 * （設定 → IDMデバッグ が表示されるはず）
 */
add_action('admin_menu', function () {
    add_options_page(
        'IDMデバッグ', 'IDMデバッグ',
        'manage_options', 'idm-debug',
        function () {
            echo '<div class="wrap"><h1>IDM Debug</h1><p>admin_menu フックは動作しています。</p></div>';
        }
    );
}, 1);
// --- 会員系ページのガード（キャッシュ無効 & ログイン中はリダイレクト） ---
add_action('template_redirect', function () {
  if (!is_singular()) return;

  global $post;
  if (!$post) return;

  // このページに会員系ショートコードが含まれていたらキャッシュ禁止
  $shortcodes = ['idm_login','idm_register','idm_members_only','idm_my_campaigns','idm_campaign_buttons','idm_member_name','idm_member_email'];
  foreach ($shortcodes as $sc) {
    if (has_shortcode($post->post_content, $sc)) {
      if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
      nocache_headers();
      break;
    }
  }

  // ログイン/登録ページに来たログイン済みユーザーはマイページへ
  if (has_shortcode($post->post_content, 'idm_login') || has_shortcode($post->post_content, 'idm_register')) {
    $auth = new \IDM\Membership\Auth();
    if ($auth->current_member()) {
      wp_safe_redirect(home_url('/members/'));
      exit;
    }
  }
}, 1);
// 会員ページ & 会員クッキー所持時はキャッシュ禁止 + no-cache ヘッダー
add_action('template_redirect', function () {
  $is_members_page = function_exists('is_page') && (is_page('members') || is_page('member'));
  $has_idm_cookie = false;
  foreach ($_COOKIE as $k => $v) {
    if (strpos($k, 'idm_member_') === 0) { $has_idm_cookie = true; break; }
  }
  if ($is_members_page || $has_idm_cookie) {
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    if (!defined('LSCACHE_NO_CACHE')) define('LSCACHE_NO_CACHE', true);
    if (!defined('DONOTCACHE')) define('DONOTCACHE', true);
    if (!headers_sent()) nocache_headers();
  }
});
