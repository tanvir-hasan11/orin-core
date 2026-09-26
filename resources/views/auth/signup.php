<?php
/** @var array<string, array<int, string>> $errors */
/** @var array<string, string> $old */
$errors = $errors ?? [];
$old = $old ?? [];
?>
<section style="max-width:420px;margin:3rem auto">
    <h1>Create your account</h1>

    <?php foreach ($errors as $messages): ?>
        <?php foreach ($messages as $message): ?>
            <p style="color:#b91c1c"><?= e($message) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <form method="post" action="/signup">
        <?= csrf_field() ?>
        <p><label>Your name<br>
            <input type="text" name="name" required value="<?= e(old(['old' => $old], 'name')) ?>" style="width:100%">
        </label></p>
        <p><label>Company<br>
            <input type="text" name="company" required value="<?= e(old(['old' => $old], 'company')) ?>" style="width:100%">
        </label></p>
        <p><label>Email<br>
            <input type="email" name="email" required value="<?= e(old(['old' => $old], 'email')) ?>" style="width:100%">
        </label></p>
        <p><label>Password (min 8 chars)<br>
            <input type="password" name="password" required minlength="8" style="width:100%">
        </label></p>
        <p><label>Confirm password<br>
            <input type="password" name="password_confirmation" required minlength="8" style="width:100%">
        </label></p>
        <p><button class="btn" type="submit">Create account</button></p>
    </form>

    <p>Already have an account? <a href="/login">Log in</a></p>
</section>
