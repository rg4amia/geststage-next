import React from 'react';
import { Col, Row } from 'reactstrap';
import { Link } from '@/velzone/inertia-router';
import UsurpationBanner from './UsurpationBanner';

interface BreadCrumbProps {
    title: string;
    pageTitle: string;
}

const BreadCrumb = ({ title, pageTitle }: BreadCrumbProps) => {
    return (
        <React.Fragment>
            <Row>
                <Col xs={12}>
                    <div className="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 className="mb-sm-0">{title}</h4>

                        <div className="page-title-right">
                            <ol className="breadcrumb m-0">
                                <li className="breadcrumb-item">
                                    <Link to="/">{pageTitle}</Link>
                                </li>
                                <li className="breadcrumb-item active">
                                    {title}
                                </li>
                            </ol>
                        </div>
                    </div>
                </Col>
            </Row>
            {/* Rendu ici, juste sous le titre de page, pour ne jamais être
                masqué derrière le topbar fixe (voir UsurpationBanner). */}
            <UsurpationBanner />
        </React.Fragment>
    );
};

export default BreadCrumb;
