<?php

$file = 'app/Domain/Workflow/Services/DesseDoublonService.php';
$content = file_get_contents($file);

$search = "    public function applyDuplicateFilter(Builder \$query, DoublonTypeEnum \$type): Builder\n    {";

$replace = <<<'PHP'
    /**
     * Exclut d'une requête (Paiement, DroitPaiement, InstanceParcours...) toutes les lignes 
     * qui sont détectées comme des doublons non traités.
     * Le paramètre $stageRelation définit le chemin de la relation vers 'stage' depuis le modèle query.
     */
    public function applyDuplicateExclusionFilter($query, string $stageRelation = 'stage'): void
    {
        $duplicateKeysByType = [];
        foreach (DoublonTypeEnum::cases() as $type) {
            $duplicateKeysByType[$type->value] = $this->computeDuplicateKeys($type);
        }

        $query->whereDoesntHave($stageRelation, function ($q) use ($duplicateKeysByType) {
            $q->join('beneficiaires', 'beneficiaires.id', '=', 'stages.beneficiaire_id')
                ->where(function ($sub) use ($duplicateKeysByType) {
                    foreach (self::TYPES as $typeValue => $config) {
                        $keys = $duplicateKeysByType[$typeValue] ?? collect();
                        if ($keys->isNotEmpty()) {
                            $sub->orWhereIn(DB::raw($config['expr']), $keys);
                        }
                    }

                    $compteKeys = $duplicateKeysByType[DoublonTypeEnum::COMPTE_PAIEMENT->value] ?? collect();
                    if ($compteKeys->isNotEmpty()) {
                        $sub->orWhereIn(DB::raw("CONCAT('TM:', UPPER(TRIM(beneficiaires.numero_tresor_money)))"), $compteKeys)
                             ->orWhereIn(DB::raw("CONCAT('WV:', UPPER(TRIM(beneficiaires.numero_wave)))"), $compteKeys);
                    }
                });
        });
    }

    public function applyDuplicateFilter(Builder $query, DoublonTypeEnum $type): Builder
    {
PHP;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Patched DesseDoublonService.\n";
