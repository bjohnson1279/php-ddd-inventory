<?php
declare(strict_types=1);

// PHPUnit's Clover project metrics contain the aggregate statement coverage.
// Reject missing/empty reports rather than treating absent instrumentation as 100%.
$path = $argv[1] ?? 'build/coverage.xml';
$minimum = (float) ($argv[2] ?? 50);
if (!is_file($path)) {
    fwrite(STDERR, "Coverage report is missing: {$path}\n");
    exit(1);
}
$report = simplexml_load_file($path);
$metrics = $report === false ? null : $report->project->metrics;
$statements = $metrics === null ? 0 : (int) $metrics['statements'];
$covered = $metrics === null ? 0 : (int) $metrics['coveredstatements'];
if ($statements <= 0 || $covered > $statements) {
    fwrite(STDERR, "Coverage report has invalid or empty statement metrics.\n");
    exit(1);
}
$percentage = 100 * $covered / $statements;
printf("Statement coverage: %.2f%%; minimum: %.2f%%\n", $percentage, $minimum);
exit($percentage >= $minimum ? 0 : 1);
