<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Core {
  // ★必要に応じて編集：ログインページのスラッグ／保護したいページのスラッグ
  public static $login_slug = 'member-login';
  public static $protected_slugs = ['members'];

  public static function init() {
        // Ensure custom caps on every load
        // ensure_caps disabled for stability
        // ensure Campaigns class is loaded
        if (is_admin()) { @require_once IDM_MEMBERSHIP_DIR . 'admin/class-campaigns.php'; }
        Install::maybe_upgrade();
    (new Assets())->init();
    (new Shortcodes())->init();
    (new Guard())->init();
        (new Campaigns())->init();
        (new Join())->init();
        (new Admin())->init();
  }
}
