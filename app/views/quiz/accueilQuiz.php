<?php
require_once __DIR__ . '/../layout/header.php';
?>
<main class="img-fond accueil-quiz">
    <a href="/" class="fiche-back-quiz"> ← Retour à l'accueil</a><br>
    <section class="display-cards">
        <div class="header-display">
            <h1 class="titre-quiz">Bienvenue dans <span>OrnithoQuizz</span> !</h1>
            <p class="texte-jeu">Testez vos connaissances sur les oiseaux et leurs chants.</p><br>
            <p>Activer le son pour entendre les sons des oiseaux.</p>
        </div>
        <div class="card-grid">
            <div class="first-card">
                <h2 class="titre-jeu">Trouvez l'intrus</h2>
                <form action="/quiz/start" method="POST">
                    <input type="hidden" name="jeu" value="jeu1">
                    
                    <div>
                        <label for="questions">Choisissez le nombre de questions :</label>
                        <select name="nb_questions" class="select-jeux">
                            <option value="5">5 questions</option>
                            <option value="10">10 questions</option>
                            <option value="20">20 questions</option>
                            <option value="40">40 questions</option>
                        </select>
                        <label for="region">Choisissez la région :</label>
                        <select name="region" class="select-jeux">
                            <option value="">Tous les oiseaux</option>
                            <option value="metropole">France métropolitaine</option>
                            <option value="guyane">Guyane</option>
                        </select>
                    </div>
                    <button type="submit" class="bouton-jouer">Jouer</button>
                </form>
            </div>
            <div class="second-card">
                <h2 class="titre-jeu">Chant ou Cri ?</h2>
                <form action="/quiz/start" method="POST">
                    <input type="hidden" name="jeu" value="jeu2">
                    
                    <div>
                        <label for="questions">Choisissez le nombre de questions :</label>
                        <select name="nb_questions" class="select-jeux">
                            <option value="5">5 questions</option>
                            <option value="10">10 questions</option>
                            <option value="20">20 questions</option>
                            <option value="40">40 questions</option>
                        </select>
                        <label for="region">Choisissez la région :</label>
                        <select name="region" class="select-jeux">
                            <option value="">Tous les oiseaux</option>
                            <option value="metropole">France métropolitaine</option>
                            <option value="guyane">Guyane</option>
                        </select>
                    </div>
                    <button type="submit" class="bouton-jouer">Jouer</button>
                </form>
            </div>
            <div class="third-card">
                <h2 class="titre-jeu">Son de l'Oiseau</h2>
                <form action="/quiz/start" method="POST">
                    <input type="hidden" name="jeu" value="jeu3">
                    
                    <div>
                        <label for="questions">Choisissez le nombre de questions :</label>
                        <select name="nb_questions" class="select-jeux">
                            <option value="5">5 questions</option>
                            <option value="10">10 questions</option>
                            <option value="20">20 questions</option>
                            <option value="40">40 questions</option>
                        </select>
                        <label for="region">Choisissez la région :</label>
                        <select name="region" class="select-jeux">
                            <option value="">Tous les oiseaux</option>
                            <option value="metropole">France métropolitaine</option>
                            <option value="guyane">Guyane</option>
                        </select>
                    </div>
                    <button type="submit" class="bouton-jouer">Jouer</button>
                </form>
            </div>
            <div class="fourth-card">
                <h2 class="titre-jeu">Nom de l'oiseau</h2>
                <form action="/quiz/start" method="POST">
                    <input type="hidden" name="jeu" value="jeu4">
                    
                    <div>
                        <label for="questions">Choisissez le nombre de questions :</label>
                        <select name="nb_questions" class="select-jeux">
                            <option value="5">5 questions</option>
                            <option value="10">10 questions</option>
                            <option value="20">20 questions</option>
                            <option value="40">40 questions</option>
                        </select>
                        <label for="region">Choisissez la région :</label>
                        <select name="region" class="select-jeux">
                            <option value="">Tous les oiseaux</option>
                            <option value="metropole">France métropolitaine</option>
                            <option value="guyane">Guyane</option>
                        </select>
                    </div>
                    <button type="submit" class="bouton-jouer">Jouer</button>
                </form>
            </div>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>