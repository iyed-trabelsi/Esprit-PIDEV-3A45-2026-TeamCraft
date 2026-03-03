<?php
/**
 * Fix PHPStan annotations and inline-merged property declarations
 * Handles case where comment and property are on same line (Set-Content bug)
 */

$entityDir = __DIR__ . '/src/Entity';
$files = glob($entityDir . '/*.php');
$fixed = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;

    // Case 1: comment and private ?int $id are on SAME line (corrupted by Set-Content)
    // Pattern: "// @phpstan-ignore-next-line property.unusedType    private ?int $id = null;"
    $content = preg_replace(
        '/    \/\/ @phpstan-ignore-next-line property\.unusedType\s+private \?int \$id = null;/',
        '    /** @var int|null */' . "\n" . '    private ?int $id = null;',
        $content
    );

    // Case 2: orphan @phpstan-ignore-next-line on separate line (no error to ignore anymore)
    $content = preg_replace(
        '/    \/\/ @phpstan-ignore-next-line property\.unusedType\r?\n    (private \?int \$id = null;)/',
        '    /** @var int|null */' . "\n" . '    $1',
        $content
    );

    // Case 3: same as Case 2 for files rewritten (Admin.php, ResetPasswordRequest.php)
    $content = preg_replace(
        '/    \/\/ @phpstan-ignore-next-line\r?\n    (private \?int \$id = null;)/',
        '    /** @var int|null */' . "\n" . '    $1',
        $content
    );

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Fixed: " . basename($file) . "\n";
        $fixed++;
    }
}

echo "\nTotal: $fixed files fixed\n";
