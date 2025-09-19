<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Template {
  public static function locate($name) {
    $paths = [
      trailingslashit(get_stylesheet_directory()).'idm-membership/'.$name.'.php',
      trailingslashit(get_template_directory()).'idm-membership/'.$name.'.php',
      IDM_MEMBERSHIP_DIR.'public/templates/'.$name.'.php',
    ];
    foreach ($paths as $p) if (file_exists($p)) return $p;
    return '';
  }
  public static function render($name, $vars = []) {
    $file = self::locate($name);
    if ($file) { extract($vars); include $file; }
  }
}
