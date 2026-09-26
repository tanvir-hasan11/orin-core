<?php
/** @var array<string, array<int, string>> $errors */
/** @var array<string, string> $old */
$errors = $errors ?? [];
$old = $old ?? [];
?>
<section style="max-width:380px;margin:3rem auto">
    <h1>Log in</h1>

    <?php foreach ($errors as $messages): ?>
        <?php foreach ($messages as $message): ?>
            <p style="color:#b91c1c"><?= e($message) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <form method="post" action="/login">
        <?= csrf_field() ?>
        <p>
            <label>Email<br>
                <input type="email" name="email" required value="<?= e(old(['old' => $old], 'email')) ?>" style="width:100%">
            </label>
        </p>
        <p>
            <label>Password<br>
                <input type="password" name="password" required style="width:100%">
            </label>
        </p>
        <p><button class="btn" type="submit">Log in</button></p>
    </form>

    <p><a href="/forgot-password">Forgot your password?</a></p>
    <p>No account yet? <a href="/signup">Sign up</a></p>
</section>
