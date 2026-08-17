import React from 'react';
import { Container } from 'reactstrap';

// import Components
import BreadCrumb from '../../Components/Common/BreadCrumb';

import ChartMapWidgets from './Chart&MapWidgets';
import CreditCard from './CreditCard';
import EcommerceWidgets from './EcommerceWidgets';
import OtherWidgets from './OtherWidgets';
import TileBoxs from './TileBoxs';
import UpcomingActivity from './UpcomingActivities';

const Widgets = () => {
    document.title = "Widgets | Velzon - React Admin & Dashboard Template";

    return (
        <React.Fragment>
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Widgets" pageTitle="Velzon" />
                    {/* Tile Boxs Widgets */}
                    <TileBoxs />

                    {/* Other Widgets */}
                    <OtherWidgets />

                    {/* Upcoming Activity */}
                    <UpcomingActivity />

                    {/* Chart & Map Widgets */}
                    <ChartMapWidgets />

                    {/* Chart & EcommerceWidgets  */}
                    <EcommerceWidgets />

                    {/* CreditCard */}
                    <CreditCard />

                </Container>
            </div>
        </React.Fragment>
    );
};

export default Widgets;
