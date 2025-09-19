<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Shortcodes {

  public function init() {
    add_shortcode('idm_register', [$this, 'register_form']);
    add_shortcode('idm_login',    [$this, 'login_form']);
    add_shortcode('idm_logout',   [$this, 'logout_link']);

    // マイページ用
    add_shortcode('idm_member_name',       [$this, 'sc_member_name']);
    add_shortcode('idm_member_email',      [$this, 'sc_member_email']);
    add_shortcode('idm_member_join_count', [$this, 'sc_member_join_count']);
    add_shortcode('idm_member_wins_count', [$this, 'sc_member_wins_count']);
    add_shortcode('idm_my_campaigns',      [$this, 'sc_my_campaigns']);

    // 会員限定＆キャンペーン
    add_shortcode('idm_members_only',      [$this, 'sc_members_only']);
    add_shortcode('idm_campaign_buttons',  [$this, 'sc_campaign_buttons']);

    // 動作確認用
    add_shortcode('idm_ping', function(){ return 'pong'; });
  }

  /* ======================================================
   * フォーム系
   * ====================================================== */

  public function register_form($atts) {
    // 登録成功時は /members/ に遷移
    $atts = shortcode_atts(['redirect' => home_url('/members/')], $atts, 'idm_register');
    $redirect = wp_validate_redirect($atts['redirect'], home_url('/members/'));

    $msg = '';
    $old = ['email' => '', 'name' => ''];

    if (!empty($_POST['idm_reg_nonce']) && wp_verify_nonce($_POST['idm_reg_nonce'], 'idm_register')) {
      global $wpdb;
      $members = $wpdb->prefix . 'idm_members';

      $email = isset($_POST['idm_email']) ? sanitize_email( wp_unslash($_POST['idm_email']) ) : '';
      $pass1 = isset($_POST['idm_pass1']) ? (string) wp_unslash($_POST['idm_pass1']) : '';
      $pass2 = isset($_POST['idm_pass2']) ? (string) wp_unslash($_POST['idm_pass2']) : '';
      $name  = isset($_POST['idm_name'])  ? trim( sanitize_text_field( wp_unslash($_POST['idm_name']) ) ) : '';

      $old['email'] = $email;
      $old['name']  = $name;

      if (!$email || !$pass1 || !$pass2 || $name === '') {
        $msg = __('Required fields are missing (name is required).', 'idm-membership');
      } elseif (!is_email($email)) {
        $msg = __('Invalid email address.', 'idm-membership');
      } elseif ($pass1 !== $pass2) {
        $msg = __('Passwords do not match.', 'idm-membership');
      } else {
        // 重複チェック
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$members} WHERE email=%s", $email));
        if ($exists) {
          $msg = __('Email already registered.', 'idm-membership');
        } else {
          $hash = password_hash($pass1, PASSWORD_DEFAULT);
          $ok = $wpdb->insert(
            $members,
            ['email'=>$email,'pass_hash'=>$hash,'name'=>$name,'status'=>1,'role'=>'member'],
            ['%s','%s','%s','%d','%s']
          );
          if ($ok) { wp_safe_redirect($redirect); exit; }
          $msg = __('Registration failed.', 'idm-membership');
        }
      }
    }

    ob_start();
    if (class_exists(__NAMESPACE__.'\\Template')) {
      Template::render('register-form', [
        'message'   => $msg,
        'old_email' => $old['email'],
        'old_name'  => $old['name'],
      ]);
    } else {
      // テンプレート未設置時の簡易フォーム
      ?>
      <form method="post" class="idm-form">
        <?php if ($msg) echo '<div class="idm-error">'.esc_html($msg).'</div>'; ?>
        <p><label>Xアカウント名（必須）<br>
          <input type="text" name="idm_name" value="<?php echo esc_attr($old['name']); ?>" required></label></p>
        <p><label>メールアドレス（必須）<br>
          <input type="email" name="idm_email" value="<?php echo esc_attr($old['email']); ?>" required></label></p>
        <p><label>パスワード（必須）<br>
          <input type="password" name="idm_pass1" required></label></p>
        <p><label>パスワード（確認）<br>
          <input type="password" name="idm_pass2" required></label></p>
        <?php wp_nonce_field('idm_register', 'idm_reg_nonce'); ?>
        <p><button type="submit">登録する</button></p>
      </form>
      <?php
    }
    return ob_get_clean();
  }

  public function login_form($atts) {
    // ログイン成功時は /members/ に遷移（既ログインでも即遷移）
    $atts = shortcode_atts(['redirect' => (isset($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : home_url('/members/'))], $atts, 'idm_login');
    $redirect = wp_validate_redirect($atts['redirect'], home_url('/members/'));

    $auth = new Auth();
    if ($auth->current_member()) {
      wp_safe_redirect($redirect); exit;
    }

    $msg = '';
    if (!empty($_POST['idm_login_nonce']) && wp_verify_nonce($_POST['idm_login_nonce'], 'idm_login')) {
      global $wpdb;
      $members = $wpdb->prefix . 'idm_members';
      $email = sanitize_email( isset($_POST['idm_email']) ? wp_unslash($_POST['idm_email']) : '' );
      $pass  = (string) ( isset($_POST['idm_pass']) ? wp_unslash($_POST['idm_pass']) : '' );

      $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$members} WHERE email=%s AND status=1", $email), ARRAY_A);
      if ($user && password_verify($pass, $user['pass_hash'])) {
        $auth->login((int)$user['id']);
        wp_safe_redirect($redirect); exit;
      } else {
        $msg = __('Email or password is incorrect.', 'idm-membership');
      }
    }

    ob_start();
    if (class_exists(__NAMESPACE__.'\\Template')) {
      Template::render('login-form', ['message' => $msg, 'registered' => isset($_GET['registered'])]);
    } else {
      // テンプレート未設置時の簡易フォーム
      ?>
      <form method="post" class="idm-form">
        <?php if ($msg) echo '<div class="idm-error">'.esc_html($msg).'</div>'; ?>
        <p><label>メールアドレス<br>
          <input type="email" name="idm_email" required></label></p>
        <p><label>パスワード<br>
          <input type="password" name="idm_pass" required></label></p>
        <?php wp_nonce_field('idm_login', 'idm_login_nonce'); ?>
        <p><button type="submit">ログイン</button></p>
      </form>
      <?php
    }
    return ob_get_clean();
  }

