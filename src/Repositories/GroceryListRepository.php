<?php
class GroceryListRepository {
    public function __construct(private PDO $db) {}

    public function getListForUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT
                gli.*,
                p.kroger_product_id,
                p.upc,
                p.description,
                p.brand,
                p.size,
                p.image_url,
                p.aisle_locations,
                p.categories,
                p.country_origin,
                p.temperature,
                p.regular_price,
                p.sale_price,
                p.promo_description,
                p.last_seen_at
            FROM grocery_list_items gli
            LEFT JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :uid
            ORDER BY gli.is_checked ASC, gli.created_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function addItem(int $userId, ?int $productId, ?string $customName, int $quantity = 1): int {
        if ($productId !== null) {
            $existing = $this->db->prepare("
                SELECT id, quantity
                FROM grocery_list_items
                WHERE user_id = :uid AND product_id = :pid AND is_checked = 0
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $existing->execute([
                ':uid' => $userId,
                ':pid' => $productId,
            ]);

            $row = $existing->fetch();
            if ($row) {
                $newQuantity = (int) $row['quantity'] + max(1, $quantity);
                $this->updateQuantity((int) $row['id'], $newQuantity);
                return (int) $row['id'];
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO grocery_list_items (user_id, product_id, custom_name, quantity, is_checked)
            VALUES (:uid, :pid, :name, :quantity, 0)
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':pid' => $productId,
            ':name' => $customName,
            ':quantity' => max(1, $quantity),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateChecked(int $id, int $checked): void {
        $stmt = $this->db->prepare("UPDATE grocery_list_items SET is_checked = :checked WHERE id = :id");
        $stmt->execute([
            ':checked' => $checked ? 1 : 0,
            ':id' => $id,
        ]);
    }

    public function updateQuantity(int $id, int $quantity): void {
        $stmt = $this->db->prepare("UPDATE grocery_list_items SET quantity = :quantity WHERE id = :id");
        $stmt->execute([
            ':quantity' => max(1, $quantity),
            ':id' => $id,
        ]);
    }

    public function removeItem(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM grocery_list_items WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function getItemById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT
                gli.*,
                p.kroger_product_id,
                p.upc,
                p.description,
                p.brand,
                p.size,
                p.image_url,
                p.aisle_locations,
                p.categories,
                p.country_origin,
                p.temperature,
                p.regular_price,
                p.sale_price,
                p.promo_description,
                p.raw_json,
                p.last_seen_at
            FROM grocery_list_items gli
            LEFT JOIN products p ON p.id = gli.product_id
            WHERE gli.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();

        return $item ?: null;
    }

    public function getUsualItemsForUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT
                ui.*,
                p.description,
                p.brand,
                p.size,
                p.image_url,
                p.sale_price,
                p.regular_price
            FROM usual_items ui
            LEFT JOIN products p ON p.id = ui.product_id
            WHERE ui.user_id = :uid
            ORDER BY ui.sort_order ASC, ui.created_at ASC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function addUsualItem(int $userId, ?int $productId, ?string $customName, int $quantity = 1): int {
        $sortOrderStmt = $this->db->prepare("
            SELECT COALESCE(MAX(sort_order) + 1, 0) AS next_sort_order
            FROM usual_items
            WHERE user_id = :uid
        ");
        $sortOrderStmt->execute([':uid' => $userId]);
        $nextSortOrder = (int) (($sortOrderStmt->fetch()['next_sort_order'] ?? 0));

        $stmt = $this->db->prepare("
            INSERT INTO usual_items (user_id, product_id, custom_name, quantity, sort_order)
            VALUES (:uid, :pid, :name, :quantity, :sort_order)
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':pid' => $productId,
            ':name' => $customName,
            ':quantity' => max(1, $quantity),
            ':sort_order' => $nextSortOrder,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function removeUsualItem(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM usual_items WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function addAllUsualItemsToCart(int $userId): int {
        $items = $this->getUsualItemsForUser($userId);
        $added = 0;
        foreach ($items as $item) {
            $this->addItem(
                $userId,
                isset($item['product_id']) ? (int) $item['product_id'] : null,
                $item['custom_name'] ?? null,
                isset($item['quantity']) ? (int) $item['quantity'] : 1
            );
            $added++;
        }

        return $added;
    }

    public function getTrackedProductsForUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.id, p.kroger_product_id
            FROM products p
            INNER JOIN (
                SELECT product_id
                FROM grocery_list_items
                WHERE user_id = :uid1 AND product_id IS NOT NULL
                UNION
                SELECT product_id
                FROM usual_items
                WHERE user_id = :uid2 AND product_id IS NOT NULL
            ) tracked ON tracked.product_id = p.id
            ORDER BY p.id ASC
        ");
        $stmt->execute([
            ':uid1' => $userId,
            ':uid2' => $userId,
        ]);
        return $stmt->fetchAll();
    }
}
