<?php
require_once __DIR__ . '/../layout/header.php';

$pourcentage = $nbQuestions > 0 ? round(($score / $nbQuestions) * 100) : 0;

if($pourcentage >= 80){
    $appreciation = "Excellent !";
} elseif($pourcentage >= 60){
    $appreciation = "Bien !";
} elseif($pourcentage >= 40){
    $appreciation = "Pas mal...";
} else {
    $appreciation = "C'est dur mais ne vous découragez pas !";
}
?>
<main  class="img-fond">
    <section class="display-cards">
        <h1 class="titre-quiz">Quizz terminé !</h1><br>
        <p class="texte-jeu">Votre score : <span class="score-quiz"><?php echo $score; ?> / <?php echo $nbQuestions; ?></span></p><br>
        <p class="texte-jeu">Vous avez obtenu <span class="pourcentage-quiz"><?php echo $pourcentage; ?>%</span> de bonnes réponses !</p><br>
        <p class="texte-jeu">Appréciation : <span class="appreciation-quiz"><?php echo $appreciation; ?></span></p><br><br>
        <a href="/quiz" class="action-quiz">Rejouer</a><br>
        <a href="/" class="action-quiz">Accueil</a>
    </section>
</main>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>