public function logout_link() {
  if (!empty($_GET['idm_logout']) && wp_verify_nonce(($_GET['_wpnonce'] ?? ''), 'idm_logout')) {
    nocache_headers();              // ←キャッシュ回避
    (new Auth())->logout();         // ←独自会員のみログアウト
    wp_safe_redirect(home_url('/'));
    exit;
  }
  $url = wp_nonce_url(add_query_arg('idm_logout', '1'), 'idm_logout');
  return '<a href="'.esc_url($url).'" rel="nofollow">ログアウト</a>';
}


  /* ======================================================
   * ヘルパー
   * ====================================================== */

  private function get_current_member_id() {
    $auth = new Auth();
    $m = $auth->current_member();
    return is_array($m) ? (int)($m['id'] ?? 0) : (int)$m;
  }

  private function table_exists($table) {
    global $wpdb;
    // LIKE用に '_' と '%' をエスケープ
    $like = str_replace(['_', '%'], ['\\_', '\\%'], $table);
    $found = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $like) );
    return $found === $table;
  }

  private function detect_campaign_columns($table) {
    global $wpdb;
    $cols = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
    $campaign = in_array('campaign_key', $cols, true) ? 'campaign_key'
              : (in_array('campaign', $cols, true) ? 'campaign' : null);
    $date = in_array('joined_at', $cols, true) ? 'joined_at'
          : (in_array('created_at', $cols, true) ? 'created_at' : null);
    return [$campaign, $date];
  }

  /* ======================================================
   * マイページ用ショートコード
   * ====================================================== */

  /** [idm_member_name] */
  public function sc_member_name() {
    $mid = $this->get_current_member_id();
    if (!$mid) return '';
    global $wpdb;
    $t = $wpdb->prefix.'idm_members';
    $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$t} WHERE id=%d", $mid));
    return esc_html($name ?: '');
  }

  /** [idm_member_email] */
  public function sc_member_email() {
    $mid = $this->get_current_member_id();
    if (!$mid) return '';
    global $wpdb;
    $t = $wpdb->prefix.'idm_members';
    $email = $wpdb->get_var($wpdb->prepare("SELECT email FROM {$t} WHERE id=%d", $mid));
    return esc_html($email ?: '');
  }

  /** [idm_member_join_count] */
  public function sc_member_join_count() {
    $mid = $this->get_current_member_id();
    if (!$mid) return '0';
    global $wpdb;
    $t = $wpdb->prefix.'idm_campaign_joins';
    if (!$this->table_exists($t)) return '0';
    list($col) = $this->detect_campaign_columns($t);
    if (!$col) return '0';
    $cnt = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT `$col`) FROM `{$t}` WHERE member_id=%d", $mid));
    return (string)$cnt;
  }

  /** [idm_member_wins_count] */
  public function sc_member_wins_count() {
    $mid = $this->get_current_member_id();
    if (!$mid) return '0';
    global $wpdb;
    $t = $wpdb->prefix.'idm_campaign_winners';
    if (!$this->table_exists($t)) return '0';
    $cnt = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$t}` WHERE winner_member_id=%d", $mid));
    return (string)$cnt;
  }

  /**
   * [idm_my_campaigns table="1" limit="50"]
   * 応募履歴一覧（table=1 でテーブル描画、他はカード風）
   */
  public function sc_my_campaigns($atts) {
    $atts = shortcode_atts(['table' => '1', 'limit' => '50'], $atts, 'idm_my_campaigns');
    $mid = $this->get_current_member_id();
    if (!$mid) return '<div class="idm-empty">ログインが必要です。</div>';

    global $wpdb;
    $t = $wpdb->prefix.'idm_campaign_joins';
    if (!$this->table_exists($t)) return '<div class="idm-empty">応募履歴はまだありません。</div>';

    list($col, $dcol) = $this->detect_campaign_columns($t);
    if (!$col) return '<div class="idm-empty">応募履歴はまだありません。</div>';

    $limit = max(1, min(500, (int)$atts['limit']));
    $selDate  = $dcol ? "`$dcol`" : "NULL";
    $orderCol = $dcol ? "`$dcol` DESC" : "1";

    $sql = $wpdb->prepare(
      "SELECT `$col` AS campaign, $selDate AS joined_at
       FROM `{$t}`
       WHERE member_id=%d
       ORDER BY $orderCol
       LIMIT %d",
      $mid, $limit
    );
    $rows = $wpdb->get_results($sql, ARRAY_A);

    if (!$rows) return '<div class="idm-empty">応募履歴はまだありません。</div>';

    if ($atts['table'] === '1') {
      ob_start(); ?>
      <div class="idm-table-wrap">
        <table class="idm-table">
          <thead><tr><th>企画キー</th><th>応募日時</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo esc_html($r['campaign']); ?></td>
              <td><?php echo $r['joined_at'] ? esc_html($r['joined_at']) : '-'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
      return ob_get_clean();
    }

    // カード表示
    $html = '<div class="idm-cards">';
    foreach ($rows as $r) {
      $html .= '<div class="idm-card"><div><strong>'.esc_html($r['campaign']).'</strong></div>'.
               '<div class="idm-sub">'.($r['joined_at'] ? esc_html($r['joined_at']) : '-').'</div></div>';
    }
    $html .= '</div>';
    return $html;
  }

  /**
   * [idm_members_only] 会員限定ガード
   * [idm_members_only show_login="1" message="会員限定です。ログインしてください。"]...[/idm_members_only]
   */
  public function sc_members_only($atts, $content = null) {
    $atts = shortcode_atts([
      'show_login'   => '1',
      'message'      => '会員限定コンテンツです。ログインしてください。',
      'redirect_to'  => home_url('/members/'),
    ], $atts, 'idm_members_only');

    $auth = new Auth();
    if ($auth->current_member()) {
      return do_shortcode($content);
    }

    $html  = '<div class="idm-card">';
    $html .= '<p class="idm-sub">'.esc_html($atts['message']).'</p>';
    if ($atts['show_login'] === '1') {
      $html .= do_shortcode('[idm_login redirect="'.esc_url($atts['redirect_to']).'"]');
    }
    $html .= '</div>';
    return $html;
  }

  /**
   * [idm_campaign_buttons keys="alpha,beta,gamma"]
   * - keys="" の場合は管理画面（独自会員→キャンペーン管理）に登録済みの全キャンペーンを表示
   * - 締め切りを過ぎている場合は「受付終了」表示／クリック不可
   * - テーブル: {$prefix}idm_campaign_joins
   *   必須: member_id / campaign_key(またはcampaign) / joined_at(またはcreated_at)
   */
    public function sc_campaign_buttons($atts) {
    $atts = shortcode_atts(['keys' => '', 'limit' => '12'], $atts, 'idm_campaign_buttons');

    // 会員チェック
    $auth = new Auth();
    $member = $auth->current_member();
    if (!$member) {
      return '<div class="idm-card"><p class="idm-sub">会員限定です。ログインしてください。</p>'.do_shortcode('[idm_login]').'</div>';
    }
    $member_id = is_array($member) ? (int)($member['id'] ?? 0) : (int)$member;

    // 管理画面のメタ（画像/タイトル/締め切り/リンク）を取得
    $meta = get_option('idm_campaigns', []);
    if (!is_array($meta)) $meta = [];

    // keys="" の場合は登録済み全キー、keys 指定時はその順に
    $keys = array_filter(array_map('trim', explode(',', (string)$atts['keys'])));
    if (empty($keys)) $keys = array_keys($meta);
    $keys = array_values(array_unique($keys));
    $lim  = max(1, min(500, (int)$atts['limit']));
    if (count($keys) > $lim) $keys = array_slice($keys, 0, $lim);

    if (empty($keys)) {
      return '<div class="idm-empty">表示するキャンペーンがありません。（管理画面で登録 or keys="" を指定）</div>';
    }

    // DB テーブルとカラム
    global $wpdb;
    $table = $wpdb->prefix . 'idm_campaign_joins';
    if (!$this->table_exists($table)) {
      return '<div class="idm-empty">応募テーブルが見つかりません：'.esc_html($table).'</div>';
    }
    list($campaign_col, $date_col) = $this->detect_campaign_columns($table);
    if (!$campaign_col) {
      return '<div class="idm-empty">応募テーブルのカラムが想定外です。（campaign_key または campaign が必要）</div>';
    }

    // 参加処理 (?idm_join=1&campaign=KEY&_wpnonce=XXX)
    // 成功時は「設定されたアフィリンク」に即リダイレクト
    if (isset($_GET['idm_join'], $_GET['campaign']) && $_GET['idm_join'] === '1') {
      $key = sanitize_text_field( wp_unslash($_GET['campaign']) );
      $action = 'idm_join_'.$key;
      if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], $action)) {

        // 締め切りチェック
        $m = $meta[$key] ?? [];
        $deadline_ok = true;
        if (!empty($m['deadline'])) {
          $deadline_ts = strtotime($m['deadline']);
          $now_ts      = (int) current_time('timestamp');
          if ($deadline_ts && $now_ts > $deadline_ts) $deadline_ok = false;
        }

        // リダイレクト先（アフィリンク）を取得
        $affiliate = !empty($m['link']) ? esc_url_raw($m['link']) : '';

        if (!$deadline_ok) {
          // 受付終了 → 通常描画に落とす（下の一覧に「受付終了」表示される）
        } else {
          // 重複参加チェック
          $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE member_id=%d AND `$campaign_col`=%s LIMIT 1",
            $member_id, $key
          ));

          if (!$exists) {
            $data = ['member_id'=>$member_id, $campaign_col=>$key];
            $fmt  = ['%d','%s'];
            if ($date_col) { $data[$date_col] = current_time('mysql'); $fmt[] = '%s'; }
            $wpdb->insert($table, $data, $fmt);
            // insert失敗でもアフィリンクに飛ばす場合はここで分岐しない
          }

          // ★ここが肝：応募が済んだら外部URLへ即リダイレクト
          if ($affiliate) {
            // 外部ドメインなので wp_safe_redirect ではなく wp_redirect を使用
            wp_redirect($affiliate);
            exit;
          }
        }
      }
      // 不正nonceは何もせず通常描画（エラーメッセージを出したいならここで作る）
    }

    // 既参加キーの抽出（ボタンの「参加済み」表示用）
    $placeholders = implode(',', array_fill(0, count($keys), '%s'));
    $joined = $wpdb->get_col($wpdb->prepare(
      "SELECT `$campaign_col` FROM `{$table}` WHERE member_id=%d AND `$campaign_col` IN ($placeholders)",
      array_merge([$member_id], $keys)
    ));
    $joined = array_map('strval', (array)$joined);

    // 現在URL（クエリ掃除）
    $current_url = remove_query_arg(['idm_join','campaign','_wpnonce']);

    // 出力
    ob_start(); ?>
    <div class="idm-stats" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
      <?php foreach ($keys as $key):
        $m = $meta[$key] ?? [];
        $title    = $m['title']    ?? $key;
        $image_id = (int)($m['image_id'] ?? 0);
        $deadline = $m['deadline'] ?? '';
        $deadline_ts = $deadline ? strtotime($deadline) : 0;
        $now_ts      = (int) current_time('timestamp');
        $is_closed   = ($deadline_ts && $now_ts > $deadline_ts);
        $is_joined   = in_array($key, $joined, true);

        $action   = 'idm_join_'.$key;
        $join_url = (!$is_joined && !$is_closed)
          ? wp_nonce_url( add_query_arg(['idm_join'=>'1','campaign'=>$key], $current_url), $action )
          : '#';
        ?>
        <article class="idm-card">
          <?php if ($image_id): ?>
            <div class="idm-cover" style="margin:-12px -12px 8px -12px;overflow:hidden;border-radius:10px 10px 0 0;">
              <?php echo wp_get_attachment_image($image_id, 'medium_large'); ?>
            </div>
          <?php endif; ?>

          <h3 style="margin:0 0 8px;font-size:16px;line-height:1.35;"><?php echo esc_html($title); ?></h3>
          <div class="idm-sub" style="margin-bottom:10px;"><code><?php echo esc_html($key); ?></code></div>
          <?php if ($deadline): ?>
            <div class="idm-sub" style="margin-bottom:10px;">締め切り: <?php echo esc_html($deadline); ?></div>
          <?php endif; ?>

          <?php if ($is_closed): ?>
            <span class="idm-btn" style="pointer-events:none;opacity:.6;">受付終了</span>
          <?php elseif ($is_joined): ?>
            <span class="idm-btn" style="pointer-events:none;opacity:.6;">参加済み</span>
          <?php else: ?>
            <a class="idm-btn"
   href="<?php echo esc_url($join_url); ?>"
   target="_blank"
   rel="nofollow sponsored noopener noreferrer">
  エントリーする
</a>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
  }
}