import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Dropdown,
    DropdownItem,
    DropdownMenu,
    DropdownToggle,
} from 'reactstrap';

import avatar from '../../assets/images/users/avatar-1.jpg';

type AuthUser = {
    name?: string;
    email?: string;
};

type SharedProps = {
    auth?: {
        user?: AuthUser | null;
    };
};

const ProfileDropdown = () => {
    const { auth } = usePage<SharedProps>().props;
    const user = auth?.user;
    const [isOpen, setIsOpen] = useState(false);
    const userName = user?.name || user?.email || 'Anna Adame';
    const userRole = user ? 'Utilisateur' : 'Founder';

    return (
        <Dropdown
            isOpen={isOpen}
            toggle={() => setIsOpen((open) => !open)}
            className="ms-sm-3 header-item topbar-user"
        >
            <DropdownToggle tag="button" type="button" className="btn">
                <span className="d-flex align-items-center">
                    <img
                        className="rounded-circle header-profile-user"
                        src={avatar}
                        alt="Photo de profil"
                    />
                    <span className="ms-xl-2 text-start">
                        <span className="d-none d-xl-inline-block fw-medium user-name-text ms-1">
                            {userName}
                        </span>
                        <span className="d-none d-xl-block fs-12 user-name-sub-text ms-1 text-muted">
                            {userRole}
                        </span>
                    </span>
                </span>
            </DropdownToggle>

            <DropdownMenu className="dropdown-menu-end">
                <h6 className="dropdown-header">Bienvenue, {userName}</h6>

                {user ? (
                    <>
                        <DropdownItem tag="div" className="p-0">
                            <Link
                                href="/settings/profile"
                                className="dropdown-item"
                            >
                                <i className="mdi mdi-account-circle fs-16 me-1 align-middle text-muted" />
                                <span className="align-middle">Mon profil</span>
                            </Link>
                        </DropdownItem>
                        <DropdownItem tag="div" className="p-0">
                            <Link
                                href="/settings/security"
                                className="dropdown-item"
                            >
                                <i className="mdi mdi-shield-key-outline fs-16 me-1 align-middle text-muted" />
                                <span className="align-middle">Sécurité</span>
                            </Link>
                        </DropdownItem>
                        <div className="dropdown-divider" />
                        <DropdownItem tag="div" className="p-0">
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="dropdown-item"
                            >
                                <i className="mdi mdi-logout fs-16 me-1 align-middle text-muted" />
                                <span className="align-middle">
                                    Se déconnecter
                                </span>
                            </Link>
                        </DropdownItem>
                    </>
                ) : (
                    <DropdownItem tag="div" className="p-0">
                        <Link href="/login" className="dropdown-item">
                            <i className="mdi mdi-login fs-16 me-1 align-middle text-muted" />
                            <span className="align-middle">Se connecter</span>
                        </Link>
                    </DropdownItem>
                )}
            </DropdownMenu>
        </Dropdown>
    );
};

export default ProfileDropdown;
