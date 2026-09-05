/**
 * Socle commun aux onglets de la page DMG / Paiements : types partagés, formats d'affichage
 * et lecture des endpoints JSON de la chaîne de paiement.
 */

export interface RefItem {
    id: number;
    nom: string;
    raison_sociale?: string;
}

/** Ligne du tableau « Ajournés » (un stagiaire bloqué). */
export interface AjourneRow {
    paiement_id: number;
    statut: string;
    montant: number | string;
    ajourne_le: string | null;
    stage_id: number;
    date_debut: string | null;
    date_fin: string | null;
    nom: string;
    prenoms: string;
    numero_aej: string | null;
    date_naissance: string | null;
    tresor_pay: string | null;
    entreprise: string | null;
    agence: string | null;
    source_financement: string | null;
    type_stage: string | null;
    periode: string | null;
    dossier: string | null;
    multi_dossier: string | null;
    motif: string | null;
}

/**
 * Dossier de paiement tel que rendu par `CorbeilleService::dossierRows()` (props différées de
 * la page). Sous-ensemble suffisant pour les onglets OP / bordereaux.
 */
export interface DossierEligibleRow {
    id: number;
    numero: string;
    agence: { nom: string };
    source_financement: { libelle: string };
    nombre_stagiaires: number;
    montant_total: number;
    statut: string;
    date_creation: string;
}

/** Ligne du tableau « Ordres de paiement ». */
export interface OpRow {
    id: number;
    numero: string;
    libelle: string | null;
    statut: string;
    montant_total: number;
    montant_etat_financement: number | null;
    source_financement: string | null;
    bordereau: string | null;
    dossiers_count: number;
    stagiaires_count: number;
    agences: string | null;
    created_at: string | null;
}

/** Dossier rattaché à un OP, ou OP rattaché à un bordereau (dépliement d'une ligne). */
export interface DossierOpRow {
    id: number;
    numero: string;
    libelle?: string | null;
    nature?: string;
    statut: string;
    agence?: string | null;
    source_financement?: string | null;
    nombre_stagiaires?: number;
    dossiers_count?: number;
    montant_total: number;
}

/** Ligne du tableau « Bordereaux » (modèle brut servi en prop différée). */
export interface BordereauRow {
    id: number;
    numero: string;
    statut: string;
    montant_total: number | string;
    ordres_paiement_count?: number;
    created_at?: string | null;
}

export interface ReponsePaginee<T> {
    data: T[];
    total: number;
    page: number;
    per_page: number;
    last_page: number;
}

export interface OptionDossier {
    value: string;
    label: string;
}

export const formatMontant = (montant?: number | string | null): string =>
    `${Number(montant || 0).toLocaleString('fr-FR')} FCFA`;

export const formatDate = (valeur?: string | null): string => {
    if (!valeur) {
return '-';
}

    const date = new Date(valeur);

    return Number.isNaN(date.getTime()) ? valeur : date.toLocaleDateString('fr-FR');
};

export const getStatutBadge = (statut?: string): string => {
    switch ((statut || '').toUpperCase()) {
        case 'VALIDE':
        case 'VALIDE_CB':
        case 'TRAITE':
            return 'success';
        case 'AJOURNE':
        case 'AJOURNE_DMG':
        case 'AJOURNE_CB':
        case 'ANNULE':
            return 'danger';
        case 'TRANSMIS_CB':
        case 'TRANSMIS_AC':
        case 'BROUILLON':
            return 'warning';
        case 'A_TRAITER':
        case 'EN_COURS':
        case 'EN_DOSSIER':
        case 'EN_OP':
        case 'EN_BORDEREAU':
            return 'info';
        default:
            return 'secondary';
    }
};

export const construireUrl = (
    base: string,
    params: Record<string, string | number | null | undefined> = {},
): string => {
    const recherche = new URLSearchParams();
    Object.entries(params).forEach(([cle, valeur]) => {
        if (valeur !== undefined && valeur !== null && `${valeur}` !== '') {
            recherche.set(cle, `${valeur}`);
        }
    });
    const chaine = recherche.toString();

    return chaine ? `${base}?${chaine}` : base;
};

/**
 * GET JSON. Lève sur réponse non 2xx : l'appelant décide quoi montrer, plutôt que de laisser
 * l'écran afficher une liste vide sur une erreur 403 ou 500 — indiscernable d'un mois sans données.
 */
export async function getJson<T>(
    url: string,
    params: Record<string, string | number | null | undefined> = {},
    signal?: AbortSignal,
): Promise<T> {
    const reponse = await fetch(construireUrl(url, params), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal,
    });

    if (!reponse.ok) {
        throw new Error(`Chargement impossible (${reponse.status}).`);
    }

    return (await reponse.json()) as T;
}
