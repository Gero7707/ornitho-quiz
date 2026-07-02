<?php
require_once __DIR__ . '/../layout/header.php';
?>
<main>
    <h1 class="titre-liste">Liste des espèces</h1>
    <a href="/" class="fiche-back"> ← Retour à l'accueil</a><br>
    <div class="carte-liste">
        <div class="fond-liste-gauche"></div>
        <div class="liste">
            <form method="GET" action="/oiseaux" class="form-liste">
                <input type="text" 
                    name="recherche" 
                    placeholder="Rechercher un oiseau..." 
                    value="<?= htmlspecialchars($_GET['recherche'] ?? '') ?>">
                <button type="submit">Rechercher</button>
                <label for="lettre">Recherche alphabétique :</label>
                <select name="lettre" id="lettre" onchange="this.form.submit()">
                    <option value="">-- Choisir une lettre --</option>
                    <?php foreach (range('A', 'Z') as $lettre): ?>
                        <option value="<?= $lettre ?>"><?= $lettre ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="region">Filtrer par région :</label>
                <select name="region" onchange="this.form.submit()">
                    <option value="">Toutes les régions</option>
                    <option value="metropole" <?= ($_GET['region'] ?? '') === 'metropole' ? 'selected' : '' ?>>France métropolitaine</option>
                    <option value="guyane" <?= ($_GET['region'] ?? '') === 'guyane' ? 'selected' : '' ?>>Guyane</option>
                    <option value="reu_mad_may" <?= ($_GET['region'] ?? '') === 'reu_mad_may' ? 'selected' : '' ?>>La Réunion, Madagascar, Mayotte</option>
                    <option value="antilles" <?= ($_GET['region'] ?? '') === 'antilles' ? 'selected' : '' ?>>Antilles</option>
                </select>

            </form>
            <ul>
                <?php foreach ($this->oiseaux as $oiseau): ?>
                    <li><a href="/oiseau/<?= $oiseau['id'] ?>" class="element-liste"><?= htmlspecialchars($oiseau['nom_commun']) ?> — <em><?= htmlspecialchars($oiseau['nom_latin'] ?? '') ?></em></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="fond-liste-droite"></div>
    </div>
</main>


<?php
require_once __DIR__ . '/../layout/footer.php';
?>