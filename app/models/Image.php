<?php
require_once __DIR__ . '/../../core/Database.php';
class Image {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    public function getByOiseauId($id) {
        $stmt = $this->db->prepare("SELECT url FROM oiseaux_images WHERE oiseau_id = ? ORDER BY rang");
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}