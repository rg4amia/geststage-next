<?php

namespace App\Domain\Supervision\Services;

use App\Models\Document\Document;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Pièces justificatives d'un stage, pour l'écran de supervision régionale
 * (portage de l'écran legacy `Telechargement_Pieces` / `Telecharger_Fichier`).
 *
 * Deux sources, dans cet ordre :
 *  1. les documents déposés dans Gestage Next (`documents` + `versions_documents`) ;
 *  2. à défaut, le snapshot de migration `conservations_contrats_pae`, seul endroit où
 *     subsistent les chemins des fichiers legacy — la reprise n'a pas recréé de documents.
 *
 * Le legacy postait le nom du fichier depuis le navigateur puis faisait
 * `storage_path('app/public/'.$request->file_cni)` : n'importe quel chemin envoyé par le
 * client était servi. Ici le client n'envoie qu'un stage et une clé de pièce ; le chemin
 * est résolu côté serveur puis vérifié comme dans PaiementAcController::cheminPieceAbsolu
 * (realpath + confinement sous la racine du disque + is_file).
 */
class PiecesStageService
{
    /**
     * Clés de pièces exposées, dans l'ordre d'affichage, avec leur libellé et la ou les
     * colonnes legacy correspondantes (la première renseignée gagne).
     *
     * Les codes de type de document reprennent ceux déjà déposés par le CIP et le DMG
     * (cf. RejetDmgService::TYPES_DOCUMENT), pour qu'une pièce re-déposée dans Gestage Next
     * remplace bien la pièce legacy au lieu de s'ajouter à côté.
     *
     * Les attestations de présence mensuelles de l'écran legacy ne figurent pas ici :
     * elles étaient lues sur `file_janvier`…`file_decembre`, colonnes absentes de
     * `contrats_pae` et donc du snapshot de reprise. Le « fichier groupé » legacy
     * (`trans_attestation_grp`) n'est pas un fichier mais un drapeau de transmission :
     * il est remplacé ici par l'archive ZIP construite à la volée par archiveZip().
     *
     * @var array<string, array{libelle: string, colonnes: array<int, string>, type_document: ?string}>
     */
    private const PIECES = [
        'rib' => ['libelle' => 'Fiche RIB', 'colonnes' => ['file_rib'], 'type_document' => 'FICHIER_RIB'],
        'tresor' => ['libelle' => 'Fiche inscription Trésor', 'colonnes' => ['file_fiche_yup'], 'type_document' => 'TRESOR_MONEY'],
        'cni' => ['libelle' => "Pièce d'identité", 'colonnes' => ['file_cni'], 'type_document' => 'PIECE_IDENTITE'],
        'contrat' => ['libelle' => 'Contrat de stage', 'colonnes' => [], 'type_document' => 'CONTRAT'],
        'fiche_aej' => ['libelle' => 'Fiche AEJ', 'colonnes' => ['file_fiche_aej'], 'type_document' => 'FICHE_AEJ'],
        'diplome' => ['libelle' => 'Diplôme', 'colonnes' => ['file_diplome'], 'type_document' => 'FICHIER_DIPLOME'],
        'admissibilite' => ['libelle' => "Attestation d'admissibilité", 'colonnes' => ['file_attestation'], 'type_document' => 'FICHIER_ATTESTATION'],
        'certificat_frequentation' => ['libelle' => 'Certificat de fréquentation', 'colonnes' => ['file_certificat_frequentation'], 'type_document' => 'FICHIER_CERTIFICAT_FREQUENTATION'],
        'demarrage' => ['libelle' => 'Attestation de démarrage', 'colonnes' => ['attest_demarrage'], 'type_document' => null],
        'presence' => ['libelle' => 'Attestation de présence', 'colonnes' => ['attest_presence'], 'type_document' => null],
    ];

    /**
     * @return array<int, string>
     */
    public static function clesValides(): array
    {
        return array_keys(self::PIECES);
    }

    /**
     * Inventaire des pièces d'un stage : libellé, disponibilité et nom du fichier.
     * Aucun chemin absolu n'est renvoyé au client.
     *
     * @return array<int, array{cle: string, libelle: string, disponible: bool, nom_fichier: ?string}>
     */
    public function inventaire(int $stageId): array
    {
        $snapshot = $this->donneesConservees($stageId);
        $documents = $this->documentsParType($stageId);

        $pieces = [];

        foreach (self::PIECES as $cle => $definition) {
            $chemin = $this->cheminRelatif($cle, $snapshot, $documents);

            $pieces[] = [
                'cle' => $cle,
                'libelle' => $definition['libelle'],
                'disponible' => $chemin !== null,
                'nom_fichier' => $chemin !== null ? basename($chemin['chemin']) : null,
            ];
        }

        return $pieces;
    }

    /**
     * Chemin absolu vérifié de la pièce demandée, ou null si elle n'existe pas
     * (référence absente, fichier disparu, ou chemin sortant de la racine du disque).
     */
    public function cheminAbsolu(int $stageId, string $cle): ?string
    {
        if (! array_key_exists($cle, self::PIECES)) {
            return null;
        }

        $reference = $this->cheminRelatif($cle, $this->donneesConservees($stageId), $this->documentsParType($stageId));

        if ($reference === null) {
            return null;
        }

        return $this->resoudre($reference['disque'], $reference['chemin']);
    }

    /**
     * Construit une archive ZIP de toutes les pièces disponibles d'un stage, équivalent du
     * « fichier groupé » legacy, et renvoie son chemin temporaire (à supprimer après envoi).
     */
    public function archiveZip(int $stageId, string $prefixeNom): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $snapshot = $this->donneesConservees($stageId);
        $documents = $this->documentsParType($stageId);
        $fichiers = [];

        foreach (array_keys(self::PIECES) as $cle) {
            $reference = $this->cheminRelatif($cle, $snapshot, $documents);

            if ($reference === null) {
                continue;
            }

            $absolu = $this->resoudre($reference['disque'], $reference['chemin']);

            if ($absolu !== null) {
                $fichiers[$cle] = $absolu;
            }
        }

        if ($fichiers === []) {
            return null;
        }

        $archive = tempnam(sys_get_temp_dir(), 'pieces_').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($fichiers as $cle => $absolu) {
            $zip->addFile($absolu, $prefixeNom.'_'.$cle.'.'.pathinfo($absolu, PATHINFO_EXTENSION));
        }

        $zip->close();

        return $archive;
    }

    /**
     * Référence (disque + chemin relatif) d'une pièce : document Gestage Next si le stage
     * en porte un, sinon chemin legacy du snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, array{disque: string, chemin: string}>  $documents
     * @return array{disque: string, chemin: string}|null
     */
    private function cheminRelatif(string $cle, array $snapshot, array $documents): ?array
    {
        $definition = self::PIECES[$cle];
        $typeDocument = $definition['type_document'];

        if ($typeDocument !== null && isset($documents[$typeDocument])) {
            return $documents[$typeDocument];
        }

        $chemin = $cle === 'contrat'
            ? $this->cheminContrat($snapshot)
            : $this->premiereColonneRenseignee($snapshot, $definition['colonnes']);

        return $chemin === null ? null : ['disque' => 'legacy_pieces', 'chemin' => $chemin];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, string>  $colonnes
     */
    private function premiereColonneRenseignee(array $snapshot, array $colonnes): ?string
    {
        foreach ($colonnes as $colonne) {
            $valeur = $snapshot[$colonne] ?? null;
            $valeur = is_string($valeur) ? trim($valeur) : '';

            // Le legacy écrivait '0' au lieu de NULL quand aucun fichier n'était déposé.
            if ($valeur !== '' && $valeur !== '0') {
                return $valeur;
            }
        }

        return null;
    }

    /**
     * Choix legacy entre contrat initial et avenant : le contrat initial est retenu sauf
     * quand le stagiaire est en renouvellement sans dates de renouvellement complètes.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function cheminContrat(array $snapshot): ?string
    {
        $contrat = $this->premiereColonneRenseignee($snapshot, ['file_contrat']);
        $avenant = $this->premiereColonneRenseignee($snapshot, ['filecontratavenant']);

        $renouvellement = (int) ($snapshot['etatrenouvellement_id'] ?? 0);
        $renouvellementComplet = $renouvellement === 1
            && ! empty($snapshot['date_debut_renouv']) && ! empty($snapshot['date_fin_renouv']);

        if ($renouvellement === 0 || $renouvellementComplet) {
            return $contrat;
        }

        return $avenant ?? $contrat;
    }

    /**
     * Dernière version de chaque document déposé sur le stage, indexée par code de type.
     *
     * @return array<string, array{disque: string, chemin: string}>
     */
    private function documentsParType(int $stageId): array
    {
        return Document::query()
            ->join('types_document', 'types_document.id', '=', 'documents.type_document_id')
            ->join('versions_documents', 'versions_documents.document_id', '=', 'documents.id')
            ->where('documents.stage_id', $stageId)
            ->orderBy('versions_documents.numero_version')
            ->get([
                'types_document.code as code',
                'versions_documents.disque as disque',
                'versions_documents.chemin as chemin',
            ])
            // La dernière version l'emporte : le tri croissant fait que chaque itération
            // écrase la précédente pour un même type de document.
            ->mapWithKeys(fn ($ligne): array => [
                $ligne->code => ['disque' => $ligne->disque, 'chemin' => $ligne->chemin],
            ])
            ->all();
    }

    /**
     * Snapshot de migration du contrat legacy rattaché au stage.
     *
     * @return array<string, mixed>
     */
    private function donneesConservees(int $stageId): array
    {
        $conserve = DB::table('conservations_contrats_pae')
            ->where('stage_id', $stageId)
            ->orderByDesc('id')
            ->first();

        return $conserve ? (json_decode((string) $conserve->donnees_originales, true) ?: []) : [];
    }

    /**
     * Chemin absolu confiné à la racine du disque, ou null si le fichier est hors limites
     * ou absent. C'est le seul point où un chemin est converti en accès disque.
     */
    private function resoudre(string $disque, string $cheminRelatif): ?string
    {
        $racine = realpath((string) config("filesystems.disks.{$disque}.root", ''));

        if ($racine === false) {
            return null;
        }

        $fichier = realpath($racine.DIRECTORY_SEPARATOR.$cheminRelatif);

        if ($fichier === false
            || ! str_starts_with($fichier, $racine.DIRECTORY_SEPARATOR)
            || ! is_file($fichier)) {
            return null;
        }

        return $fichier;
    }
}
