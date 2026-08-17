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

    public function findByEmail(string $email){
        $stmt = $this->db->prepare("SELECT * FROM utilisateur WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function findByPseudo(string $pseudo){
        $stmt = $this->db->prepare("SELECT * FROM utilisateur WHERE pseudo = :pseudo");
        $stmt->execute(['pseudo' => $pseudo]);
        return $stmt->fetch();
    }

    public function findById(int $id){
        $stmt = $this->db->prepare("SELECT * FROM utilisateur WHERE id = :id");
        $stmt->bindValue(':id' , $id , PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser(array $data){
        $stmt = $this->db->prepare("INSERT INTO utilisateur (pseudo, email, mot_de_passe_hash) VALUES (:pseudo, :email, :mot_de_passe_hash)");
        $stmt->bindValue(':pseudo', $data['pseudo'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':mot_de_passe_hash', $data['password'], PDO::PARAM_STR);
        $stmt->execute();
        return $this->db->lastInsertId();
    }
}