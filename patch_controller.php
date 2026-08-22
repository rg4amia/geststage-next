<?php
$file = 'app/Http/Controllers/Dmg/PaiementDmgController.php';
$content = file_get_contents($file);
$replacement = <<<CODE
    public function documentsByStage(Request \$request): JsonResponse
    {
        \$request->validate(['stage_id' => 'required|integer']);
        \$stageId = \$request->input('stage_id');

        \$documents = DB::table('documents')
            ->join('versions_documents', 'documents.id', '=', 'versions_documents.document_id')
            ->leftJoin('types_document', 'documents.type_document_id', '=', 'types_document.id')
            ->where('documents.stage_id', \$stageId)
            ->select(
                'documents.id',
                'documents.nom',
                'types_document.code as type_code',
                'types_document.nom as type_nom',
                'versions_documents.chemin',
                'versions_documents.nom_original',
                'versions_documents.type_mime',
                'versions_documents.taille_octets'
            )
            ->orderByDesc('versions_documents.numero_version')
            ->get();

        return response()->json(['data' => \$documents]);
    }
}
CODE;
$content = preg_replace('/\}\s*$/', $replacement, $content);
file_put_contents($file, $content);
