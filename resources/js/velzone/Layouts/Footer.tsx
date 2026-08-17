import { usePage } from '@inertiajs/react';
import React from 'react';
import { Col, Container, Row } from 'reactstrap';

const Footer = () => {
    const { name = 'GestStage' } = usePage<{ name?: string }>().props;

    return (
        <React.Fragment>
            <footer className="footer">
                <Container fluid>
                    <Row>
                        <Col sm={6}>
                            {new Date().getFullYear()} © {name}.
                        </Col>
                        <Col sm={6}>
                            <div className="text-sm-end d-none d-sm-block">
                                Gestion des stages et des bénéficiaires
                            </div>
                        </Col>
                    </Row>
                </Container>
            </footer>
        </React.Fragment>
    );
};

export default Footer;
