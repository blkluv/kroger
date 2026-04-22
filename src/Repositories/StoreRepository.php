<?php

final class StoreRepository
{
    public function __construct(private PDO $db) {}

    public function search(string $query = '', int $limit = 20): array
    {
        $sql = '
            SELECT id, location_id, chain, name, phone, store_number, division_number,
                   address_line_1, address_line_2, city, county, state_code, zip_code,
                   latitude, longitude, timezone, hours_json, raw_json, updated_at
            FROM locations
        ';

        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE name LIKE :q OR city LIKE :q OR state_code LIKE :q OR zip_code LIKE :q OR store_number LIKE :q';
            $params[':q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT :limit';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
