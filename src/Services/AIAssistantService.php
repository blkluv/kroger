<?php

class AIAssistantService {
    public function __construct(
        private PDO $db,
        private ?string $openaiApiKey = null
    ) {}

    public function processQuery(string $query, int $userId, string $locationId = null): array {
        $query = trim($query);
        $response = [];

        // Determine query intent
        $intent = $this->detectIntent($query);

        // Route to appropriate handler
        switch ($intent) {
            case 'product_search':
                $response = $this->handleProductSearch($query, $locationId);
                break;
            case 'nutrition_info':
                $response = $this->handleNutritionQuery($query);
                break;
            case 'allergen_check':
                $response = $this->handleAllergenQuery($query);
                break;
            case 'price_check':
                $response = $this->handlePriceQuery($query, $locationId);
                break;
            case 'recommendation':
                $response = $this->handleRecommendation($query, $userId, $locationId);
                break;
            case 'budget_help':
                $response = $this->handleBudgetAssistance($query, $userId, $locationId);
                break;
            case 'availability':
                $response = $this->handleAvailabilityQuery($query, $locationId);
                break;
            case 'recipe_help':
                $response = $this->handleRecipeHelp($query, $locationId);
                break;
            default:
                $response = $this->handleGeneralQuery($query);
        }

        // Log interaction for learning
        $this->logInteraction($userId, $query, $intent, $response);

        return array_merge($response, ['intent' => $intent]);
    }

    private function detectIntent(string $query): string {
        $query = strtolower($query);

        // Pattern matching for intents
        if (preg_match('/(find|search|show|list).*(?:product|item)/i', $query)) {
            return 'product_search';
        }
        if (preg_match('/(nutrient|calorie|carb|protein|fat|vitamin|mineral)/i', $query)) {
            return 'nutrition_info';
        }
        if (preg_match('/(allerg|free from|contains|avoid)/i', $query)) {
            return 'allergen_check';
        }
        if (preg_match('/(price|cost|cheap|expensive|discount|sale)/i', $query)) {
            return 'price_check';
        }
        if (preg_match('/(suggest|recommend|what should|what would)/i', $query)) {
            return 'recommendation';
        }
        if (preg_match('/(budget|afford|save|cheap)/i', $query)) {
            return 'budget_help';
        }
        if (preg_match('/(in stock|available|where|location)/i', $query)) {
            return 'availability';
        }
        if (preg_match('/(recipe|meal|cook|prepare|ingredient)/i', $query)) {
            return 'recipe_help';
        }

        return 'general';
    }

