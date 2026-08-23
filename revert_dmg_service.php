<?php
$content = file_get_contents('app/Domain/Payment/Services/DmgService.php');
$search = "->whereHas('stage.contrats')\n                    ->whereHas('stage.sourceFinancement', fn (\$sf) => \$sf->where('code', '!=', 'PEJEDEC'))";
$replace = "->whereHas('stage.contrats')";
$content = str_replace($search, $replace, $content);
file_put_contents('app/Domain/Payment/Services/DmgService.php', $content);
