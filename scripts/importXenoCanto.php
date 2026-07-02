<?php

/**
 * Script d'import Xeno-canto
 * 
 * Récupère les sons manquants depuis l'API Xeno-canto
 * et les insère en BDD (URL de streaming, pas de téléchargement).
 * 
 * Usage : php scripts/import_xenocanto.php
 * 
 */

// Chargement manuel du .env pour l'exécution en CLI
$env = parse_ini_file(__DIR__ . '/../.env');
foreach ($env as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
}

require_once __DIR__ . '/../core/Database.php';

// ------------------------------------------------
// CONFIG
// ------------------------------------------------
const XENO_API     = 'https://xeno-canto.org/api/3/recordings';
const MAX_PAR_ESPECE = 5;    // nombre max de sons à ajouter par espèce
const QUALITES      = ['A', 'B'];  // on garde seulement les meilleurs enregistrements
const PAUSE_MS      = 500;   // pause entre chaque requête (ms) pour ne pas surcharger l'API

// ------------------------------------------------
// CONNEXION BDD
// ------------------------------------------------
$db = Database::getInstance()->getConnection();

// ------------------------------------------------
// RÉCUPÉRATION DES ESPÈCES EN BDD
// ------------------------------------------------
$especes = $db->query("SELECT id, nom_commun, nom_latin FROM oiseaux ORDER BY nom_commun")->fetchAll(PDO::FETCH_ASSOC);

$especes = array_slice($especes, 10, 3); // test sur 3 espèces seulement

echo "=== Import Xeno-canto ===\n";
echo count($especes) . " espèces trouvées en BDD\n\n";

$totalAjoutes  = 0;
$totalErreurs  = 0;
$totalIgnores  = 0;

foreach ($especes as $espece) {

    if (empty($espece['nom_latin'])) {
        echo "⚠️  {$espece['nom_commun']} — nom latin manquant, ignoré\n";
        $totalIgnores++;
        continue;
    }

    echo "🔍 {$espece['nom_commun']} ({$espece['nom_latin']})...\n";

    // ------------------------------------------------
    // COMBIEN DE SONS A-T-ON DÉJÀ POUR CETTE ESPÈCE ?
    // ------------------------------------------------
    $stmt = $db->prepare("SELECT COUNT(*) FROM sons WHERE oiseau_id = ?");
    $stmt->execute([$espece['id']]);
    $nbSonsExistants = (int) $stmt->fetchColumn();

    if ($nbSonsExistants >= 5) {
        echo "   ✅ Déjà {$nbSonsExistants} sons, on passe\n";
        $totalIgnores++;
        continue;
    }

    $aAjouter = MAX_PAR_ESPECE - max(0, $nbSonsExistants - 5);
    if ($aAjouter <= 0) $aAjouter = 1;

    // ------------------------------------------------
    // APPEL API XENO-CANTO
    // ------------------------------------------------
    $nomLatinEncoded = urlencode($espece['nom_latin']);
   // Sépare le nom latin en genre et espèce
    $parties = explode(' ', trim($espece['nom_latin']));
    $genre   = $parties[0] ?? '';
    $sp      = $parties[1] ?? '';

    $url = XENO_API . "?query=gen:" . urlencode($genre) . "+sp:" . urlencode($sp) . "&page=1&key=" . $_ENV['XENO_CANTO_KEY'];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'OrnithoQuiz/1.0 (ornitho-quiz.fr)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        echo "   ❌ Erreur API\n";
        $totalErreurs++;
        usleep(PAUSE_MS * 1000);
        continue;
    }

    $data = json_decode($response, true);

    if (empty($data['recordings'])) {
        echo "   ⚠️  Aucun enregistrement trouvé\n";
        $totalIgnores++;
        usleep(PAUSE_MS * 1000);
        continue;
    }

    // ------------------------------------------------
    // FILTRAGE DES ENREGISTREMENTS
    // ------------------------------------------------
    $enregistrements = array_filter($data['recordings'], function($r) {
        return in_array($r['q'] ?? '', QUALITES);  // qualité A ou B seulement
    });

    // Trie par qualité (A avant B)
    usort($enregistrements, function($a, $b) {
        return strcmp($a['q'] ?? 'Z', $b['q'] ?? 'Z');
    });

    $enregistrements = array_slice(array_values($enregistrements), 0, $aAjouter);

    if (empty($enregistrements)) {
        echo "   ⚠️  Aucun enregistrement de qualité A/B\n";
        $totalIgnores++;
        usleep(PAUSE_MS * 1000);
        continue;
    }

    // ------------------------------------------------
    // INSERTION EN BDD
    // ------------------------------------------------
    $nbAjoutes = 0;

    foreach ($enregistrements as $rec) {

        // URL de streaming direct
        $urlAudio = $rec['file'] ?? '';

        // Vérification doublon (même URL déjà en BDD)
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM sons WHERE chemin_fichier = ?");
        $stmtCheck->execute([$urlAudio]);
        if ((int) $stmtCheck->fetchColumn() > 0) {
            continue;
        }

        // Détermination du type de son
        $typeSon = determinerTypeSon($rec['type'] ?? '');

        // Titre propre
        $titre = ucfirst(strtolower($rec['type'] ?? 'Son')) . ' — ' . $espece['nom_commun'];

        // Auteur
        $auteur = trim($rec['rec'] ?? '');

        $stmtInsert = $db->prepare("
            INSERT INTO sons (oiseau_id, titre, chemin_fichier, auteur, type_son)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([
            $espece['id'],
            $titre,
            $urlAudio,
            $auteur,
            $typeSon
        ]);

        $nbAjoutes++;
        $totalAjoutes++;
    }

    echo "   ➕ {$nbAjoutes} son(s) ajouté(s)\n";

    usleep(PAUSE_MS * 1000);
}

// ------------------------------------------------
// RÉSUMÉ
// ------------------------------------------------
echo "\n=== Terminé ===\n";
echo "✅ Ajoutés  : {$totalAjoutes}\n";
echo "⏭️  Ignorés  : {$totalIgnores}\n";
echo "❌ Erreurs  : {$totalErreurs}\n";

// ------------------------------------------------
// FONCTIONS
// ------------------------------------------------

/**
 * Mappe le type Xeno-canto vers ton enum type_son
 */
function determinerTypeSon(string $type): string
{
    $type = strtolower($type);

    if (str_contains($type, 'song') || str_contains($type, 'chant')) {
        return 'Chant';
    }
    if (str_contains($type, 'call') || str_contains($type, 'cri')) {
        return 'Cri';
    }
    if (str_contains($type, 'alarm')) {
        return 'Cri';
    }
    if (str_contains($type, 'drum')) {
        return 'Tambourinage';
    }
    if (str_contains($type, 'flight')) {
        return 'Cri';
    }

    return 'Autre';
}
