<?php

class Admin_EvaluateController extends Zend_Controller_Action {
    public function init() {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('get-shipments', 'html')
                    ->addActionContext('update-shipment-comment', 'html')
                    ->addActionContext('update-shipment-status', 'html')
                    ->addActionContext('delete-dts-response', 'html')
                    ->addActionContext('vl-range', 'html')
                    ->initContext();        
        $this->_helper->layout()->pageName = 'analyze';
    }

    public function indexAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $evalService = new Application_Service_Evaluation();
            $evalService->echoAllDistributions($params);
        }
		if ($this->_hasParam('scheme') && $this->_hasParam('showcalc')){
            $this->view->showcalc = ($this->_getParam('showcalc'));
            $this->view->scheme = $this->_getParam('scheme');
		}
    }

    public function getShipmentsAction() {
        if ($this->_hasParam('did')){
            $id = (int)($this->_getParam('did'));
            $evalService = new Application_Service_Evaluation();
            $this->view->shipments = $evalService->getShipments($id);            
        } else {
            $this->view->shipments = false;
        }
    }

    public function shipmentAction() {
        if ($this->_hasParam('sid')) {
            $id = (int)base64_decode($this->_getParam('sid'));
            $reEvaluate = false;
            if ($this->_hasParam('re')) {
                if(base64_decode($this->_getParam('re')) == 'yes'){
                    $reEvaluate = true;
                }
            }
            $evalService = new Application_Service_Evaluation();
            $shipment = $this->view->shipment = $evalService->getShipmentToEvaluate($id,$reEvaluate);
            $this->view->shipmentsUnderDistro = $evalService->getShipments($shipment[0]['distribution_id']);
        } else {
            $this->_redirect("/admin/evaluate/");
        }
    }

    public function viewAction() {
        if ($this->_hasParam('sid') && $this->_hasParam('pid') && $this->_hasParam('scheme')) {
            $this->view->currentUrl = "/admin/evaluate/view/sid/".$this->_getParam('sid')."/pid/".$this->_getParam('pid')."/scheme/".$this->_getParam('scheme');
            $sid = (int)base64_decode($this->_getParam('sid'));
            $pid = (int)base64_decode($this->_getParam('pid'));
            $this->view->scheme = $scheme = base64_decode($this->_getParam('scheme'));
            $schemeService = new Application_Service_Schemes();
            if ($scheme == 'eid') {
                $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
                $this->view->detectionAssay = $schemeService->getEidDetectionAssay();

            } else if($scheme == 'dts') {
                $this->view->allTestKits = $schemeService->getAllDtsTestKit();
            } else if ($scheme == 'vl') {
                $this->view->vlRange = $schemeService->getVlRange($sid);
                $this->view->vlAssay = $schemeService->getVlAssay();
            } else if ($scheme == 'tb') {
                $this->view->assays = $schemeService->getTbAssayReferenceMap();
            }
            $evalService = new Application_Service_Evaluation();
            $this->view->evaluateData = $evalService->viewEvaluation($sid,$pid,$scheme);
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
            $this->view->customField1 = $globalConfigDb->getValue('custom_field_1');
            $this->view->customField2 = $globalConfigDb->getValue('custom_field_2');
            $this->view->haveCustom = $globalConfigDb->getValue('custom_field_needed');
        } else {
            $this->_redirect("/admin/evaluate/");
        }
    }

    public function editAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $evalService = new Application_Service_Evaluation();
            $evalService->updateShipmentResults($params);
            $shipmentId = base64_encode($params['shipmentId']);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = "Shipment Results updated successfully";
            if (isset($params['whereToGo']) && $params['whereToGo'] != "") {
               $this->_redirect($params['whereToGo']); 
            } else {
                $this->_redirect("/admin/evaluate/shipment/sid/$shipmentId");    
            }
        } else {
            if ($this->_hasParam('sid') && $this->_hasParam('pid')  && $this->_hasParam('scheme')) {
                $this->view->currentUrl = "/admin/evaluate/edit/sid/".$this->_getParam('sid')."/pid/".$this->_getParam('pid')."/scheme/".$this->_getParam('scheme');
                $sid = (int)base64_decode($this->_getParam('sid'));
                $pid = (int)base64_decode($this->_getParam('pid'));
                $this->view->scheme = $scheme = base64_decode($this->_getParam('scheme'));
                $schemeService = new Application_Service_Schemes();
                $evalService = new Application_Service_Evaluation();
                $this->view->evaluateData = $evalService->editEvaluation($sid,$pid,$scheme);
                if ($scheme == 'eid') {
                    $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
                    $this->view->detectionAssay = $schemeService->getEidDetectionAssay();
                } else if ($scheme == 'dts') {
                    $this->view->allTestKits = $schemeService->getAllDtsTestKit();
                } else if ($scheme == 'dbs') {
                    $this->view->wb = $schemeService->getDbsWb();
                    $this->view->eia = $schemeService->getDbsEia();
                } else if ($scheme == 'vl') {
                    $this->view->vlRange = $schemeService->getVlRange($sid);
                    $this->view->vlAssay = $schemeService->getVlAssay();
                } else if ($scheme == 'tb') {
                    $this->view->assays = $schemeService->getTbAssayReferenceMap();
                    if (isset($this->view->evaluateData['shipment']['follows_up_from']) &&
                        $this->view->evaluateData['shipment']['follows_up_from'] > 0) {
                        $attributes = json_decode($this->view->evaluateData['shipment']['attributes'], true);
                        $correctiveActionsFromPreviousRound = array();
                        $previousShipmentData = $schemeService->getShipmentData($this->view->evaluateData['shipment']['follows_up_from'], $pid);
                        if (isset($attributes['corrective_actions_from_previous_round'])) {
                            $correctiveActionsFromPreviousRound = $attributes['corrective_actions_from_previous_round'];
                        } else {
                            $previousShipmentAttributes = json_decode($previousShipmentData['attributes'], true);
                            if (isset($previousShipmentAttributes['corrective_actions'])) {
                                foreach ($previousShipmentAttributes['corrective_actions'] as $correctiveActionFromPreviousRound) {
                                    array_push($correctiveActionsFromPreviousRound, array(
                                        'checked_off' => false,
                                        'corrective_action' => $correctiveActionFromPreviousRound
                                    ));
                                }
                            }
                        }
                        $this->view->followsUpFrom = $previousShipmentData['shipment_code'];
                        $this->view->correctiveActionsFromPreviousRound = $correctiveActionsFromPreviousRound;
                    }
                    $instrumentDb = new Application_Model_DbTable_Instruments();
                    $this->view->instruments = $instrumentDb->getInstruments($pid, true);
                }
                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $this->view->customField1 = $globalConfigDb->getValue('custom_field_1');
                $this->view->customField2 = $globalConfigDb->getValue('custom_field_2');
                $this->view->haveCustom = $globalConfigDb->getValue('custom_field_needed');
                $commonService = new Application_Service_Common();
                $this->view->globalQcAccess = $commonService->getConfig('qc_access');
            } else {
                $this->_redirect("/admin/evaluate/");
            }
        }
    }

    public function updateShipmentCommentAction() {
        if ($this->_hasParam('sid')) {
            $sid = (int)base64_decode($this->_getParam('sid'));
            $comment = $this->_getParam('comment');
            $evalService = new Application_Service_Evaluation();
            $this->view->message = $evalService->updateShipmentComment($sid,$comment);
        } else {
            $this->view->message = "Unable to update shipment comment. Please try again later.";
        }
    }

    public function updateShipmentStatusAction() {
        if ($this->_hasParam('sid')) {
            $sid = (int)base64_decode($this->_getParam('sid'));
            $status = $this->_getParam('status');
            $evalService = new Application_Service_Evaluation();
            $this->view->message = $evalService->updateShipmentStatus($sid, $status);
        } else {
            $this->view->message = "Unable to update shipment status. Please try again later.";
        }
    }

    public function deleteDtsResponseAction() {
        if ($this->_hasParam('mid')) {
            if ($this->getRequest()->isPost()) {
                $mapId = (int)base64_decode($this->_getParam('mid'));
                $schemeType = ($this->_getParam('schemeType'));
                $shipmentService = new Application_Service_Shipments();
				if ($schemeType == 'dts') {
					$this->view->result = $shipmentService->removeDtsResults($mapId);
				} else if ($schemeType == 'eid') {
					$this->view->result = $shipmentService->removeDtsEidResults($mapId);
				} else if ($schemeType == 'vl') {
					$this->view->result = $shipmentService->removeDtsVlResults($mapId);
				} else if ($schemeType == 'tb') {
                    $this->view->result = $shipmentService->removeDtsTbResults($mapId);
                } else {
					$this->view->result = "Failed to delete";
				}
            }
        } else {
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function vlRangeAction() {
		if ($this->_hasParam('manualRange')) {
			$params = $this->getRequest()->getPost();
			$schemeService = new Application_Service_Schemes();
			$schemeService->updateVlInformation($params);
			$shipmentId = (int)base64_decode($this->_getParam('sid'));
			$this->_redirect("/admin/evaluate/index/scheme/vl/showcalc/".base64_encode($shipmentId));
		}
		if ($this->_hasParam('sid')) {
			if ($this->getRequest()->isPost()) {
				$shipmentId = (int)base64_decode($this->_getParam('sid'));
				$schemeService = new Application_Service_Schemes();
				$this->view->result = $schemeService->getVlRangeInformation($shipmentId);
				$this->view->shipmentId = $shipmentId;
			}
		} else {
			$this->view->message = "Unable to fetch Viral Load Range for this Shipment.";
		}
    }

    public function recalculateVlRangeAction() {
        if ($this->_hasParam('sid')) {
			$shipmentId = (int)($this->_getParam('sid'));
			$schemeService = new Application_Service_Schemes();
			$this->view->result = $schemeService->setVlRange($shipmentId);
			$this->_redirect("/admin/evaluate/index/scheme/vl/showcalc/".base64_encode($shipmentId));
		} else {
			$this->_redirect("/admin/evaluate/");
		}
    }
	
	public function vlSamplePlotAction() {
		$shipmentId = $this->_getParam('shipment');
		$sampleId = $this->_getParam('sample');
		$schemeService = new Application_Service_Schemes();
		$this->view->vlRange = $schemeService->getVlRange($shipmentId,$sampleId);
		$this->view->shipmentId = $shipmentId;
		$this->view->sampleId = $sampleId;
	}
	
	public function addManualLimitsAction() {
		$this->_helper->layout()->disableLayout();
        if ($this->_hasParam('id')) {
            $this->_helper->layout()->setLayout('modal');
            $schemeService = new Application_Service_Schemes();
            $combineId = base64_decode($this->_getParam('id'));
			$expStr=explode("#",$combineId);
			$shipmentId=(int)$expStr[0];
			$sampleId=(int)$expStr[1];
			$vlAssay=(int)$expStr[2];
			$this->view->result = $schemeService->getVlManualValue($shipmentId,$sampleId,$vlAssay);
		}
		if ($this->getRequest()->isPost()) {
			$params = $this->getRequest()->getPost();
			$updatedResult=$schemeService->updateVlManualValue($params);
			$this->view->updatedResult=$updatedResult;
			$this->view->sampleId=base64_decode($params['sampleId']);
			$this->view->vlAssay=base64_decode($params['vlAssay']);
			$this->view->mLowLimit=round($params['manualLowLimit'],4);
			$this->view->mHighLimit=round($params['manualHighLimit'],4);
		}
	}
}