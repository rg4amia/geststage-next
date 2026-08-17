import { Link, useForm, usePage } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import { Card, CardBody, Col, Container, Input, Label, Row, Button, Form, FormFeedback, Alert, Spinner } from 'reactstrap';
import logoLight from "../../assets/images/logo-light.png";
import ParticlesAuth from "../AuthenticationInner/ParticlesAuth";


const Login = () => {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [passwordShow, setPasswordShow] = useState<boolean>(false);
    
    // Get status from page props (like reset password success message)
    const { status } = usePage().props as any;

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const submit = (e: any) => {
        e.preventDefault();
        post('/login');
    };

    document.title = "SignIn | GestStage";

    return (
        <React.Fragment>
            <ParticlesAuth>
                <div className="auth-page-content mt-lg-5">
                    <Container>
                        <Row>
                            <Col lg={12}>
                                <div className="text-center mt-sm-5 mb-4 text-white-50">
                                    <div>
                                        <Link href="/" className="d-inline-block auth-logo">
                                            <img src={logoLight} alt="" height="20" />
                                        </Link>
                                    </div>
                                    <p className="mt-3 fs-15 fw-medium">GestStage - Authentification</p>
                                </div>
                            </Col>
                        </Row>

                        <Row className="justify-content-center">
                            <Col md={8} lg={6} xl={5}>
                                <Card className="mt-4 card-bg-fill">
                                    <CardBody className="p-4">
                                        <div className="text-center mt-2">
                                            <h5 className="text-primary">Welcome Back !</h5>
                                            <p className="text-muted">Sign in to continue to GestStage.</p>
                                        </div>
                                        {status && (<Alert color="success"> {status} </Alert>)}
                                        {errors.email && (<Alert color="danger"> {errors.email} </Alert>)}
                                        <div className="p-2 mt-4">
                                            <Form onSubmit={submit}>

                                                <div className="mb-3">
                                                    <Label htmlFor="email" className="form-label">Email</Label>
                                                    <Input
                                                        name="email"
                                                        className="form-control"
                                                        placeholder="Enter email"
                                                        type="email"
                                                        onChange={(e) => setData('email', e.target.value)}
                                                        value={data.email}
                                                        invalid={errors.email ? true : false}
                                                    />
                                                    {errors.email ? (
                                                        <FormFeedback type="invalid">{errors.email}</FormFeedback>
                                                    ) : null}
                                                </div>

                                                <div className="mb-3">
                                                    <div className="float-end">
                                                        <Link href="/forgot-password" className="text-muted">Forgot password?</Link>
                                                    </div>
                                                    <Label className="form-label" htmlFor="password-input">Password</Label>
                                                    <div className="position-relative auth-pass-inputgroup mb-3">
                                                        <Input
                                                            name="password"
                                                            value={data.password}
                                                            type={passwordShow ? "text" : "password"}
                                                            className="form-control pe-5"
                                                            placeholder="Enter Password"
                                                            onChange={(e) => setData('password', e.target.value)}
                                                            invalid={errors.password ? true : false}
                                                        />
                                                        {errors.password ? (
                                                            <FormFeedback type="invalid">{errors.password}</FormFeedback>
                                                        ) : null}
                                                        <button className="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" type="button" id="password-addon" onClick={() => setPasswordShow(!passwordShow)}><i className="ri-eye-fill align-middle"></i></button>
                                                    </div>
                                                </div>

                                                <div className="form-check">
                                                    <Input 
                                                        className="form-check-input" 
                                                        type="checkbox" 
                                                        id="auth-remember-check" 
                                                        checked={data.remember}
                                                        onChange={(e) => setData('remember', e.target.checked)}
                                                    />
                                                    <Label className="form-check-label" htmlFor="auth-remember-check">Remember me</Label>
                                                </div>

                                                <div className="mt-4">
                                                    <Button color="success"
                                                        disabled={processing}
                                                        className="btn btn-success w-100" type="submit">
                                                        {processing && <Spinner size="sm" className='me-2'> Loading... </Spinner>}
                                                        Sign In
                                                    </Button>
                                                </div>

                                            </Form>
                                        </div>
                                    </CardBody>
                                </Card>

                                <div className="mt-4 text-center">
                                    <p className="mb-0">Don't have an account ? <Link href="/register" className="fw-semibold text-primary text-decoration-underline"> Signup </Link> </p>
                                </div>

                            </Col>
                        </Row>
                    </Container>
                </div>
            </ParticlesAuth>
        </React.Fragment>
    );
};

export default Login;