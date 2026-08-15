import React from 'react';
import { Head } from '@inertiajs/react';
import { Col, Container, Row } from 'reactstrap';

//import Components
import BreadCrumb from '../../Components/Common/BreadCrumb';
import UpgradeAccountNotise from './UpgradeAccountNotise';
import UsersByDevice from './UsersByDevice';
import Widget from './Widget';
import AudiencesMetrics from './AudiencesMetrics';
import AudiencesSessions from './AudiencesSessions';
import LiveUsers from './LiveUsers';
import TopReferrals from './TopReferrals';
import TopPages from './TopPages';

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
