<?php
/** @var string $title */
/** @var array<int, string> $features */
?>
<section class="hero">
    <h1>Resilient AI infrastructure for builders</h1>
    <p>
        One API over OpenAI, Gemini and OpenRouter &mdash; with automatic failover,
        token accounting and cost guardrails built in.
    </p>
    <p>
        <a class="btn" href="/signup">Create an account</a>
    </p>
</section>

<section>
    <h2>What you get</h2>
    <ul>
        <?php foreach ($features as $feature): ?>
            <li><?= e($feature) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
