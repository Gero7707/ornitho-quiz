<?php
require_once __DIR__ . '/../models/Oiseau.php';
require_once __DIR__ . '/../models/Son.php';





class QuizController {

    public function accueilQuiz(){
        include __DIR__ . '/../views/quiz/accueilQuiz.php';
    }


    private function checkSession($jeu) {
        if(!isset($_SESSION['nb_questions'])) {
            header('Location: /quiz');
            exit;
        }
        if(isset($_SESSION['jeu']) && $_SESSION['jeu'] !== $jeu) {
            header('Location: /quiz/' . $_SESSION['jeu']);
            exit;
        }
    }
    public function jeu1() {
        $this->checkSession('jeu1');
        $region = $_SESSION['region'] ?? '';
        $oiseaux = new Oiseau();
        $son = new Son();
        $oiseau = $oiseaux->getRandomOiseau($region);
        $sonsAleatoires = $son->getRandomByOiseauId($oiseau['id'], 3);
        $intru = $son->getRandomIntrus($oiseau['id'], $region);
        $intruOiseauId = $intru['oiseau_id'];
        $bonneReponseId = $intru['id'];
        $sons = $son->getByOiseauId($oiseau['id']);
        $sonsAleatoires[] = $intru;
        $quatreSons = $sonsAleatoires;
        shuffle($quatreSons);
        include __DIR__ . '/../views/quiz/quiz.php';
    }

    public function valider(){
        if(!isset($_POST['son_id'])) {
            header('Location: /quiz/jeu1');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $bonneReponseId = $_POST['bonne_reponse_id'];
            $sonChoisi = $_POST['son_id'];
            $resultat = ($bonneReponseId == $sonChoisi) ? 'correct' : 'incorrect';

            $_SESSION['question_actuelle']++;
            if($resultat === 'correct') $_SESSION['score']++;
            $derniere = $_SESSION['question_actuelle'] >= $_SESSION['nb_questions'];

            // Stocker en session
            $_SESSION['resultat'] = $resultat;
            $_SESSION['oiseau_id'] = $_POST['oiseau_intrus_id']  ?? $_POST['oiseau_id'];
            $_SESSION['oiseau_cible_id'] = $_POST['oiseau_id'];
            $_SESSION['son_id'] = $_POST['son_id'];
            $_SESSION['sons_ids'] = $_POST['sons_ids'];
            $_SESSION['jeu'] = 'jeu1';
            $_SESSION['derniere'] = $derniere;
            $_SESSION['oiseau_cible_id'] = $_POST['oiseau_cible_id'] ?? null;

            header('Location: /quiz/resultat');
            exit;
        } else {
            echo "Aucune réponse reçue.";
        }
    }

    public function start(){
        $_SESSION['nb_questions'] = (int)$_POST['nb_questions'];
        $_SESSION['score'] = 0;
        $_SESSION['question_actuelle'] = 0;
        $_SESSION['jeu'] = $_POST['jeu'];
        $_SESSION['region'] = $_POST['region'] ?? '';  // ← ajouter
        $jeu = $_POST['jeu'];
        header('Location: /quiz/' . $jeu);
        exit;
    }

    public function fin(){
        $score = $_SESSION['score'] ?? 0;
        $nbQuestions = $_SESSION['nb_questions'] ?? 0;
        include __DIR__ . '/../views/quiz/finQuiz.php';
    }

    public function jeu2() {
        $this->checkSession('jeu2');
        $region = $_SESSION['region'] ?? '';
        $sonModel = new Son();
        $chantOuCri = $sonModel->getRandomSonChantOuCri($region);
        $oiseauModel = new Oiseau();
        $oiseau = $oiseauModel->getById($chantOuCri['oiseau_id']);
        include __DIR__ . '/../views/quiz/quiz2.php';
    }

