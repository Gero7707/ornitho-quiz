<?php
require_once __DIR__ . '/../models/Son.php';

class SonController {
    

    public function getBySon() {
        $sonModel = new Son();
        $sons = $sonModel->getByOiseauId(1);
        include __DIR__ . '/../views/sons/sonsList.php';
    }
}