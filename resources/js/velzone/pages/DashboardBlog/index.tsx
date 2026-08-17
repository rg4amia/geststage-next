import React from 'react'
import { Container, Row } from 'reactstrap'
import BreadCrumb from 'Components/Common/BreadCrumb'
import Device from './Device'
import RecentArticleTable from './RecentArticleTable'
import RecentComment from './RecentComment'
import Widget from './Widget'

const DashboardBlog = () => {

  document.title="Blog | Velzon - React Admin & Dashboard Template";

  return (
    <React.Fragment>
      <div className="page-content">
        <Container fluid>
          <BreadCrumb title="Blog" pageTitle="Dashboards" />
          <Row>

            <Widget />

            <RecentComment />

            <RecentArticleTable />

            <Device />

          </Row>
        </Container>
      </div>
    </React.Fragment>
  )
}

export default DashboardBlog