    public function validerJeu2(){
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            
            $bonneReponse = $_POST['bonne_reponse'];
            $reponse = $_POST['reponse'];
            $resultat = ($bonneReponse === $reponse) ? 'correct' : 'incorrect';
            
            $_SESSION['question_actuelle']++;
            if($resultat === 'correct') $_SESSION['score']++;

            $derniere = $_SESSION['question_actuelle'] >= $_SESSION['nb_questions'];

            // Stocker en session
            $_SESSION['resultat'] = $resultat;
            $_SESSION['oiseau_id'] = $_POST['oiseau_id'];
            $_SESSION['son_id'] = $_POST['son_id'];
            $_SESSION['jeu'] = 'jeu2';
            $_SESSION['derniere'] = $derniere;

            header('Location: /quiz/resultat');
            exit;

        } else {
            echo "Aucune réponse reçue.";
        }
    }

    public function jeu3() {
        $this->checkSession('jeu3');
        $region = $_SESSION['region'] ?? '';
        $sonModel = new Son();
        $son = $sonModel->getRandomSon($region);
        $oiseauModel = new Oiseau();
        $vrai = rand(0, 1);
        $bonneReponse = $vrai ? 'Vrai' : 'Faux';
        if ($vrai) {
            $oiseau = $oiseauModel->getById($son['oiseau_id']);
        } else {
            $oiseau = $oiseauModel->getRandomOiseau($region);
        }
        include __DIR__ . '/../views/quiz/quiz3.php';
    }

    public function validerJeu3(){
        $bonneReponse = $_POST['bonne_reponse'];
        $reponse = $_POST['reponse'];
        $resultat = ($bonneReponse === $reponse) ? 'correct' : 'incorrect';
        
        $_SESSION['question_actuelle']++;
        if($resultat === 'correct') $_SESSION['score']++;
        $derniere = $_SESSION['question_actuelle'] >= $_SESSION['nb_questions'];

        $sonModel = new Son();
        $son = $sonModel->getById($_POST['son_id']);
        
        $_SESSION['vrai_oiseau_id'] = $son['oiseau_id'];

        $_SESSION['resultat'] = $resultat;
        $_SESSION['oiseau_id'] = $_POST['oiseau_id'];
        $_SESSION['son_id'] = $_POST['son_id'];
        $_SESSION['jeu'] = 'jeu3';
        $_SESSION['derniere'] = $derniere;

        header('Location: /quiz/resultat');
        exit;
    }

    public function jeu4() {
        $this->checkSession('jeu4');
        $region = $_SESSION['region'] ?? '';
        $troisOiseaux = [];
        $sonModel = new Son();
        $son = $sonModel->getRandomSon($region);
        $oiseauModel = new Oiseau();
        for ($i = 0; $i < 3; $i++) {
            $troisOiseaux[] = $oiseauModel->getRandomOiseau($region);
        }
        $oiseau = $oiseauModel->getById($son['oiseau_id']);
        $troisOiseaux[] = $oiseau;
        $quatreNoms = $troisOiseaux;
        shuffle($quatreNoms);
        $bonneReponse = $oiseau['id'];
        include __DIR__ . '/../views/quiz/quiz4.php';
    }

    public function validerJeu4(){
        if(!isset($_POST['reponse'])) {
            header('Location: /quiz/jeu4');
            exit;
        }
        $bonneReponse = $_POST['bonne_reponse'];
        $reponse = $_POST['reponse'];
        $resultat = ($bonneReponse === $reponse) ? 'correct' : 'incorrect';
        
        $_SESSION['question_actuelle']++;
        if($resultat === 'correct') $_SESSION['score']++;
        $derniere = $_SESSION['question_actuelle'] >= $_SESSION['nb_questions'];

        // Stocker en session
        $_SESSION['resultat'] = $resultat;
        $_SESSION['oiseau_id'] = $_POST['oiseau_id'];
        $_SESSION['son_id'] = $_POST['son_id'];
        $_SESSION['jeu'] = 'jeu4';
        $_SESSION['derniere'] = $derniere;

        header('Location: /quiz/resultat');
        exit;
    }

    public function resultat(){
        $resultat = $_SESSION['resultat'] ?? null;
        $oiseauId = $_SESSION['oiseau_id'] ?? null;
        $sonId = $_SESSION['son_id'] ?? null;
        $sonsIds = $_SESSION['sons_ids'] ?? null;
        $jeu = $_SESSION['jeu'] ?? null;
        $derniere = $_SESSION['derniere'] ?? false;
        
        $oiseauModel = new Oiseau();
        $sonModel = new Son();
        $oiseau = $oiseauModel->getById($oiseauId);
        $vraiOiseau = $oiseauModel->getById($_SESSION['vrai_oiseau_id'] ?? null);
        $vraiIntrus = $oiseauModel->getById($_SESSION['oiseau_id']);

        $son = $sonModel->getById($sonId);

        foreach(explode(',', $sonsIds) as $id){
            $s = $sonModel->getById($id);
            $s['nom_commun'] = $oiseauModel->getById($s['oiseau_id'])['nom_commun'];
            $quatreSons[] = $s;
        }

        if($jeu === 'jeu4'){
            $troisOiseaux = [];
            for($i = 0; $i < 3; $i++){
                $troisOiseaux[] = $oiseauModel->getRandomOiseau();
            }
            $troisOiseaux[] = $oiseau;
            $quatreNoms = $troisOiseaux;
            shuffle($quatreNoms);
        }

        $vues = [
            'jeu1' => 'quiz.php',
            'jeu2' => 'quiz2.php',
            'jeu3' => 'quiz3.php',
            'jeu4' => 'quiz4.php',
        ];
        include __DIR__ . '/../views/quiz/' . $vues[$jeu];
    }
}