<?php

final class DashboardRepository
{
    public function __construct(private PDO $db) {}

    public function summary(): array
    {
        return [
            'users' => (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'stores' => (int) $this->db->query('SELECT COUNT(*) FROM locations')->fetchColumn(),
            'products' => (int) $this->db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'cart_items' => $this->tableCount('shopping_cart_items'),
            'price_changes_24h' => $this->tableCountSince('product_price_history', 'captured_at'),
        ];
    }

    public function recentProducts(int $limit = 12): array
    {
        $stmt = $this->db->prepare('
            SELECT id, product_id, upc, description, brand, temperature_indicator, snap_eligible, updated_at
            FROM products
            ORDER BY updated_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function recentStores(int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT id, location_id, name, city, state_code, zip_code, phone, updated_at
            FROM locations
            ORDER BY updated_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function recentUsers(int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT id, email, display_name, updated_at
            FROM users
            ORDER BY updated_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function priceHistorySeries(int $days = 14): array
    {
        $stmt = $this->db->prepare('
            SELECT DATE(captured_at) AS day, COUNT(*) AS total
            FROM product_price_history
            WHERE captured_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY DATE(captured_at)
            ORDER BY day ASC
        ');
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function tableCount(string $table): int
    {
        try {
            return (int) $this->db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function tableCountSince(string $table, string $column): int
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM {$table} WHERE {$column} >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
