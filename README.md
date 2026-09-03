# OrnithoQuizz

**OrnithoQuizz** est une application web française de quiz et d'identification
ornithologiques, couvrant la France métropolitaine et une partie des territoires
d'outre-mer (Antilles).

Le projet a un double objectif :
- un outil ornithologique fonctionnel (fiches espèces, quiz, identification par le son) qui va évoluer avec le temps en ajoutant des espèces provenant de tous les territoires et départements d'outre mer. Les quizs vont aussi évoluer pour intégrer une partie découverte pour les enfants te une difficulté à plusieurs niveau de débutants à experts;
- un projet de démonstration technique réalisé dans le cadre de la préparation de l'ECF
  (RNCP niveau 5 — Développeur Web et Web Mobile, Studi).

> **Projet personnel — non destiné à être redéployé.** Ce dépôt est rendu public
> temporairement pour les besoins de l'évaluation ECF. Il ne contient volontairement pas
> de guide d'installation : les identifiants, variables d'environnement et données ne
> sont pas fournis.

---

## Fonctionnalités

- **Catalogue d'espèces** : fiches détaillées (description, statut de conservation IUCN,
  images, sons) pour plus de 1000 espèces
- **Quiz** sous 4 formats différents (reconnaissance visuelle, sonore, textuelle...)
- **Identification par le son (BirdNET)** : enregistrement ou import d'un fichier audio,
  analyse par intelligence artificielle **entièrement dans le navigateur** (aucun audio
  envoyé au serveur), avec filtrage géographique optionnel
- **Comptes utilisateurs** : inscription, connexion, gestion de profil, suppression de
  compte (RGPD)
- **Statistiques personnelles** : suivi des scores par jeu et global, avec graphiques
- **Sécurité** : en-têtes HTTP durcis (CSP, Permissions-Policy, etc.), mots de passe
  hachés, protection IDOR

---

## Stack technique

| Composant | Choix |
|---|---|
| Backend | PHP 8.3, MVC "maison" (sans framework) |
| Base de données relationnelle | MySQL / MariaDB |
| Base de données NoSQL | MongoDB Atlas (statistiques de quiz) |
| Frontend | Alpine.js, Bootstrap, CSS custom |
| Graphiques | Chart.js |
| Identification IA | TensorFlow.js (modèle BirdNET v2.4, exécution côté client) |
| Stockage audio | Cloudflare R2 |
| Hébergement | Alwaysdata |
| Environnement de dev | Docker (PHP + MariaDB + MongoDB), WSL |

## Architecture

Le projet suit un pattern **MVC classique**, avec un routeur "maison" (`preg_replace` +
capture de paramètres). Les contrôleurs ne dialoguent jamais directement avec la base de
données : toute requête passe par un modèle dédié.

```
app/
├── controllers/    # OiseauController, QuizController, BirdnetController, UserController...
├── models/         # Oiseau, Son, Image, StatModel (MongoDB)...
└── views/          # Vues PHP, layout via header.php / footer.php
```

## Sources et crédits

- Données et sons : [Xeno-canto](https://xeno-canto.org) (API v3)
- Statuts de conservation : [IUCN Red List](https://www.iucnredlist.org) (API v4)
- Descriptions et images : Wikipedia / Wikimedia Commons
- Identification par le son : **BirdNET v2.4** (Cornell Lab of Ornithology), licence
  CC BY-NC-SA 4.0

## Outillage
 
Les scripts Python d'import et d'enrichissement de données (Xeno-canto, IUCN,
descriptions Wikipedia, images, nettoyage R2) ont été développés avec l'assistance d'un
outil d'intelligence artificielle. Le développement de l'application elle-même (PHP/MVC,
sécurité, base de données) a été réalisé personnellement, avec l'IA utilisée en tant que
mentor pédagogique (explications, guidage, revue de code).


## Documents légaux

- [Mentions légales](https://ornitho-quiz.fr/mentions-legales)
- [Politique de confidentialité](https://ornitho-quiz.fr/politique-confidentialite)

---

## Auteur

Projet développé et maintenu par Vincent Geraghty. Ce projet est d
