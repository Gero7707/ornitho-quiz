<?php
require_once __DIR__ . '/../../core/MongoDatabase.php';
class StatModel {

    private object $collection;

    public function __construct(){
        $this->collection = MongoDatabase::getInstance()->getCollection('stats_utilisateurs');
    }

    public function enregistrerQuiz(int $utilisateurId, string $jeu, int $score, int $nbQuestions): void {
        $this->collection->updateOne(
            ['utilisateur_id' => $utilisateurId],
            ['$inc' => [
                'stats_par_region.metropole.global.total_quizs'     => 1,
                'stats_par_region.metropole.global.bonnes_reponses' => $score,
                'stats_par_region.metropole.global.total_questions' => $nbQuestions,
                "stats_par_region.metropole.jeux.$jeu.total_quizs"     => 1,
                "stats_par_region.metropole.jeux.$jeu.bonnes_reponses" => $score,
                "stats_par_region.metropole.jeux.$jeu.total_questions" => $nbQuestions,
            ]],
            ['upsert' => true]
        );
    }

    public function findByUtilisateurId(int $utilisateurId): ?array{
        return $this->collection->findOne(
            ['utilisateur_id' => $utilisateurId],
            ['typeMap' => [
                'root'     => 'array',
                'document' => 'array',
                'array'    => 'array',
            ]]
        );
    }

    public function supprimerStats(int $id): bool{
        $deleteResult = $this->collection->deleteOne(['utilisateur_id' => $id]);
        return $deleteResult->getDeletedCount() > 0;
    }
}