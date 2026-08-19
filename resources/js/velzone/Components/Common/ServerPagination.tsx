import { router } from '@inertiajs/react';
import React from 'react';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

/** Lien de pagination tel que Laravel le sérialise (LengthAwarePaginator). */
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** Sous-ensemble du LengthAwarePaginator suffisant pour ce composant. */
export interface PaginationMeta {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

export interface ServerPaginationProps {
    /** Objet méta renvoyé par Laravel (ou extrait de la réponse Inertia). */
    pagination: PaginationMeta;

    /**
     * Libellé affiché dans la ligne d'information, ex. "demandeurs", "entreprises".
     * Défaut : "enregistrements".
     */
    itemLabel?: string;

    /**
     * Callback appelé quand l'utilisateur change de page.
     * - Si fourni : Kiro appelle onPageChange(numéro) ; c'est la page qui gère la navigation.
     * - Si absent  : Kiro navigue directement vers link.url avec Inertia
     *               (preserveState + preserveScroll).
     */
    onPageChange?: (page: number) => void;

    /** Classes CSS supplémentaires sur le conteneur. */
    className?: string;

    /**
     * Nombre de pages affichées autour de la page courante avant l'ellipse.
     * Défaut : 1 (affiche 1 page autour, soit le pattern «  1  2  …  N-1  N »).
     */
    siblingCount?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Utilitaire : calcul des numéros de pages à afficher
// ─────────────────────────────────────────────────────────────────────────────

type PageItem = number | '...';

function buildPageRange(current: number, last: number, siblings: number): PageItem[] {
    // Toujours afficher la 1re et la dernière page
    // + siblingCount pages autour de la page courante
    const delta = siblings;

    const left = Math.max(2, current - delta);
    const right = Math.min(last - 1, current + delta);

    const pages: PageItem[] = [];

    // Première page
    pages.push(1);

    // Ellipse gauche
    if (left > 2) {
        pages.push('...');
    }

    // Pages du milieu
    for (let i = left; i <= right; i++) {
        pages.push(i);
    }

    // Ellipse droite
    if (right < last - 1) {
        pages.push('...');
    }

    // Dernière page (seulement si > 1)
    if (last > 1) {
        pages.push(last);
    }

    return pages;
}

// ─────────────────────────────────────────────────────────────────────────────
// Composant principal
// ─────────────────────────────────────────────────────────────────────────────

const ServerPagination: React.FC<ServerPaginationProps> = ({
    pagination,
    itemLabel = 'enregistrements',
    onPageChange,
    className = '',
    siblingCount = 1,
}) => {
    const { current_page, last_page, from, to, total, links } = pagination;

    // Récupère l'URL d'une page donnée en cherchant dans les links Laravel
    const getUrlForPage = (page: number): string | null => {
        const match = links.find((l) => {
            if (!l.url) {
return false;
}

            const params = new URL(l.url, window.location.origin).searchParams;

            return Number(params.get('page')) === page;
        });

        // Si on ne trouve pas (page 1 sans ?page=), on prend l'URL sans page
        if (!match) {
            const prevLink = links.find((l) => l.url && l.label.includes('Previous'));

            if (prevLink?.url) {
                const base = new URL(prevLink.url, window.location.origin);
                base.searchParams.delete('page');

                if (page > 1) {
base.searchParams.set('page', String(page));
}

                return base.toString();
            }

            const nextLink = links.find((l) => l.url && l.label.includes('Next'));

            if (nextLink?.url) {
                const base = new URL(nextLink.url, window.location.origin);
                base.searchParams.set('page', String(page));

                return base.toString();
            }

            return null;
        }

        return match.url;
    };

    const navigate = (page: number) => {
        if (page < 1 || page > last_page) {
return;
}

        if (onPageChange) {
            onPageChange(page);

            return;
        }

        const url = getUrlForPage(page);

        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
        }
    };

    const pages = buildPageRange(current_page, last_page, siblingCount);

    // Formatage du total avec séparateur de milliers
    const fmt = (n: number) => n.toLocaleString('fr-FR');

    if (total === 0) {
return null;
}

    return (
        <div className={`d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2 mt-3 pt-3 border-top ${className}`}>
            {/* ── Ligne d'information ── */}
            <div className="text-muted fs-13">
                Affichage de{' '}
                <span className="fw-semibold text-body">{fmt(from ?? 0)}</span>
                {' '}à{' '}
                <span className="fw-semibold text-body">{fmt(to ?? 0)}</span>
                {' '}sur{' '}
                <span className="fw-semibold text-body">{fmt(total)}</span>
                {' '}{itemLabel}
                <span className="mx-2 text-muted opacity-50">—</span>
                Page{' '}
                <span className="fw-semibold text-body">{fmt(current_page)}</span>
                {' '}sur{' '}
                <span className="fw-semibold text-body">{fmt(last_page)}</span>
            </div>

            {/* ── Boutons de navigation ── */}
            {last_page > 1 && (
                <ul className="pagination pagination-separated mb-0 flex-shrink-0">
                    {/* Précédent */}
                    <li className={`page-item${current_page <= 1 ? ' disabled' : ''}`}>
                        <button
                            type="button"
                            className="page-link"
                            onClick={() => navigate(current_page - 1)}
                            disabled={current_page <= 1}
                            aria-label="Page précédente"
                        >
                            <i className="ri-arrow-left-s-line align-middle" />
                        </button>
                    </li>

                    {/* Numéros de pages */}
                    {pages.map((page, idx) =>
                        page === '...' ? (
                            <li key={`ellipsis-${idx}`} className="page-item disabled">
                                <span className="page-link px-2">…</span>
                            </li>
                        ) : (
                            <li
                                key={page}
                                className={`page-item${page === current_page ? ' active' : ''}`}
                            >
                                <button
                                    type="button"
                                    className="page-link"
                                    onClick={() => navigate(page as number)}
                                    aria-current={page === current_page ? 'page' : undefined}
                                    aria-label={`Page ${page}`}
                                >
                                    {page}
                                </button>
                            </li>
                        )
                    )}

                    {/* Suivant */}
                    <li className={`page-item${current_page >= last_page ? ' disabled' : ''}`}>
                        <button
                            type="button"
                            className="page-link"
                            onClick={() => navigate(current_page + 1)}
                            disabled={current_page >= last_page}
                            aria-label="Page suivante"
                        >
                            <i className="ri-arrow-right-s-line align-middle" />
                        </button>
                    </li>
                </ul>
            )}
        </div>
    );
};

export default ServerPagination;

// ─────────────────────────────────────────────────────────────────────────────
// Helper : normalise un objet paginé Laravel (format Inertia ou API)
// en PaginationMeta utilisable par le composant.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Laravel renvoie parfois `meta.current_page` (resource collection) ou
 * directement `current_page` à la racine (LengthAwarePaginator simple).
 * Cette fonction accepte les deux formes.
 */
export function normalizePagination(paginatedData: any): PaginationMeta {
    const src = paginatedData?.meta ?? paginatedData;

    return {
        current_page: src.current_page ?? 1,
        last_page: src.last_page ?? 1,
        from: src.from ?? null,
        to: src.to ?? null,
        total: src.total ?? 0,
        links: src.links ?? [],
    };
}
