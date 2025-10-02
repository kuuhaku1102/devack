<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Admin {

    /**
     * Default weight (100% = 等確率) used when no custom rule is matched.
     */
    const DEFAULT_WEIGHT = 100;

    /** @var array[] */
    private static $messages = [];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_idm_campaign_draw', [__CLASS__, 'ajax_draw_campaign']);
        add_action('wp_ajax_idm_campaign_save_winner', [__CLASS__, 'ajax_save_winner']);
        add_action('admin_init', [__CLASS__, 'maybe_warn_charset']); // 追加: DBがutf8mb4でない場合の警告
    }

    public static function register_menu() {
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
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_idm-membership') {
            return;
        }

        $base_url = plugin_dir_url(__FILE__);
        $campaigns = self::get_campaigns();
        $selected_campaign = self::get_selected_campaign(array_keys($campaigns));

        wp_enqueue_style(
            'idm-membership-dashboard',
            $base_url . 'dashboard.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'idm-membership-dashboard',
            $base_url . 'dashboard.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'idm-membership-dashboard',
            'idmDashboard',
            [
                'ajaxUrl'  => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('idm_draw_campaign'),
                'campaign' => $selected_campaign,
                'defaultWeight' => self::DEFAULT_WEIGHT,
                'i18n'     => [
                    'noEntries'     => __('応募者がいません。', 'idm-membership'),
                    'drawing'       => __('抽選中...', 'idm-membership'),
                    'selectedNone'  => __('選択された応募者がありません。', 'idm-membership'),
                    'weightInvalid' => __('抽選確率は1以上の数値を入力してください。', 'idm-membership'),
                    'applySuccess'  => __('抽選確率を適用しました。設定を保存してください。', 'idm-membership'),
                    'applyPartial'  => __('抽選確率を適用しましたが、名前が未設定の応募者は対象外です。', 'idm-membership'),
                    'applyFailed'   => __('抽選確率を適用できませんでした。対象となる応募者を選択してください。', 'idm-membership'),
                    'saveButton'    => __('抽選結果を保存', 'idm-membership'),
                    'saveSaving'    => __('保存中...', 'idm-membership'),
                    'saveSuccess'   => __('抽選結果を保存しました。', 'idm-membership'),
                    'saveError'     => __('抽選結果を保存できませんでした。', 'idm-membership'),
                ],
            ]
        );
    }

    public static function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', 'idm-membership'));
        }

        $campaigns = self::get_campaigns();
        $selected_campaign = self::get_selected_campaign(array_keys($campaigns));

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idm_action'])) {
            $selected_campaign = self::handle_post($selected_campaign);
            // Re-fetch to reflect latest changes.
            $campaigns = self::get_campaigns();
            if ($selected_campaign === '' || !isset($campaigns[$selected_campaign])) {
                $selected_campaign = self::get_selected_campaign(array_keys($campaigns));
            }
        }

        $weights_all     = self::get_weight_options();
        $current_weights = $weights_all[$selected_campaign] ?? [];
        $entries         = ($selected_campaign !== '')
            ? self::apply_weights(self::get_campaign_entries($selected_campaign), $current_weights)
            : [];

        echo '<div class="wrap idm-dashboard">';
        echo '<h1>' . esc_html__('独自会員 ダッシュボード', 'idm-membership') . '</h1>';

        self::render_notices();

        if (empty($campaigns)) {
            echo '<p>' . esc_html__('キャンペーンが登録されていません。まずは「キャンペーン管理」で登録してください。', 'idm-membership') . '</p>';
            echo '</div>';
            return;
        }

        self::render_campaign_selector($campaigns, $selected_campaign);

        if ($selected_campaign === '') {
            echo '<p>' . esc_html__('キャンペーンを選択してください。', 'idm-membership') . '</p>';
            echo '</div>';
            return;
        }

        self::render_entries_section($entries);
        self::render_weights_form($selected_campaign, $current_weights);
        self::render_draw_section($entries);

        do_action('idm_membership_admin_dashboard', $selected_campaign, $entries, $current_weights);

        echo '</div>';
    }

    private static function render_notices() {
        if (empty(self::$messages)) {
            return;
        }
        foreach (self::$messages as $notice) {
            $class = isset($notice['type']) ? $notice['type'] : 'updated';
            $message = isset($notice['message']) ? $notice['message'] : '';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
        }
    }

    private static function render_campaign_selector(array $campaigns, $selected) {
        echo '<form method="get" class="idm-campaign-selector">';
        echo '<input type="hidden" name="page" value="idm-membership" />';
        echo '<label>' . esc_html__('キャンペーンを選択:', 'idm-membership') . ' ';
        echo '<select name="campaign">';
        foreach ($campaigns as $key => $campaign) {
            $label = isset($campaign['title']) && $campaign['title'] !== ''
                ? $campaign['title'] . ' (' . $key . ')'
                : $key;
            printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($selected, $key, false), esc_html($label));
        }
        echo '</select>';
        echo '</label> ';
        submit_button(__('切り替え', 'idm-membership'), 'secondary', '', false);
        echo '</form>';
    }

    private static function render_entries_section(array $entries) {
        echo '<h2>' . esc_html__('応募者一覧', 'idm-membership') . '</h2>';
        if (empty($entries)) {
            echo '<p class="idm-empty">' . esc_html__('応募者がまだいません。', 'idm-membership') . '</p>';
            return;
        }

        echo '<p>' . sprintf(
            /* translators: %d: number of entries */
            esc_html__('応募人数: %d名', 'idm-membership'),
            count($entries)
        ) . '</p>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('名前', 'idm-membership') . '</th>';
        echo '<th>' . esc_html__('応募日時', 'idm-membership') . '</th>';
        echo '<th>' . esc_html__('抽選確率(%)', 'idm-membership') . '</th>';
        echo '</tr></thead>';
        echo '<tbody class="idm-entrant-list">';
        foreach ($entries as $entry) {
            $raw_name  = isset($entry['name']) ? (string) $entry['name'] : '';
            $name      = $raw_name !== '' ? $raw_name : __('(未設定)', 'idm-membership');
            $joined_at = isset($entry['joined_at']) ? (string) $entry['joined_at'] : '';
            $weight    = isset($entry['weight']) ? (int) $entry['weight'] : self::DEFAULT_WEIGHT;

            printf(
                '<tr class="idm-entrant" data-member-id="%1$d" data-entry-id="%6$d" data-weight="%4$d" data-name="%5$s">'
                . '<td class="idm-entrant-name"><label><input type="checkbox" class="idm-entrant-select" value="%1$d" /> '
                . '<span class="idm-entrant-name-text">%2$s</span></label></td>'
                . '<td>%3$s</td>'
                . '<td class="idm-entrant-weight">%4$d</td>'
                . '</tr>',
                (int) $entry['member_id'],
                esc_html($name),
                esc_html($joined_at),
                (int) $weight,
                esc_attr($raw_name),
                (int) $entry['id']
            );
        }
        echo '</tbody>';
        echo '</table>';

        $empty_text = __('応募者一覧でチェックを入れるとここに表示されます。', 'idm-membership');
        echo '<div class="idm-selected-panel">';
        echo '<h3>' . esc_html__('選択中の応募者', 'idm-membership') . '</h3>';
        echo '<p class="idm-selected-empty" data-empty-text="' . esc_attr($empty_text) . '">' . esc_html($empty_text) . '</p>';
        echo '<ul class="idm-selected-list"></ul>';
        echo '<div class="idm-selected-actions">';
        echo '<label>' . esc_html__('抽選確率(%)', 'idm-membership') . ' ';
        echo '<input type="number" class="small-text idm-selected-weight" min="1" max="1000" step="1" value="' . esc_attr(self::DEFAULT_WEIGHT) . '" /></label> ';
        echo '<button type="button" class="button idm-selected-apply">' . esc_html__('選択中に適用', 'idm-membership') . '</button>';
        echo '</div>';
        echo '<p class="description idm-selected-note">' . esc_html__('抽選確率の適用後は「設定を保存」を押して確定してください。', 'idm-membership') . '</p>';
        echo '<p class="idm-selected-message" aria-live="polite"></p>';
        echo '</div>';
    }

    private static function render_weights_form($campaign, array $weights) {
        echo '<h2>' . esc_html__('抽選確率の調整', 'idm-membership') . '</h2>';
        echo '<p class="description">' . esc_html__('特定の会員を名前で指定し、抽選確率を％で上書きできます。未指定の応募者は100%（等確率）です。', 'idm-membership') . '</p>';

        echo '<form method="post" class="idm-weights-form">';
        wp_nonce_field('idm_save_weights');
        echo '<input type="hidden" name="idm_action" value="save_weights" />';
        echo '<input type="hidden" name="campaign" value="' . esc_attr($campaign) . '" />';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('名前', 'idm-membership') . '</th>';
        echo '<th>' . esc_html__('確率(%)', 'idm-membership') . '</th>';
        echo '<th class="idm-column-actions"></th>';
        echo '</tr></thead>';
        echo '<tbody id="idm-weight-rows">';

        if (empty($weights)) {
            $weights = [];
        }

        $index = 0;
        foreach ($weights as $weight) {
            $field  = isset($weight['field']) ? $weight['field'] : 'name';
            $value  = isset($weight['value']) ? $weight['value'] : '';
            $chance = isset($weight['weight']) ? (int)$weight['weight'] : self::DEFAULT_WEIGHT;
            self::render_weight_row($index, $field, $value, $chance);
            $index++;
        }

        // Empty template row (will be cloned by JS when adding new rule).
        self::render_weight_row('__INDEX__', 'name', '', self::DEFAULT_WEIGHT, true);

        echo '</tbody>';
        echo '</table>';

        echo '<p><button type="button" class="button" id="idm-add-weight">' . esc_html__('＋ 条件を追加', 'idm-membership') . '</button></p>';
        submit_button(__('設定を保存', 'idm-membership'));
        echo '</form>';
    }

    private static function render_weight_row($index, $field, $value, $chance, $is_template = false) {
        $tr_class = $is_template ? 'idm-weight-row is-template' : 'idm-weight-row';
        $name_prefix = is_numeric($index) ? 'weights[' . $index . ']' : 'weights[__INDEX__]';
        $disabled = $is_template ? ' disabled="disabled"' : '';
        $field_value = in_array($field, ['email', 'name'], true) ? $field : 'name';
        $label_name  = __('名前', 'idm-membership');
        $label_email = __('メールアドレス（旧設定）', 'idm-membership');
        $label = $field_value === 'email' ? $label_email : $label_name;

        echo '<tr class="' . esc_attr($tr_class) . '"' . ($is_template ? ' data-template="1" style="display:none;"' : '') . '>';
        echo '<td class="idm-weight-identifier">';
        echo '<span class="idm-weight-field-label' . ($field_value === 'email' ? ' is-legacy' : '') . '" data-label-name="' . esc_attr($label_name) . '" data-label-email="' . esc_attr($label_email) . '">' . esc_html($label) . '</span>';
        echo '<input type="hidden" class="idm-weight-field-input" name="' . esc_attr($name_prefix . '[field]') . '" value="' . esc_attr($field_value) . '"' . $disabled . ' />';
        echo '<input type="text" name="' . esc_attr($name_prefix . '[value]') . '" value="' . esc_attr($value) . '" class="regular-text idm-weight-value-input"' . $disabled . ' />';
        if (!$is_template && $field_value === 'email') {
            echo '<p class="description idm-weight-legacy-note">' . esc_html__('旧形式の設定です。必要に応じて名前で設定し直してください。', 'idm-membership') . '</p>';
        }
        echo '</td>';
        echo '<td>';
        echo '<input type="number" name="' . esc_attr($name_prefix . '[weight]') . '" value="' . esc_attr($chance) . '" min="1" max="1000" class="small-text"' . $disabled . ' /> %';
        echo '</td>';
        echo '<td class="idm-column-actions"><button type="button" class="button-link-delete idm-remove-weight"' . ($is_template ? ' disabled="disabled"' : '') . '>' . esc_html__('削除', 'idm-membership') . '</button></td>';
        echo '</tr>';
    }

    private static function render_draw_section(array $entries) {
        echo '<h2>' . esc_html__('抽選', 'idm-membership') . '</h2>';
        echo '<p>' . esc_html__('ボタンを押すと現在の応募者の中から確率に応じて当選者を1名抽選します。', 'idm-membership') . '</p>';

        $disabled = empty($entries) ? 'disabled' : '';
        printf(
            '<button id="idm-draw-button" class="button button-primary" %s>%s</button>',
            $disabled,
            esc_html__('抽選を実行', 'idm-membership')
        );
        echo '<div id="idm-draw-result" class="idm-draw-result" aria-live="polite"></div>';
    }

    private static function handle_post($selected_campaign) {
        $current = $selected_campaign;
        if (!isset($_POST['idm_action'])) {
            return $current;
        }

        if ($_POST['idm_action'] === 'save_weights') {
            $new = self::save_weights();
            if ($new !== null) {
                $current = $new;
            }
        }
        return $current;
    }

    private static function save_weights() {
        if (!current_user_can('manage_options')) {
            return null;
        }

        check_admin_referer('idm_save_weights');

        $submitted_campaign = isset($_POST['campaign']) ? self::sanitize_campaign_key(wp_unslash($_POST['campaign'])) : '';
        if ($submitted_campaign === '') {
            self::$messages[] = [
                'type'    => 'notice notice-error',
                'message' => __('キャンペーンが選択されていません。', 'idm-membership'),
            ];
            return null;
        }

        $weights_input = isset($_POST['weights']) ? wp_unslash($_POST['weights']) : [];
        $sanitized     = self::sanitize_weights($weights_input);

        $options = self::get_weight_options();
        if (empty($sanitized)) {
            unset($options[$submitted_campaign]);
        } else {
            $options[$submitted_campaign] = $sanitized;
        }

        update_option('idm_campaign_weights', $options);

        self::$messages[] = [
            'type'    => 'notice notice-success',
            'message' => __('抽選確率の設定を保存しました。', 'idm-membership'),
        ];
        return $submitted_campaign;
    }

    /**
     * Emojiを保持したままテキストをサニタイズ（タグ除去のみ）
     */
    private static function sanitize_text_allow_emoji($text) {
        // 期待値: プレーンテキスト（HTMLタグ無し）、絵文字は残す
        if (is_array($text)) {
            $text = '';
        }
        $text = wp_strip_all_tags((string) $text, true);
        // 制御文字などは除外（タブ/改行は許可）
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        return trim($text);
    }

    private static function sanitize_weights($weights) {
    $clean = [];
    if (!is_array($weights)) {
        return $clean;
    }

    foreach ($weights as $weight) {
        $field = isset($weight['field']) ? sanitize_key($weight['field']) : 'name';
        if (!in_array($field, ['email', 'name'], true)) {
            $field = 'name';
        }

        // ★ 絵文字を許容（タグは除去するが、emojiは残す）
        $value = isset($weight['value']) ? wp_strip_all_tags($weight['value'], true) : '';
        $value = trim($value);
        if ($value === '') {
            continue;
        }

        $chance = isset($weight['weight']) ? intval($weight['weight']) : self::DEFAULT_WEIGHT;
        if ($chance <= 0) {
            continue;
        }

        $clean[] = [
            'field'  => $field,
            'value'  => $value,
            'weight' => $chance,
        ];
    }

    return $clean;
}

    private static function get_campaigns() {
        $campaigns = get_option('idm_campaigns', []);
        if (!is_array($campaigns)) {
            return [];
        }
        return $campaigns;
    }

    private static function sanitize_campaign_key($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $value = sanitize_text_field((string) $value);
        // Remove control characters that may slip through sanitize_text_field.
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

        return trim($value);
    }

    private static function get_selected_campaign(?array $available = null) {
        $raw = isset($_GET['campaign']) ? wp_unslash($_GET['campaign']) : '';
        $campaign = self::sanitize_campaign_key($raw);
        if ($campaign !== '') {
            if (empty($available) || in_array($campaign, $available, true)) {
                return $campaign;
            }
        }

        if (is_scalar($raw)) {
            $raw = trim((string) $raw);
            if ($raw !== '' && (empty($available) || in_array($raw, $available, true))) {
                return $raw;
            }
        }

        if (is_array($available) && !empty($available)) {
            return (string) reset($available);
        }

        $value = (string) $value;
        $value = preg_replace('/[^A-Za-z0-9_\-]/', '', $value);
        return $value;
    }

    private static function get_selected_campaign(?array $available = null) {
        $campaign = isset($_GET['campaign']) ? self::sanitize_campaign_key(wp_unslash($_GET['campaign'])) : '';
        if ($campaign !== '') {
            if (empty($available) || in_array($campaign, $available, true)) {
                return $campaign;
            }
        }

        if (is_array($available) && !empty($available)) {
            return (string) reset($available);
        }

        return '';
    }

    private static function get_weight_options() {
        $weights = get_option('idm_campaign_weights', []);
        if (!is_array($weights)) {
            return [];
        }
        return $weights;
    }

    private static function get_campaign_entries($campaign) {
        global $wpdb;

        $joins_table   = $wpdb->prefix . 'idm_campaign_joins';
        $members_table = $wpdb->prefix . 'idm_members';

        $sql = "SELECT j.id, j.member_id, j.joined_at, m.name, m.email
                FROM {$joins_table} AS j
                LEFT JOIN {$members_table} AS m ON m.id = j.member_id
                WHERE j.campaign_key = %s
                ORDER BY j.joined_at ASC";

        $results = $wpdb->get_results($wpdb->prepare($sql, $campaign), ARRAY_A);
        if (!$results) {
            return [];
        }

        $entries = [];
        foreach ($results as $row) {
            $entries[] = [
                'id'        => (int) $row['id'],
                'member_id' => (int) $row['member_id'],
                'name'      => (string) ($row['name'] ?? ''),
                'email'     => (string) ($row['email'] ?? ''),
                'joined_at' => (string) ($row['joined_at'] ?? ''),
            ];
        }

        return $entries;
    }

    private static function apply_weights(array $entries, array $weights) {
        if (empty($entries)) {
            return $entries;
        }

        foreach ($entries as &$entry) {
            $entry['weight'] = self::DEFAULT_WEIGHT;
            foreach ($weights as $rule) {
                $field = $rule['field'] ?? 'email';
                $value = $rule['value'] ?? '';
                $chance = isset($rule['weight']) ? (int) $rule['weight'] : self::DEFAULT_WEIGHT;

                if ($field === 'email') {
                    // emailは大文字小文字を無視して比較
                    if (strcasecmp((string) $entry['email'], (string) $value) === 0) {
                        $entry['weight'] = max(1, $chance);
                        break;
                    }
                } elseif ($field === 'name') {
                    // 名前は完全一致（絵文字含む）
                    if ((string) $entry['name'] === (string) $value) {
                        $entry['weight'] = max(1, $chance);
                        break;
                    }
                }
            }
        }
        unset($entry);

        return $entries;
    }

    public static function ajax_draw_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('権限がありません。', 'idm-membership')], 403);
        }

        check_ajax_referer('idm_draw_campaign', 'nonce');

        $campaign = isset($_POST['campaign']) ? self::sanitize_campaign_key(wp_unslash($_POST['campaign'])) : '';
        if ($campaign === '') {
            wp_send_json_error(['message' => __('キャンペーンが指定されていません。', 'idm-membership')]);
        }

        $entries = self::get_campaign_entries($campaign);
        $weights = self::get_weight_options();
        $entries = self::apply_weights($entries, $weights[$campaign] ?? []);

        if (empty($entries)) {
            wp_send_json_error(['message' => __('応募者が存在しません。', 'idm-membership')]);
        }

        $winner = self::pick_winner($entries);
        if (!$winner) {
            wp_send_json_error(['message' => __('抽選に失敗しました。', 'idm-membership')]);
        }

        wp_send_json_success([
            'winner' => [
                'member_id' => $winner['member_id'],
                'entry_id'  => isset($winner['id']) ? (int) $winner['id'] : 0,
                'name'      => isset($winner['name']) ? $winner['name'] : '',
                'email'     => isset($winner['email']) ? $winner['email'] : '',
                'weight'    => isset($winner['weight']) ? (int) $winner['weight'] : self::DEFAULT_WEIGHT,
                'record_id' => 0,
            ],
        ]);
    }

    public static function ajax_save_winner() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('権限がありません。', 'idm-membership')], 403);
        }

        check_ajax_referer('idm_draw_campaign', 'nonce');

        $campaign = isset($_POST['campaign']) ? self::sanitize_campaign_key(wp_unslash($_POST['campaign'])) : '';
        if ($campaign === '') {
            wp_send_json_error(['message' => __('キャンペーンが指定されていません。', 'idm-membership')]);
        }

        $entry_id  = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;

        $entries = self::get_campaign_entries($campaign);
        if (empty($entries)) {
            wp_send_json_error(['message' => __('応募者が存在しません。', 'idm-membership')]);
        }

        $weights = self::get_weight_options();
        $entries = self::apply_weights($entries, $weights[$campaign] ?? []);

        $winner = null;
        foreach ($entries as $entry) {
            if ($entry_id > 0 && (int) $entry['id'] === $entry_id) {
                $winner = $entry;
                break;
            }
        }

        if ($winner === null && $member_id > 0) {
            foreach ($entries as $entry) {
                if ((int) $entry['member_id'] === $member_id) {
                    $winner = $entry;
                    break;
                }
            }
        }

        if ($winner === null) {
            wp_send_json_error(['message' => __('該当する応募者が見つかりません。', 'idm-membership')]);
        }

        $record_id = self::record_winner($campaign, $winner);
        if ($record_id === false) {
            wp_send_json_error(['message' => __('抽選結果を保存できませんでした。', 'idm-membership')]);
        }

        wp_send_json_success([
            'record_id' => $record_id,
            'winner'    => [
                'member_id' => (int) $winner['member_id'],
                'entry_id'  => isset($winner['id']) ? (int) $winner['id'] : 0,
                'name'      => isset($winner['name']) ? (string) $winner['name'] : '',
                'email'     => isset($winner['email']) ? (string) $winner['email'] : '',
                'weight'    => isset($winner['weight']) ? (int) $winner['weight'] : self::DEFAULT_WEIGHT,
            ],
            'message'   => __('抽選結果を保存しました。', 'idm-membership'),
        ]);
    }

    private static function pick_winner(array $entries) {
        $total_weight = 0;
        foreach ($entries as $entry) {
            $weight = isset($entry['weight']) ? (int) $entry['weight'] : self::DEFAULT_WEIGHT;
            if ($weight > 0) {
                $total_weight += $weight;
            }
        }

        if ($total_weight <= 0) {
            return null;
        }

        $rand = random_int(1, $total_weight);
        $cumulative = 0;
        foreach ($entries as $entry) {
            $weight = isset($entry['weight']) ? (int) $entry['weight'] : self::DEFAULT_WEIGHT;
            if ($weight <= 0) {
                continue;
            }
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $entry;
            }
        }

        return end($entries);
    }

    private static function record_winner($campaign, array $winner) {
        global $wpdb;

        $table = $wpdb->prefix . 'idm_campaign_winners';

        if (!self::table_exists($table)) {
            if (!class_exists(__NAMESPACE__ . '\\Install')) {
                $install_file = defined('IDM_MEMBERSHIP_DIR') ? IDM_MEMBERSHIP_DIR . 'includes/class-install.php' : '';
                if ($install_file && is_readable($install_file)) {
                    require_once $install_file;
                }
            }

            if (class_exists(__NAMESPACE__ . '\\Install')) {
                Install::maybe_upgrade();
            }
            if (!self::table_exists($table)) {
                return false;
            }
        }

        $data = [
            'campaign_key'     => $campaign,
            'winner_member_id' => isset($winner['member_id']) ? (int) $winner['member_id'] : 0,
            'entry_id'         => isset($winner['id']) ? (int) $winner['id'] : 0,
            'winner_name'      => isset($winner['name']) ? (string) $winner['name'] : '',
            'winner_email'     => isset($winner['email']) ? (string) $winner['email'] : '',
            'winner_weight'    => isset($winner['weight']) ? (int) $winner['weight'] : self::DEFAULT_WEIGHT,
            'drawn_at'         => current_time('mysql'),
        ];

        $formats = ['%s', '%d', '%d', '%s', '%s', '%d', '%s'];

        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    private static function table_exists($table) {
        global $wpdb;

        $prepared = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
        $found = $wpdb->get_var($prepared);

        return $found === $table;
    }

    /**
     * DBの照合順序がutf8mb4系でない場合に注意喚起（emoji保存用）
     */
    public static function maybe_warn_charset() {
        if (!current_user_can('manage_options')) return;

        global $wpdb;
        // 取得できない環境もあるため @ で抑止しつつ安全に
        $collation = '';
        $row = $wpdb->get_row("SHOW VARIABLES LIKE 'collation_database'");
        if ($row && isset($row->Value)) {
            $collation = (string) $row->Value;
        }
        if ($collation && stripos($collation, 'utf8mb4') === false) {
            self::$messages[] = [
                'type'    => 'notice notice-warning',
                'message' => sprintf(
                    /* translators: %s: collation string */
                    __('現在のDB照合順序は %s です。絵文字（🍒 など）を正しく保存するには utf8mb4 系（例: utf8mb4_unicode_ci）への変更を推奨します。', 'idm-membership'),
                    $collation
                ),
            ];
        }
    }
}

Admin::init();
