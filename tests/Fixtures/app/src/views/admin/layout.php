<admin csrf="<?= w_csrf() ?>"<?= isset($spaPage) ? ' page="' . htmlspecialchars(json_encode($spaPage)) . '"' : '' ?>><?= $content ?></admin>
