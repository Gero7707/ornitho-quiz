<?php
require_once __DIR__ . '/../../core/Database.php';

class UserModel{
    private PDO $db;

    public function __construct(){
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByInput(string $identifiant) {
        $stmt = $this->db->prepare("SELECT * FROM utilisateur WHERE email = :identifiant OR pseudo = :identifiant");
        $stmt->bindValue(':identifiant', $identifiant, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}