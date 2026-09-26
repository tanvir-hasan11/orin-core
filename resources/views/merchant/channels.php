<?php
/** @var array<int, array<string, mixed>> $connections */
/** @var string $whatsappCallback */
/** @var string $messengerCallback */
/** @var array<int, string> $errors */
?>
<h2>Channels</h2>
<p style='color:#64748b'>Connect the WhatsApp number and Facebook page that ORIN should answer on. These are your own accounts.</p>

<?php foreach ($errors as $message): ?>
    <p style='color:#b91c1c'><?= e($message) ?></p>
<?php endforeach; ?>

<h3>Connected</h3>
<table>
    <thead><tr><th>Channel</th><th>Account</th><th>Name</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($connections as $row): ?>
        <tr>
            <td><?= e($row['channel']) ?></td>
            <td><code><?= e((string) $row['external_account_id']) ?></code></td>
            <td><?= e((string) ($row['display_name'] ?? '')) ?></td>
            <td>
                <?= e((string) $row['status']) ?>
                <?php if (!empty($row['last_error'])): ?>
                    <br><small style='color:#b91c1c'><?= e((string) $row['last_error']) ?></small>
                <?php endif; ?>
            </td>
            <td>
                <?php if (($row['status'] ?? '') !== 'disconnected'): ?>
                    <form method='post' action='/merchant/channels/<?= (int) $row['id'] ?>/disconnect' style='display:inline'>
                        <?= csrf_field() ?>
                        <button class='btn' type='submit' style='background:#b91c1c'>Disconnect</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($connections === []): ?>
        <tr><td colspan='5' style='color:#64748b'>Nothing connected yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3 style='margin-top:1.5rem'>Connect WhatsApp</h3>
<p style='color:#64748b'>In Meta, set the callback URL to <code><?= e($whatsappCallback) ?></code> and subscribe to the <code>messages</code> field.</p>
<form method='post' action='/merchant/channels/whatsapp' style='max-width:520px'>
    <?= csrf_field() ?>
    <p><label>Phone number ID<br><input type='text' name='external_account_id' required style='width:100%'></label></p>
    <p><label>Permanent access token<br><input type='text' name='access_token' required style='width:100%'></label></p>
    <p><label>App secret (for signature checks)<br><input type='text' name='app_secret' style='width:100%'></label></p>
    <p><label>Verify token (must match WA_VERIFY_TOKEN)<br><input type='text' name='verify_token' style='width:100%'></label></p>
    <p><label>Label<br><input type='text' name='display_name' placeholder='Main shop number' style='width:100%'></label></p>
    <p><button class='btn' type='submit'>Connect WhatsApp</button></p>
</form>

<h3 style='margin-top:1.5rem'>Connect Messenger</h3>
<p style='color:#64748b'>In Meta, set the callback URL to <code><?= e($messengerCallback) ?></code> and subscribe to <code>messages</code>.</p>
<form method='post' action='/merchant/channels/messenger' style='max-width:520px'>
    <?= csrf_field() ?>
    <p><label>Page ID<br><input type='text' name='external_account_id' required style='width:100%'></label></p>
    <p><label>Page access token<br><input type='text' name='access_token' required style='width:100%'></label></p>
    <p><label>App secret<br><input type='text' name='app_secret' style='width:100%'></label></p>
    <p><label>Label<br><input type='text' name='display_name' placeholder='Facebook shop page' style='width:100%'></label></p>
    <p><button class='btn' type='submit'>Connect Messenger</button></p>
</form>
