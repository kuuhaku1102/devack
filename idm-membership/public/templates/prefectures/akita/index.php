<?php
use IDM\Membership\Template;

if (!defined('ABSPATH')) {
    exit;
}

Template::render('prefectures/_base', [
    'prefecture' => [
        'slug' => 'akita',
        'name' => '秋田県',
        'kana' => 'あきたけん',
    ],
    'title' => '秋田県の会員情報',
    'lead' => '秋田県に関する紹介文をここに記載します。',
    'stats' => [
        '登録会員数' => '',
        '最終更新日' => '',
    ],
    'sections' => [
        [
            'title' => '主なトピック',
            'body' => '秋田県に関する詳細情報を入力してください。',
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
