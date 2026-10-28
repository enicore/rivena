<?php // the public pages' layout (Controller) and the error pages', the site's design; init copies it to the project ?>
<!DOCTYPE html>
<html lang="en" class="<?= w_html_class() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= w_site_title($title ?? '') ?></title>
    <?= w_style('/css/app.css') ?>
    <?= w_script('/js/app.js', ['defer' => true]) ?>
</head>
<body>
    <?= $content ?>
</body>
</html>
