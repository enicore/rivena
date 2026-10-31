<!DOCTYPE html>
<html lang="en" class="<?= w_html_class() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf" content="<?= w_csrf() ?>">
    <meta name="user-path" content="<?= w_user_path() ?>">
    <title><?= w_site_title($title ?? '') ?></title>
    <?= w_style('/css/app.css') ?>
    <?= w_script('/js/app.js', ['defer' => true]) ?>
    <?= $this->partial('admin/partials/head') ?>
</head>
<body class="admin">
    <?= $this->partial('admin/partials/navbar') ?>
    <div id="container">
        <?= $this->partial('admin/partials/sidebar') ?>
        <div id="page">
            <div id="content" class="p-lg"><?= $content ?></div>
            <div id="page-loader"><div class="icon-loader a-spin"></div></div>
        </div>
    </div>

    <div id="notifications"></div>
    <?= w_spa_data($spaPage ?? null) ?>
    <?= $this->partial('admin/partials/confirm-dialog') ?>
    <?= $this->partial('admin/partials/content-dialog') ?>
</body>
</html>
