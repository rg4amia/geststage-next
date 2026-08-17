import React from 'react';
import { Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import MarketStatus from './MarketStatus';
import PortfolioStatistics from './PortfolioStatistics';
import RecentTransaction from './RecentTransaction';
import Watchlist from './Watchlist';
import Widgets from './Widgets';


const MyWallet = () => {
    document.title="My Wallet | Velzon - React Admin & Dashboard Template";

    return (
        <React.Fragment>
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="My Wallet" pageTitle="Crypto" />
                    <Row>
                        <Col xxl={9}>
                            <PortfolioStatistics dataColors='["--vz-info"]' />
                            <Watchlist />
                            <MarketStatus />
                        </Col>
                        <Col xxl={3}>
                            <Widgets />
                            <RecentTransaction />
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default MyWallet;
