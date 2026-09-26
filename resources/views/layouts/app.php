<?php
/** @var string $content */
/** @var string $title */
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Orin') ?></title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; line-height: 1.6; }
        header, main, footer { padding: 1.25rem 1.5rem; max-width: 960px; margin: 0 auto; }
        header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #8884; }
        nav a { margin-left: 1rem; text-decoration: none; }
        .hero { padding: 3rem 0; }
        .hero h1 { font-size: 2.25rem; margin: 0 0 .5rem; }
        .btn { display: inline-block; padding: .6rem 1.1rem; border-radius: .4rem; background: #2563eb; color: #fff; text-decoration: none; }
        footer { border-top: 1px solid #8884; color: #888; font-size: .875rem; }
    </style>
</head>
<body>
    <header>
        <a href="/" style="font-weight:700;text-decoration:none;color:inherit">Orin</a>
        <nav>
            <a href="/pricing">Pricing</a>
            <a href="/docs">Docs</a>
            <a href="/login">Login</a>
            <a class="btn" href="/signup">Get started</a>
        </nav>
    </header>

    <main>
        <?= $content ?>
    </main>

    <footer>
        &copy; <?= date('Y') ?> Orin. All rights reserved.
    </footer>
</body>
</html>
