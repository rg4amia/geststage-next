import React from 'react';

import Client from './client';
import Contact from './contact';
import Counter from './counter';
import Cta from './cta';
import Faqs from './faq';
import Features from './features';
import Footer from './footer';
import Home from './home';
import Navbar from './navbar';
import Plans from './plans';
import Reviews from './reviews';
import Services from './services';
import Team from './team';
import WorkProcess from './workProcess';

const Index = () => {
    document.title = " Landing | Velzon - React Admin & Dashboard Template";
    
    window.onscroll = function () {
        scrollFunction();
    };

    const scrollFunction = () => {
        const element = document.getElementById("back-to-top");

        if (element) {
            if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
                element.style.display = "block";
            } else {
                element.style.display = "none";
            }
        }
    };

    const toTop = () => {
        document.body.scrollTop = 0;
        document.documentElement.scrollTop = 0;
    };

    return (
        <React.Fragment>
            <div className="layout-wrapper landing">
                <Navbar />
                <Home />
                <Client />
                <Services />
                <Features />
                <Plans />
                <Faqs />
                <Reviews />
                <Counter />
                <WorkProcess />
                <Team />
                <Contact />
                <Cta />
                <Footer />
                <button onClick={() => toTop()} className="btn btn-danger btn-icon landing-back-top" id="back-to-top">
                    <i className="ri-arrow-up-line"></i>
                </button>
            </div>
        </React.Fragment>
    );
};

export default Index;