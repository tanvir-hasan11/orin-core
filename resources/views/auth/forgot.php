<?php
/** @var array<string, array<int, string>> $errors */
/** @var array<string, string> $old */
/** @var bool|null $sent */
/** @var string|null $debugLink */
$errors = $errors ?? [];
$old = $old ?? [];
?>
<section style="max-width:380px;margin:3rem auto">
    <h1>Forgot password</h1>

    <?php if (!empty($sent)): ?>
        <p>If that email exists, a reset link has been sent.</p>
        <?php if (!empty($debugLink)): ?>
            <p style="word-break:break-all"><small>Dev link: <a href="<?= e($debugLink) ?>"><?= e($debugLink) ?></a></small></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php foreach ($errors as $messages): ?>
        <?php foreach ($messages as $message): ?>
            <p style="color:#b91c1c"><?= e($message) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <form method="post" action="/forgot-password">
        <?= csrf_field() ?>
        <p><label>Email<br>
            <input type="email" name="email" required value="<?= e(old(['old' => $old], 'email')) ?>" style="width:100%">
        </label></p>
        <p><button class="btn" type="submit">Send reset link</button></p>
    </form>

    <p><a href="/login">Back to login</a></p>
</section>
