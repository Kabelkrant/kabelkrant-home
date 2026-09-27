<?php
defined('APP_ROOT') || exit;

use App\Settings;
use App\View;

/** @var array $settings  @var string $title  @var list<string> $stylesheets */
$favicon = $settings['favicon'] ?: $settings['logo'];
$c = $settings['colors'];
?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= View::e($title) ?></title>
  <link rel="icon" type="<?= View::mimeFor($favicon) ?>" href="<?= View::asset($favicon) ?>">
<?php foreach ($stylesheets as $css): ?>
  <link rel="stylesheet" href="<?= View::asset($css) ?>">
<?php endforeach ?>
  <style>
    :root {
      --color-background: <?= $c['background'] ?>;
      --color-text: <?= $c['text'] ?>;
      --color-link: <?= $c['link'] ?>;
      --color-link-hover: <?= $c['link_hover'] ?>;
      --color-accent: <?= $c['accent'] ?>;
      --background-image: <?= Settings::backgroundCss($settings) ?>;
    }
  </style>
