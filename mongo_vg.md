# Récapitulatif session — Phase 4 : Intégration MongoDB
 
---
 
## ✅ Décisions architecturales
 
### Pas de table d'archivage
- Le problème d'affichage des commandes terminées se règle par un **filtre SQL** (`WHERE statut != 'terminee'`), pas par une table séparée
- Une table d'archivage casserait les FK (`historique_statut`, `avis` référencent `commande_id`)
### Rôle complémentaire de MongoDB
- **MySQL** = source de vérité (toutes les commandes, toutes les données métier)
- **MongoDB** = base d'agrégation analytique (commandes terminées uniquement, pour les stats)
- Les données ne sont **pas déplacées** de MySQL vers MongoDB — elles sont **copiées** au moment où une commande passe à `terminee`
### Règle métier CA
- Le chiffre d'affaires est généré quand la commande passe au statut `terminee` (matériel rendu)
- Les commandes `annulee` n'ont aucun impact sur le CA (annulation avant acceptation)
### Structure du document MongoDB
```json
{
    "commande_id": 1,
    "menu_id": 3,
    "menu_titre": "Menu Prestige",
    "prix_total": 450.0,
    "date_terminee": "2026-06-15 14:18:08"
}
```
- `commande_id` : clé de traçabilité vers MySQL (null pour les données de seed)
- `menu_titre` : dénormalisé volontairement (pas de JOIN possible en MongoDB)
- `prix_total` : float (cast explicite depuis `$commande['prix_menu']`)
- `date_terminee` : date du statut `terminee` dans `historique_statut`, générée côté PHP
---
 
## ✅ Infrastructure Docker
 
### `docker-compose.yml` — ajouts
```yaml
mongodb:
    image: mongo:7
    container_name: vite_gourmand_mongodb
    restart: always
    ports:
      - "27018:27017"   # 27018 côté hôte pour éviter conflit avec MongoDB Windows
    volumes:
      - mongodb_data:/data/db
 
volumes:
  mongodb_data:   # volume persistant — données conservées après docker-compose down
```
 
### Variables d'environnement (`.env` + `docker-compose.yml`)
```env
MONGO_HOST=mongodb
MONGO_PORT=27017
MONGO_DB=vite_gourmand
```
 
### Driver PHP
- `ext-mongodb` installé dans le container via `Dockerfile` :
```dockerfile
RUN pecl install mongodb && docker-php-ext-enable mongodb
```
- Library PHP installée via Composer :
```bash
composer require --dev mongodb/mongodb
# → version 2.1.2 (compatible ext-mongodb installé)
```
 
---
 
## ✅ `core/MongoDatabase.php` — Singleton MongoDB
 
Pattern identique à `Database.php` (PDO).
 
```php
class MongoDatabase {
    private static ?MongoDatabase $MongoInstance = null;
    private object $mongo;
 
    private function __construct() {
        $host   = getenv('MONGO_HOST');
        $port   = getenv('MONGO_PORT');
        try {
            $dsn = "mongodb://{$host}:{$port}";
            $this->mongo = new MongoDB\Client($dsn);
        } catch (Exception $e) {
            die("Database connection failure: " . $e->getMessage());
        }
    }
 
    public static function getInstance(): MongoDatabase {
        if (self::$MongoInstance === null) {
            self::$MongoInstance = new MongoDatabase();
        }
        return self::$MongoInstance;
    }
 
    public function getCollection(string $collectionName): object {
        $dbname = getenv('MONGO_DB');
        return $this->mongo->$dbname->$collectionName;
    }
}
```
 
**Points clés :**
- Pas de `setAttribute()` — MongoDB lève des exceptions automatiquement
- `getCollection()` retourne directement la collection MongoDB (pas la connexion brute)
- `$dbname` récupéré via `getenv()` dans la méthode (pas stocké en propriété)
- Type `object` pour éviter les faux positifs Intelephense sur `MongoDB\Client` et `MongoDB\Collection`
---
 
## ✅ `app/models/MongoModel.php`
 
```php
class MongoModel {
    public function insertCommande(array $data): void {
        $collection = MongoDatabase::getInstance()->getCollection('commandes');
        $collection->insertOne($data);
    }
}
```
 
