import React from 'react';
import { toast } from 'react-toastify';
import { Spinner } from 'reactstrap';

import 'react-toastify/dist/ReactToastify.css';

const Loader = (props : any) => {
    return (
        <React.Fragment>
            <div className="d-flex justify-content-center mx-2 mt-2">
                <Spinner color="primary"> Loading... </Spinner>
            </div>
            {toast.error(props.error, { position: "top-right", hideProgressBar: false, progress: undefined, toastId: "" })}
        </React.Fragment>
    );
};

export default Loader;
