import React, { useEffect } from 'react';
import { Container } from 'reactstrap';
import SimpleBar from 'simplebar-react';
import { Link } from '@/velzone/inertia-router';
//import logo
import logoDark from '../assets/images/logo-dark.png';
import logoLight from '../assets/images/logo-light.png';
import logoSm from '../assets/images/logo-sm.png';

//Import Components
import HorizontalLayout from './HorizontalLayout';
import TwoColumnLayout from './TwoColumnLayout';
import VerticalLayout from './VerticalLayouts';

const Sidebar = ({ layoutType }: any) => {
    useEffect(() => {
        const verticalOverlay =
            document.getElementsByClassName('vertical-overlay');
        const overlay = verticalOverlay[0];
        const closeSidebar = () => {
            document.body.classList.remove('vertical-sidebar-enable');
        };

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        return () => {
            overlay?.removeEventListener('click', closeSidebar);
        };
    }, []);

    const addEventListenerOnSmHoverMenu = () => {
        // add listener Sidebar Hover icon on change layout from setting
        if (
            document.documentElement.getAttribute('data-sidebar-size') ===
            'sm-hover'
        ) {
            document.documentElement.setAttribute(
                'data-sidebar-size',
                'sm-hover-active',
            );
        } else if (
            document.documentElement.getAttribute('data-sidebar-size') ===
            'sm-hover-active'
        ) {
            document.documentElement.setAttribute(
                'data-sidebar-size',
                'sm-hover',
            );
        } else {
            document.documentElement.setAttribute(
                'data-sidebar-size',
                'sm-hover',
            );
        }
    };

    return (
        <React.Fragment>
            <div className="app-menu navbar-menu">
                <div className="navbar-brand-box">
                    <Link to="/" className="logo logo-dark">
                        <span className="logo-sm">
                            <img src={logoSm} alt="" style={{ height: '22px', width: 'auto' }} />
                        </span>
                        <span className="logo-lg">
                            <img src={logoDark} alt="" style={{ height: '17px', width: 'auto' }} />
                        </span>
                    </Link>

                    <Link to="/" className="logo logo-light">
                        <span className="logo-sm">
                            <img src={logoSm} alt="" style={{ height: '22px', width: 'auto' }} />
                        </span>
                        <span className="logo-lg">
                            <img src={logoLight} alt="" style={{ height: '17px', width: 'auto' }} />
                        </span>
                    </Link>
                    <button
                        onClick={addEventListenerOnSmHoverMenu}
                        type="button"
                        className="btn btn-sm fs-20 header-item btn-vertical-sm-hover float-end p-0"
                        id="vertical-hover"
                    >
                        <i className="ri-record-circle-line"></i>
                    </button>
                </div>

                {layoutType === 'horizontal' ? (
                    <div id="scrollbar">
                        <Container fluid>
                            <div id="two-column-menu"></div>
                            <ul className="navbar-nav" id="navbar-nav">
                                <HorizontalLayout />
                            </ul>
                        </Container>
                    </div>
                ) : layoutType === 'twocolumn' ? (
                    <React.Fragment>
                        <TwoColumnLayout layoutType={layoutType} />
                        <div className="sidebar-background"></div>
                    </React.Fragment>
                ) : (
                    <React.Fragment>
                        <SimpleBar id="scrollbar" className="h-100">
                            <Container fluid>
                                <div id="two-column-menu"></div>
                                <ul className="navbar-nav" id="navbar-nav">
                                    <VerticalLayout />
                                </ul>
                            </Container>
                        </SimpleBar>
                        <div className="sidebar-background"></div>
                    </React.Fragment>
                )}
            </div>
            <div className="vertical-overlay"></div>
        </React.Fragment>
    );
};

export default Sidebar;
