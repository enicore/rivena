<?php // the login, forgot password and reset password pages (AuthController), apart from the site's public layout ?>
<!DOCTYPE html>
<html lang="en" class="<?= w_html_class() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= w_site_title($title ?? '') ?></title>
    <?= w_style('/css/app.css') ?>
    <?= w_script('/js/app.js', ['defer' => true]) ?>
</head>
<body>
    <?= $content ?>
</body>
</html>
