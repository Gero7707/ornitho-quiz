<?php

class BirdnetController
{
    /**
     * Page principale d'identification BirdNET
     */
    public function index()
    {
        require_once __DIR__ . '/../views/birdnet/birdnet.php';
    }

    /**
     * API endpoint : cherche une espèce dans la BDD par nom latin
     * Appelé en AJAX depuis le JS après analyse BirdNET
     */
    public function matchSpecies()
{
    header('Content-Type: application/json');

        $nomLatin = $_GET['nom_latin'] ?? '';

        if (empty($nomLatin)) {
            echo json_encode(['found' => false]);
            return;
        }

        $db = Database::getInstance()->getConnection();

        // 1. Chercher par nom latin exact
        $stmt = $db->prepare("SELECT id, nom_commun, nom_latin FROM oiseaux WHERE nom_latin = ?");
        $stmt->execute([$nomLatin]);
        $oiseau = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Si pas trouvé, chercher par nom de genre + espèce (deuxième mot)
        if (!$oiseau) {
            $parts = explode(' ', $nomLatin);
            if (count($parts) >= 2) {
                $espece = $parts[1];
                $stmt = $db->prepare("SELECT id, nom_commun, nom_latin FROM oiseaux WHERE nom_latin LIKE ?");
                $stmt->execute(['%' . $espece]);
                $oiseau = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        if ($oiseau) {
            echo json_encode([
                'found' => true,
                'id' => $oiseau['id'],
                'nom_commun' => $oiseau['nom_commun'],
                'nom_latin' => $oiseau['nom_latin'],
                'url' => '/oiseau/' . $oiseau['id']
            ]);
        } else {
            echo json_encode([
                'found' => false,
                'nom_latin' => $nomLatin
            ]);
        }
    }
}