<?php
namespace IDM\Membership {
  if (!defined('ABSPATH')) exit;

  class Admin_Campaigns {

    public static function init() {
      add_action('admin_menu', [__CLASS__, 'menu']);
      add_action('admin_init', [__CLASS__, 'register']);
      add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function menu() {
      // 「設定」メニュー配下に追加（トップに出したい場合は add_menu_page に変更）
      add_options_page(
        'IDMキャンペーン', 'IDMキャンペーン',
        'manage_options', 'idm-campaigns',
        [__CLASS__, 'render']
      );
    }

    public static function register() {
      register_setting('idm_campaigns_group', 'idm_campaigns', [
        'type'              => 'array',
        'sanitize_callback' => [__CLASS__, 'sanitize'],
        'default'           => [],
      ]);
    }

    public static function assets($hook) {
      if ($hook !== 'settings_page_idm-campaigns') return;

      wp_enqueue_media();

      // このファイルのディレクトリURL（例: /plugins/idm-membership/admin/）
      $base = plugin_dir_url(__FILE__);

      wp_enqueue_script(
        'idm-campaigns-admin',
        $base . 'campaigns.js',
        ['jquery'],
        '1.0',
        true
      );

      // インラインJS/CSS
      wp_add_inline_script('idm-campaigns-admin', self::inline_js());
      wp_register_style('idm-campaigns-admin-inline', false);
      wp_enqueue_style('idm-campaigns-admin-inline');
      wp_add_inline_style('idm-campaigns-admin-inline', self::inline_css());
    }

    private static function inline_css() {
      return '
        .idm-cp-table{width:100%;border-collapse:collapse;margin-top:12px}
        .idm-cp-table th,.idm-cp-table td{border:1px solid #ddd;padding:8px;vertical-align:top}
        .idm-cp-row__img{display:flex;gap:8px;align-items:center}
        .idm-cp-row__img img{max-width:80px;height:auto;border:1px solid #ddd;border-radius:4px}
      ';
    }

    private static function inline_js() {
      return <<<JS
(function($){
  function addRow(data){
    data = data || {key:'',title:'',deadline:'',image_id:'',link:''};
    var idx = $('#idm-cp-rows tr').length;
    var html = '<tr>\
      <td><input type="text" name="idm_campaigns['+idx+'][key]" value="'+(data.key||'')+'" placeholder="例: summer-2025" class="regular-text" required /></td>\
      <td><input type="text" name="idm_campaigns['+idx+'][title]" value="'+(data.title||'')+'" placeholder="タイトル" class="regular-text" /></td>\
      <td><input type="datetime-local" name="idm_campaigns['+idx+'][deadline]" value="'+(data.deadline||'')+'" /></td>\
      <td class="idm-cp-row__img">\
           <input type="number" min="0" name="idm_campaigns['+idx+'][image_id]" value="'+(data.image_id||'')+'" class="small-text" />\
           <button type="button" class="button select-image">画像選択</button>\
           <img src="" alt="" style="display:none" />\
      </td>\
      <td><input type="url" name="idm_campaigns['+idx+'][link]" value="'+(data.link||'')+'" placeholder="https://..." class="regular-text code" /></td>\
      <td><button type="button" class="button remove-row">削除</button></td>\
    </tr>';
    $('#idm-cp-rows').append(html);
  }

  $(document).on('click','#idm-cp-add',function(e){
    e.preventDefault(); addRow();
  });

  $(document).on('click','.remove-row',function(){
    $(this).closest('tr').remove();
  });

  $(document).on('click','.select-image',function(e){
    e.preventDefault();
    var btn = $(this), td = btn.closest('td');
    var input = td.find('input[type=number]');
    var img = td.find('img');

    var frame = wp.media({ title: '画像を選択', multiple:false, library:{type:'image'} });
    frame.on('select', function(){
      var a = frame.state().get('selection').first().toJSON();
      input.val(a.id);
      var url = (a.sizes && (a.sizes.medium || a.sizes.thumbnail) ? (a.sizes.medium || a.sizes.thumbnail).url : a.url);
      img.attr('src', url).show();
    });
    frame.open();
  });
})(jQuery);
JS;
    }

    public static function sanitize($input) {
      $out = [];
      if (!is_array($input)) return $out;

      foreach ($input as $row) {
        $key = sanitize_key($row['key'] ?? '');
        if (!$key) continue;

        $deadline = trim((string)($row['deadline'] ?? '')); // そのまま保存

        $out[$key] = [
          'title'    => sanitize_text_field($row['title'] ?? ''),
          'deadline' => $deadline,
          'image_id' => intval($row['image_id'] ?? 0),
          'link'     => esc_url_raw($row['link'] ?? ''),
        ];
      }
      return $out;
    }

    public static function render() {
      if (!current_user_can('manage_options')) return;
      $data = get_option('idm_campaigns', []);
      if (!is_array($data)) $data = [];
      ?>
      <div class="wrap">
        <h1>IDMキャンペーン</h1>
        <p>キャンペーンのキー・タイトル・締め切り・画像・リンク（アフィリンク）を管理します。</p>

        <form method="post" action="options.php">
          <?php settings_fields('idm_campaigns_group'); ?>

          <table class="idm-cp-table">
            <thead>
              <tr>
                <th style="width:14%">キー<span style="color:#d63638">*</span></th>
                <th style="width:18%">タイトル</th>
                <th style="width:18%">締め切り</th>
                <th style="width:24%">画像（ID）</th>
                <th style="width:22%">リンク（外部URL）</th>
                <th style="width:4%"></th>
              </tr>
            </thead>
            <tbody id="idm-cp-rows">
              <?php
              $i = 0;
              foreach ($data as $key => $m):
                $title    = (string)($m['title'] ?? '');
                $deadline = (string)($m['deadline'] ?? '');
                $image_id = (int)($m['image_id'] ?? 0);
                $link     = (string)($m['link'] ?? '');
              ?>
              <tr>
                <td><input type="text" name="idm_campaigns[<?php echo $i; ?>][key]" value="<?php echo esc_attr($key); ?>" class="regular-text" required /></td>
                <td><input type="text" name="idm_campaigns[<?php echo $i; ?>][title]" value="<?php echo esc_attr($title); ?>" class="regular-text" /></td>
                <td><input type="datetime-local" name="idm_campaigns[<?php echo $i; ?>][deadline]" value="<?php echo esc_attr($deadline); ?>" /></td>
                <td class="idm-cp-row__img">
                  <input type="number" min="0" name="idm_campaigns[<?php echo $i; ?>][image_id]" value="<?php echo esc_attr($image_id); ?>" class="small-text" />
                  <button type="button" class="button select-image">画像選択</button>
                  <?php if ($image_id): ?>
                    <img src="<?php echo esc_url( wp_get_attachment_image_url($image_id, 'thumbnail') ); ?>" alt="" />
                  <?php else: ?>
                    <img src="" alt="" style="display:none" />
                  <?php endif; ?>
                </td>
                <td><input type="url" name="idm_campaigns[<?php echo $i; ?>][link]" value="<?php echo esc_attr($link); ?>" class="regular-text code" placeholder="https://..." /></td>
                <td><button type="button" class="button remove-row">削除</button></td>
              </tr>
              <?php $i++; endforeach; ?>
            </tbody>
          </table>

          <p><button id="idm-cp-add" class="button">+ 行を追加</button></p>
          <?php submit_button(); ?>
        </form>
      </div>
      <?php
    }
  }
}