<?php

$file = 'app/Domain/Payment/Services/DmgService.php';
$content = file_get_contents($file);

// revert
$search1 = "->when(\$mois, fn (Builder \$q) => \$q->whereHas('periode', fn (Builder \$p) => \$p->where('code', \$mois)));\n\n        // Mur Pare-feu (Doublon DESSE) : on exclut de la DMG (Démarrage et Présence) tous les stagiaires détectés comme doublons non traités.\n        app(\App\Domain\Workflow\Services\DesseDoublonService::class)->applyDuplicateExclusionFilter(\$query, 'droitPaiement.stage');\n                    ->whereHas('stage.contrats')";
$replace1 = "->when(\$mois, fn (Builder \$q) => \$q->whereHas('periode', fn (Builder \$p) => \$p->where('code', \$mois)))\n                    ->whereHas('stage.contrats')";
$content = str_replace($search1, $replace1, $content);

// apply correctly to $query
$search2 = "        return \$query;\n    }";
$replace2 = "        // Mur Pare-feu (Doublon DESSE) : on exclut de la DMG (Démarrage et Présence) tous les stagiaires détectés comme doublons non traités.\n        app(\App\Domain\Workflow\Services\DesseDoublonService::class)->applyDuplicateExclusionFilter(\$query, 'droitPaiement.stage');\n\n        return \$query;\n    }";
$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "Syntax fixed.\n";
