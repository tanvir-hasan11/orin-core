<?php
/** @var string $content */
/** @var string $title */
$user = current_user();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Panel') ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; display: flex; min-height: 100vh; }
        aside { width: 240px; background: #0f172a; color: #cbd5e1; padding: 1.25rem; }
        aside a { display: block; color: #cbd5e1; text-decoration: none; padding: .5rem .25rem; border-radius: .35rem; }
        aside a:hover { background: #1e293b; color: #fff; }
        aside h1 { font-size: 1.1rem; margin: 0 0 1rem; color: #fff; }
        main { flex: 1; padding: 1.5rem 2rem; }
        .topbar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: .75rem; margin-bottom: 1.25rem; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 1rem; }
        .card { border: 1px solid #e2e8f0; border-radius: .5rem; padding: 1rem; }
        .card .n { font-size: 1.6rem; font-weight: 700; }
        .card .l { color: #64748b; font-size: .875rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: .5rem .4rem; border-bottom: 1px solid #e2e8f0; font-size: .925rem; }
        .btn { display: inline-block; padding: .35rem .7rem; border-radius: .35rem; background: #2563eb; color: #fff; border: none; cursor: pointer; text-decoration: none; font-size: .875rem; }
    </style>
</head>
<body>
<aside>
    <h1>Orin</h1>
    <?= $sidebar ?? '' ?>
</aside>
<main>
    <div class="topbar">
        <strong><?= e($title ?? '') ?></strong>
        <span>
            <?= e($user['name'] ?? '') ?> (<?= e($user['role'] ?? '') ?>)
            <form method="post" action="/logout" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn" type="submit">Log out</button>
            </form>
        </span>
    </div>
    <?= $content ?>
</main>
</body>
</html>
