<?php
require_once('../library/tcpdf/tcpdf.php');
require_once('../library/FPDI/src/autoload.php');
require_once('../library/FPDI/src/TcpdfFpdi.php');

use setasign\Fpdi\TcpdfFpdi;

class Reports_ShipmentController extends Zend_Controller_Action {
    public function init() {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('generate-forms', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'Generate Forms';
    }

    public function generateFormsAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $shipmentId = $params["shipmentId"];
            $shipmentService = new Application_Service_Shipments();
            foreach (array_keys($params) as $paramKey) {
                if (strpos($paramKey, 'submissionDueDate_') === 0) {
                    $countryId = substr($paramKey, 18);
                    $shipmentService->updateShipmentCountry($shipmentId, $countryId, $params[$paramKey]);
                }
            }
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender(true);
            if (isset($shipmentId) && $shipmentId != '') {
                $templateData = $shipmentService->getDetailsForSubmissionForms($shipmentId);
                $shipmentCode = $templateData['shipment']['shipment_code'];
                $submissionForm = new SubmissionForm();
                $pageWidth = 296.92599647222;
                $pageHeight = 209.97333686111;
                $mediumFontName = "helvetica";
                $mediumFontSize = 10;
                $smallFontName = "helvetica";
                $smallFontSize = 9;
                $participantIndex = 0;
                foreach ($templateData["participant"] as $participant) {
                    if ($participantIndex == 0) {
                        $submissionForm->AddPage(
                            "L",
                            array(
                                "width" => $pageWidth,
                                "0" => $pageWidth,
                                "height" => $pageHeight,
                                "1" => $pageHeight,
                                "orientation" => "L"
                            )
                        );
                    } else {
                        $submissionForm->endPage();
                        $submissionForm->_tplIdx = $submissionForm->importPage(1);
                        $submissionForm->AddPage();
                    }
                    $submissionForm->SetFont(
                        $mediumFontName,
                        'B',
                        $mediumFontSize,
                        '',
                        'default',
                        true
                    );
                    $submissionForm->SetTextColor(0, 0, 0);
                    $submissionForm->SetXY(43, 46.5);
                    $submissionForm->Write(0, $shipmentCode);
                    $submissionForm->SetXY(100, 46.5);
                    $submissionForm->Write(0, $participant["participant_name"]);
                    $submissionForm->SetXY(237, 46.5);
                    $submissionForm->Write(0, $participant["due_date"]);

                    $submissionForm->SetFont(
                        $smallFontName,
                        'N',
                        $smallFontSize,
                        '',
                        'default',
                        true
                    );

                    $submissionForm->SetXY(67, 64);
                    $submissionForm->Write(0, $participant["pt_id"]);
                    $submissionForm->SetXY(67, 69.5);
                    $submissionForm->Write(0, $participant["username"]);
                    $submissionForm->SetXY(67, 75.5);
                    $submissionForm->Write(0, $participant["password"]);

                    $submissionForm->SetFont(
                        $smallFontName,
                        'N',
                        7.5,
                        '',
                        'default',
                        true
                    );
                    $submissionForm->SetXY(28, 130);
                    $submissionForm->Write(0, substr($templateData["sample"][0]["sample_label"], 0, 7));
                    $submissionForm->SetXY(28, 134);
                    $submissionForm->Write(0, substr($templateData["sample"][0]["sample_label"], 8));
                    $submissionForm->SetXY(28, 140.5);
                    $submissionForm->Write(0, substr($templateData["sample"][1]["sample_label"], 0, 7));
                    $submissionForm->SetXY(28, 144.5);
                    $submissionForm->Write(0, substr($templateData["sample"][1]["sample_label"], 8));
                    $submissionForm->SetXY(28, 151);
                    $submissionForm->Write(0, substr($templateData["sample"][2]["sample_label"], 0, 7));
                    $submissionForm->SetXY(28, 155);
                    $submissionForm->Write(0, substr($templateData["sample"][2]["sample_label"], 8));
                    $submissionForm->SetXY(28, 161.5);
                    $submissionForm->Write(0, substr($templateData["sample"][3]["sample_label"], 0, 7));
                    $submissionForm->SetXY(28, 165.5);
                    $submissionForm->Write(0, substr($templateData["sample"][3]["sample_label"], 8));
                    $submissionForm->SetXY(28, 172);
                    $submissionForm->Write(0, substr($templateData["sample"][4]["sample_label"], 0, 7));
                    $submissionForm->SetXY(28, 176);
                    $submissionForm->Write(0, substr($templateData["sample"][4]["sample_label"], 8));

                    if ($submissionForm->numPages > 1) {
                        for ($i = 2; $i <= $submissionForm->numPages; $i++) {
                            $submissionForm->endPage();
                            $submissionForm->_tplIdx = $submissionForm->importPage($i);
                            $submissionForm->AddPage();
                            if ($i == 2 && isset($templateData["country"][$participant["country"]]["pecc_details"]) &&
                                $templateData["country"][$participant["country"]]["pecc_details"] != "" &&
                                $templateData["country"][$participant["country"]]["pecc_details"] != ",") {
                                $submissionForm->SetFont(
                                    $smallFontName,
                                    'N',
                                    $smallFontSize,
                                    '',
                                    'default',
                                    true
                                );
                                $peccDetailsString = "If you are experiencing challenges testing the panel or submitting results please contact ";
                                $submissionForm->SetXY(28, 176);
                                $peccDetails = array_unique(explode(",", $templateData["country"][$participant["country"]]["pecc_details"]));
                                for ($ii = 0; $ii < count($peccDetails); $ii++) {
                                    if ($ii > 0) {
                                        if ($ii == count($peccDetails) - 1) {
                                            $peccDetailsString .= " or ";
                                        } else {
                                            $peccDetailsString .= ", ";
                                        }
                                    }
                                    $peccDetailsString .= $peccDetails[$ii];
                                }
                                $submissionForm->Write(0, $peccDetailsString);
                            }
                        }
                    }
                    $participantIndex++;
                }

                $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $shipmentCode) . '_submission_forms.pdf';
                $filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'reports'. DIRECTORY_SEPARATOR .$fileName;
                error_log($filePath);
                $submissionForm->Output($filePath, 'F');
                echo json_encode(array("fileName" => $fileName));
            }
        } else if ($this->_hasParam('sid')) {
            $this->_helper->layout()->setLayout('adminmodal');
            $shipmentId = (int) base64_decode($this->_getParam('sid'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipmentId = $shipmentId;
            $this->view->shipmentCountries = $shipmentService->getShipmentCountries($shipmentId);
        }
    }
}


class SubmissionForm extends TcpdfFpdi {
    var $_tplIdx;
    function Header() {
        if (is_null($this->_tplIdx)) {
            $this->numPages = $this->setSourceFile('./templates/ept_tb_submission_form.pdf');
            $this->_tplIdx = $this->importPage(1);
        }
        $this->useTemplate($this->_tplIdx);
    }

    function Footer() {
        $this->SetFont(
            'Helvetica',
            'N',
            9,
            '',
            'default',
            true
        );
        $this->SetY(-12.5);
        $this->SetX(220);
        $this->Write(0, 'Effective Date: ' . date("j F Y"));
    }
}
