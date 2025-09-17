<?php if (!empty($registered)) : ?>
  <div class="idm-ok">登録が完了しました。ログインしてください。</div>
<?php endif; ?>
<?php if (!empty($message)) : ?>
  <div class="idm-err"><?php echo esc_html($message); ?></div>
<?php endif; ?>
<form method="post" class="idm-form">
  <p><label>メールアドレス</label><br><input type="email" name="idm_email" required autocomplete="email"></p>
  <p><label>パスワード</label><br><input type="password" name="idm_pass" required autocomplete="current-password"></p>
  <?php wp_nonce_field('idm_login','idm_login_nonce'); ?>
  <p><button type="submit">ログイン</button></p>
</form>
<p>未登録の方は <a href="<?php echo esc_url( home_url('/register/') ); ?>">こちら</a> から登録できます。</p>
