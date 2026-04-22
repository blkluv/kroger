<?php

class ProductRepository
{
    public function __construct(private PDO $db) {}

    public function upsertFromKrogerProduct(array $p, string $locationId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO products (
                kroger_product_id, upc, description, brand, size,
                image_url, aisle_locations, categories, country_origin,
                temperature, regular_price, sale_price, national_price,
                promo_description, inventory_level,
                fulfillment_instore, fulfillment_shiptohome,
                fulfillment_delivery, fulfillment_curbside,
                raw_json, last_seen_at
            ) VALUES (
                :kroger_product_id, :upc, :description, :brand, :size,
                :image_url, :aisle_locations, :categories, :country_origin,
                :temperature, :regular_price, :sale_price, :national_price,
                :promo_description, :inventory_level,
                :fulfillment_instore, :fulfillment_shiptohome,
                :fulfillment_delivery, :fulfillment_curbside,
                :raw_json, NOW()
            )
            ON DUPLICATE KEY UPDATE
                upc = VALUES(upc),
                description = VALUES(description),
                brand = VALUES(brand),
                size = VALUES(size),
                image_url = VALUES(image_url),
                aisle_locations = VALUES(aisle_locations),
                categories = VALUES(categories),
                country_origin = VALUES(country_origin),
                temperature = VALUES(temperature),
                regular_price = VALUES(regular_price),
                sale_price = VALUES(sale_price),
                national_price = VALUES(national_price),
                promo_description = VALUES(promo_description),
                inventory_level = VALUES(inventory_level),
                fulfillment_instore = VALUES(fulfillment_instore),
                fulfillment_shiptohome = VALUES(fulfillment_shiptohome),
                fulfillment_delivery = VALUES(fulfillment_delivery),
                fulfillment_curbside = VALUES(fulfillment_curbside),
                raw_json = VALUES(raw_json),
                last_seen_at = NOW(),
                updated_at = NOW()
        ");

        $item = $p['items'][0] ?? null;
        $price = $item['price'] ?? [];
        $national = $item['nationalPrice'] ?? [];
        $fulfill = $item['fulfillment'] ?? [];
        $inventory = $item['inventory']['stockLevel'] ?? null;

        $stmt->execute([
            ':kroger_product_id'   => $p['productId'] ?? null,
            ':upc'                 => $p['upc'] ?? null,
            ':description'         => $p['description'] ?? null,
            ':brand'               => $p['brand'] ?? null,
            ':size'                => $item['size'] ?? null,
            ':image_url'           => $p['images'][0]['sizes'][0]['url'] ?? null,
            ':aisle_locations'     => json_encode($p['aisleLocations'] ?? []),
            ':categories'          => json_encode($p['categories'] ?? []),
            ':country_origin'      => $p['countryOrigin'] ?? null,
            ':temperature'         => $p['temperature']['indicator'] ?? null,
            ':regular_price'       => $price['regular'] ?? null,
            ':sale_price'          => $price['promo'] ?? null,
            ':national_price'      => $national['regular'] ?? null,
            ':promo_description'   => null,
            ':inventory_level'     => $inventory,
            ':fulfillment_instore' => !empty($fulfill['instore']) ? 1 : 0,
            ':fulfillment_shiptohome' => !empty($fulfill['shiptohome']) ? 1 : 0,
            ':fulfillment_delivery'   => !empty($fulfill['delivery']) ? 1 : 0,
            ':fulfillment_curbside'   => !empty($fulfill['curbside']) ? 1 : 0,
            ':raw_json'            => json_encode($p),
        ]);
    }
}
