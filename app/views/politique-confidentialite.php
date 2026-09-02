<?php
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../views/layout/nav.php';
?>

<main class="main-confidentialite">
    <section class="politique-confidentialite d-flex flex-column mb-5">
        <h1 class="text-center">Politique de confidentialité</h1>
        <p><em>Dernière mise à jour : 02/09/2026</em></p>

        <p>
            Cette politique explique quelles données personnelles OrnithoQuizz collecte,
            pourquoi, et comment exercer vos droits. Elle complète les
            <strong><a href="/mentions-legales">mentions légales</a></strong> et s'applique conformément au
            Règlement Général sur la Protection des Données (RGPD).
        </p>

        <h2>1. Responsable du traitement</h2>
        <ul>
            <li><strong>Nom, prénom :</strong> Vincent Geraghty</li>
            <li><strong>Contact :</strong> ornitho-quiz@outlook.fr</li>
        </ul>

        <h2>2. Données collectées et finalités</h2>
        <table>
            <thead>
                <tr>
                    <th>Donnée</th>
                    <th>Où</th>
                    <th>Finalité</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Email, pseudo</td>
                    <td>MySQL (utilisateur)</td>
                    <td>Création et gestion du compte, connexion</td>
                </tr>
                <tr>
                    <td>Mot de passe</td>
                    <td>MySQL (utilisateur), stocké <strong>haché</strong> (jamais en clair)</td>
                    <td>Authentification</td>
                </tr>
                <tr>
                    <td>Statistiques de quiz (scores, bonnes réponses, quiz joués)</td>
                    <td>MongoDB Atlas</td>
                    <td>Affichage de vos statistiques personnelles dans votre profil</td>
                </tr>
                <tr>
                    <td>Cookie de session (PHPSESSID)</td>
                    <td>Navigateur</td>
                    <td>Maintenir votre connexion pendant la navigation (cookie strictement nécessaire, pas de consentement requis)</td>
                </tr>
            </tbody>
        </table>
        <p>
            Un visiteur non connecté peut utiliser les quiz sans qu'aucune de ces données ne
            soit créée ou conservée.
        </p>

        <h3>Fonctionnalité d'identification par le son (BirdNET)</h3>
        <p>
            La page <strong><a href="/identifier">Identifier un oiseau</a></strong> demande l'accès au
            <strong>microphone</strong> et à la <strong>géolocalisation</strong> de votre
            navigateur. Ces deux accès sont utilisés uniquement pour :
        </p>
        <ul>
            <li>enregistrer un son afin de l'analyser (microphone) ;</li>
            <li>affiner la reconnaissance d'espèces en filtrant celles présentes dans votre zone géographique (géolocalisation).</li>
        </ul>
        <p>
            <strong>L'analyse audio est effectuée entièrement dans votre navigateur</strong>
            (modèle d'intelligence artificielle TensorFlow.js exécuté localement).
            <strong>Aucun enregistrement audio ni donnée de géolocalisation n'est envoyé,
            transmis ou stocké sur nos serveurs ou ceux de nos prestataires.</strong> Vous
            pouvez refuser ces accès : l'identification par enregistrement ne sera pas
            disponible, mais l'import de fichier audio reste utilisable (la géolocalisation
            étant alors simplement ignorée, sans filtrage géographique).
        </p>

        <h2>3. Base légale du traitement</h2>
        <ul>
            <li><strong>Consentement</strong> : donné explicitement lors de l'inscription (case à cocher RGPD).</li>
            <li><strong>Exécution du contrat</strong> : la fourniture du service (compte, statistiques) nécessite le traitement de l'email, du pseudo et du mot de passe.</li>
        </ul>

        <h2>4. Destinataires et sous-traitants</h2>
        <p>
            Vos données ne sont ni vendues ni utilisées à des fins publicitaires. Elles sont
            traitées par les prestataires techniques suivants, dans le cadre strict du
            fonctionnement du site :
        </p>
        <table>
            <thead>
                <tr>
                    <th>Prestataire</th>
                    <th>Rôle</th>
                    <th>Localisation</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Alwaysdata</td>
                    <td>Hébergement du site et de la base MySQL</td>
                    <td>France</td>
                </tr>
                <tr>
                    <td>MongoDB Atlas</td>
                    <td>Hébergement des statistiques de quiz</td>
                    <td>Union européenne (Paris, eu-west-3)</td>
                </tr>
                <tr>
                    <td>Cloudflare R2</td>
                    <td>Stockage des fichiers audio (sons d'oiseaux)</td>
                    <td>Union européenne (Europe de l'Ouest, WEUR)</td>
                </tr>
                <tr>
                    <td>Brevo</td>
                    <td>Envoi des emails transactionnels (confirmation d'inscription, réinitialisation de mot de passe)</td>
                    <td>France / Union européenne</td>
                </tr>
            </tbody>
        </table>

        <h2>5. Durée de conservation</h2>
        <p>
            Vos données sont conservées tant que votre compte existe. La suppression de
            votre compte entraîne la suppression immédiate et définitive de vos données
            (statistiques MongoDB supprimées avant les données du compte MySQL).
        </p>

        <h2>6. Vos droits</h2>
        <p>
            Conformément au RGPD, vous disposez des droits suivants sur vos données :
        </p>
        <ul>
            <li>
                <strong>Droit d'accès et de rectification</strong> — consultables et
                modifiables directement depuis votre page <strong><a href="/profil">Profil</a></strong>.
            </li>
            <li>
                <strong>Droit à l'effacement</strong> — supprimez votre compte et l'ensemble
                de vos données associées depuis la page
                <strong><a href="/supprimer-profil">Supprimer mon profil</a></strong>.
            </li>
            <li>
                <strong>Droit d'opposition et de portabilité</strong> — exerçable en nous
                contactant à ornitho-quiz@outlook.fr.
            </li>
        </ul>
        <p>
            Vous disposez également du droit d'introduire une réclamation auprès de la CNIL
            (<strong><a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a></strong>)
            si vous estimez que vos droits ne sont pas respectés.
        </p>

        <h2>7. Sécurité des données</h2>
        <ul>
            <li>Mots de passe <strong>jamais stockés en clair</strong> (hachage via l'algorithme recommandé par PHP, password_hash).</li>
            <li>Connexion au site chiffrée (<strong>HTTPS forcé</strong>).</li>
            <li>En-têtes de sécurité HTTP (Content-Security-Policy, etc.) limitant les risques d'injection et de vol de données côté navigateur.</li>
        </ul>

        <h2>8. Cookies</h2>
        <p>
            OrnithoQuizz utilise uniquement un cookie de session (PHPSESSID),
            strictement nécessaire au fonctionnement du site (maintien de la connexion).
            Aucun cookie publicitaire ou de traçage tiers n'est déposé.
        </p>

        <h2>9. Modification de cette politique</h2>
        <p>
            Cette politique peut être mise à jour pour refléter des évolutions du site ou de
            la réglementation. La date de dernière mise à jour est indiquée en haut de cette
            page.
        </p>
    </section>
</main>

<?php
require_once __DIR__ . '/layout/footer.php';
?>