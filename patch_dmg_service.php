<?php
$file = 'app/Domain/Payment/Services/DmgService.php';
$content = file_get_contents($file);

$search = "->when(\$mois, fn (Builder \$q) => \$q->whereHas('periode', fn (Builder \$p) => \$p->where('code', \$mois)))";
$replace = "->when(\$mois, fn (Builder \$q) => \$q->whereHas('periode', fn (Builder \$p) => \$p->where('code', \$mois)));\n\n        // Mur Pare-feu (Doublon DESSE) : on exclut de la DMG (Démarrage et Présence) tous les stagiaires détectés comme doublons non traités.\n        app(\App\Domain\Workflow\Services\DesseDoublonService::class)->applyDuplicateExclusionFilter(\$query, 'droitPaiement.stage');";

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Patched DmgService.\n";
