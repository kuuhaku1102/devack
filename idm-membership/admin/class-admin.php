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

        add_submenu_page(
            $slug,
            __('ダッシュボード', 'idm-membership'),
            __('ダッシュボード', 'idm-membership'),
            'manage_options',
            $slug,
            [__CLASS__, 'render_dashboard']
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
                'i18n'     => [
                    'noEntries' => __('応募者がいません。', 'idm-membership'),
                    'drawing'   => __('抽選中...', 'idm-membership'),
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
        echo '<th>' . esc_html__('メールアドレス', 'idm-membership') . '</th>';
        echo '<th>' . esc_html__('応募日時', 'idm-membership') . '</th>';
        echo '<th>' . esc_html__('抽選確率(%)', 'idm-membership') . '</th>';
        echo '</tr></thead>';
        echo '<tbody class="idm-entrant-list">';
        foreach ($entries as $entry) {
            $name  = $entry['name'] !== '' ? $entry['name'] : __('(未設定)', 'idm-membership');
            $email = $entry['email'] !== '' ? $entry['email'] : __('(メール不明)', 'idm-membership');
            printf(
                '<tr class="idm-entrant" data-member-id="%1$d" data-weight="%4$d"><td>%2$s</td><td>%3$s</td><td>%5$s</td><td>%4$d</td></tr>',
                (int) $entry['member_id'],
                esc_html($name),
                esc_html($email),
                (int) $entry['weight'],
                esc_html($entry['joined_at'])
            );
        }
        echo '</tbody>';
        echo '</table>';
    }

    private static function render_weights_form($campaign, array $weights) {
        echo '<h2>' . esc_html__('抽選確率の調整', 'idm-membership') . '</h2>';
        echo '<p class="description">' . esc_html__('特定の会員を名前またはメールアドレスで指定し、抽選確率を％で上書きできます。未指定の応募者は100%（等確率）です。', 'idm-membership') . '</p>';

        echo '<form method="post" class="idm-weights-form">';
        wp_nonce_field('idm_save_weights');
        echo '<input type="hidden" name="idm_action" value="save_weights" />';
        echo '<input type="hidden" name="campaign" value="' . esc_attr($campaign) . '" />';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('対象', 'idm-membership') . '</th>';
        echo '<th>' . esc_html__('識別値', 'idm-membership') . '</th>';
        echo '<th>' . esc_html__('確率(%)', 'idm-membership') . '</th>';
        echo '<th class="idm-column-actions"></th>';
        echo '</tr></thead>';
        echo '<tbody id="idm-weight-rows">';

        if (empty($weights)) {
            $weights = [];
        }

        $index = 0;
        foreach ($weights as $weight) {
            $field  = isset($weight['field']) ? $weight['field'] : 'email';
            $value  = isset($weight['value']) ? $weight['value'] : '';
            $chance = isset($weight['weight']) ? (int)$weight['weight'] : self::DEFAULT_WEIGHT;
            self::render_weight_row($index, $field, $value, $chance);
            $index++;
        }

        // Empty template row (will be cloned by JS when adding new rule).
        self::render_weight_row('__INDEX__', 'email', '', self::DEFAULT_WEIGHT, true);

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

        echo '<tr class="' . esc_attr($tr_class) . '"' . ($is_template ? ' data-template="1" style="display:none;"' : '') . '>';
        echo '<td>';
        echo '<select name="' . esc_attr($name_prefix . '[field]') . '"' . $disabled . '>';
        printf('<option value="email" %s>%s</option>', selected($field, 'email', false), esc_html__('メールアドレス', 'idm-membership'));
        printf('<option value="name" %s>%s</option>', selected($field, 'name', false), esc_html__('名前', 'idm-membership'));
        echo '</select>';
        echo '</td>';
        echo '<td><input type="text" name="' . esc_attr($name_prefix . '[value]') . '" value="' . esc_attr($value) . '" class="regular-text"' . $disabled . ' /></td>';
        echo '<td><input type="number" name="' . esc_attr($name_prefix . '[weight]') . '" value="' . esc_attr($chance) . '" min="1" max="1000" class="small-text"' . $disabled . ' /> %</td>';
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

        $submitted_campaign = isset($_POST['campaign']) ? sanitize_key(wp_unslash($_POST['campaign'])) : '';
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

    private static function sanitize_weights($weights) {
        $clean = [];
        if (!is_array($weights)) {
            return $clean;
        }

        foreach ($weights as $weight) {
            $field = isset($weight['field']) ? sanitize_key($weight['field']) : 'email';
            if (!in_array($field, ['email', 'name'], true)) {
                $field = 'email';
            }

            $value = isset($weight['value']) ? sanitize_text_field($weight['value']) : '';
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

    private static function get_selected_campaign(?array $available = null) {
        $campaign = isset($_GET['campaign']) ? sanitize_key(wp_unslash($_GET['campaign'])) : '';
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
                    if (strcasecmp((string) $entry['email'], (string) $value) === 0) {
                        $entry['weight'] = max(1, $chance);
                        break;
                    }
                } elseif ($field === 'name') {
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

        $campaign = isset($_POST['campaign']) ? sanitize_key(wp_unslash($_POST['campaign'])) : '';
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
                'name'      => $winner['name'],
                'email'     => $winner['email'],
                'weight'    => $winner['weight'],
            ],
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
}

Admin::init();
