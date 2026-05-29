<?php
// Simple test harness for CV analysis and token overlap
// Run: php scripts/run_cv_analysis_tests.php

chdir(__DIR__ . '/..');
require_once 'app/helpers.php';
require_once 'app/core/Model.php';
require_once 'app/core/Controller.php';
require_once 'app/Controllers/Applications.php';

// Minimal session environment
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Ensure upload folders exist
$cvDir = UPLOAD_ROOT . 'cv' . DIRECTORY_SEPARATOR;
if (!is_dir($cvDir)) {
    mkdir($cvDir, 0755, true);
}

// Write sample CV files (short, medium, long)
$files = [
    'cv_short.txt' => "John Doe\nNo relevant skills\n",
    'cv_medium.txt' => "Jane Candidate\nExperience with PHP, MySQL, JavaScript, Laravel, React\nWorked on projects and internships.\n",
    'cv_good.txt' => "Senior Developer\nPHP, MySQL, JavaScript, React, Docker, AWS\n3 years experience in backend and API development.\nProjects: e-commerce, payroll system. Certificates: PHP cert.\n"
];

foreach ($files as $name => $content) {
    file_put_contents($cvDir . $name, $content);
}

$application = new Applications();
$reflection = new ReflectionClass($application);

$methodOverlap = $reflection->getMethod('calculateTokenOverlapScore');
$methodOverlap->setAccessible(true);

$methodAnalyze = $reflection->getMethod('analyzeCvDocument');
$methodAnalyze->setAccessible(true);

// Test token overlap
$candidateTokens = ['php', 'mysql', 'javascript', 'react'];
$jobTokens = ['php', 'mysql', 'api', 'docker'];
$overlap = $methodOverlap->invokeArgs($application, [$candidateTokens, $jobTokens, 8]);

echo "Token overlap sample: " . $overlap . "%\n";

// Test analyzeCvDocument on each sample file
$jobContext = 'Backend developer PHP MySQL API Docker';
$jobSkills = 'PHP, MySQL, Docker, API';
$profileEvidence = 'Experienced PHP developer with projects';

foreach (array_keys($files) as $file) {
    $result = $methodAnalyze->invokeArgs($application, [$file, $jobContext, $jobSkills, $profileEvidence]);
    echo "\nAnalysis for {$file}:\n";
    echo " has_file: " . ($result['has_file'] ? 'yes' : 'no') . "\n";
    echo " has_valid_text: " . ($result['has_valid_text'] ? 'yes' : 'no') . "\n";
    echo " is_disqualified: " . ($result['is_disqualified'] ? 'yes' : 'no') . "\n";
    echo " job_relevance: " . ($result['job_relevance'] ?? 'N/A') . "\n";
    echo " profile_consistency: " . ($result['profile_consistency'] ?? 'N/A') . "\n";
    echo " quality_factor: " . ($result['quality_factor'] ?? 'N/A') . "\n";
    echo " summary: " . ($result['summary'] ?? '') . "\n";
}

// Cleanup note (files kept for inspection)
echo "\nTest run complete. Sample CV files placed in uploads/cv/.\n";
