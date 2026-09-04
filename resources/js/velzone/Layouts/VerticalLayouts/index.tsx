import { useState } from 'react';
import { Link, useLocation } from '@/velzone/inertia-router';
import { businessMenuSections, menuItems } from '../LayoutMenuData';
import type { MenuHeaderItem, MenuLinkItem } from '../LayoutMenuData';

interface VerticalMenuItem {
    id?: string;
    label: string;
    isHeader?: boolean;
    icon?: string;
    link?: string;
    actor?: string;
    subItems?: MenuLinkItem[];
}

const isMenuLink = (
    item: MenuHeaderItem | MenuLinkItem,
): item is MenuLinkItem => !('isHeader' in item);

const linkItems = menuItems.filter(isMenuLink);
const groupedItemIds = new Set(
    businessMenuSections.flatMap((section) => section.itemIds),
);

const navData: VerticalMenuItem[] = [
    { label: 'Navigation', isHeader: true },
    ...linkItems.filter((item) => !groupedItemIds.has(item.id)),
    ...businessMenuSections.map((section) => ({
        id: section.id,
        label: section.label,
        icon: section.icon,
        subItems: section.itemIds
            .map((itemId) => linkItems.find((item) => item.id === itemId))
            .filter((item): item is MenuLinkItem => Boolean(item)),
    })),
];

const normalizePath = (value: string): string => {
    const path = value.split(/[?#]/, 1)[0] || '/';

    return path === '/' ? path : path.replace(/\/+$/, '');
};

const findActiveItemId = (pathname: string): string | null => {
    const currentPath = normalizePath(pathname);

    return (
        linkItems
            .filter(({ link }) => {
                const menuPath = normalizePath(link);

                return (
                    currentPath === menuPath ||
                    currentPath.startsWith(`${menuPath}/`)
                );
            })
            .sort((first, second) => second.link.length - first.link.length)[0]
            ?.id ?? null
    );
};

const VerticalLayout = () => {
    const { pathname } = useLocation();
    const activeItemId = findActiveItemId(pathname);
    const activeSectionId =
        businessMenuSections.find((section) =>
            section.itemIds.includes(activeItemId ?? ''),
        )?.id ?? null;
    const [menuState, setMenuState] = useState<{
        pathname: string;
        openSectionId: string | null;
    }>({ pathname, openSectionId: activeSectionId });
    const openSectionId =
        menuState.pathname === pathname
            ? menuState.openSectionId
            : activeSectionId;

    return (
        <>
            {navData.map((item, index) => {
                if (item.isHeader) {
                    return (
                        <li
                            className="menu-title"
                            key={`${item.label}-${index}`}
                        >
                            <span>{item.label}</span>
                        </li>
                    );
                }

                if (item.subItems?.length) {
                    const isOpen = openSectionId === item.id;
                    const hasActiveChild = item.id === activeSectionId;
                    const collapseId = `${item.id}-menu`;

                    return (
                        <li
                            className={`nav-item${hasActiveChild ? ' active' : ''}`}
                            key={item.id}
                        >
                            <button
                                type="button"
                                className={`nav-link menu-link w-100 border-0 bg-transparent text-start${hasActiveChild ? ' active' : ''}`}
                                aria-expanded={isOpen}
                                aria-controls={collapseId}
                                onClick={() =>
                                    setMenuState({
                                        pathname,
                                        openSectionId:
                                            openSectionId === item.id
                                                ? null
                                                : (item.id ?? null),
                                    })
                                }
                            >
                                {item.icon ? <i className={item.icon} /> : null}
                                <span>{item.label}</span>
                                <i
                                    className={
                                        isOpen
                                            ? 'ri-arrow-up-s-line ms-auto'
                                            : 'ri-arrow-down-s-line ms-auto'
                                    }
                                    aria-hidden="true"
                                />
                            </button>

                            {isOpen ? (
                                <div
                                    className="menu-dropdown show"
                                    id={collapseId}
                                >
                                    <ul className="nav nav-sm flex-column">
                                        {item.subItems.map((subItem) => {
                                            const childActive =
                                                activeItemId === subItem.id;

                                            return (
                                                <li
                                                    className="nav-item"
                                                    key={subItem.id}
                                                >
                                                    <Link
                                                        className={`nav-link${childActive ? ' active' : ''}`}
                                                        to={subItem.link}
                                                        aria-current={
                                                            childActive
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                    >
                                                        <span>{subItem.label}</span>
                                                        {subItem.actor ? (
                                                            <span className="badge rounded-pill bg-light ms-auto text-secondary">
                                                                {subItem.actor}
                                                            </span>
                                                        ) : null}
                                                    </Link>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            ) : null}
                        </li>
                    );
                }

                const href = item.link || '#';
                const isActive = item.id === activeItemId;

                return (
                    <li className="nav-item" key={item.id || href}>
                        <Link
                            className={`nav-link menu-link${isActive ? ' active' : ''}`}
                            to={href}
                            aria-current={isActive ? 'page' : undefined}
                        >
                            {item.icon ? <i className={item.icon} /> : null}
                            <span>{item.label}</span>
                        </Link>
                    </li>
                );
            })}
        </>
    );
};

export default VerticalLayout;
