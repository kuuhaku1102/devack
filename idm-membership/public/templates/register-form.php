<?php if (!empty($message)) : ?>
  <div class="idm-err"><?php echo esc_html($message); ?></div>
<?php endif; ?>
<form method="post" class="idm-form">
  <p><label>ニックネーム（必須）</label><br><input type="text" name="idm_name" id="idm_name"
  value="<?php echo isset($old_name) ? esc_attr($old_name) : ''; ?>"
  required aria-required="true" autocomplete="name"></p>
  <p><label>メールアドレス</label><br><input type="email" name="idm_email" required autocomplete="email"></p>
  <p><label>パスワード</label><br><input type="password" name="idm_pass1" required autocomplete="new-password"></p>
  <p><label>パスワード（確認）</label><br><input type="password" name="idm_pass2" required autocomplete="new-password"></p>
  <?php wp_nonce_field('idm_register','idm_reg_nonce'); ?>
  <p><button type="submit">登録する</button></p>
</form>
