<?php
/**
 * Test script for EvaluationCalculator
 * 
 * Run this script from command line to test the calculator:
 * php test_evaluation_calculator.php
 */

require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/EvaluationCalculator.php';

echo "=== Testing EvaluationCalculator ===\n\n";

try {
    $calculator = new EvaluationCalculator($pdo);
    
    // Test 1: Get current method
    echo "Test 1: Get current evaluation method\n";
    $method = $calculator->getEvaluationMethod();
    echo "Current method: $method\n";
    echo "Method name: " . $calculator->getMethodName($method) . "\n\n";
    
    // Test 2: Calculate final score - manager_only
    echo "Test 2: Calculate final score (manager_only)\n";
    $calculator->setEvaluationMethod('manager_only');
    $managerScore = 80;
    $supervisorScore = 90;
    $finalScore = $calculator->calculateFinalScore($managerScore, $supervisorScore);
    echo "Manager: $managerScore%, Supervisor: $supervisorScore%\n";
    echo "Final score (manager_only): $finalScore%\n";
    echo "Expected: 80%\n";
    echo $finalScore == 80 ? "✓ PASS\n\n" : "✗ FAIL\n\n";
    
    // Test 3: Calculate final score - average
    echo "Test 3: Calculate final score (average)\n";
    $calculator->setEvaluationMethod('average');
    $finalScore = $calculator->calculateFinalScore($managerScore, $supervisorScore);
    echo "Manager: $managerScore%, Supervisor: $supervisorScore%\n";
    echo "Final score (average): $finalScore%\n";
    echo "Expected: 85%\n";
    echo $finalScore == 85 ? "✓ PASS\n\n" : "✗ FAIL\n\n";
    
    // Test 4: Edge case - only manager score
    echo "Test 4: Edge case - only manager score\n";
    $finalScore = $calculator->calculateFinalScore(75, null);
    echo "Manager: 75%, Supervisor: null\n";
    echo "Final score (average): $finalScore%\n";
    echo "Expected: 75%\n";
    echo $finalScore == 75 ? "✓ PASS\n\n" : "✗ FAIL\n\n";
    
    // Test 5: Edge case - only supervisor score
    echo "Test 5: Edge case - only supervisor score\n";
    $finalScore = $calculator->calculateFinalScore(null, 85);
    echo "Manager: null, Supervisor: 85%\n";
    echo "Final score (average): $finalScore%\n";
    echo "Expected: 85%\n";
    echo $finalScore == 85 ? "✓ PASS\n\n" : "✗ FAIL\n\n";
    
    // Test 6: Edge case - both scores null
    echo "Test 6: Edge case - both scores null\n";
    $finalScore = $calculator->calculateFinalScore(null, null);
    echo "Manager: null, Supervisor: null\n";
    echo "Final score: " . ($finalScore === null ? 'null' : $finalScore) . "\n";
    echo "Expected: null\n";
    echo $finalScore === null ? "✓ PASS\n\n" : "✗ FAIL\n\n";
    
    // Test 7: Get employee scores (if data exists)
    echo "Test 7: Get employee scores (testing with sample data)\n";
    $stmt = $pdo->query("SELECT employee_id, cycle_id FROM employee_evaluations LIMIT 1");
    $sample = $stmt->fetch();
    
    if ($sample) {
        $scores = $calculator->getEmployeeScores($sample['employee_id'], $sample['cycle_id']);
        echo "Employee ID: {$sample['employee_id']}, Cycle ID: {$sample['cycle_id']}\n";
        echo "Manager score: " . ($scores['manager_score'] ?? 'null') . "\n";
        echo "Supervisor score: " . ($scores['supervisor_score'] ?? 'null') . "\n";
        echo "Final score: " . ($scores['final_score'] ?? 'null') . "\n";
        echo "Method: {$scores['method']}\n";
        echo "✓ Test completed\n\n";
    } else {
        echo "No evaluation data found in database\n";
        echo "⚠ Test skipped\n\n";
    }
    
    // Test 8: Invalid method handling
    echo "Test 8: Invalid method handling\n";
    try {
        $calculator->setEvaluationMethod('invalid_method');
        echo "✗ FAIL - Should have thrown exception\n\n";
    } catch (InvalidArgumentException $e) {
        echo "Exception caught: " . $e->getMessage() . "\n";
        echo "✓ PASS\n\n";
    }
    
    // Reset to default
    echo "Resetting to default method (manager_only)...\n";
    $calculator->setEvaluationMethod('manager_only');
    echo "✓ Reset complete\n\n";
    
    echo "=== All tests completed ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
