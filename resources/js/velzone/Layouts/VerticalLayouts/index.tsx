import { Link, useLocation } from '@/velzone/inertia-router';
import navdata from '../LayoutMenuData';

const VerticalLayout = () => {
    const navData = navdata().props.children as Array<{
        id?: string;
        label: string;
        isHeader?: boolean;
        icon?: string;
        link?: string;
        badge?: string;
        subItems?: Array<{
            id?: string;
            label: string;
            link: string;
        }>;
    }>;
    const { pathname } = useLocation();

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

                const href = item.link || '#';
                const hasActiveChild = item.subItems?.some(
                    (subItem) => pathname === subItem.link,
                );
                const isActive = pathname === href || hasActiveChild;

                return (
                    <li className="nav-item" key={item.id || href}>
                        <Link
                            className={`nav-link menu-link${isActive ? ' active' : ''}`}
                            to={href}
                            aria-current={isActive ? 'page' : undefined}
                            >
                                {item.icon ? <i className={item.icon} /> : null}
                                <span>{item.label}</span>
                            {item.badge ? (
                                <span className="badge badge-pill bg-danger ms-auto">
                                    {item.badge}
                                </span>
                            ) : item.subItems ? (
                                <i className="ri-arrow-right-s-line ms-auto"></i>
                            ) : null}
                        </Link>
                        {item.subItems && isActive ? (
                            <div className="menu-dropdown show">
                                <ul className="nav nav-sm flex-column">
                                    {item.subItems.map((subItem) => {
                                        const childActive =
                                            pathname === subItem.link;

                                        return (
                                            <li
                                                className="nav-item"
                                                key={
                                                    subItem.id || subItem.link
                                                }
                                            >
                                                <Link
                                                    className={`nav-link${childActive ? ' active' : ''}`}
                                                    to={subItem.link}
                                                >
                                                    {subItem.label}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        ) : null}
                    </li>
                );
            })}
        </>
    );
};

export default VerticalLayout;
