<?php

final class UserRepository
{
    public function __construct(private PDO $db) {}

    public function search(string $query = '', int $limit = 20): array
    {
        $sql = 'SELECT id, email, display_name, created_at, updated_at FROM users';
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE email LIKE :q OR display_name LIKE :q';
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
