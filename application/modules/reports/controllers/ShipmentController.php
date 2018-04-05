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

    public function generateFormsAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        if ($this->_hasParam('sid')) {
            $shipmentId = (int) base64_decode($this->_getParam('sid'));
            $shipmentService = new Application_Service_Shipments();
            $templateData = $shipmentService->getDetailsForSubmissionForms($shipmentId);
            $shipmentCode = $templateData['shipment']['shipment_code'];
            $submissionForm = new SubmissionForm();
            $submissionForm->SetMargins(0, 0, 0, false);
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
                    $mediumFontSize ,
                    '',
                    'default',
                    true
                );
                $submissionForm->SetTextColor(0, 0, 0);
                $submissionForm->SetXY(85, 19.5);
                $submissionForm->Write(0, $shipmentCode);
                $submissionForm->SetXY(154, 19.5);
                $submissionForm->Write(0, $templateData["country"][$participant["country"]]["country_name"]);
                $submissionForm->SetXY(237, 19.5);
                $submissionForm->Write(0, $templateData["shipment"]["lastdate_response"]);

                $submissionForm->SetFont(
                    $smallFontName,
                    'N',
                    $smallFontSize ,
                    '',
                    'default',
                    true
                );

                $submissionForm->SetXY(70, 38);
                $submissionForm->Write(0, $participant["participant_name"]);
                $submissionForm->SetXY(70, 49);
                $submissionForm->Write(0, $participant["pt_id"]);
                $submissionForm->SetXY(70, 60);
                $submissionForm->Write(0, $participant["username"]);
                $submissionForm->SetXY(70, 73.5);
                $submissionForm->Write(0, $participant["password"]);

                $submissionForm->SetFont(
                    $smallFontName,
                    'B',
                    $smallFontSize ,
                    '',
                    'default',
                    true
                );
                $submissionForm->SetXY(28, 127);
                $submissionForm->Write(0, $templateData["sample"][0]["sample_label"]);
                $submissionForm->SetXY(28, 134);
                $submissionForm->Write(0, $templateData["sample"][1]["sample_label"]);
                $submissionForm->SetXY(28, 141);
                $submissionForm->Write(0, $templateData["sample"][2]["sample_label"]);
                $submissionForm->SetXY(28, 148);
                $submissionForm->Write(0, $templateData["sample"][3]["sample_label"]);
                $submissionForm->SetXY(28, 155);
                $submissionForm->Write(0, $templateData["sample"][4]["sample_label"]);

                if($submissionForm->numPages > 1) {
                    for ($i = 2; $i <= $submissionForm->numPages; $i++) {
                        $submissionForm->endPage();
                        $submissionForm->_tplIdx = $submissionForm->importPage($i);
                        $submissionForm->AddPage();
                        if ($i == 2 && isset($templateData["country"][$participant["country"]]["pecc_details"]) &&
                            $templateData["country"][$participant["country"]]["pecc_details"] != "") {
                            $submissionForm->SetFont(
                                $smallFontName,
                                'N',
                                $smallFontSize ,
                                '',
                                'default',
                                true
                            );
                            $submissionForm->SetXY(36, 170.9);
                            $submissionForm->Write(0, "If you are experiencing challenges testing the panel or submitting results please contact");
                            $peccDetails = array_unique(explode(",", $templateData["country"][$participant["country"]]["pecc_details"]));
                            for ($ii = 0; $ii < count($peccDetails); $ii++) {
                                $peccDetailsString = "";
                                if ($ii > 0 && $ii == count($peccDetails) - 1) {
                                    $peccDetailsString .= "or ";
                                }
                                $peccDetailsString .= $peccDetails[$ii];
                                $submissionForm->SetXY(160, 170.9 + ($ii * 10));
                                $submissionForm->Write(0, $peccDetailsString);
                            }
                        }
                    }
                }
                $participantIndex++;
            }

            $submissionForm->Output(preg_replace('/[^A-Za-z0-9.]/', '-', $shipmentCode) . '_submission_forms.pdf', 'D');
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
