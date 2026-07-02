<?php
require_once __DIR__ . '/../models/Oiseau.php'; 
require_once __DIR__ . '/../models/Image.php';

class OiseauController {

    private $oiseaux;
    public function index() {
        $oiseauModel = new Oiseau();
        $region = isset($_GET['region']) ? $_GET['region'] : '';

        if (isset($_GET['recherche']) && $_GET['recherche'] !== '') {
            $this->oiseaux = $oiseauModel->search($_GET['recherche'], $region);
        } elseif (isset($_GET['lettre'])) {
            $this->oiseaux = $oiseauModel->getByLettre($_GET['lettre'], $region);
        } else {
            $this->oiseaux = $oiseauModel->getAll($region);
        }
        include __DIR__ . '/../views/oiseaux/oiseauxParEspece.php';
    }

    public function show($id) {
        $oiseauModel = new Oiseau();
        $oiseau = $oiseauModel->getById($id);
        if (!$oiseau) {
            http_response_code(404);
            echo "Oiseau non trouvé";
            return;
        }
        $sonModel = new Son();
        $sons = $sonModel->getByOiseauId($id);
        $nomWiki = str_replace(' ', '_', $oiseau['nom_commun']);
        $apiUrl = "https://fr.wikipedia.org/api/rest_v1/page/summary/" . urlencode($nomWiki);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'OrnithoQuizz/1.0');
        $json = curl_exec($ch);
        $data = json_decode($json, true);
        $photoUrl = $data['thumbnail']['source'] ?? null;
        $imageModel = new Image();
        $images = $imageModel->getByOiseauId($id);
        $description = $data['extract'] ?? null;

        include __DIR__ . '/../views/sons/sonsListe.php';
    }

    public function getByLettre($lettre) {
        $oiseauModel = new Oiseau();
        $this->oiseaux = $oiseauModel->getByLettre($lettre);
        include __DIR__ . '/../views/oiseaux/oiseauxParEspece.php';
    }

}