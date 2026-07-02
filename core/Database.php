<?php

class Database{
    private static $instance =null;

    private $pdo;

    private function __construct(){
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');

        try{
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $this->pdo = new PDO($dsn,$username,$password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }catch(PDOException $e){
            die("Database connection failure: " . $e->getMessage());
        }
    }

    public static function getInstance(){
        if(self::$instance === null){
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(){
        return $this->pdo;
    }
}