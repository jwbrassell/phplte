<?php
require_once 'config.php';
require_once 'includes/OnCallCalendar.php';

try {
    $calendar = new OnCallCalendar();
    
    // Test getting teams
    echo "Getting teams...\n";
    $teams = $calendar->getTeams();
    var_dump($teams);
    
    // Test adding a team
    echo "\nAdding new team...\n";
    $newTeam = $calendar->addTeam("Test Team 2", "success");
    var_dump($newTeam);
    
    // Get teams again to verify
    echo "\nVerifying teams...\n";
    $teams = $calendar->getTeams();
    var_dump($teams);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