**Points clés :**
- Pas de constructeur — collection récupérée directement dans chaque méthode (Option B)
- `insertOne()` accepte directement un tableau PHP — pas de `prepare/bindValue/execute`
- Chargé dans `index.php` via `require_once` (pas d'autoload PSR-4 pour les classes maison)
---
 
## ✅ Insertion automatique dans `CommandeController::changerStatutCommande()`
 
Après `createHistorique()`, si le statut suivant est `terminee` :
 
```php
if ($statutSuivant === 'terminee') {
    $data = [
        'commande_id'   => $id,
        'menu_id'       => $statutActuel['menu_id'],
        'menu_titre'    => $statutActuel['titre'],
        'prix_total'    => (float) $statutActuel['prix_menu'],
        'date_terminee' => $dateModif
    ];
    $this->mongo->insertCommande($data);
}
```
 
**Points clés :**
- `$statutActuel` contient déjà `menu_id`, `titre`, `prix_menu` grâce à la JOIN dans `findById()`
- `$dateModif` est générée juste avant `createHistorique()` — réutilisée ici
- Cast `(float)` obligatoire — PDO retourne les `DECIMAL` MySQL en string
- `MongoModel` instancié dans le constructeur de `CommandeController` : `$this->mongo = new MongoModel()`
---
 
## ✅ Bug CSS/JS corrigé — filtres menus
 
**Problème :** Bootstrap applique `display: flex !important` via la classe `d-flex` sur `.carte-menu`, ce qui écrasait le `style="display: none"` appliqué par JS.
 
**Règle apprise :** Quand un framework CSS utilise `!important`, ne pas manipuler `element.style.display` en JS — manipuler les classes à la place.
 
**Correction dans `filtres.js` :**
```javascript
// Avant
carte.style.display = "none";
carte.style.display = "block";
if (carte.style.display !== 'none') { ... }
 
// Après
carte.classList.add('d-none');
carte.classList.remove('d-none');
if (!carte.classList.contains('d-none')) { ... }
```
 
---
 
## ✅ Script de migration `migrate_mongo.php` (usage unique)
 
Inséré les 11 commandes `terminee` existantes dans MySQL vers MongoDB.
 
**Requête SQL :**
```sql
SELECT commande.commande_id, commande.menu_id, menu.titre AS menu_titre,
       commande.prix_menu, historique_statut.date_modification AS date
FROM commande
JOIN menu ON menu.menu_id = commande.menu_id
JOIN historique_statut ON historique_statut.commande_id = commande.commande_id
WHERE historique_statut.statut = 'terminee'
```
 
**Points clés :**
- Préfixer `commande.commande_id` et `commande.menu_id` — ambiguïté avec la table `menu`
- Script procédural (pas de classe) — usage unique, pas de code de production
- Supprimé après exécution
---
 
## ✅ Script de seed `seed_mongo.php` (usage unique)
 
Génère 125 documents fictifs (5 mois × 25 commandes) pour la démo ECF.
 
```php
$months = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'];
 
foreach ($months as $month) {
    for ($i = 0; $i < 25; $i++) {
        $menu_id      = array_rand($menus);
        $date_terminee = $month . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT) . ' 10:00:00';
        $nb_personnes  = rand(12, 30);
        $prix_total    = $nb_personnes * $menus[$menu_id]['prix_par_personne'];
 
        $collection->insertOne([
            'commande_id'   => null,
            'menu_id'       => $menu_id,
            'menu_titre'    => $menus[$menu_id]['titre'],
            'prix_total'    => $prix_total,
            'date_terminee' => $date_terminee
        ]);
    }
}
```
 
**Points clés :**
- `commande_id = null` — données fictives sans équivalent MySQL
- `array_rand()` — retourne une clé aléatoire du tableau associatif
- `str_pad(..., 2, '0', STR_PAD_LEFT)` — formate le jour sur 2 chiffres (`01`, `05`...)
- Supprimé après exécution
---
 
## 🔲 Prochaines étapes
 
1. **Méthodes de lecture dans `MongoModel`** :
   - `getCommandesParMenu()` → agrégation pour le graphique comparatif
   - `getCAParMenu(array $filtres)` → CA par menu avec filtres (menu, période)
2. **Dashboard admin — vue statistiques** :
   - Graphique comparatif nombre de commandes par menu (Chart.js ou autre)
   - Tableau CA par menu avec filtres
3. **Accessibilité RGAA**
4. **Déploiement Alwaysdata + Brevo SMTP**
5. **Documentation technique + manuel utilisateur**
 