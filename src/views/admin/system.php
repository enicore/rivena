<?php // the System Info page: cards of names and values, and the mail with a test email ?>
<div id="page-system" x-data="systemController">
    <h4>System Info</h4>

    <div class="grid grid-350 gap">
        <?php foreach ($sections as $section => $values): ?>
            <div class="card">
                <h6><?= htmlspecialchars($section) ?></h6>
                <table class="mb-0">
                    <?php foreach ($values as $name => $value): ?>
                        <tr>
                            <td class="opacity-60"><?= htmlspecialchars($name) ?></td>
                            <td><?= htmlspecialchars((string)$value) ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </div>
        <?php endforeach ?>

        <div class="card">
            <h6>Mail</h6>
            <table>
                <?php foreach ($mail as $name => $value): ?>
                    <tr>
                        <td class="opacity-60"><?= htmlspecialchars($name) ?></td>
                        <td><?= htmlspecialchars((string)$value) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
            <div class="alert danger mb" x-show="error" x-text="error"></div>
            <button class="small" :class="{progress: busy}" :disabled="busy || !data.mailConfigured"
                    @click="sendTestEmail()">Send test email</button>
        </div>
    </div>
</div>
