<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Autoloader {
  public static function init() {
    spl_autoload_register([__CLASS__, 'autoload']);
  }
  public static function autoload($class) {
    if (strpos($class, __NAMESPACE__) !== 0) return;
    $path = str_replace('\\', '/', substr($class, strlen(__NAMESPACE__) + 1));
    $file_rel = 'class-' . strtolower($path) . '.php';

    $candidates = [
      IDM_MEMBERSHIP_DIR . 'includes/' . $file_rel,
      IDM_MEMBERSHIP_DIR . 'public/'   . $file_rel,
          IDM_MEMBERSHIP_DIR . 'admin/'    . $file_rel,
      // 追加の場所があればここに
    ];
    foreach ($candidates as $file) {
      if (file_exists($file)) { require $file; return; }
    }
  }
}
