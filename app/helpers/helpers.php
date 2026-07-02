<?php
function nettoyerTitre($titre) {
    $titre = preg_replace('/^[A-Z]\d+\s*-\s*/', '', $titre);
    $titre = preg_replace('/\.mp3$/i', '', $titre);
    return trim($titre);
}

function getAudioUrl(string $cheminFichier): string{
    if (str_starts_with($cheminFichier, 'http')) {
        return $cheminFichier;
    }
    return Son::$audioBaseUrl . $cheminFichier;
}

function iucnCouleur(string $statut): string {
    return match($statut) {
        'LC'        => '#60aa22',
        'NT'        => '#cccc00',
        'VU'        => '#f4a800',
        'EN'        => '#fc7f3f',
        'CR'        => '#d40000',
        'EW', 'EX'  => '#000000',
        default     => '#aaaaaa',
    };
}