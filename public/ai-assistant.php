<?php
// Add these endpoints to public/api.php

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    match ($action) {
        'ai_query' => handleAIQuery(),
        'ai_search_products' => handleAIProductSearch(),
        'ai_get_recommendations' => handleAIRecommendations(),
        'ai_budget_analysis' => handleAIBudgetAnalysis(),
        'ai_allergen_check' => handleAIAllergenCheck(),
        'ai_recipe_help' => handleAIRecipeHelp(),
        default => jsonError('Unknown action'),
    };
} catch (Exception $e) {
    jsonError($e->getMessage());
}

function handleAIQuery() {
    $query = $_POST['query'] ?? $_GET['q'] ?? '';
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? 1;
    $locationId = $_POST['location_id'] ?? $_GET['location_id'] ?? null;

    if (empty($query)) {
        return jsonError('Query is required');
    }

    global $db;
    $aiService = new AIAssistantService($db);
    $result = $aiService->processQuery($query, (int) $userId, $locationId);

    json($result);
}

function handleAIProductSearch() {
    $query = $_POST['query'] ?? $_GET['q'] ?? '';
    $locationId = $_POST['location_id'] ?? $_GET['location_id'] ?? null;

    if (empty($query)) {
        return jsonError('Search query required');
    }

    global $db;
    $aiService = new AIAssistantService($db);
    $result = $aiService->processQuery("search for $query", 1, $locationId);

    json($result);
}

function handleAIRecommendations() {
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? 1;
    $locationId = $_POST['location_id'] ?? $_GET['location_id'] ?? null;

    global $db;
    $aiService = new AIAssistantService($db);
    $result = $aiService->processQuery('What should I buy based on my cart?', (int) $userId, $locationId);

    json($result);
}

function handleAIBudgetAnalysis() {
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? 1;
    $locationId = $_POST['location_id'] ?? $_GET['location_id'] ?? null;

    global $db;
    $aiService = new AIAssistantService($db);
    $result = $aiService->processQuery('How much is my cart and where can I save money?', (int) $userId, $locationId);

    json($result);
}

function handleAIAllergenCheck() {
    $productName = $_POST['product'] ?? $_GET['product'] ?? '';
    $allergens = $_POST['allergens'] ?? $_GET['allergens'] ?? '';

    if (empty($productName)) {
        return jsonError('Product name required');
    }

    global $db;
    $aiService = new AIAssistantService($db);
    $result = $aiService->processQuery("Is $productName free from $allergens?", 1);

    json($result);
}

function handleAIRecipeHelp() {
    $recipe = $_POST['recipe'] ?? $_GET['recipe'] ?? '';

    if (empty($recipe)) {
        return jsonError('Recipe name required');
    }

    global $db;
    $aiService = new AIAssistantService($db);
    $result = $aiService->processQuery("Help me find ingredients for $recipe", 1);

    json($result);
}

function json($data) {
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function jsonError($message) {
    json(['error' => true, 'message' => $message]);
}