    private function handleProductSearch(string $query, ?string $locationId): array {
        // Extract product keywords
        $keywords = $this->extractKeywords($query);
        
        if (empty($keywords)) {
            return [
                'type' => 'error',
                'message' => 'Could not identify what product you\'re looking for. Try being more specific.',
                'products' => [],
            ];
        }

        $searchTerm = '%' . implode('%', $keywords) . '%';
        $stmt = $this->db->prepare("
            SELECT 
                id, description, brand, size, image_url,
                regular_price, sale_price,
                inventory_level, categories,
                ROUND(((regular_price - COALESCE(sale_price, regular_price)) / regular_price) * 100, 1) as discount_percent
            FROM products
            WHERE (description LIKE :term OR brand LIKE :term OR categories LIKE :term)
            ORDER BY sale_price IS NOT NULL DESC, COALESCE(sale_price, regular_price) ASC
            LIMIT 8
        ");
        $stmt->execute([':term' => $searchTerm]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($products)) {
            return [
                'type' => 'no_results',
                'message' => 'No products found matching "' . implode(' ', $keywords) . '". Try different keywords.',
                'products' => [],
            ];
        }

        $response = [
            'type' => 'success',
            'message' => 'Found ' . count($products) . ' product(s) matching your search.',
            'products' => array_map(fn($p) => [
                'id' => $p['id'],
                'description' => $p['description'],
                'brand' => $p['brand'],
                'size' => $p['size'],
                'image_url' => $p['image_url'],
                'price' => $p['sale_price'] ?? $p['regular_price'],
                'regular_price' => $p['regular_price'],
                'sale_price' => $p['sale_price'],
                'discount' => $p['discount_percent'],
                'in_stock' => $p['inventory_level'] !== 'TEMPORARILY_OUT_OF_STOCK',
                'stock_level' => $p['inventory_level'],
            ], $products),
        ];

        return $response;
    }

    private function handleNutritionQuery(string $query): array {
        // Extract product from query
        $keywords = $this->extractKeywords($query);
        $searchTerm = '%' . implode('%', $keywords) . '%';

        $stmt = $this->db->prepare("
            SELECT id, description, raw_json FROM products
            WHERE description LIKE :term OR brand LIKE :term
            LIMIT 1
        ");
        $stmt->execute([':term' => $searchTerm]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'type' => 'error',
                'message' => 'Could not find product information.',
            ];
        }

        $data = json_decode($product['raw_json'], true) ?: [];
        $nutrition = $data['nutritionInformation'] ?? [];

        if (empty($nutrition)) {
            return [
                'type' => 'no_data',
                'message' => "Nutrition information not available for {$product['description']}.",
            ];
        }

        $nutrients = [];
        foreach (($nutrition['nutrients'] ?? []) as $nutrient) {
            $nutrients[] = [
                'name' => $nutrient['displayName'] ?? $nutrient['description'],
                'quantity' => $nutrient['quantity'],
                'unit' => $nutrient['unitOfMeasure']['abbreviation'] ?? '',
                'daily_value' => $nutrient['percentDailyIntake'] ?? 0,
            ];
        }

        return [
            'type' => 'success',
            'product' => $product['description'],
            'serving_size' => $nutrition['servingSize']['description'] ?? 'Unknown',
            'servings_per_package' => $nutrition['servingsPerPackage']['value'] ?? null,
            'nutrients' => array_slice($nutrients, 0, 8),
            'ingredients' => $nutrition['ingredientStatement'] ?? 'Not available',
        ];
    }

    private function handleAllergenQuery(string $query): array {
        $keywords = $this->extractKeywords($query);
        $searchTerm = '%' . implode('%', $keywords) . '%';

        $stmt = $this->db->prepare("
            SELECT id, description, raw_json FROM products
            WHERE description LIKE :term OR brand LIKE :term
            LIMIT 1
        ");
        $stmt->execute([':term' => $searchTerm]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'type' => 'error',
                'message' => 'Product not found.',
            ];
        }

        $data = json_decode($product['raw_json'], true) ?: [];
        $allergens = $data['allergens'] ?? [];
        $allergensDesc = $data['allergensDescription'] ?? 'Not available';

        $safeFrom = [];
        $contains = [];

        foreach ($allergens as $allergen) {
            $info = [
                'name' => $allergen['name'],
                'containment' => $allergen['levelOfContainmentName'],
            ];
            
            if ($allergen['levelOfContainmentName'] === 'Free from') {
                $safeFrom[] = $info;
            } else {
                $contains[] = $info;
            }
        }

        return [
            'type' => 'success',
            'product' => $product['description'],
            'safe_from' => $safeFrom,
            'contains' => $contains,
            'allergen_summary' => $allergensDesc,
        ];
    }

    private function handlePriceQuery(string $query, ?string $locationId): array {
        $keywords = $this->extractKeywords($query);
        $searchTerm = '%' . implode('%', $keywords) . '%';

        $stmt = $this->db->prepare("
            SELECT 
                id, description, brand,
                regular_price, sale_price,
                ROUND(((regular_price - COALESCE(sale_price, regular_price)) / regular_price) * 100, 1) as discount_percent
            FROM products
            WHERE description LIKE :term OR brand LIKE :term
            ORDER BY sale_price IS NOT NULL DESC
            LIMIT 5
        ");
        $stmt->execute([':term' => $searchTerm]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($products)) {
            return [
                'type' => 'error',
                'message' => 'No products found.',
            ];
        }

        $cheapest = min($products, fn($a, $b) => ($a['sale_price'] ?? $a['regular_price']) <=> ($b['sale_price'] ?? $b['regular_price']));
        $onSale = count(array_filter($products, fn($p) => $p['sale_price'] !== null));

        return [
            'type' => 'success',
            'products' => array_map(fn($p) => [
                'description' => $p['description'],
                'brand' => $p['brand'],
                'regular_price' => $p['regular_price'],
                'sale_price' => $p['sale_price'],
                'current_price' => $p['sale_price'] ?? $p['regular_price'],
                'discount' => $p['discount_percent'],
                'status' => $p['sale_price'] ? 'On Sale!' : 'Regular Price',
            ], $products),
            'summary' => [
                'cheapest' => $cheapest['description'] . ' at $' . ($cheapest['sale_price'] ?? $cheapest['regular_price']),
                'savings_available' => $onSale > 0,
                'items_on_sale' => $onSale,
            ],
        ];
    }

    private function handleRecommendation(string $query, int $userId, ?string $locationId): array {
        // Get user's cart context
        $stmt = $this->db->prepare("
            SELECT p.categories FROM grocery_list_items gli
            JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :user_id AND gli.is_checked = 0
            GROUP BY p.categories
        ");
        $stmt->execute([':user_id' => $userId]);
        $categories = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'categories') ?: [];

        $recommendations = [];
        if (!empty($categories)) {
            $catPattern = '%' . trim(explode(',', $categories[0])[0]) . '%';
            $stmt = $this->db->prepare("
                SELECT id, description, brand, image_url, sale_price, regular_price
                FROM products
                WHERE categories LIKE :cat AND sale_price IS NOT NULL
                ORDER BY (regular_price - sale_price) DESC
                LIMIT 5
            ");
            $stmt->execute([':cat' => $catPattern]);
            $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [
            'type' => 'success',
            'message' => 'Based on your cart, here are some great deals:',
            'recommendations' => array_map(fn($p) => [
                'description' => $p['description'],
                'brand' => $p['brand'],
                'image_url' => $p['image_url'],
                'price' => $p['sale_price'],
                'regular_price' => $p['regular_price'],
                'savings' => $p['regular_price'] - $p['sale_price'],
            ], $recommendations),
        ];
    }

    private function handleBudgetAssistance(string $query, int $userId, ?string $locationId): array {
        $stmt = $this->db->prepare("
            SELECT 
                SUM(gli.quantity * COALESCE(p.sale_price, p.regular_price, 0)) as subtotal,
                COUNT(*) as item_count,
                SUM(CASE WHEN p.sale_price IS NOT NULL THEN 1 ELSE 0 END) as items_on_sale,
                SUM(CASE WHEN p.sale_price IS NOT NULL THEN (p.regular_price - p.sale_price) * gli.quantity ELSE 0 END) as potential_savings
            FROM grocery_list_items gli
            LEFT JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :user_id AND gli.is_checked = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $subtotal = (float) ($cart['subtotal'] ?? 0);
        $savings = (float) ($cart['potential_savings'] ?? 0);

        return [
            'type' => 'success',
            'current_cart_total' => round($subtotal, 2),
            'items_in_cart' => (int) ($cart['item_count'] ?? 0),
            'items_on_sale' => (int) ($cart['items_on_sale'] ?? 0),
            'potential_savings' => round($savings, 2),
            'savings_percentage' => $subtotal > 0 ? round(($savings / $subtotal) * 100, 1) : 0,
            'advice' => $savings > 0 ? 
                "You can save \${$savings} by buying items currently on sale!" :
                "Check back soon for deals on items in your cart.",
        ];
    }

    private function handleAvailabilityQuery(string $query, ?string $locationId): array {
        $keywords = $this->extractKeywords($query);
        $searchTerm = '%' . implode('%', $keywords) . '%';

        $stmt = $this->db->prepare("
            SELECT 
                id, description, inventory_level,
                fulfillment_instore, fulfillment_delivery, fulfillment_curbside
            FROM products
            WHERE description LIKE :term OR brand LIKE :term
            LIMIT 1
        ");
        $stmt->execute([':term' => $searchTerm]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'type' => 'error',
                'message' => 'Product not found.',
            ];
        }

        $availability = [];
        if ($product['fulfillment_instore']) $availability[] = 'In-Store';
        if ($product['fulfillment_delivery']) $availability[] = 'Delivery';
        if ($product['fulfillment_curbside']) $availability[] = 'Curbside Pickup';

        $inStock = $product['inventory_level'] !== 'TEMPORARILY_OUT_OF_STOCK';

        return [
            'type' => 'success',
            'product' => $product['description'],
            'stock_status' => $inStock ? $product['inventory_level'] : 'Currently Out of Stock',
            'in_stock' => $inStock,
            'fulfillment_options' => $availability,
            'message' => $inStock ? 
                "Great! This item is available via: " . implode(', ', $availability) :
                "Sorry, this item is temporarily out of stock. Check back soon!",
        ];
    }

    private function handleRecipeHelp(string $query, ?string $locationId): array {
        // Extract ingredients from query
        preg_match_all('/for\s+(.+?)(?:\?|$)/i', $query, $matches);
        $recipe = $matches[1][0] ?? null;

        if (!$recipe) {
            return [
                'type' => 'error',
                'message' => 'Could not identify recipe. Try "Help me find ingredients for [recipe name]"',
            ];
        }

        // Basic ingredient suggestions for common recipes
        $commonRecipes = [
            'pasta' => ['pasta', 'sauce', 'olive oil', 'garlic', 'parmesan', 'basil'],
            'salad' => ['lettuce', 'tomato', 'cucumber', 'dressing', 'cheese'],
            'tacos' => ['tortillas', 'meat', 'cheese', 'lettuce', 'salsa', 'sour cream'],
            'curry' => ['rice', 'curry paste', 'coconut milk', 'vegetables', 'onion', 'garlic'],
            'pizza' => ['flour', 'yeast', 'cheese', 'sauce', 'olive oil', 'toppings'],
        ];

        $ingredients = [];
        foreach ($commonRecipes as $name => $items) {
            if (stripos($recipe, $name) !== false) {
                $ingredients = $items;
                break;
            }
        }

        if (empty($ingredients)) {
            return [
                'type' => 'info',
                'message' => "Recipe '$recipe' not in our suggestions. Try searching for specific ingredients instead.",
            ];
        }

        $products = [];
        foreach ($ingredients as $ingredient) {
            $stmt = $this->db->prepare("
                SELECT id, description, brand, image_url, COALESCE(sale_price, regular_price) as price
                FROM products
                WHERE description LIKE :term OR categories LIKE :term
                ORDER BY sale_price IS NOT NULL DESC, price ASC
                LIMIT 1
            ");
            $stmt->execute([':term' => '%' . $ingredient . '%']);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) {
                $products[] = $product;
            }
        }

        return [
            'type' => 'success',
            'recipe' => $recipe,
            'suggested_ingredients' => $products,
            'message' => 'Here are ingredients for ' . $recipe . '. Click to add to cart!',
        ];
    }

    private function handleGeneralQuery(string $query): array {
        return [
            'type' => 'info',
            'message' => 'I can help you with:
- Product searches: "Find organic milk"
- Nutrition info: "Show nutrition for eggs"
- Allergen checking: "Is this gluten free?"
- Price comparisons: "Find cheap alternatives to [product]"
- Recommendations: "What should I buy?"
- Budget help: "How much is my cart?"
- Availability: "Where is [product]?"
- Recipe help: "Find ingredients for pasta"

What would you like to know?',
        ];
    }

    private function extractKeywords(string $query): array {
        // Remove common words
        $stopwords = ['find', 'search', 'show', 'what', 'where', 'is', 'are', 'the', 'a', 'an', 'for', 'in', 'on', 'at', 'to', 'from', 'of', 'and', 'or', 'not', 'but', 'can', 'have'];
        
        $words = preg_split('/\s+/', strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $stopwords));
        
        return array_values($keywords);
    }

    private function logInteraction(int $userId, string $query, string $intent, array $response): void {
        $stmt = $this->db->prepare("
            CREATE TABLE IF NOT EXISTS ai_interactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                query TEXT NOT NULL,
                intent VARCHAR(64),
                response_type VARCHAR(64),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->db->exec($stmt->queryString ?? '');

        $stmt = $this->db->prepare("
            INSERT INTO ai_interactions (user_id, query, intent, response_type)
            VALUES (:user_id, :query, :intent, :response_type)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':query' => $query,
            ':intent' => $intent,
            ':response_type' => $response['type'] ?? 'unknown',
        ]);
    }
}
