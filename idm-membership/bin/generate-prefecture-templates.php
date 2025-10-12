#!/usr/bin/env php
<?php
$root = dirname(__DIR__) . '/public/templates/prefectures';

$prefectures = [
    ['slug' => 'hokkaido',   'name' => '北海道',   'kana' => 'ほっかいどう'],
    ['slug' => 'aomori',     'name' => '青森県',   'kana' => 'あおもりけん'],
    ['slug' => 'iwate',      'name' => '岩手県',   'kana' => 'いわてけん'],
    ['slug' => 'miyagi',     'name' => '宮城県',   'kana' => 'みやぎけん'],
    ['slug' => 'akita',      'name' => '秋田県',   'kana' => 'あきたけん'],
    ['slug' => 'yamagata',   'name' => '山形県',   'kana' => 'やまがたけん'],
    ['slug' => 'fukushima',  'name' => '福島県',   'kana' => 'ふくしまけん'],
    ['slug' => 'ibaraki',    'name' => '茨城県',   'kana' => 'いばらきけん'],
    ['slug' => 'tochigi',    'name' => '栃木県',   'kana' => 'とちぎけん'],
    ['slug' => 'gunma',      'name' => '群馬県',   'kana' => 'ぐんまけん'],
    ['slug' => 'saitama',    'name' => '埼玉県',   'kana' => 'さいたまけん'],
    ['slug' => 'chiba',      'name' => '千葉県',   'kana' => 'ちばけん'],
    ['slug' => 'tokyo',      'name' => '東京都',   'kana' => 'とうきょうと'],
    ['slug' => 'kanagawa',   'name' => '神奈川県', 'kana' => 'かながわけん'],
    ['slug' => 'niigata',    'name' => '新潟県',   'kana' => 'にいがたけん'],
    ['slug' => 'toyama',     'name' => '富山県',   'kana' => 'とやまけん'],
    ['slug' => 'ishikawa',   'name' => '石川県',   'kana' => 'いしかわけん'],
    ['slug' => 'fukui',      'name' => '福井県',   'kana' => 'ふくいけん'],
    ['slug' => 'yamanashi',  'name' => '山梨県',   'kana' => 'やまなしけん'],
    ['slug' => 'nagano',     'name' => '長野県',   'kana' => 'ながのけん'],
    ['slug' => 'gifu',       'name' => '岐阜県',   'kana' => 'ぎふけん'],
    ['slug' => 'shizuoka',   'name' => '静岡県',   'kana' => 'しずおかけん'],
    ['slug' => 'aichi',      'name' => '愛知県',   'kana' => 'あいちけん'],
    ['slug' => 'mie',        'name' => '三重県',   'kana' => 'みえけん'],
    ['slug' => 'shiga',      'name' => '滋賀県',   'kana' => 'しがけん'],
    ['slug' => 'kyoto',      'name' => '京都府',   'kana' => 'きょうとふ'],
    ['slug' => 'osaka',      'name' => '大阪府',   'kana' => 'おおさかふ'],
    ['slug' => 'hyogo',      'name' => '兵庫県',   'kana' => 'ひょうごけん'],
    ['slug' => 'nara',       'name' => '奈良県',   'kana' => 'ならけん'],
    ['slug' => 'wakayama',   'name' => '和歌山県', 'kana' => 'わかやまけん'],
    ['slug' => 'tottori',    'name' => '鳥取県',   'kana' => 'とっとりけん'],
    ['slug' => 'shimane',    'name' => '島根県',   'kana' => 'しまねけん'],
    ['slug' => 'okayama',    'name' => '岡山県',   'kana' => 'おかやまけん'],
    ['slug' => 'hiroshima',  'name' => '広島県',   'kana' => 'ひろしまけん'],
    ['slug' => 'yamaguchi',  'name' => '山口県',   'kana' => 'やまぐちけん'],
    ['slug' => 'tokushima',  'name' => '徳島県',   'kana' => 'とくしまけん'],
    ['slug' => 'kagawa',     'name' => '香川県',   'kana' => 'かがわけん'],
    ['slug' => 'ehime',      'name' => '愛媛県',   'kana' => 'えひめけん'],
    ['slug' => 'kochi',      'name' => '高知県',   'kana' => 'こうちけん'],
    ['slug' => 'fukuoka',    'name' => '福岡県',   'kana' => 'ふくおかけん'],
    ['slug' => 'saga',       'name' => '佐賀県',   'kana' => 'さがけん'],
    ['slug' => 'nagasaki',   'name' => '長崎県',   'kana' => 'ながさきけん'],
    ['slug' => 'kumamoto',   'name' => '熊本県',   'kana' => 'くまもとけん'],
    ['slug' => 'oita',       'name' => '大分県',   'kana' => 'おおいたけん'],
    ['slug' => 'miyazaki',   'name' => '宮崎県',   'kana' => 'みやざきけん'],
    ['slug' => 'kagoshima',  'name' => '鹿児島県', 'kana' => 'かごしまけん'],
    ['slug' => 'okinawa',    'name' => '沖縄県',   'kana' => 'おきなわけん'],
];

$options = getopt('', ['force', 'dry-run']);
$force   = array_key_exists('force', $options);
$dryRun  = array_key_exists('dry-run', $options);

if (!is_dir($root)) {
    if ($dryRun) {
        echo "[dry-run] would create directory: {$root}\n";
    } else {
        mkdir($root, 0775, true);
        echo "created directory: {$root}\n";
    }
}

$template = <<<'PHP'
<?php
use IDM\Membership\Template;

if (!defined('ABSPATH')) {
    exit;
}

Template::render('prefectures/_base', [
    'prefecture' => [
        'slug' => '%slug%',
        'name' => '%name%',
        'kana' => '%kana%',
    ],
    'title' => '%name%の会員情報',
    'lead' => '%name%に関する紹介文をここに記載します。',
    'stats' => [
        '登録会員数' => '',
        '最終更新日' => '',
    ],
    'sections' => [
        [
            'title' => '主なトピック',
            'body' => '%name%に関する詳細情報を入力してください。',
            'items' => [
                [
                    'label' => 'サンプル項目',
                    'description' => '必要に応じて項目を更新してください。',
                ],
            ],
        ],
    ],
    'links' => [
        [
            'label' => 'お問い合わせ',
            'url' => '',
            'target' => '',
            'rel' => '',
        ],
    ],
]);
 
PHP;

foreach ($prefectures as $prefecture) {
    $dir = $root . '/' . $prefecture['slug'];
    $file = $dir . '/index.php';
    if (!is_dir($dir)) {
        if ($dryRun) {
            echo "[dry-run] would create directory: {$dir}\n";
        } else {
            mkdir($dir, 0775, true);
            echo "created directory: {$dir}\n";
        }
    }

    if (file_exists($file) && !$force) {
        echo "skipped existing file: {$file}\n";
        continue;
    }

    $content = str_replace(
        ['%slug%', '%name%', '%kana%'],
        [$prefecture['slug'], $prefecture['name'], $prefecture['kana']],
        $template
    );

    if ($dryRun) {
        echo "[dry-run] would write: {$file}\n";
    } else {
        file_put_contents($file, rtrim($content) . PHP_EOL);
        echo "wrote file: {$file}\n";
    }
}
