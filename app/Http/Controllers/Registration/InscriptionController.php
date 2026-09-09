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
use App\Models\Internship\Stage;
use App\Models\Workflow\DesseDoublonDecision;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class InscriptionController extends Controller
{
    public function __construct(
        private readonly InscriptionStagiaireService $inscriptionService
    ) {}

    public function index()
    {
        $agencesAutorisees = $this->agencesAutorisees();

        // Sans ce filtre, la liste exposait les dossiers de toutes les agences aux
        // profils bornés à un périmètre (CIP, Chef d'Agence).
        $instances = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'etapeCourante', 'taches_ouvertes'])
            ->whereHas('taches_ouvertes')
            ->whereHas('stage', function ($q) use ($agencesAutorisees): void {
                if ($agencesAutorisees !== null) {
                    $q->whereIn('agence_id', $agencesAutorisees);
                }
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
            'stage.documents.versions.deposePar',
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

    /**
     * Rôles dont la visibilité est bornée à leur périmètre d'agence. Les profils centraux
     * (DESSE, DAICG, DMG, CB, AC...) consultent les dossiers de tout le réseau.
     */
    private const ROLES_PERIMETRE_AGENCE = ['cip', 'chef_agence'];

    /**
     * Garde-fou de consultation : authentification, permission métier, existence du stage
     * et périmètre d'agence. Appliqué à la fiche détail comme au téléchargement des pièces.
     */
    private function assertCanView(InstanceParcours $inscription): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->can('voir_beneficiaires'), 403);
        abort_unless($inscription->stage()->exists(), 404);

        $agencesAutorisees = $this->agencesAutorisees();

        if ($agencesAutorisees !== null
            && ! in_array((int) $inscription->stage()->value('agence_id'), $agencesAutorisees, true)) {
            abort(403, "Ce dossier ne relève pas de votre périmètre d'agence.");
        }
    }

    /**
     * Identifiants d'agence visibles par l'utilisateur, ou null quand la consultation
     * est nationale (administrateur et profils centraux).
     *
     * @return array<int, int>|null
     */
    private function agencesAutorisees(): ?array
    {
        $user = Auth::user();

        if ($user === null || ! $user->hasAnyRole(self::ROLES_PERIMETRE_AGENCE)) {
            return null;
        }

        $agenceIds = $user->perimetresAgences()->pluck('agences.id')->all();

        return $agenceIds === [] ? null : $agenceIds;
    }

    /**
     * Droits d'action calculés côté serveur et consommés par la fiche : la vue n'a plus à
     * deviner le rôle de l'utilisateur pour décider quels boutons afficher. Chaque droit
     * reste par ailleurs revérifié par la route qui porte l'action.
     *
     * @return array<string, bool>
     */
    private function droits(InstanceParcours $inscription): array
    {
        $user = Auth::user();

        return [
            'peut_modifier' => $this->peutModifier($inscription),
            'peut_telecharger_documents' => (bool) $user?->can('voir_beneficiaires'),
            'peut_voir_pointages' => (bool) $user?->can('voir_pointages'),
            'peut_voir_paiements' => (bool) $user?->can('voir_paiements_dmg'),
            'peut_traiter_doublons' => (bool) $user?->can('valider_desse'),
            'peut_valider_etape' => $this->peutValiderEtapeCourante($inscription),
        ];
    }

    /**
     * Reprend, sans lever d'exception, la règle de assertCanEdit() : rôle habilité puis
     * périmètre d'agence.
     */
    private function peutModifier(InstanceParcours $inscription): bool
    {
        $user = Auth::user();

        if ($user === null || ! $user->hasAnyRole(['administrateur', 'chef_agence'])) {
            return false;
        }

        if ($user->hasRole('administrateur')) {
            return true;
        }

        $perimetre = $user->perimetresAgences()->pluck('agences.id')->all();

        return in_array((int) $inscription->stage()->value('agence_id'), $perimetre, true);
    }

    /**
     * L'utilisateur porte le rôle responsable de l'étape courante et une tâche y est
     * ouverte : la validation de l'étape elle-même reste opérée par le module workflow.
     */
    private function peutValiderEtapeCourante(InstanceParcours $inscription): bool
    {
        $user = Auth::user();
        $etape = $inscription->etapeCourante;

        if ($user === null || $etape === null || $inscription->taches_ouvertes->isEmpty()) {
            return false;
        }

        return $user->roles->pluck('id')->contains($etape->role_responsable_id);
    }

    /**
     * Métadonnées GED de la fiche : type, nom d'origine, version courante, dépôt (auteur,
     * date), taille, MIME et statut. Les octets ne transitent jamais par Inertia, seul le
     * lien vers la route de téléchargement sécurisée est exposé.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentsPourAffichage(InstanceParcours $inscription): array
    {
        $stage = $inscription->stage;

        if ($stage === null) {
            return [];
        }

        return $stage->documents
            ->map(function (Document $document) use ($inscription): array {
                // Même sélection que downloadDocument() : dernière version déposée.
                $version = $document->versions
                    ->sortBy([['numero_version', 'desc'], ['id', 'desc']])
                    ->first();

                return [
                    'id' => $document->id,
                    'type' => $document->typeDocument?->nom,
                    'type_code' => $document->typeDocument?->code,
                    'nom_original' => $version?->nom_original ?: $document->nom,
                    'version' => $version?->numero_version,
                    'date_depot' => $version?->created_at?->toDateTimeString(),
                    'taille_octets' => $version?->taille_octets,
                    'type_mime' => $version?->type_mime,
                    'statut' => $document->statut,
                    'auteur' => $version?->deposePar?->nom,
                    'telechargeable' => $version !== null,
                    'url' => route('inscriptions.documents.download', [
                        'inscription' => $inscription->id,
                        'document' => $document->id,
                    ]),
                ];
            })
            ->values()
            ->all();
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

    /**
     * Fiche détail d'un dossier stagiaire (legacy : `/traitement/{id_contrat}/detail`).
     * Lecture seule : toute mutation passe par les routes dédiées du module concerné
     * (édition, validation, pointage, paiement), jamais depuis cet écran.
     */
    public function show(InstanceParcours $inscription, DesseDoublonService $doublons, SuiviPointageService $suivi)
    {
        $this->assertCanView($inscription);

        $inscription->load($this->inscriptionRelations());
        $stage = $inscription->stage;

        return Inertia::render('Inscriptions/Show', [
            'instance' => $inscription,
            'corbeilleActuelle' => $suivi->corbeille($inscription->corbeille_actuelle),
            'suiviPointages' => $suivi->pourStage($stage),
            'documents' => $this->documentsPourAffichage($inscription),
            'doublons' => $this->doublonsPourStage($stage, $doublons, $inscription),
            'droits' => $this->droits($inscription),
        ]);
    }

    public function downloadDocument(InstanceParcours $inscription, Document $document)
    {
        // Le téléchargement rejoue les mêmes contrôles que la consultation : masquer le
        // bouton côté React ne protège rien, l'URL du document est devinable.
        $this->assertCanView($inscription);
        abort_unless((int) $document->stage_id === (int) $inscription->stage_id, 404);

        $version = $document->versions()
            ->latest('numero_version')
            ->latest('id')
            ->firstOrFail();
        $disk = $version->disque ?: config('filesystems.default');

        abort_unless(Storage::disk($disk)->exists($version->chemin), 404);

        return Storage::disk($disk)->download(
            $version->chemin,
            $version->nom_original ?: $document->nom,
            ['Content-Type' => $version->type_mime ?: 'application/octet-stream'],
        );
    }

    /**
     * Types de doublons DESSE (pare-feu) dans lesquels ce stage est actuellement impliqué.
     * Réutilise DesseDoublonService pour ne pas dupliquer la logique de détection : la vue
     * n'affiche que ce que le service confirme, elle ne recalcule aucune clé.
     *
     * Le pare-feu ne bloque plus le dossier dès lors que la DESSE a tranché le doublon
     * (DesseDoublonDecision), même règle que applyDuplicateExclusionFilter().
     *
     * @return array<int, array<string, mixed>>
     */
    private function doublonsPourStage(?Stage $stage, DesseDoublonService $service, InstanceParcours $inscription): array
    {
        if ($stage === null) {
            return [];
        }

        $duplicateKeysByType = collect(DoublonTypeEnum::cases())
            ->mapWithKeys(fn (DoublonTypeEnum $type) => [$type->value => $service->computeDuplicateKeys($type)])
            ->all();

        $decisions = DesseDoublonDecision::where('instance_parcours_id', $inscription->id)
            ->get()
            ->keyBy('type_doublon');

        $peutTraiter = (bool) Auth::user()?->can('valider_desse');

        return collect($service->matchingTypesForStage($stage, $duplicateKeysByType))
            ->map(function ($cle, $type) use ($decisions, $peutTraiter) {
                $enum = DoublonTypeEnum::from($type);
                $decision = $decisions->get($type);
                $bloquant = $decision === null;

                return [
                    'type' => $type,
                    'label' => $enum->label(),
                    'cle' => $cle,
                    'bloquant' => $bloquant,
                    'pare_feu' => $bloquant ? 'ACTIF' : 'LEVE',
                    'message' => $bloquant
                        ? sprintf(
                            'Ce dossier partage « %s » avec au moins un autre dossier. Le pare-feu DESSE le retient hors des files de paiement tant que la DESSE n\'a pas tranché.',
                            $enum->label(),
                        )
                        : sprintf(
                            'Doublon « %s » tranché par la DESSE le %s (%s). Le dossier poursuit son parcours.',
                            $enum->label(),
                            $decision->decide_le?->format('d/m/Y') ?? '-',
                            $decision->decision,
                        ),
                    'decision' => $decision?->decision,
                    'decide_le' => $decision?->decide_le?->toDateTimeString(),
                    'motif' => $decision?->motif,
                    // Lien vers le groupe correspondant dans l'écran DESSE, seulement pour
                    // qui a le droit d'y statuer.
                    'lien' => $peutTraiter
                        ? route('desse.stagiaires.index', [
                            'tab' => 'doublons',
                            'type_doublon' => $type,
                            'doublon_cle' => $cle,
                        ])
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
