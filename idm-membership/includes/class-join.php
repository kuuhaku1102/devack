<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Join {
  public function init() {
    add_action('init', [$this, 'handle_join']);
    add_shortcode('idm_join', [$this, 'shortcode']);
  }

  public function shortcode($atts, $content='') {
    $atts = shortcode_atts([
      'campaign' => '',
      'url'      => home_url('/'),
      'text'     => '',
      'class'    => 'btn',
      'tags'     => '', // カンマ区切り
    ], $atts);

    $campaign = sanitize_key($atts['campaign']);
    if (!$campaign) return '<!-- idm_join: campaign missing -->';
    $redirect = esc_url_raw($atts['url']);
    $nonce = wp_create_nonce('idm_join_' . $campaign);

    $params = [
      'idm_join' => 1,
      'c'        => $campaign,
      'redirect' => $redirect,
      '_wpnonce' => $nonce,
    ];

    $tags = trim((string)$atts['tags']);
    if ($tags !== '') {
      $params['tags'] = rawurlencode($tags);
    }

    $join_url = add_query_arg($params, home_url('/'));

    $label = $content !== '' ? $content : ($atts['text'] !== '' ? $atts['text'] : '参加する');
    return '<a class="'.esc_attr($atts['class']).'" href="'.esc_url($join_url).'">'.esc_html($label).'</a>';
  }

  public function handle_join() {
        if (is_admin()) return;
    if (!isset($_GET['idm_join'])) return;

    $campaign = isset($_GET['c']) ? sanitize_key(wp_unslash($_GET['c'])) : '';
    $redirect = isset($_GET['redirect']) ? esc_url_raw(wp_unslash($_GET['redirect'])) : home_url('/');
    $nonce    = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

    if (!$campaign || !wp_verify_nonce($nonce, 'idm_join_' . $campaign)) {
      wp_die('Invalid request.');
    }

    $auth = new Auth();
    $member = $auth->current_member();
    if (!$member) {
      $login = home_url('/' . Core::$login_slug . '/');
      $back  = urlencode($this->current_url());
      wp_safe_redirect($login . '?redirect=' . $back);
      exit;
    }

    global $wpdb;
    $joins_table = $wpdb->prefix . 'idm_campaign_joins';

    // Insert join (ignore duplicates)
    $exists = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $joins_table WHERE member_id=%d AND campaign_key=%s",
      (int)$member['id'], $campaign
    ));
    if (!$exists) {
      $wpdb->insert($joins_table, [
        'member_id'    => (int)$member['id'],
        'campaign_key' => $campaign,
        'joined_at'    => current_time('mysql', true),
      ], ['%d','%s','%s']);
    }

    // Tags
    $tags_param = isset($_GET['tags']) ? wp_unslash($_GET['tags']) : '';
    if ($tags_param !== '') {
      $tags = array_filter(array_map('trim', explode(',', $tags_param)));
      if ($tags) {
        $tags_table = $wpdb->prefix . 'idm_member_tags';
        foreach ($tags as $tag) {
          $clean = sanitize_text_field($tag);
          if ($clean === '') continue;
          $exists_tag = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tags_table WHERE member_id=%d AND tag=%s",
            (int)$member['id'], $clean
          ));
          if (!$exists_tag) {
            $wpdb->insert($tags_table, [
              'member_id' => (int)$member['id'],
              'tag'       => $clean,
              'created_at'=> current_time('mysql', true),
            ], ['%d','%s','%s']);
          }
        }
      }
    }

    $redirect = add_query_arg(['joined' => '1', 'campaign' => $campaign], $redirect);
    wp_safe_redirect($redirect);
    exit;
  }

  private function current_url() {
    $scheme = is_ssl() ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    return $scheme . $host . $uri;
}
}
