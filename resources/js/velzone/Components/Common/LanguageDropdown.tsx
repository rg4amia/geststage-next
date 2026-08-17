import lodash from "lodash";
import React, { useEffect, useState } from 'react';
import { Dropdown, DropdownItem, DropdownMenu, DropdownToggle } from 'reactstrap';

//i18n
import languages from "../../common/languages";
import i18n from "../../i18n";

const { get } = lodash;

const LanguageDropdown = () => {
    // Declare a new state variable, which we'll call "menu"
    const [selectedLang, setSelectedLang] = useState("en");

    useEffect(() => {
        const storedLanguage = localStorage.getItem("I18N_LANGUAGE");
        const userSelectedLanguage =
            localStorage.getItem("I18N_LANGUAGE_USER_SELECTED") === "1";
        const currentLanguage =
            storedLanguage && (storedLanguage !== "fr" || userSelectedLanguage)
                ? storedLanguage
                : "en";

        setSelectedLang(currentLanguage);
    }, []);

    const changeLanguageAction = (lang : any) => {
        //set language as i18n
        i18n.changeLanguage(lang);
        localStorage.setItem("I18N_LANGUAGE", lang);
        localStorage.setItem("I18N_LANGUAGE_USER_SELECTED", "1");
        setSelectedLang(lang);
    };


    const [isLanguageDropdown, setIsLanguageDropdown] = useState<boolean>(false);
    const toggleLanguageDropdown = () => {
        setIsLanguageDropdown(!isLanguageDropdown);
    };

    return (
        <React.Fragment>
            <Dropdown isOpen={isLanguageDropdown} toggle={toggleLanguageDropdown} className="ms-1 topbar-head-dropdown header-item">
                <DropdownToggle className="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" tag="button">
                    <img
                        src={get(languages, `${selectedLang}.flag`)}
                        alt="Header Language"
                        className="rounded"
                        style={{ height: '20px', width: 'auto' }}
                    />
                </DropdownToggle>
                <DropdownMenu className="notify-item language py-2">
                    {Object.keys(languages).map(key => (
                        <DropdownItem
                            key={key}
                            onClick={() => changeLanguageAction(key)}
                            className={`notify-item d-flex align-items-center ${selectedLang === key ? "active" : "none"
                                }`}
                        >
                            <img
                                src={get(languages, `${key}.flag`)}
                                alt="Skote"
                                className="me-2 rounded"
                                style={{ height: '18px', width: 'auto' }}
                            />
                            <span className="align-middle">
                                {get(languages, `${key}.label`)}
                            </span>
                        </DropdownItem>
                    ))}
                </DropdownMenu>
            </Dropdown>
        </React.Fragment>
    );
};

export default LanguageDropdown;
