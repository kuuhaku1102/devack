# IDM Membership テーマ開発リポジトリ

このリポジトリには WordPress 本番環境と同期するための GitHub Actions ワークフローが含まれています。

## デプロイフロー

- `main` ブランチへ push されると、自動的に本番環境へ rsync でファイルを転送します。
- 管理者は `Actions` タブから `Deploy to Production WordPress` ワークフローを手動実行することもできます。

## 必要なシークレット

リポジトリ (もしくは Organization) の `Settings > Secrets and variables > Actions` に以下を登録してください。

| 名前 | 説明 |
| ---- | ---- |
| `SSH_PRIVATE_KEY` | 本番サーバーへ接続するための秘密鍵 (OpenSSH 形式)。 |
| `SSH_HOST` | 接続先ホスト名または IP アドレス。 |
| `SSH_USER` | SSH ログインユーザー名。 |
| `SSH_REMOTE_PATH` | ファイルを配置するサーバー側パス (例: `/var/www/html/wp-content/themes/idm-membership`). |
| `SSH_PORT` | (任意) SSH ポート番号。未設定時は 22 を使用。 |
| `SSH_KNOWN_HOSTS` | (任意) `ssh-keyscan` で取得した known_hosts エントリ。未設定の場合はワークフローが自動で取得します。 |
| `SSH_POST_DEPLOY_COMMAND` | (任意) デプロイ後に実行するコマンド。キャッシュクリアなどに使用します。 |

## 同期されるファイル

以下のものは自動的に除外されます。

- `.git/`
- `.github/`
- `scripts/`
- `README.md`

必要に応じて `.github/workflows/deploy.yml` の `rsync` オプションを調整してください。

## 都道府県テンプレートのスキャフォールディング

都道府県ごとの会員ページテンプレートは `public/templates/prefectures/` 以下に配置されます。共通レイアウトは `_base.php` にまとめており、各都道府県の `index.php` から `\IDM\Membership\Template::render('prefectures/_base', ...)` で呼び出せるようにしています。

テンプレート一式は以下のスクリプトで自動生成・更新できます。

```bash
php bin/generate-prefecture-templates.php        # 既存ファイルは維持
php bin/generate-prefecture-templates.php --force # 既存ファイルを上書き
php bin/generate-prefecture-templates.php --dry-run
```

生成された各ファイルはダミーデータを含むので、必要に応じて個別に編集してください。
