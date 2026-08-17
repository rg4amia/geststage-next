import React from 'react';
import Connect from './Connect';
import CTA from './CTA';
import DiscoverItems from "./DiscoverItems";
import Features from './Features';
import Footer from "./footer";
import Home from "./Home";
import Navbar from "./Navbar";
import Products from "./Products";
import TopCreator from "./TopCreator ";
import Trending from "./Trending ";

const index = () => {
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
                <Connect />
                <Products />
                <Features />
                <Trending />
                <DiscoverItems />
                <TopCreator />
                <CTA />
                <Footer />
                <button onClick={() => toTop()} className="btn btn-danger btn-icon landing-back-top" id="back-to-top">
                    <i className="ri-arrow-up-line"></i>
                </button>
            </div>
        </React.Fragment>
    );
};

export default index;