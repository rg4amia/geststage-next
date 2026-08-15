<?php

namespace App\Domain\Registration\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\InstanceParcours;
use App\Models\Document\Document;
use App\Models\Document\VersionDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class InscriptionStagiaireService
{
    public function __construct(
        private readonly WorkflowTransitionService $workflowService
    ) {}

    /**
     * Inscrit un nouveau stagiaire en créant le bénéficiaire, le stage, le contrat initial
     * et en initialisant le moteur de workflow.
     */
    public function inscrire(
        array $donneesBeneficiaire,
        array $donneesStage,
        array $donneesContrat,
        array $donneesDocuments,
        User $cip
    ): InstanceParcours {
        return DB::transaction(function () use ($donneesBeneficiaire, $donneesStage, $donneesContrat, $donneesDocuments, $cip) {
            // 1. Création du bénéficiaire
            $beneficiaire = Beneficiaire::create($donneesBeneficiaire);

            // 2. Création du stage
            $stage = Stage::create(array_merge($donneesStage, [
                'beneficiaire_id' => $beneficiaire->id,
            ]));

            // 3. Création du contrat initial (Brouillon)
            $contrat = Contrat::create(array_merge($donneesContrat, [
                'stage_id' => $stage->id,
                'statut' => 'BROUILLON',
                'version_verrouillage' => 0,
            ]));

            // 4. Gestion des pièces justificatives (GED)
            foreach ($donneesDocuments as $key => $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    // Création ou récupération du type de document
                    $typeDoc = DB::table('types_document')->where('code', strtoupper($key))->first();
                    if (!$typeDoc) {
                        $typeDocId = DB::table('types_document')->insertGetId([
                            'code' => strtoupper($key),
                            'nom' => ucfirst(str_replace('_', ' ', $key)),
                            'actif' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $typeDocId = $typeDoc->id;
                    }

                    // Stockage du fichier (disque local 'documents' ou par défaut)
                    $path = $file->store('dossiers_stagiaires/' . $beneficiaire->id);

                    // Création du document racine
                    $document = Document::create([
                        'type_document_id' => $typeDocId,
                        'beneficiaire_id' => $beneficiaire->id,
                        'stage_id' => $stage->id,
                        'contrat_id' => $contrat->id,
                        'cree_par_id' => $cip->id,
                        'nom' => $file->getClientOriginalName(),
                        'statut' => 'VALIDE',
                        'prive' => true,
                    ]);

                    // Création de la version de document
                    VersionDocument::create([
                        'document_id' => $document->id,
                        'depose_par_id' => $cip->id,
                        'numero_version' => 1,
                        'disque' => config('filesystems.default'),
                        'chemin' => $path,
                        'nom_original' => $file->getClientOriginalName(),
                        'type_mime' => $file->getMimeType(),
                        'taille_octets' => $file->getSize(),
                        'empreinte_sha256' => hash_file('sha256', $file->getRealPath()),
                    ]);
                }
            }

            // 4. Initialisation du Workflow
            $definition = DefinitionParcours::where('code', 'PAE')->where('active', true)->firstOrFail();
            
            return $this->workflowService->initier(
                $definition,
                $cip,
                ['stage_id' => $stage->id],
                ['action' => 'Inscription stagiaire']
            );
        });
    }
}
