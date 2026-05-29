<?php

declare(strict_types=1);

/**
 * Dependency-free coverage gate.
 *
 * Reads a Clover report and fails (exit code 1) when statement or method
 * coverage is below the required threshold.
 *
 * Usage: php tools/coverage-check.php <clover.xml> [min-percent]
 */

$cloverPath = $argv[1] ?? 'build/clover.xml';
$threshold = isset($argv[2]) ? (float) $argv[2] : 100.0;

if (!is_file($cloverPath)) {
    fwrite(STDERR, sprintf("Coverage report not found: %s\n", $cloverPath));
    exit(1);
}

$xml = @simplexml_load_file($cloverPath);
if (false === $xml) {
    fwrite(STDERR, sprintf("Unable to parse coverage report: %s\n", $cloverPath));
    exit(1);
}

$metrics = $xml->project->metrics ?? null;
if (null === $metrics) {
    fwrite(STDERR, "No <metrics> element found in coverage report.\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];
$methods = (int) $metrics['methods'];
$coveredMethods = (int) $metrics['coveredmethods'];

$percent = static fn (int $covered, int $total): float => 0 === $total ? 100.0 : ($covered / $total) * 100;

$statementPct = $percent($coveredStatements, $statements);
$methodPct = $percent($coveredMethods, $methods);

printf(
    "Coverage — statements: %.2f%% (%d/%d), methods: %.2f%% (%d/%d), threshold: %.2f%%\n",
    $statementPct,
    $coveredStatements,
    $statements,
    $methodPct,
    $coveredMethods,
    $methods,
    $threshold,
);

$failed = false;
if ($statementPct < $threshold) {
    fwrite(STDERR, sprintf("FAIL: statement coverage %.2f%% < %.2f%%\n", $statementPct, $threshold));
    $failed = true;
}
if ($methodPct < $threshold) {
    fwrite(STDERR, sprintf("FAIL: method coverage %.2f%% < %.2f%%\n", $methodPct, $threshold));
    $failed = true;
}

if ($failed) {
    exit(1);
}

echo "Coverage gate passed.\n";
exit(0);
