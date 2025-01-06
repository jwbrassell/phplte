<?php
function get_datatable_data($filename) {
    $json_path = dirname(__FILE__) . '/../../data/examples/datatable/' . $filename;
    
    if (!file_exists($json_path)) {
        error_log("JSON file not found: " . $json_path);
        return [
            "error" => "JSON file not found",
            "title" => "Error",
            "last_updated" => date("Y-m-d"),
            "headers" => [],
            "data" => []
        ];
    }
    
    $json_content = file_get_contents($json_path);
    if ($json_content === false) {
        error_log("Failed to read JSON file: " . $json_path);
        return [
            "error" => "Failed to read JSON file",
            "title" => "Error",
            "last_updated" => date("Y-m-d"),
            "headers" => [],
            "data" => []
        ];
    }
    
    $data = json_decode($json_content, true);
    if ($data === null) {
        error_log("Failed to parse JSON: " . json_last_error_msg());
        return [
            "error" => "Invalid JSON format",
            "title" => "Error",
            "last_updated" => date("Y-m-d"),
            "headers" => [],
            "data" => []
        ];
    }
    
    return $data;
}
?>
