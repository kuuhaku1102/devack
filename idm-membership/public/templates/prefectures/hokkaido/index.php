<?php
use IDM\Membership\Template;

if (!defined('ABSPATH')) {
    exit;
}

Template::render('prefectures/_base', [
    'prefecture' => [
        'slug' => 'hokkaido',
        'name' => '北海道',
        'kana' => 'ほっかいどう',
    ],
    'title' => '北海道の会員情報',
    'lead' => '北海道に関する紹介文をここに記載します。',
    'stats' => [
        '登録会員数' => '',
        '最終更新日' => '',
    ],
    'sections' => [
        [
            'title' => '主なトピック',
            'body' => '北海道に関する詳細情報を入力してください。',
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
