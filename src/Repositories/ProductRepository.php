<?php

final class ProductRepository
{
    public function __construct(private PDO $db) {}

    public function search(string $query = '', int $limit = 24): array
    {
        $sql = '
            SELECT
                p.id,
                p.product_id,
                p.upc,
                p.product_page_uri,
                p.brand,
                p.description,
                p.country_origin,
                p.temperature_indicator,
                p.ratings_average_overall,
                p.ratings_total_review_count,
                p.snap_eligible,
                p.categories_json,
                p.raw_json,
                p.updated_at,
                (
                    SELECT pi.price_regular
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS regular_price,
                (
                    SELECT pi.price_promo
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS sale_price,
                (
                    SELECT pi.national_price_regular
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS national_price,
                (
                    SELECT pi.size
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS size,
                (
                    SELECT pi.inventory_stock_level
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS inventory_level,
                (
                    SELECT pi.fulfillment_instore
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS fulfillment_instore,
                (
                    SELECT pi.fulfillment_shiptohome
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS fulfillment_shiptohome,
                (
                    SELECT pi.fulfillment_delivery
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS fulfillment_delivery,
                (
                    SELECT pi.fulfillment_curbside
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS fulfillment_curbside,
                (
                    SELECT pis.url
                    FROM product_images pim
                    INNER JOIN product_image_sizes pis ON pis.product_image_ref_id = pim.id
                    WHERE pim.product_ref_id = p.id
                    ORDER BY pim.is_default DESC, pis.created_at DESC
                    LIMIT 1
                ) AS image_url,
                (
                    SELECT pal.raw_json
                    FROM product_aisle_locations pal
                    WHERE pal.product_ref_id = p.id
                    ORDER BY pal.created_at DESC
                    LIMIT 1
                ) AS aisle_location_json
            FROM products p
        ';

        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE p.description LIKE :q OR p.brand LIKE :q OR p.upc LIKE :q OR p.product_id LIKE :q';
            $params[':q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY p.updated_at DESC LIMIT :limit';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['categories'] = $this->firstCategory($row['categories_json'] ?? null);
            $row['aisle_locations'] = $this->firstAisleLocation($row['aisle_location_json'] ?? null);
        }

        return $rows;
    }

    public function detail(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.*,
                (
                    SELECT pi.price_regular
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS regular_price,
                (
                    SELECT pi.price_promo
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS sale_price,
                (
                    SELECT pi.national_price_regular
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS national_price,
                (
                    SELECT pi.size
                    FROM product_items pi
                    WHERE pi.product_ref_id = p.id
                    ORDER BY pi.updated_at DESC
                    LIMIT 1
                ) AS size,
                (
                    SELECT pis.url
                    FROM product_images pim
                    INNER JOIN product_image_sizes pis ON pis.product_image_ref_id = pim.id
                    WHERE pim.product_ref_id = p.id
                    ORDER BY pim.is_default DESC, pis.created_at DESC
                    LIMIT 1
                ) AS image_url
            FROM products p
            WHERE p.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['categories'] = $this->decodeJson($row['categories_json'] ?? null);
        $row['raw'] = $this->decodeJson($row['raw_json'] ?? null);
        return $row;
    }

    private function firstCategory(?string $json): ?string
    {
        $items = $this->decodeJson($json);
        if (!is_array($items) || !isset($items[0])) {
            return null;
        }

        $first = $items[0];
        if (is_array($first)) {
            return $first['description'] ?? ($first['name'] ?? json_encode($first));
        }

        return (string) $first;
    }

    private function firstAisleLocation(?string $json): ?string
    {
        $items = $this->decodeJson($json);
        if (!is_array($items) || !isset($items[0])) {
            return null;
        }

        $first = $items[0];
        if (!is_array($first)) {
            return (string) $first;
        }

        $parts = array_filter([
            $first['description'] ?? null,
            $first['aisle_number'] ?? null,
            $first['bay_number'] ?? null,
        ]);

        return $parts ? implode(' • ', $parts) : null;
    }

    private function decodeJson(?string $json): mixed
    {
        if (!$json) {
            return null;
        }

        return json_decode($json, true);
    }
}
