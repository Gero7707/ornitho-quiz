<?php

class MongoDatabase{

    private static ?MongoDatabase $MongoInstance = null;

    private object $mongo;

    private function __construct(){

        try{
            $dsn = getenv('MONGO_DSN') ?: 'mongodb://' . getenv('MONGO_HOST') . ':' . getenv('MONGO_PORT');

            $this->mongo = new MongoDB\Client($dsn);

        }catch(Exception $e){
            
            die("Database connection failure: " . $e->getMessage());
        }
    }

    
    public static function getInstance(): MongoDatabase {
        // Instanciation "paresseuse" : on ne crée l'objet que la première fois.
        if(self::$MongoInstance === null){
            self::$MongoInstance = new MongoDatabase();
        }
        return self::$MongoInstance;
    }

    /**
     * Renvoie une collection MongoDB prête à l'emploi (insertOne, find, etc.).
     * @param string $collectionName Nom de la collection.
     */
    public function getCollection(string $collectionName): object {
        // Nom de la base lu dans l'environnement au moment de l'appel
        // (pas stocké en propriété : récupéré à la demande).
        $dbname = getenv('MONGO_DB');

        // Accès dynamique : $this->mongo->{base}->{collection}.
        // MongoDB\Client surcharge l'accès propriété pour exposer bases et collections.
        return $this->mongo->$dbname->$collectionName; 
    }
}