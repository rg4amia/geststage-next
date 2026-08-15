import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { createSelector } from 'reselect';

import { Link } from '@/velzone/inertia-router';
import FullScreenDropdown from '../Components/Common/FullScreenDropdown';
import LanguageDropdown from '../Components/Common/LanguageDropdown';
import LightDark from '../Components/Common/LightDark';
import ProfileDropdown from '../Components/Common/ProfileDropdown';
import SearchOption from '../Components/Common/SearchOption';
import WebAppsDropdown from '../Components/Common/WebAppsDropdown';
import MyCartDropdown from '../Components/Common/MyCartDropdown';
import NotificationDropdown from '../Components/Common/NotificationDropdown';
import logoDark from '../assets/images/logo-dark.png';
import logoLight from '../assets/images/logo-light.png';
import logoSm from '../assets/images/logo-sm.png';
import { changeSidebarVisibility } from '../slices/thunks';

type HeaderProps = {
    onChangeLayoutMode: (mode: string) => void;
    layoutModeType: string;
    headerClass: string;
};

const Header = ({
    onChangeLayoutMode,
    layoutModeType,
    headerClass,
}: HeaderProps) => {
    const dispatch = useDispatch<any>();
    const selectSidebarVisibility = createSelector(
        (state: any) => state.Layout,
        (layout) => layout.sidebarVisibilitytype,
    );
    const sidebarVisibilitytype = useSelector(selectSidebarVisibility);

    const toggleMenu = () => {
        const windowSize = document.documentElement.clientWidth;
        const hamburgerIcon = document.querySelector('.hamburger-icon');
        const layout = document.documentElement.getAttribute('data-layout');

        dispatch(changeSidebarVisibility('show'));

        if (windowSize > 767) {
            hamburgerIcon?.classList.toggle('open');
        }

        if (layout === 'horizontal') {
            document.body.classList.toggle('menu');
        }

        if (
            sidebarVisibilitytype === 'show' &&
            (layout === 'vertical' || layout === 'semibox')
        ) {
            if (windowSize < 1025 && windowSize > 767) {
                document.body.classList.remove('vertical-sidebar-enable');
                const sidebarSize =
                    document.documentElement.getAttribute('data-sidebar-size');
                document.documentElement.setAttribute(
                    'data-sidebar-size',
                    sidebarSize === 'sm' ? '' : 'sm',
                );
            } else if (windowSize > 1025) {
                document.body.classList.remove('vertical-sidebar-enable');
                const sidebarSize =
                    document.documentElement.getAttribute('data-sidebar-size');
                document.documentElement.setAttribute(
                    'data-sidebar-size',
                    sidebarSize === 'lg' ? 'sm' : 'lg',
                );
            } else {
                document.body.classList.add('vertical-sidebar-enable');
                document.documentElement.setAttribute(
                    'data-sidebar-size',
                    'lg',
                );
            }
        }

        if (layout === 'twocolumn') {
            document.body.classList.toggle('twocolumn-panel');
        }
    };

    return (
        <header id="page-topbar" className={headerClass}>
            <div className="layout-width">
                <div className="navbar-header">
                    <div className="d-flex align-items-center">
                        <div className="navbar-brand-box horizontal-logo">
                            <Link to="/" className="logo logo-dark">
                                <span className="logo-sm">
                                    <img
                                        src={logoSm}
                                        alt="GestStage"
                                        style={{ height: '22px', width: 'auto' }}
                                    />
                                </span>
                                <span className="logo-lg">
                                    <img
                                        src={logoDark}
                                        alt="GestStage"
                                        style={{ height: '17px', width: 'auto' }}
                                    />
                                </span>
                            </Link>

                            <Link to="/" className="logo logo-light">
                                <span className="logo-sm">
                                    <img
                                        src={logoSm}
                                        alt="GestStage"
                                        style={{ height: '22px', width: 'auto' }}
                                    />
                                </span>
                                <span className="logo-lg">
                                    <img
                                        src={logoLight}
                                        alt="GestStage"
                                        style={{ height: '17px', width: 'auto' }}
                                    />
                                </span>
                            </Link>
                        </div>

                        <button
                            onClick={toggleMenu}
                            type="button"
                            className="btn btn-sm fs-16 header-item vertical-menu-btn topnav-hamburger px-3"
                            id="topnav-hamburger-icon"
                            aria-label="Ouvrir ou réduire le menu"
                        >
                            <span className="hamburger-icon">
                                <span />
                                <span />
                                <span />
                            </span>
                        </button>

                        <SearchOption />
                    </div>

                    <div className="d-flex align-items-center">
                        <LanguageDropdown />
                        <WebAppsDropdown />
                        <MyCartDropdown />
                        <FullScreenDropdown />
                        <LightDark
                            layoutMode={layoutModeType}
                            onChangeLayoutMode={onChangeLayoutMode}
                        />
                        <NotificationDropdown />
                        <ProfileDropdown />
                    </div>
                </div>
            </div>
        </header>
    );
};

export default Header;
