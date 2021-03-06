<?php

class Application_Model_DbTable_Shipments extends Zend_Db_Table_Abstract {

    protected $_name = 'shipment';
    protected $_primary = 'shipment_id';
    protected $_session = null;

    public function __construct() {
        parent::__construct();
        $this->_session = new Zend_Session_Namespace('datamanagers');
    }

    public function getShipmentData($sId, $pId) {
        return $this->getAdapter()->fetchRow(
            $this->getAdapter()->select()
                ->from(array('s' => $this->_name))
				->join(array('sl'=>'scheme_list'),'s.scheme_type=sl.scheme_id',array('scheme_name'))
                ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
                ->joinLeft(array('rntr' => 'response_not_tested_reason'),
                    'rntr.not_tested_reason_id=sp.not_tested_reason',array('NotTestedReason'=>'not_tested_reason'))
                ->where("s.shipment_id = ?", $sId)
                ->where("sp.participant_id = ?", $pId));
    }

    public function getShipmentRowInfo($sId) {
        $result = $this->getAdapter()
            ->fetchRow(
                $this->getAdapter()->select()
                    ->from(array('s' => 'shipment'))
                    ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
                    ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('sl.scheme_name'))
                    ->group('s.shipment_id')
                    ->where("s.shipment_id = ?", $sId));
        if ($result != "") {
            $tableName = "reference_result_dts";
            if ($result['scheme_type'] == 'vl') {
                $tableName = "reference_result_vl";
            } else if($result['scheme_type'] == 'eid') {
                $tableName = "reference_result_eid";
            } else if($result['scheme_type'] == 'dts') {
                $tableName = "reference_result_dts";
            }
            $result['referenceResult'] = $this->getAdapter()->fetchAll(
                $this->getAdapter()->select()
                    ->from(array($tableName))
                    ->where('shipment_id = ? ',$result['shipment_id']));
        }
        return $result;
    }

    public function getSampleCount($shipmentID, $lessExcluded = true, $lessExempt = true) {
        $query = $this->getAdapter()->select()
                    ->from(array('r' => 'reference_result_tb'))
                    ->where("s.shipment_id = ?", $shipmentID);

        if($lessExcluded) $query->where("is_excluded = 'no'");
        if($lessExempt) $query->where("is_exempt = 'no'");

        $result = $this->getAdapter()->fetchRow($query);

        return count($result);
    }

    public function getTbShipmentRowInfo($distributionId) {
        $result=$this->getAdapter()->fetchRow($this->getAdapter()->select()->from(array('s' => 'shipment'))
            ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('sl.scheme_name'))
            ->group('s.shipment_id')
            ->where("s.distribution_id = ?", $distributionId)
            ->where("s.scheme_type = 'tb'"));
        if ($result != "") {
            $result['referenceResult']=$this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array("reference_result_tb"))
                ->where('shipment_id = ? ',$result['shipment_id']));
        }
        return $result;
    }

    public function getTbShipmentRowInfoByShipmentCode($shipmentCode) {
        $result = $this->getAdapter()->fetchRow(
            $this->getAdapter()
                ->select()
                ->from(array('s' => $this->_name))
                ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id',
                    array('distribution_code', 'distribution_date'))
                ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type',
                    array('sl.scheme_name'))
                ->group('s.shipment_id')
                ->where("s.shipment_code = ?", $shipmentCode)
                ->where("s.scheme_type = 'tb'"));
        return $result;
    }

    public function updateShipmentStatus($shipmentId, $status) {
        if (isset($status) && $status != null && $status != "") {
            return $this->update(array('status' => $status), "shipment_id = $shipmentId");
        } else {
            return 0;
        }
    }

    public function responseSwitch($shipmentId, $switchStatus) {
        if (isset($switchStatus) && $switchStatus != null && $switchStatus != "") {
			$this->update(array('response_switch' => $switchStatus), "shipment_id = $shipmentId");
			return "Shipment status updated to $switchStatus successfully";
        } else {
            return "Unable to update Shipment status updated to $switchStatus. Please try again later.";
        }
    }

    public function updateShipmentStatusByDistribution($distributionId, $status) {
        if (isset($status) && $status != null && $status != "") {
            return $this->update(array(
                'response_switch' => 'on',
                'status' => $status),
                "distribution_id = $distributionId");
        } else {
            return 0;
        }
    }

    public function getShipmentShippedPushNotifications($distributionId) {
        $query = $this->getAdapter()->select()
            ->from(array('s' => 'shipment'), array('shipment_id', 'shipment_code'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id = s.shipment_id', array())
            ->join(array('p' => 'participant'), 'p.participant_id = spm.participant_id', array('lab_name', 'participant_id'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id = spm.participant_id', array())
            ->join(array('pnt' => 'push_notification_token'), 'pnt.dm_id = pmm.dm_id', array('push_notification_token'))
            ->where('s.distribution_id = ?', $distributionId);

        return $this->getAdapter()->fetchAll($query);
    }

    public function getShipmentFinalisedPushNotifications($shipmentId) {
        $query = $this->getAdapter()->select()
            ->from(array('s' => 'shipment'), array('shipment_id', 'shipment_code'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id = s.shipment_id', array())
            ->join(array('p' => 'participant'), 'p.participant_id = spm.participant_id', array('lab_name', 'participant_id'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id = spm.participant_id', array())
            ->join(array('pnt' => 'push_notification_token'), 'pnt.dm_id = pmm.dm_id', array('push_notification_token'))
            ->where('s.shipment_id = ?', $shipmentId);

        $results = $this->getAdapter()->fetchAll($query);

        $output = [];
        foreach ($results as $result) {
            $output[] = $result;
        }
        return $output;
    }

    public function getPendingShipmentsByDistribution($distributionId) {
        return $this->fetchAll("status ='pending' AND distribution_id = $distributionId");
    }

    public function getShipmentOverviewDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'scheme_name');
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    if ($parameters['iSortCol_' . $i] == 0) {
                        $sOrder .= "MAX(shipment_date) " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= "MAX(".$aColumns[intval($parameters['iSortCol_' . $i])] . ")
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display */
        $sQuery = $this->getAdapter()->select()
            ->from(array('s' => 'shipment'), array(
                's.scheme_type',
                'SHIP_YEAR' => 'year(s.shipment_date)',
                'TOTALSHIPMEN' => new Zend_Db_Expr("COUNT('s.shipment_id')")))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id',
                    array(
                        'ONTIME' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,3,1) WHEN 1 THEN 1 END)"),
                        'NORESPONSE' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,2,1) WHEN 9 THEN 1 END)"),
                        'reported_count' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,4,1) WHEN '1' THEN 1 WHEN '2' THEN 1 END)")
                    ))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id', array())
			->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->where("s.status = 'shipped' OR s.status = 'evaluated' OR s.status = 'finalized'")
            ->where("year(s.shipment_date) + 5 > year(CURDATE())")
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->group('s.scheme_type')
            ->group('SHIP_YEAR');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()
            ->from(array('s' => 'shipment'), array(
                's.scheme_type',
                'SHIP_YEAR' => 'year(s.shipment_date)',
                'TOTALSHIPMEN' => new Zend_Db_Expr("COUNT('s.shipment_id')")))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array(
                'ONTIME' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,3,1) WHEN 1 THEN 1 END)"),
                'NORESPONSE' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,2,1) WHEN 9 THEN 1 END)"),
                'reported_count' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,4,1) WHEN '1' THEN 1 WHEN '2' THEN 1 END)")))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id', array())
			->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array())
            ->where("s.status = 'shipped' OR s.status = 'evaluated' OR s.status = 'finalized'")
            ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
            ->where("pmm.dm_id = ?", $this->_session->dm_id)
            ->group('s.scheme_type')
            ->group('SHIP_YEAR');

        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['SHIP_YEAR'];
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['TOTALSHIPMEN'];
            $row[] = $aRow['ONTIME'];
            $row[] = $aRow['TOTALSHIPMEN'] - $aRow['reported_count'];

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentCurrentDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
        $aColumns = array('DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_name', 'shipment_code','unique_identifier', 'first_name', 'DATE_FORMAT(lastdate_response,"%d-%b-%Y")', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');
        $orderColumns = array('shipment_date','scheme_name','shipment_code','unique_identifier', 'first_name', 'lastdate_response', 'spm.shipment_test_report_date');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */
		$sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array(
            's.scheme_type',
            's.shipment_date',
            's.shipment_code',
            's.lastdate_response',
            's.shipment_id',
            's.status',
            's.response_switch'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(
                "spm.map_id",
                "spm.attributes",
                "spm.evaluation_status",
                "spm.participant_id",
                "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')",
                "spm.shipment_receipt_date",
                "qc_done",
                "qc_date",
                "qc_done_by",
                "supervisor_approval",
                "participant_supervisor",
                "user_comment"
            ))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(
                'p.unique_identifier','p.first_name', 'p.last_name'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status='shipped' OR s.status='evaluated'");

		if (isset($parameters['currentType'])) {
			if ($parameters['currentType'] == 'active') {
				$sQuery = $sQuery->where("s.response_switch = 'on'");
			} else if ($parameters['currentType'] == 'inactive') {
				$sQuery = $sQuery->where("s.response_switch = 'off'");
			}
		}
        if (isset($parameters['received'])) {
            if ($parameters['received'] == 'yes') {
                //evaluation_status[1] = 1 (Received) AND evaluation_status[2] = 9 (Not Responded)
                $sQuery = $sQuery->where("(spm.shipment_receipt_date IS NOT NULL OR substr(spm.evaluation_status, 2, 1) = '1') AND substr(spm.evaluation_status, 3, 1) = '9'");
            } else {
                //evaluation_status[1] = 1 (Not Received) AND evaluation_status[2] = 9 (Not Responded)
                $sQuery = $sQuery->where("spm.shipment_receipt_date IS NULL AND substr(spm.evaluation_status, 2, 1) = '9' AND substr(spm.evaluation_status, 3, 1) = '9'");
            }
        }
        if (isset($parameters['submitted']) && $parameters['submitted'] == 'yes') {
            //evaluation_status[2] = 1 (Responded)
            $sQuery = $sQuery->where("substr(spm.evaluation_status, 3, 1) = '1'");
        }
        if (isset($parameters['sid'])) {
            $sQuery = $sQuery->where("spm.shipment_id = ".$parameters['sid']);
        }
        if (isset($parameters['pid'])) {
            $sQuery = $sQuery->where("spm.participant_id = ".$parameters['pid']);
        }
        if (isset($parameters["forMobileApp"]) && $parameters["forMobileApp"]) {
            $sQuery = $sQuery->where("s.scheme_type = 'tb'");
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id','s.status'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id'))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier','p.first_name', 'p.last_name'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'")
                ->where("year(s.shipment_date) + 5 > year(CURDATE())");

		if (isset($parameters['currentType'])) {
			if ($parameters['currentType'] == 'active') {
				$sQuery = $sQuery->where("s.response_switch = 'on'");
			} else if ($parameters['currentType'] == 'inactive'){
				$sQuery = $sQuery->where("s.response_switch = 'off'");
			}
		}
        if (isset($parameters['received'])) {
            if ($parameters['received'] == 'yes') {
                //evaluation_status[1] = 1 (Received) AND evaluation_status[2] = 9 (Not Responded)
                $sQuery = $sQuery->where("(spm.shipment_receipt_date IS NOT NULL OR substr(spm.evaluation_status, 2, 1) = '1') AND substr(spm.evaluation_status, 3, 1) = '9'");
            } else {
                //evaluation_status[1] = 1 (Not Received) AND evaluation_status[2] = 9 (Not Responded)
                $sQuery = $sQuery->where("spm.shipment_receipt_date IS NULL AND substr(spm.evaluation_status, 2, 1) = '9' AND substr(spm.evaluation_status, 3, 1) = '9'");
            }
        }
        if (isset($parameters['sid'])) {
            $sQuery = $sQuery->where("spm.shipment_id = ".$parameters['sid']);
        }
        if (isset($parameters['pid'])) {
            $sQuery = $sQuery->where("spm.participant_id = ".$parameters['pid']);
        }
        if (isset($parameters["forMobileApp"]) && $parameters["forMobileApp"]) {
            $sQuery = $sQuery->where("s.scheme_type = 'tb'");
        }
        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array(),
            "message" => ""
        );
        if (isset($parameters["sEcho"])) {
            $output["sEcho"] = intval($parameters['sEcho']);
        }

        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            if (isset($parameters["forMobileApp"]) && $parameters["forMobileApp"]) {
                $row = array(
                    "shipmentDate" => Pt_Commons_General::dbDateToString($aRow['shipment_date']),
                    "schemeType" => $aRow['scheme_type'],
                    "schemeName" => $aRow['scheme_name'],
                    "shipmentId" => $aRow['shipment_id'],
                    "shipmentCode" => $aRow['shipment_code'],
                    "participantId" => $aRow['participant_id'],
                    "participantCode" => $aRow["unique_identifier"],
                    "fullName" => $aRow['first_name'] . " " . $aRow['last_name'],
                    "deadlineDate" => Pt_Commons_General::dbDateToString($aRow['lastdate_response']),
                    "responseDate" => Pt_Commons_General::dbDateToString($aRow['RESPONSEDATE']),
                    "dateReceived" => Pt_Commons_General::dbDateToString($aRow['shipment_receipt_date']),
                    "evaluationStatus" => $aRow['evaluation_status']
                );
            } else {
                $isEditable=$shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'],$aRow['participant_id']);
                $row = array();
                $row[] = $general->humanDateFormat($aRow['shipment_date']);
                $row[] = ($aRow['scheme_name']);
                $row[] = $aRow['shipment_code'];
                $row[] = $aRow['unique_identifier'];
                $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
                $row[] = $general->humanDateFormat($aRow['lastdate_response']);
                $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);

                $buttonText = "View/Edit";
                $download = '';
                $delete = '';
                if ($isEditable) {
                    if ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00') {
                        if ($this->_session->view_only_access == 'no') {
                            $delete = '<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type'] . '\',\'' . base64_encode($aRow['map_id']) . '\')" class="btn btn-danger" style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
                        }
                    } else {
                        $buttonText = "Enter Response";
                        $download = '<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default"  style="margin:3px 0;" target="_BLANK" download > <i class="icon icon-download"></i> Download Form</a>';
                    }
                }

                $row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-success" style="margin:3px 0;"> <i class="icon icon-edit"></i>  ' . $buttonText . ' </a>'
                    . $delete
                    . $download;
            }

            $output['aaData'][] = $row;
        }
        if (count($output['aaData']) == 0) {
            $output["message"] = "Could not get shipment using your login details. Are you logged in as the correct user?";
        }

        echo json_encode($output);
    }

    public function getShipmentDefaultDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_name', 'shipment_code', 'unique_identifier','first_name', 'DATE_FORMAT(lastdate_response,"%d-%b-%Y")', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.status','SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id','s.response_switch'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id = s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "ACTION" => new Zend_Db_Expr("CASE WHEN substr(spm.evaluation_status, 2, 1) = '1' THEN 'View' WHEN (substr(spm.evaluation_status, 2, 1) = '9' AND s.lastdate_response >= CURDATE()) OR (s.status = 'finalized') THEN 'Enter Result' END"), "STATUS" => new Zend_Db_Expr("CASE substr(spm.evaluation_status, 3, 1) WHEN 1 THEN 'On Time' WHEN '2' THEN 'Late' WHEN '0' THEN 'No Response' END")))
				->join(array('sl' => 'scheme_list'), 'sl.scheme_id = s.scheme_type', array('scheme_name'))
                ->join(array('p' => 'participant'), 'p.participant_id = spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id = p.participant_id')
                ->where("pmm.dm_id = ?", $this->_session->dm_id)
                ->where("s.status = 'shipped' OR s.status = 'evaluated'")
                ->where("year(s.shipment_date) + 5 > year(CURDATE())")
                ->where("s.lastdate_response <  CURDATE()")
                ->where("substr(spm.evaluation_status, 3, 1) <> '1'")
                ->order('s.shipment_date')
                ->order('spm.participant_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id = s.shipment_id', array(''))
                ->join(array('p' => 'participant'), 'p.participant_id = spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id = p.participant_id', array(''))
                ->where("pmm.dm_id = ?", $this->_session->dm_id)
                ->where("s.status = 'shipped' OR s.status = 'evaluated'")
                ->where("year(s.shipment_date) + 5 > year(CURDATE())")
                ->where("s.lastdate_response < CURDATE()")
                ->where("substr(spm.evaluation_status, 3, 1) <> '1'");

        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $isEditable=$shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'],$aRow['participant_id']);
            $row = array();
            if ($aRow['ACTION'] == "View") {
                $aRow['ACTION'] = "View";
                if ($aRow['response_switch'] == 'on' && $aRow['status'] != 'finalized') {
                    $aRow['ACTION'] = "Edit/View";
                }
            }

            $row[] = $aRow['SHIP_YEAR'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['lastdate_response']);
            $row[] = $aRow['STATUS'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);

			$buttonText = "View/Edit";
			$download = '';
			$delete = '';
			if ($isEditable) {
				if ($aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00') {
					if ($this->_session->view_only_access == 'no') {
						$delete='<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type']. '\',\'' . base64_encode($aRow['map_id']) . '\')" class="btn btn-danger"  style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
					}
				} else {
					$buttonText = "Enter Response";
					$download='<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default" style="margin:3px 0;" target="_BLANK" download> <i class="icon icon-download"></i> Download Form</a>';
				}
			}
			$row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'].'/comingFrom/defaulted-schemes' . '" class="btn btn-success"  style="margin:3px 0;"> <i class="icon icon-edit"></i>  '.$buttonText.' </a>'
					.$delete
					.$download;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentAllDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('s.shipment_id','year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_name', 'shipment_code','unique_identifier','first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id','s.status','s.response_switch'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.report_generated','spm.map_id', "spm.evaluation_status","qc_date", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE  WHEN substr(spm.evaluation_status,3,1)='1' THEN 'View' WHEN (substr(spm.evaluation_status,3,1)='9' AND s.lastdate_response >= CURDATE()) OR (substr(spm.evaluation_status,3,1)='9' AND s.status= 'finalized') THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
				->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier','p.first_name', 'p.last_name','p.participant_id'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
		if (isset($parameters['qualityChecked']) && trim($parameters['qualityChecked'])!="") {
            if ($parameters['qualityChecked']=='yes') {
                $sQuery = $sQuery->where("spm.qc_date IS NOT NULL");
			} else {
				$sQuery = $sQuery->where("spm.qc_date IS NULL");
			}
		}
        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        //$sQuery = $this->getAdapter()->select()->from('building_type', new Zend_Db_Expr("COUNT('building_id')"));
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(''))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier','p.first_name', 'p.last_name','p.participant_id'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'")
            ->where("year(s.shipment_date)  + 5 > year(CURDATE())");

        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
		$globalQcAccess = Application_Service_Common::getConfig('qc_access');
        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $qcChkbox='';
			$qcResponse='';

            $isEditable=$shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'],$aRow['participant_id']);
            $row = array();
            if ($aRow['RESPONSE'] == "View") {
                $aRow['RESPONSE'] = "View";
                if ($aRow['response_switch'] == 'on' && $aRow['status'] != 'finalized') {
                    $aRow['RESPONSE'] = "Edit/View";
                }
            }

			$qcBtnText = " Quality Check";
			if ($aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00') {
				if ($aRow['qc_date']!="") {
					$qcBtnText = " Edit Quality Check";
					$aRow['qc_date']=$general->humanDateFormat($aRow['qc_date']);
				}
				if ($globalQcAccess=='yes') {
					if ($this->_session->qc_access=='yes') {
					    $qcChkbox='<input type="checkbox" class="checkTablePending" name="subchk[]" id="'. $aRow['map_id'].'"  value="' . $aRow['map_id'] . '" onclick="addQc(\''.$aRow['map_id'].'\',this);"  />';
						$qcResponse='<br/><a href="javascript:void(0);" onclick="addSingleQc(\''.$aRow['map_id'].'\',\''.$aRow['qc_date'].'\')" class="btn btn-primary"  style="margin:3px 0;"> <i class="icon icon-edit"></i>'. $qcBtnText.'</a>';
					}
				}
			}
			$row[] = $qcChkbox;
            $row[] = $aRow['SHIP_YEAR'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);

			$buttonText = "View";
			$download = '';
			$delete = '';


			if ($isEditable) {
				if ($aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00') {
					if ($this->_session->view_only_access=='no') {
						$delete='<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type']. '\',\'' . base64_encode($aRow['map_id']) . '\')" class="btn btn-danger"  style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
					}
				} else {
					$buttonText = "Enter Response";
					$download='<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default"  style="margin:3px 0;" target="_BLANK"> <i class="icon icon-download"></i> Download Form</a>';
				}
			}

			$row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'].'/comingFrom/all-schemes' . '" class="btn btn-success"  style="margin:3px 0;"> <i class="icon icon-edit"></i>  '.$buttonText.' </a>'
                .$delete
				.$download
				.$qcResponse;

			$downloadReports= " N.A. ";
            if ($aRow['status']=='finalized') {
                 $downloadReports = '<a href="/uploads/reports/' . $aRow['shipment_code']. '/'.$aRow['shipment_code'].'-summary.pdf" class="btn btn-primary" style="text-decoration : none;overflow:hidden;" target="_BLANK" download><i class="icon icon-download"></i> Summary Report</a>
				                    <a href="/participant/download/d92nl9d8d/' . base64_encode($aRow['map_id']) . '"  style="text-decoration : none;overflow:hidden;margin-top:4px;" class="btn btn-info" target="_BLANK" download> <i class="icon icon-download"></i> Individual ' . $aRow['REPORT'] . '</a>';
            }
            $row[] = $downloadReports;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentReportDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_type', 'shipment_code');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.shipment_id','s.status'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', "spm.participant_id"))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
                ->join(array('dm' => 'data_manager'), 'dm.dm_id=pmm.dm_id', array('dm.institute'))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->group('s.shipment_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */

        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(''))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(''))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->group('s.shipment_id');

        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = array();
            $report = "";
            $fileSafeShipmentCode = str_replace(array_merge(
                array_map('chr', range(0, 31)),
                array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
            ), '', $aRow['shipment_code']);
            $fileSafeInstitute = "";
            if (isset($aRow['institute']) && $aRow['institute'] != "") {
                $fileSafeInstitute = "-" . str_replace(array_merge(
                    array_map('chr', range(0, 31)),
                    array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
                ), '', $aRow['institute']);
            }
            $fileName = $fileSafeShipmentCode . $fileSafeInstitute . $aRow['dm_id'] . ".pdf";
            $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $fileName);
            $fileName = str_replace(" ", "-", $fileName);

            $row[] = $aRow['SHIP_YEAR'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];

            $filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $fileSafeShipmentCode . DIRECTORY_SEPARATOR . $fileName;
            if (file_exists($filePath) && $aRow['status']=='finalized' ) {
                $downloadFilePath = "/uploads" . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $fileSafeShipmentCode . DIRECTORY_SEPARATOR . $fileName;
                $report = '<a href="' . $downloadFilePath . '"  style="text-decoration : underline;" target="_BLANK">Report</a>';
            }
            $row[] = $report;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getindividualReportDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
        $aColumns = array('scheme_type', 'shipment_code', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'unique_identifier','first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');
        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;
        $sTable = $this->_name;
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }
        /*
         * Ordering
         */
        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }
        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */
        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }
        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }
        /*
         * SQL queries
         * Get data to display
         */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array(
                'SHIP_YEAR' => 'year(s.shipment_date)',
                's.scheme_type',
                's.shipment_date',
                's.shipment_code',
                's.lastdate_response',
                's.shipment_id'
            ))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(
                'spm.map_id',
                "spm.evaluation_status",
                "spm.participant_id",
                "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')",
                "RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"),
                "REPORT" => new Zend_Db_Expr("CASE WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")
            ))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(
                'p.unique_identifier',
                'p.first_name',
                'p.last_name'
            ))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(''))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(
                'p.unique_identifier',
                'p.first_name',
                'p.last_name'
            ))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");

        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
			$row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);
            $row[] = '<a href="/participant/download/d92nl9d8d/' . base64_encode($aRow['map_id']) . '"  style="text-decoration : underline;" target="_BLANK" download>' . $aRow['REPORT'] . '</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getSummaryReportDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('scheme_type', 'shipment_code', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code','s.status'))
                ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");


        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $$sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code'))
                ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");


        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $fileSafeShipmentCode = str_replace(array_merge(
                array_map('chr', range(0, 31)),
                array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
            ), '', $aRow['shipment_code']);
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $fileSafeShipmentCode . DIRECTORY_SEPARATOR .$fileSafeShipmentCode. "-summary.pdf") && $aRow['status']=='finalized') {
                 $row[] = '<a href="/uploads/reports/' . $fileSafeShipmentCode. '/'.$fileSafeShipmentCode.'-summary.pdf"  style="text-decoration : none;" download target="_BLANK">Download Report</a>';
            } else {
                $row[] = '';
            }
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getAllShipmentFormDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        //$aColumns = array('project_name','project_code','e.employee_name','client_name','architect_name','project_value','building_type_name','DATE_FORMAT(p.project_date,"%d-%b-%Y")','DATE_FORMAT(p.deadline,"%d-%b-%Y")','refered_by','emp.employee_name');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $aColumns = array("sl.scheme_name", "shipment_code", 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')");
        $orderColumns = array("sl.scheme_name", "shipment_code", 'distribution_code', 'distribution_date');


        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "shipment_id";


        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */
        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
						" . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }

            $sOrder = substr_replace($sOrder, "", -2);
        }
        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $db->select()->from(array('s' => 'shipment'))
					->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
					->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type',
                        array('SCHEME' => 'sl.scheme_name'))
					->group('s.shipment_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        //die($sQuery);

        $rResult = $db->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $db->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $db->select()->from('shipment', new Zend_Db_Expr("COUNT('shipment_id')"));
        $aResultTotal = $db->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['SCHEME'];
            $row[] = $aRow['distribution_code'];
            $row[] = Application_Service_Common::ParseDateHumanFormat($aRow['distribution_date']);
			$row[] = '<a href="/shipment-form/download/sId/' . base64_encode($aRow['shipment_id']) . '"  style="text-decoration : underline;" target="_BLANK" download> Download Report</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

	public function fetchAllFinalizedShipments($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array("DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'distribution_code', 's.shipment_code' ,'d.status');
        $orderColumns = array('distribution_date', 'distribution_code', 's.shipment_code' ,'d.status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'distribution_id';

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */
        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }

            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */
        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if($aColumns[$i] == "" || $aColumns[$i] == null){
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $authNameSpace = new Zend_Session_Namespace('administrators');
		$dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('d' => 'distributions'))
				->joinLeft(array('s'=>'shipment'),'s.distribution_id=d.distribution_id',array(
				    'shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')")
                ));
        if ($authNameSpace->is_ptcc_coordinator) {
            $sQuery = $sQuery->joinLeft(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id', array())
                ->joinLeft(array('p' => 'participant'), 'spm.participant_id=p.participant_id', array())
                ->where("p.country IS NULL OR p.country IN (".implode(",", $authNameSpace->countries).")");
        }
        $sQuery = $sQuery
            ->where("s.status='finalized'")
            ->group('d.distribution_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //die($sQuery);
        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
		$sQuery = $dbAdapter->select()->from(array('d' => 'distributions'))
				->joinLeft(array('s'=>'shipment'),'s.distribution_id=d.distribution_id',array(''))
				->where("s.status='finalized'")
				->group('d.distribution_id');
        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        $shipmentDb = new Application_Model_DbTable_Shipments();
        foreach ($rResult as $aRow) {

            $shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);

            $row = array();
	    $row['DT_RowId']="dist".$aRow['distribution_id'];
            $row[] = Application_Service_Common::ParseDateHumanFormat($aRow['distribution_date']);
            $row[] = $aRow['distribution_code'];
            $row[] = $aRow['shipments'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="getShipmentInReports(\''.($aRow['distribution_id']).'\')"><span><i class="icon-search"></i> View</span></a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fetchParticipantSchemesBySchemeId($parameters){
	    /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('DATE_FORMAT(shipment_date,"%d-%b-%Y")','shipment_code','unique_identifier','first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")','shipment_score');
        $orderColumns = array('shipment_date','shipment_code','unique_identifier','first_name', 'spm.shipment_test_report_date','shipment_score');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */

         $sQuery = $this->getAdapter()->select()
             ->from(array('s' => 'shipment'), array(
                 's.scheme_type',
                 's.shipment_date',
                 's.shipment_code',
                 's.lastdate_response',
                 's.shipment_id',
                 's.status', 's.response_switch'))
			->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(
			    'spm.report_generated',
                'spm.map_id',
                "spm.evaluation_status",
                "qc_date",
                "spm.participant_id",
                "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')",'spm.shipment_score'))
			->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
			->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(
			    'p.unique_identifier',
                'p.first_name',
                'p.last_name',
                'p.participant_id'))
			->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
			->where("pmm.dm_id=?", $this->_session->dm_id)
			->where("s.scheme_type=?", $parameters['scheme']);

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
         $tQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array(
             's.scheme_type',
             's.shipment_date',
             's.shipment_code',
             's.lastdate_response',
             's.shipment_id',
             's.status',
             's.response_switch'))
             ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(
                 'spm.report_generated',
                 'spm.map_id',
                 "spm.evaluation_status",
                 "qc_date",
                 "spm.participant_id",
                 "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')",
                 'spm.shipment_score'))
             ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
             ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(
                 'p.unique_identifier',
                 'p.first_name',
                 'p.last_name','p.participant_id'))
             ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
			->where("pmm.dm_id=?", $this->_session->dm_id)
			->where("s.scheme_type=?", $parameters['scheme']);
         $aResultTotal = $this->getAdapter()->fetchAll($tQuery);
         $shipmentArray = array();
         foreach ($aResultTotal as $total) {
             if (!in_array($total['shipment_code'],$shipmentArray)) {
                 $shipmentArray[] = $total['shipment_code'];
             }
         }
         $iTotal = count($aResultTotal);
         /*
          * Output
         */
         $output = array(
             "sEcho" => intval($parameters['sEcho']),
             "iTotalRecords" => $iTotal,
             "iTotalDisplayRecords" => $iFilteredTotal,
             "aaData" => array()
         );
         $general = new Pt_Commons_General();
         foreach ($rResult as $aRow) {
             $row = array();
             $row[] = $general->humanDateFormat($aRow['shipment_date']);
             $row[] = $aRow['shipment_code'];
             $row[] = $aRow['unique_identifier'];
             $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
             $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);
             $row[] = $aRow['shipment_score'];
             $output['aaData'][] = $row;
         }
         $output['shipmentArray'] = $shipmentArray;
         echo json_encode($output);
    }
}
