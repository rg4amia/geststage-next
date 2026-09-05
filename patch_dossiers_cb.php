<?php

$content = file_get_contents('app/Http/Controllers/Dmg/PaiementDmgController.php');
$search = <<<'EOD'
        $statut = $request->query('statut', 'TRANSMIS_CB');

        $dossiers = DossierPaiement::with(['agence', 'sourceFinancement'])
            ->withCount('paiements')
            ->where('statut', $statut)
            ->where('periode_id', $periode?->id)
EOD;
$replace = <<<'EOD'
        $statut = $request->query('statut', 'TRANSMIS_CB');
        $search = $request->query('search');

        $query = DossierPaiement::with(['agence', 'sourceFinancement'])
            ->withCount('paiements')
            ->where('statut', $statut)
            ->where('periode_id', $periode?->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhereHas('paiements.droitPaiement.stage.beneficiaire', function ($q2) use ($search) {
                      $q2->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenoms', 'like', "%{$search}%")
                         ->orWhere('numero_aej', 'like', "%{$search}%");
                  });
            });
        }

        $dossiers = $query
EOD;
$content = str_replace($search, $replace, $content);
file_put_contents('app/Http/Controllers/Dmg/PaiementDmgController.php', $content);
echo 'Patched.';
