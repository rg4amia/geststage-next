import { Head } from '@inertiajs/react';
import React from 'react';
import { Col, Container, Row } from 'reactstrap';

//import Components
import BreadCrumb from '../../Components/Common/BreadCrumb';
import AudiencesMetrics from './AudiencesMetrics';
import AudiencesSessions from './AudiencesSessions';
import LiveUsers from './LiveUsers';
import TopPages from './TopPages';
import TopReferrals from './TopReferrals';
import UpgradeAccountNotise from './UpgradeAccountNotise';
import UsersByDevice from './UsersByDevice';
import Widget from './Widget';

const DashboardAnalytics = () => {
    return (
        <React.Fragment>
            <Head title="Tableau de bord" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Tableau de bord" pageTitle="GestStage" />
                    <Row>
                        <Col xxl={5}>
                            <UpgradeAccountNotise />
                            <Widget />
                        </Col>
                        <LiveUsers />
                    </Row>
                    <Row>
                        <AudiencesMetrics />
                        <AudiencesSessions />
                    </Row>
                    <Row>
                        <UsersByDevice />
                        <TopReferrals />
                        <TopPages />
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default DashboardAnalytics;
