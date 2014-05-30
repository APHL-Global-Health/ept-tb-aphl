<?php

class Reports_DetailedController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'detailed'; 
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $shipmentService = new Application_Service_Reports();
            $response=$shipmentService->getParticipantDetailedReport($params);
            $this->view->response = $response;
            $this->view->type= $params['reportType'];
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }


}

