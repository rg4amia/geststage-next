import PropTypes from "prop-types";
import React, { useEffect } from "react";
import { useSelector, useDispatch } from "react-redux";
import { createSelector } from "reselect";
import { Navigate } from '@/velzone/inertia-router';

import withRouter from "../../Components/Common/withRouter";
import { logoutUser } from "../../slices/thunks";

//redux


const Logout = () => {
  const dispatch: any = useDispatch();


  const logoutData = createSelector(
    (state) => state.Login,
    (isUserLogout) => isUserLogout.isUserLogout
  );

  // Inside your component
  const isUserLogout = useSelector(logoutData);

  useEffect(() => {
    dispatch(logoutUser());
  }, [dispatch]);

  if (isUserLogout) {
    return <Navigate to="/login" />;
  }

  return <React.Fragment></React.Fragment>;
};

Logout.propTypes = {
  history: PropTypes.object,
};


export default withRouter(Logout);