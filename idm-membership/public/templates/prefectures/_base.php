<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prefecture shared layout template.
 *
 * Expected variables:
 * - $prefecture (array): slug/name and optional extra metadata.
 * - $title (string): Optional page title. Falls back to prefecture name.
 * - $lead (string): Optional lead text.
 * - $stats (array): Key/value pairs rendered as definition list.
 * - $sections (array): Each section supports keys `title`, `body`, `items`.
 * - $links (array): Optional list of call-to-action links with `label`/`url`.
 */

$prefecture = isset($prefecture) && is_array($prefecture) ? $prefecture : [];
$title      = isset($title) ? $title : '';
$lead       = isset($lead) ? $lead : '';
$stats      = isset($stats) && is_array($stats) ? array_filter($stats) : [];
$sections   = isset($sections) && is_array($sections) ? $sections : [];
$links      = isset($links) && is_array($links) ? $links : [];

$slug = isset($prefecture['slug']) ? sanitize_html_class($prefecture['slug']) : '';
$name = isset($prefecture['name']) ? $prefecture['name'] : '';

if (!$title && $name) {
    $title = $name;
}
?>
<div class="idm-prefecture <?php echo esc_attr($slug ? 'idm-prefecture--' . $slug : ''); ?>">
    <header class="idm-prefecture__header">
        <?php if ($title) : ?>
            <h1 class="idm-prefecture__title"><?php echo esc_html($title); ?></h1>
        <?php endif; ?>
        <?php if ($lead) : ?>
            <p class="idm-prefecture__lead"><?php echo wp_kses_post($lead); ?></p>
        <?php endif; ?>
    </header>

    <?php if ($stats) : ?>
        <dl class="idm-prefecture__stats">
            <?php foreach ($stats as $label => $value) :
                if ($value === '' || $value === null) {
                    continue;
                }
                ?>
                <div class="idm-prefecture__stat">
                    <dt class="idm-prefecture__stat-label"><?php echo esc_html($label); ?></dt>
                    <dd class="idm-prefecture__stat-value"><?php echo esc_html($value); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <?php if ($sections) : ?>
        <div class="idm-prefecture__sections">
            <?php foreach ($sections as $section) :
                if (empty($section) || !is_array($section)) {
                    continue;
                }
                $section_title = isset($section['title']) ? $section['title'] : '';
                $section_body  = isset($section['body']) ? $section['body'] : '';
                $items         = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];
                ?>
                <section class="idm-prefecture__section">
                    <?php if ($section_title) : ?>
                        <h2 class="idm-prefecture__section-title"><?php echo esc_html($section_title); ?></h2>
                    <?php endif; ?>

                    <?php if ($section_body) : ?>
                        <div class="idm-prefecture__section-body"><?php echo wp_kses_post(wpautop($section_body)); ?></div>
                    <?php endif; ?>

                    <?php if ($items) : ?>
                        <ul class="idm-prefecture__section-items">
                            <?php foreach ($items as $item) :
                                if (!is_array($item)) {
                                    $item = ['label' => $item];
                                }
                                $item_label = isset($item['label']) ? $item['label'] : '';
                                $item_desc  = isset($item['description']) ? $item['description'] : '';
                                ?>
                                <li class="idm-prefecture__section-item">
                                    <?php if ($item_label) : ?>
                                        <span class="idm-prefecture__section-item-label"><?php echo esc_html($item_label); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item_desc) : ?>
                                        <span class="idm-prefecture__section-item-description"><?php echo esc_html($item_desc); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($links) : ?>
        <div class="idm-prefecture__links">
            <ul class="idm-prefecture__link-list">
                <?php foreach ($links as $link) :
                    if (!is_array($link) || empty($link['label']) || empty($link['url'])) {
                        continue;
                    }
                    $target   = !empty($link['target']) ? $link['target'] : '';
                    $rel      = !empty($link['rel']) ? $link['rel'] : '';
                    ?>
                    <li class="idm-prefecture__link-item">
                        <a class="idm-prefecture__link" href="<?php echo esc_url($link['url']); ?>"<?php echo $target ? ' target="' . esc_attr($target) . '"' : ''; ?><?php echo $rel ? ' rel="' . esc_attr($rel) . '"' : ''; ?>>
                            <?php echo esc_html($link['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
