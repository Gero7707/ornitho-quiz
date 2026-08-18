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

    public function updateProfil(array $data){
        $stmt = $this->db->prepare("UPDATE utilisateur SET email = :email, pseudo = :pseudo WHERE id = :id ");
        $stmt->bindValue(':id', $data['id'] , PDO::PARAM_INT);
        $stmt->bindValue(':email', $data['email']  , PDO::PARAM_STR);
        $stmt->bindValue(':pseudo', $data['pseudo']  , PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function saveResetToken(string $email,string $token,string $expires){
        $stmt = $this->db->prepare("UPDATE utilisateur SET reset_token = :reset_token  , reset_token_expires_at = :reset_token_expires_at WHERE email = :email");
        $stmt->bindValue(':reset_token', $token , PDO::PARAM_STR);
        $stmt->bindValue(':reset_token_expires_at' , $expires , PDO::PARAM_STR);
        $stmt->bindValue(':email' , $email, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function findByResetToken(string $token){
        $stmt = $this->db->prepare("SELECT * FROM utilisateur WHERE reset_token = :reset_token");
        $stmt->bindValue(':reset_token', $token , PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePassword(int $id, string $hashedPassword){
        $stmt =$this->db->prepare("UPDATE  utilisateur  SET mot_de_passe_hash = :mot_de_passe_hash WHERE id = :id");
        $stmt->bindValue(':id' , $id , PDO::PARAM_INT);
        $stmt->bindValue(':mot_de_passe_hash' , $hashedPassword , PDO::PARAM_STR);
        return $stmt->execute();
    }
    
    public function clearResetToken(int $id){
        $stmt = $this->db->prepare("UPDATE utilisateur SET reset_token = null , reset_token_expires_at = null WHERE id = :id");
        $stmt-> bindValue(':id' , $id , PDO::PARAM_INT);
        return $stmt->execute();
    }

}