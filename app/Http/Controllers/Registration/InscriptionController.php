<?php

namespace App\Http\Controllers\Registration;

use App\Domain\Registration\Services\DureeStageCalculator;
use App\Domain\Registration\Services\InscriptionStagiaireService;
use App\Domain\Workflow\Services\DesseDoublonService;
use App\Domain\Workflow\Services\SuiviPointageService;
use App\Enums\DoublonTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\UpdateInscriptionRequest;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\OffreEmploi;
use App\Models\Reference\Agence;
use App\Models\Reference\Commune;
use App\Models\Reference\Conseiller;
use App\Models\Reference\Diplome;
use App\Models\Reference\Handicap;
use App\Models\Reference\LienParente;
use App\Models\Reference\NiveauEtude;
use App\Models\Reference\OrigineStagiaire;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeEnseignement;
use App\Models\Reference\TypeHandicap;
use App\Models\Reference\TypePaiement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use App\Models\Workflow\InstanceParcours;
use App\Models\Contract\Contrat;
use App\Models\Document\Document;
use App\Models\Document\VersionDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InscriptionController extends Controller
{
    public function __construct(
        private readonly InscriptionStagiaireService $inscriptionService
    ) {}

    public function index()
    {
        $user = Auth::user();

        $instances = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'taches_ouvertes'])
            ->whereHas('taches_ouvertes', function ($q) {
                // Seulement les tâches de la corbeille CIP, assignables à ce rôle
                // Simplification : instances dont l'utilisateur est concerné
            })
            ->get();

        return Inertia::render('Inscriptions/Index', [
            'instances' => $instances,
        ]);
    }

    public function create()
    {
        return Inertia::render('Inscriptions/Create', $this->formData());
    }

    public function edit(InstanceParcours $inscription)
    {
        $this->assertCanEdit($inscription);
        $inscription->load($this->inscriptionRelations());
        $stage = $inscription->stage;
        $beneficiaire = $stage->beneficiaire;
        $contrat = $stage->contrats->sortByDesc('id')->first();

        return Inertia::render('Inscriptions/Create', array_merge($this->formData(), [
            'mode' => 'edit',
            'inscriptionId' => $inscription->id,
            'initialData' => [
                'beneficiaire' => $beneficiaire->toArray(),
                'stage' => $stage->toArray(),
                'contrat' => $contrat?->toArray(),
                'documents' => $stage->documents->toArray(),
                'tachesOuvertes' => $inscription->taches_ouvertes->toArray(),
                'evenements' => $inscription->evenements->toArray(),
            ],
        ]));
    }

    /**
     * Relations chargées pour l'affichage complet d'un dossier (show et edit), reprises
     * du périmètre legacy StagiaireViewService::prepareModifierViewData.
     *
     * @return array<int, string>
     */
    private function inscriptionRelations(): array
    {
        return [
            'stage.beneficiaire.communeResidence',
            'stage.beneficiaire.typePaiement',
            'stage.beneficiaire.niveauEtude',
            'stage.beneficiaire.handicap',
            'stage.beneficiaire.typeHandicap',
            'stage.beneficiaire.diplome',
            'stage.typeStage',
            'stage.sourceFinancement',
            'stage.programme',
            'stage.entreprise',
            'stage.agence',
            'stage.conseiller',
            'stage.offreEmploi',
            'stage.contrats',
            'stage.documents.versions',
            'stage.documents.typeDocument',
            'etapeCourante',
            'evenements.acteur',
            'evenements.etapeSource',
            'evenements.etapeCible',
            'taches_ouvertes',
            ...SuiviPointageService::relationsStage(),
        ];
    }

    public function update(UpdateInscriptionRequest $request, InstanceParcours $inscription)
    {
        $this->assertCanEdit($inscription);
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $inscription): void {
            $stage = $inscription->stage()->with('beneficiaire')->firstOrFail();
            $stage->beneficiaire->update($this->onlyKnown($validated['beneficiaire'] ?? [], [
                'numero_aej', 'nom', 'prenoms', 'date_naissance', 'lieu_naissance', 'sous_prefecture_naissance',
                'sexe', 'telephone_principal', 'telephone_secondaire', 'email', 'commune_residence_id',
                'sous_prefecture_residence', 'nature_piece_identite', 'numero_piece_identite', 'numero_cmu',
                'personne_urgence', 'lien_parente_id', 'contact_urgence_1', 'contact_urgence_2', 'niveau_etude_id',
                'diplome_id', 'autre_diplome', 'specialite', 'annee_diplome', 'etablissement_frequente',
                'type_enseignement_id', 'handicap_id', 'type_handicap_id', 'autre_handicap', 'type_paiement_id',
                'numero_tresor_money', 'numero_wave',
            ]));

            $stageData = $this->onlyKnown($validated['stage'] ?? [], [
                'agence_id', 'conseiller_id', 'origine_stagiaire_id', 'date_entree_portefeuille', 'type_stage_id',
                'source_financement_id', 'programme_id', 'service_affectation', 'intitule_poste', 'localite_stage',
                'commune_stage', 'sous_prefecture_stage', 'nom_encadreur', 'fonction_encadreur', 'contact_encadreur',
                'statut_stage', 'situation_stage', 'nbr_mois_capitaliser', 'date_demarrage_capitalisation',
                'date_demarrage_capitalisation_sans_financiere', 'observations', 'date_debut', 'date_fin_prevue',
                'offre_emploi_id', 'entreprise_id',
            ]);

            // Ne fait pas confiance à la date de fin envoyée par le client si une durée
            // (en mois) est fournie : recalcule côté serveur comme le legacy (getDateFin).
            $dureeMois = $validated['stage']['duree_mois'] ?? null;
            if ($dureeMois !== null && ! empty($stageData['date_debut'])) {
                $stageData['date_fin_prevue'] = DureeStageCalculator::dateFin($stageData['date_debut'], (float) $dureeMois)->format('Y-m-d');
            }

            $stage->update($stageData);
            $contrat = $stage->contrats()->latest('id')->first();
            if ($contrat) {
                $contrat->update($this->onlyKnown($validated['contrat'] ?? [], ['numero', 'date_debut', 'date_fin', 'prime_mensuelle']));
            }
            $this->storeDocuments($request->file('documents', []), $stage, $contrat, Auth::user());
        });

        return redirect()->route('inscriptions.show', $inscription)->with('success', 'Dossier stagiaire mis à jour.');
    }

    private function assertCanEdit(InstanceParcours $inscription): void
    {
        $user = Auth::user();
        abort_unless($user->hasAnyRole(['administrateur', 'chef_agence']), 403);
        abort_unless($inscription->stage()->exists(), 404);

        if ($user->hasRole('administrateur')) {
            return;
        }

        $agenceId = $inscription->stage()->value('agence_id');
        $perimetre = $user->perimetresAgences()->pluck('agences.id')->toArray();
        abort_unless(in_array($agenceId, $perimetre, true), 403);
    }

    private function onlyKnown(array $data, array $keys): array
    {
        return collect($data)->only($keys)->all();
    }

    private function storeDocuments(array $documents, $stage, ?Contrat $contrat, $auteur): void
    {
        if (! $contrat) return;
        foreach ($documents as $key => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) continue;
            $type = DB::table('types_document')->where('code', strtoupper($key))->first();
            $typeId = $type?->id ?? DB::table('types_document')->insertGetId([
                'code' => strtoupper($key), 'nom' => ucfirst(str_replace('_', ' ', $key)),
                'actif' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $path = $file->store('dossiers_stagiaires/'.$stage->beneficiaire_id);
            $document = Document::create([
                'type_document_id' => $typeId, 'beneficiaire_id' => $stage->beneficiaire_id,
                'stage_id' => $stage->id, 'contrat_id' => $contrat->id, 'cree_par_id' => $auteur->id,
                'nom' => $file->getClientOriginalName(), 'statut' => 'VALIDE', 'prive' => true,
            ]);
            VersionDocument::create([
                'document_id' => $document->id, 'depose_par_id' => $auteur->id, 'numero_version' => 1,
                'disque' => config('filesystems.default'), 'chemin' => $path,
                'nom_original' => $file->getClientOriginalName(), 'type_mime' => $file->getMimeType(),
                'taille_octets' => $file->getSize(), 'empreinte_sha256' => hash_file('sha256', $file->getRealPath()),
            ]);
        }
    }

    private function formData(): array
    {
        $user = Auth::user();
        return [
            'offres' => OffreEmploi::with(['entreprise', 'agence', 'typeStage', 'sourceFinancement'])->where('statut', 'PUBLIEE')->get(),
            'agences' => Agence::where('actif', true)->get(), 'communes' => Commune::where('actif', true)->get(),
            'typesStage' => TypeStage::where('actif', true)->get(), 'originesStagiaire' => OrigineStagiaire::where('actif', true)->get(),
            'liensParente' => LienParente::where('actif', true)->get(), 'niveauxEtude' => NiveauEtude::where('actif', true)->get(),
            'diplomes' => Diplome::where('actif', true)->get(), 'typesEnseignement' => TypeEnseignement::where('actif', true)->get(),
            'handicaps' => Handicap::where('actif', true)->get(), 'typesHandicap' => TypeHandicap::where('actif', true)->get(),
            'typesPaiement' => TypePaiement::where('actif', true)->get(), 'sourcesFinancement' => SourceFinancement::where('actif', true)->get(),
            'statutsStage' => DB::table('statuts_stage')->get(), 'situationsStage' => DB::table('situations_stage')->get(),
            'typesStructure' => TypeStructure::where('actif', true)->get(), 'conseillers' => Conseiller::with('agence')->where('actif', true)->orderBy('nom')->get(),
            'authUserAgenceIds' => $user->perimetresAgences()->pluck('agences.id')->toArray(),
        ];
    }

    /**
     * API : charge les informations d'un demandeur AEJ par matricule.
     * GET /api/stagiaires/demandeur/{matricule}
     */
    public function demandeur(string $matricule)
    {
        $beneficiaire = Beneficiaire::where('numero_aej', $matricule)->first();

        if (! $beneficiaire) {
            return response()->json(['message' => 'Demandeur non trouvé pour ce matricule.'], 404);
        }

        return response()->json([
            'data' => [
                'numero_aej' => $beneficiaire->numero_aej,
                'nom' => $beneficiaire->nom,
                'prenom' => $beneficiaire->prenoms,
                'date_naissance' => $beneficiaire->date_naissance?->format('Y-m-d'),
                'lieu_naissance' => $beneficiaire->lieu_naissance,
                'telephone' => $beneficiaire->telephone_principal,
                'sexe' => $beneficiaire->sexe,
                'type_piece_identite' => $beneficiaire->nature_piece_identite,
                'numero_identite' => $beneficiaire->numero_piece_identite,
                'specialite' => $beneficiaire->specialite,
                'etablissement_frequente' => $beneficiaire->etablissement_frequente,
                'type_enseignement' => $beneficiaire->type_enseignement_id,
                'handicap' => $beneficiaire->handicap_id,
                'commune_de_residence' => $beneficiaire->communeResidence?->nom,
                'personne_urgence' => $beneficiaire->personne_urgence,
                'prsurgent_tel1' => $beneficiaire->contact_urgence_1,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'beneficiaire' => 'required|array',
            'stage' => 'required|array',
            'contrat' => 'required|array',
            'documents' => 'nullable|array',
            'documents.*' => 'nullable|file|max:10240', // 10MB limit per file
        ]);

        $instance = $this->inscriptionService->inscrire(
            $validated['beneficiaire'],
            $validated['stage'],
            $validated['contrat'],
            $request->file('documents') ?? [],
            Auth::user()
        );

        return redirect()->route('inscriptions.index')->with('success', 'Stagiaire inscrit et dossier initié avec succès.');
    }

    public function show($id, DesseDoublonService $doublons, SuiviPointageService $suivi)
    {
        $instance = InstanceParcours::with($this->inscriptionRelations())->findOrFail($id);

        return Inertia::render('Inscriptions/Show', [
            'instance' => $instance,
            'corbeilleActuelle' => $suivi->corbeille($instance->corbeille_actuelle),
            'suiviPointages' => $suivi->pourStage($instance->stage),
            'doublons' => $this->doublonsPourStage($instance->stage, $doublons),
        ]);
    }

    /**
     * Types de doublons DESSE (pare-feu) dans lesquels ce stage est actuellement impliqué.
     * Réutilise DesseDoublonService pour ne pas dupliquer la logique de détection.
     *
     * @return array<int, array{type: string, label: string, cle: string}>
     */
    private function doublonsPourStage($stage, DesseDoublonService $service): array
    {
        $duplicateKeysByType = collect(DoublonTypeEnum::cases())
            ->mapWithKeys(fn (DoublonTypeEnum $type) => [$type->value => $service->computeDuplicateKeys($type)])
            ->all();

        return collect($service->matchingTypesForStage($stage, $duplicateKeysByType))
            ->map(fn ($cle, $type) => [
                'type' => $type,
                'label' => DoublonTypeEnum::from($type)->label(),
                'cle' => $cle,
            ])
            ->values()
            ->all();
    }
}
