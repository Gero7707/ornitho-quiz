<?php

require_once __DIR__ . '/../../core/Database.php';

class Oiseau {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(string $region = ''): array {
        if ($region) {
            $stmt = $this->db->prepare(
                "SELECT * FROM oiseaux WHERE region = ? ORDER BY nom_commun"
            );
            $stmt->execute([$region]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM oiseaux ORDER BY nom_commun"
            );
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function getRandomOiseau(string $region = ''): array|false {
        if ($region) {
            $stmt = $this->db->prepare("SELECT oiseaux.id, 
                    oiseaux.nom_commun, oiseaux.nom_latin, 
                    oiseaux.description_courte, oiseaux.lien_wikipedia, oiseaux.lien_inpn
                FROM oiseaux 
                JOIN sons ON oiseaux.id = sons.oiseau_id
                WHERE oiseaux.region = ?
                GROUP BY oiseaux.id, oiseaux.nom_commun, oiseaux.nom_latin, 
                    oiseaux.description_courte, oiseaux.lien_wikipedia, oiseaux.lien_inpn
                HAVING COUNT(sons.id) >= 3
                ORDER BY RAND() LIMIT 1");
            $stmt->execute([$region]);
        } else {
            $stmt = $this->db->query("SELECT oiseaux.id, 
                    oiseaux.nom_commun, oiseaux.nom_latin, 
                    oiseaux.description_courte, oiseaux.lien_wikipedia, oiseaux.lien_inpn
                FROM oiseaux 
                JOIN sons ON oiseaux.id = sons.oiseau_id
                GROUP BY oiseaux.id, oiseaux.nom_commun, oiseaux.nom_latin, 
                    oiseaux.description_courte, oiseaux.lien_wikipedia, oiseaux.lien_inpn
                HAVING COUNT(sons.id) >= 3
                ORDER BY RAND() LIMIT 1");
        }
        return $stmt->fetch();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM oiseaux WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function countAll(): int{
        $stmt = $this->db->query("SELECT COUNT(*) FROM oiseaux");
        return (int) $stmt->fetchColumn();
    }

    public function getByLettre(string $lettre, string $region = ''): array {
        if ($region) {
            $stmt = $this->db->prepare(
                "SELECT * FROM oiseaux WHERE nom_commun LIKE ? AND region = ? ORDER BY nom_commun"
            );
            $stmt->execute([$lettre . '%', $region]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM oiseaux WHERE nom_commun LIKE ? ORDER BY nom_commun"
            );
            $stmt->execute([$lettre . '%']);
        }
        return $stmt->fetchAll();
    }

    public function search(string $terme, string $region = ''): array {
        if ($region) {
            $stmt = $this->db->prepare(
                "SELECT * FROM oiseaux WHERE (nom_commun LIKE ? OR nom_latin LIKE ?) AND region = ? ORDER BY nom_commun"
            );
            $stmt->execute(['%' . $terme . '%', '%' . $terme . '%', $region]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM oiseaux WHERE nom_commun LIKE ? OR nom_latin LIKE ? ORDER BY nom_commun"
            );
            $stmt->execute(['%' . $terme . '%', '%' . $terme . '%']);
        }
        return $stmt->fetchAll();
    }
}