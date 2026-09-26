<?php
/** @var array<string, mixed> $merchant */
/** @var array<string, mixed>|null $user */
/** @var array<string, array<int, string>> $errors */
/** @var array<string, array<int, string>> $passwordErrors */
$errors = $errors ?? [];
$passwordErrors = $passwordErrors ?? [];
?>
<h2>Profile</h2>
<?php foreach ($errors as $messages): ?>
    <?php foreach ($messages as $message): ?>
        <p style="color:#b91c1c"><?= e($message) ?></p>
    <?php endforeach; ?>
<?php endforeach; ?>
<form method="post" action="/merchant/settings" style="max-width:420px">
    <?= csrf_field() ?>
    <p><label>Your name<br><input type="text" name="name" value="<?= e($user['name'] ?? '') ?>" style="width:100%"></label></p>
    <p><label>Company<br><input type="text" name="company" value="<?= e($merchant['company_name'] ?? '') ?>" style="width:100%"></label></p>
    <p><label>Email<br><input type="email" value="<?= e($user['email'] ?? '') ?>" disabled style="width:100%"></label></p>
    <p><button class="btn" type="submit">Save profile</button></p>
</form>

<h2 style="margin-top:2rem">Change password</h2>
<?php foreach ($passwordErrors as $messages): ?>
    <?php foreach ($messages as $message): ?>
        <p style="color:#b91c1c"><?= e($message) ?></p>
    <?php endforeach; ?>
<?php endforeach; ?>
<form method="post" action="/merchant/settings/password" style="max-width:420px">
    <?= csrf_field() ?>
    <p><label>Current password<br><input type="password" name="current_password" required style="width:100%"></label></p>
    <p><label>New password (min 8)<br><input type="password" name="new_password" required minlength="8" style="width:100%"></label></p>
    <p><label>Confirm new password<br><input type="password" name="new_password_confirmation" required minlength="8" style="width:100%"></label></p>
    <p><button class="btn" type="submit">Update password</button></p>
</form>
