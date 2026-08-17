<?php

require_once __DIR__ . '/../models/Oiseau.php';
require_once __DIR__ . '/../models/Son.php';

class HomeController
{
    public function index(): void{
        $oiseau = new Oiseau();
        $son    = new Son();

        $stats = [
            'nb_especes' => $oiseau->countAll(),
            'nb_sons'    => $son->countAll(),
        ];

        require_once __DIR__ . '/../views/index.php';
    }

    public function aPropos(): void{
        require_once __DIR__ . '/../views/about.php';
    }
}