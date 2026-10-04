<?php
/** @var list<string> $log */
?>
<h1 class="mb-1 text-xl font-semibold text-emerald-700">Installation complete</h1>
<p class="mb-5 text-sm text-slate-500">Your database is configured and the System Owner is created. Setup is now locked.</p>

<pre class="mb-6 max-h-72 overflow-auto rounded-lg bg-slate-900 px-4 py-3 font-mono text-xs leading-relaxed text-emerald-300"><?php foreach ($log as $line): ?><?= e($line) . "\n" ?><?php endforeach; ?></pre>

<a href="/login" class="block w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-700">Sign in as the System Owner →</a>
