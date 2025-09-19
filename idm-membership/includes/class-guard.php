<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Guard {
  public function init() {
    add_action('template_redirect', [$this, 'protect']);
  }

  public function protect() {
    if (is_admin()) return;
    if (!is_page(Core::$protected_slugs)) return;

    $auth = new Auth();
    if (!$auth->current_member()) {
      $login = home_url('/' . Core::$login_slug . '/');
      $redirect = urlencode($this->current_url());
      wp_safe_redirect($login . '?redirect=' . $redirect);
      exit;
    }
  }

  private function current_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        return $scheme . $host . $uri;
    }
}
