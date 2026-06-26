<?php
/** Shared flash + error banner. Include at the top of forms/pages. */
$status = session()->get('status');
$error = session()->get('error');
$errors = session()->get('errors', []);
?>
<?php if ($status): ?>
    <div class="alert-success mb-4">
        <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <span><?= e($status) ?></span>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert-error mb-4">
        <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>
<?php if (! empty($errors) && empty($hideErrorSummary ?? false)): ?>
    <div class="alert-error mb-4">
        <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <div>
            <p class="font-medium">Please fix the following:</p>
            <ul class="mt-1 list-disc ps-5">
                <?php foreach ($errors as $fieldErrors): ?>
                    <?php foreach ((array) $fieldErrors as $message): ?>
                        <li><?= e($message) ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
