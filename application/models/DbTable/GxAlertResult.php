<?php

class Application_Model_DbTable_GxAlertResult extends Zend_Db_Table_Abstract {

    protected $_name = 'gxalert_result';
    protected $_primary = 'result_id';

    public function saveResult($shipmentMapId, $sampleId, $participantUniqueId, $gxAlertTestId,
                               $gxAlertDeploymentId, $gxAlertMessageSentOn, $patientId, $resultSampleId,
                               $testStartedOn, $testEndedOn, $mtbResult, $rifResult, $errorCode, $probeD,
                               $probeC, $probeE, $probeB, $spc, $probeA, $assayName, $reagentLotId,
                               $cartridgeExpirationDate, $cartridgeSerial, $labName, $xpertHostId,
                               $xpertSenderUser, $instrumentSerialNumber, $instrumentInstalledOn,
                               $instrumentLastCalibrated, $country, $state, $district, $city,
                               $moduleSerial, $moduleName, $countTestsLast30Days, $countErrorsLast30Days,
                               $errorCodesLast30Days) {
        $valuesArray = array(
            "map_id" => $shipmentMapId,
            "sample_id" => $sampleId,
            "participant_unique_id" => $participantUniqueId,
            "gxalert_test_id" => $gxAlertTestId,
            "gxalert_deployment_id" => $gxAlertDeploymentId,
            "gxalert_message_sent_on" => $gxAlertMessageSentOn,
            "result_patient_id" => $patientId,
            "result_sample_id" => $resultSampleId,
            "test_started_on" => $testStartedOn,
            "test_ended_on" => $testEndedOn,
            "mtb_result" => $mtbResult,
            "rif_result" => $rifResult,
            "error_code" => $errorCode,
            "probe_d" => $probeD,
            "probe_c" => $probeC,
            "probe_e" => $probeE,
            "probe_b" => $probeB,
            "spc" => $spc,
            "probe_a" => $probeA,
            "assay_name" => $assayName,
            "reagent_lot_id" => $reagentLotId,
            "cartridge_expiration_date" => $cartridgeExpirationDate,
            "cartridge_serial_number" => $cartridgeSerial,
            "lab_name" => $labName,
            "xpert_host_id" => $xpertHostId,
            "xpert_sender_user" => $xpertSenderUser,
            "instrument_serial_number" => $instrumentSerialNumber,
            "instrument_installed_on" => $instrumentInstalledOn,
            "instrument_last_calibrated_on" => $instrumentLastCalibrated,
            "country" => $country,
            "state" => $state,
            "district" => $district,
            "city" => $city,
            "module_serial_number" => $moduleSerial,
            "module_name" => $moduleName,
            "test_count_last_30_days" => $countTestsLast30Days,
            "error_count_last_30_days" => $countErrorsLast30Days,
            "error_codes_encountered_last_30_days" => $errorCodesLast30Days
        );
        $existingRow = $this->fetchRow("map_id = " . $shipmentMapId . " AND sample_id = " . $sampleId);
        if ($existingRow != "" && $existingRow["result_id"] > 0) {
            $this->update(
                array_merge($valuesArray, array("updated_on" => new Zend_Db_Expr("now()"))),
                "result_id = " . $existingRow["result_id"]);
            return $existingRow["result_id"];
        } else {
            return $this->insert(
                array_merge($valuesArray, array(
                    "created_on" => new Zend_Db_Expr("now()"),
                    "updated_on" => new Zend_Db_Expr("now()")
                )));

        }
    }
}
