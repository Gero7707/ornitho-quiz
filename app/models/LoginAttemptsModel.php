<?php

require_once __DIR__ . '/../../core/Database.php';

class LoginAttemptModel{
    private PDO  $db;

    public function __construct(){
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAttempts(string $ip){
        $stmt = $this->db->prepare("SELECT * FROM login_attempts 
                                    WHERE ip = :ip 
                                    AND attempted_at > (NOW() - INTERVAL 15 MINUTE)");
        $stmt->bindValue(':ip', $ip , PDO::PARAM_STR );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addAttempt(string $ip){
        $stmt = $this->db->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)");
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function resetAttempts(string $ip){
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE ip = :ip");
        $stmt->bindValue(':ip' , $ip , PDO::PARAM_STR);
        return $stmt->execute();
    }
}