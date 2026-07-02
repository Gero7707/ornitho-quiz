<?php

require_once __DIR__ . '/../../core/Database.php';

class Son {
    private $db;
    public static $audioBaseUrl;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        self::$audioBaseUrl = $_ENV['AUDIO_BASE_URL'] ?? '/audio/';
    }

    public function getByOiseauId($oiseau_id){
        $stmt = $this->db->prepare("SELECT sons.id,
                                        sons.titre,
                                        sons.oiseau_id,
                                        sons.auteur,
                                        sons.type_son,
                                        sons.chemin_fichier,  
                                        oiseaux.nom_commun AS nom_oiseau,
                                        oiseaux.nom_latin AS nom_latin,
                                        oiseaux.description_courte AS description_oiseau,
                                        oiseaux.lien_wikipedia AS lien_wikipedia,
                                        oiseaux.lien_inpn AS lien_inpn
                                    FROM sons 
                                    JOIN oiseaux ON sons.oiseau_id = oiseaux.id
                                    WHERE sons.oiseau_id = ?");
        $stmt->execute([$oiseau_id]);
        return $stmt->fetchAll();
    }

    public function getById($id){
        $stmt = $this->db->prepare("SELECT sons.id,
                                        sons.titre,
                                        sons.oiseau_id,
                                        sons.auteur,
                                        sons.type_son,
                                        sons.chemin_fichier,  
                                        oiseaux.nom_commun AS nom_oiseau,
                                        oiseaux.nom_latin AS nom_latin,
                                        oiseaux.description_courte AS description_oiseau
                                    FROM sons 
                                    JOIN oiseaux ON sons.oiseau_id = oiseaux.id
                                    WHERE sons.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getRandomByOiseauId($oiseau_id, $limit){
        $stmt = $this->db->prepare("SELECT * FROM sons WHERE oiseau_id = ? ORDER BY RAND() LIMIT ?");
        $stmt->bindValue(1, $oiseau_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRandomIntrus(int $exclude_oiseau_id, string $region = ''): array|false {
        if ($region) {
            $stmt = $this->db->prepare("SELECT sons.*, oiseaux.nom_commun 
                FROM sons
                JOIN oiseaux ON sons.oiseau_id = oiseaux.id
                WHERE sons.oiseau_id != ? AND sons.oiseau_id IS NOT NULL AND oiseaux.region = ?
                ORDER BY RAND() LIMIT 1");
            $stmt->execute([$exclude_oiseau_id, $region]);
        } else {
            $stmt = $this->db->prepare("SELECT sons.*, oiseaux.nom_commun 
                FROM sons
                JOIN oiseaux ON sons.oiseau_id = oiseaux.id
                WHERE sons.oiseau_id != ? AND sons.oiseau_id IS NOT NULL
                ORDER BY RAND() LIMIT 1");
            $stmt->execute([$exclude_oiseau_id]);
        }
        return $stmt->fetch();
    }

    public function getByType($type){
        $stmt = $this->db->prepare("SELECT * FROM sons WHERE type_son = ? ");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    public function getRandomSon(string $region = ''): array|false {
        if ($region) {
            $stmt = $this->db->prepare("SELECT sons.* FROM sons 
                JOIN oiseaux ON sons.oiseau_id = oiseaux.id
                WHERE sons.oiseau_id IS NOT NULL AND oiseaux.region = ?
                ORDER BY RAND() LIMIT 1");
            $stmt->execute([$region]);
        } else {
            $stmt = $this->db->query("SELECT * FROM sons WHERE oiseau_id IS NOT NULL ORDER BY RAND() LIMIT 1");
        }
        return $stmt->fetch();
    }

    public function getRandomSonChantOuCri(string $region = ''): array|false {
        if ($region) {
            $stmt = $this->db->prepare("SELECT sons.* FROM sons 
                JOIN oiseaux ON sons.oiseau_id = oiseaux.id
                WHERE sons.type_son IN ('Chant', 'Cri') AND sons.oiseau_id IS NOT NULL AND oiseaux.region = ?
                ORDER BY RAND() LIMIT 1");
            $stmt->execute([$region]);
        } else {
            $stmt = $this->db->query("SELECT * FROM sons WHERE type_son IN ('Chant', 'Cri') AND oiseau_id IS NOT NULL ORDER BY RAND() LIMIT 1");
        }
        return $stmt->fetch();
    }

    public function getRandomSonsDistinctOiseaux(int $limit, string $region = ''): array {
        if ($region) {
            $stmt = $this->db->prepare("SELECT sons.id,
                    sons.titre, sons.oiseau_id, sons.auteur,
                    sons.type_son, sons.chemin_fichier,
                    oiseaux.nom_commun AS nom_oiseau
                FROM sons 
                JOIN oiseaux ON sons.oiseau_id = oiseaux.id
                WHERE oiseaux.region = ?
                GROUP BY sons.oiseau_id
                ORDER BY RAND() LIMIT ?");
            $stmt->bindValue(1, $region, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("SELECT sons.id,
                    sons.titre, sons.oiseau_id, sons.auteur,
                    sons.type_son, sons.chemin_fichier,
                    oiseaux.nom_commun AS nom_oiseau
                FROM sons 
                JOIN oiseaux ON sons.oiseau_id = oiseaux.id
                GROUP BY sons.oiseau_id
                ORDER BY RAND() LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function countAll(): int{
        $stmt = $this->db->query("SELECT COUNT(*) FROM sons WHERE oiseau_id IS NOT NULL");
        return (int) $stmt->fetchColumn();
    }
}