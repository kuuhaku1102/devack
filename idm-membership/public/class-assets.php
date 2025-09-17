<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Assets {
  public function init() {
    add_action('wp_enqueue_scripts', [$this, 'enqueue']);
  }
  public function enqueue() {
    wp_register_style('idm-membership', IDM_MEMBERSHIP_URL.'public/assets/css/idm-membership.css', [], IDM_MEMBERSHIP_VERSION);
    wp_register_script('idm-membership', IDM_MEMBERSHIP_URL.'public/assets/js/idm-membership.js', ['jquery'], IDM_MEMBERSHIP_VERSION, true);

    if (is_page(Core::$protected_slugs) || is_page(Core::$login_slug)) {
      wp_enqueue_style('idm-membership');
      wp_enqueue_script('idm-membership');
    }
  }
}
