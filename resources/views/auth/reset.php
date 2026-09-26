<?php
/** @var array<string, array<int, string>> $errors */
/** @var string $email */
/** @var string $token */
$errors = $errors ?? [];
?>
<section style="max-width:380px;margin:3rem auto">
    <h1>Reset password</h1>

    <?php foreach ($errors as $messages): ?>
        <?php foreach ($messages as $message): ?>
            <p style="color:#b91c1c"><?= e($message) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <form method="post" action="/reset-password">
        <?= csrf_field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <p><label>New password (min 8)<br>
            <input type="password" name="password" required minlength="8" style="width:100%">
        </label></p>
        <p><label>Confirm new password<br>
            <input type="password" name="password_confirmation" required minlength="8" style="width:100%">
        </label></p>
        <p><button class="btn" type="submit">Reset password</button></p>
    </form>
</section>
