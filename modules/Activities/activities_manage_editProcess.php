<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

include '../../functions.php';
include '../../config.php';

//New PDO DB connection
$pdo = new Gibbon\sqlConnection();
$connection2 = $pdo->getConnection();

@session_start();

//Set timezone from session variable
date_default_timezone_set($_SESSION[$guid]['timezone']);

$gibbonActivityID = $_GET['gibbonActivityID'];
$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/'.getModuleName($_POST['address'])."/activities_manage_edit.php&gibbonActivityID=$gibbonActivityID&search=".$_GET['search'];

if (isActionAccessible($guid, $connection2, '/modules/Activities/activities_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if school year specified
    if ($gibbonActivityID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('gibbonActivityID' => $gibbonActivityID);
            $sql = 'SELECT * FROM gibbonActivity WHERE gibbonActivityID=:gibbonActivityID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            //Validate Inputs
            $name = $_POST['name'];
            $provider = $_POST['provider'];
            $active = $_POST['active'];
            $registration = $_POST['registration'];
            $dateType = $_POST['dateType'];
            if ($dateType == 'Term') {
                $gibbonSchoolYearTermIDList = '';
                if (isset($_POST['gibbonSchoolYearTermID'])) {
                    $terms = $_POST['gibbonSchoolYearTermID'];
                    $gibbonSchoolYearTermIDList = '';
                    for ($i = 0; $i < count($terms); ++$i) {
                        $gibbonSchoolYearTermIDList = $gibbonSchoolYearTermIDList.$terms[$i].',';
                    }
                    $gibbonSchoolYearTermIDList = substr($gibbonSchoolYearTermIDList, 0, -1);
                }
            } elseif ($dateType == 'Date') {
                $listingStart = dateConvert($guid, $_POST['listingStart']);
                $listingEnd = dateConvert($guid, $_POST['listingEnd']);
                $programStart = dateConvert($guid, $_POST['programStart']);
                $programEnd = dateConvert($guid, $_POST['programEnd']);
            }
            $gibbonYearGroupIDList = '';
            for ($i = 0; $i < $_POST['count']; ++$i) {
                if (isset($_POST["gibbonYearGroupIDCheck$i"])) {
                    if ($_POST["gibbonYearGroupIDCheck$i"] == 'on') {
                        $gibbonYearGroupIDList = $gibbonYearGroupIDList.$_POST["gibbonYearGroupID$i"].',';
                    }
                }
            }
            $gibbonYearGroupIDList = substr($gibbonYearGroupIDList, 0, (strlen($gibbonYearGroupIDList) - 1));
            $maxParticipants = $_POST['maxParticipants'];
            if (getSettingByScope($connection2, 'Activities', 'payment') == 'None' or getSettingByScope($connection2, 'Activities', 'payment') == 'Single') {
                $paymentOn = false;
                $payment = null;
            } else {
                $paymentOn = true;
                $payment = $_POST['payment'];
            }
            $description = $_POST['description'];

            if ($dateType == '' or $name == '' or $provider == '' or $active == '' or $registration == '' or $maxParticipants == '' or ($paymentOn and $payment == '') or ($dateType == 'Date' and ($listingStart == '' or $listingEnd == '' or $programStart == '' or $programEnd == ''))) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                //Scan through slots
                $partialFail = false;
                for ($i = 1; $i < 3; ++$i) {
                    $gibbonDaysOfWeekID = $_POST["gibbonDaysOfWeekID$i"];
                    $timeStart = $_POST["timeStart$i"];
                    $timeEnd = $_POST["timeEnd$i"];
                    $type = 'Internal';
                    if (isset($_POST['slot'.$i.'Location'])) {
                        $type = $_POST['slot'.$i.'Location'];
                    }
                    $gibbonSpaceID = null;
                    if ($type == 'Internal') {
                        if ($_POST["gibbonSpaceID$i"] != '') {
                            $gibbonSpaceID = $_POST["gibbonSpaceID$i"];
                        } else {
                            $gibbonSpaceID = null;
                        }
                        $locationExternal = '';
                    } else {
                        $locationExternal = $_POST['location'.$i.'External'];
                    }

                    if ($gibbonDaysOfWeekID != '' and $timeStart != '' and $timeEnd != '') {
                        try {
                            $data = array('gibbonActivityID' => $gibbonActivityID, 'gibbonDaysOfWeekID' => $gibbonDaysOfWeekID, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd, 'gibbonSpaceID' => $gibbonSpaceID, 'locationExternal' => $locationExternal);
                            $sql = 'INSERT INTO gibbonActivitySlot SET gibbonActivityID=:gibbonActivityID, gibbonDaysOfWeekID=:gibbonDaysOfWeekID, timeStart=:timeStart, timeEnd=:timeEnd, gibbonSpaceID=:gibbonSpaceID, locationExternal=:locationExternal';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }

                //Scan through staff
                $staff = null;
                if (isset($_POST['staff'])) {
                    $staff = $_POST['staff'];
                }
                $role = $_POST['role'];
                if ($role == '') {
                    $role = 'Other';
                }
                if (count($staff) > 0) {
                    foreach ($staff as $t) {
                        //Check to see if person is already registered in this activity
                        try {
                            $dataGuest = array('gibbonPersonID' => $t, 'gibbonActivityID' => $gibbonActivityID);
                            $sqlGuest = 'SELECT * FROM gibbonActivityStaff WHERE gibbonPersonID=:gibbonPersonID AND gibbonActivityID=:gibbonActivityID';
                            $resultGuest = $connection2->prepare($sqlGuest);
                            $resultGuest->execute($dataGuest);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        if ($resultGuest->rowCount() == 0) {
                            try {
                                $data = array('gibbonPersonID' => $t, 'gibbonActivityID' => $gibbonActivityID, 'role' => $role);
                                $sql = 'INSERT INTO gibbonActivityStaff SET gibbonPersonID=:gibbonPersonID, gibbonActivityID=:gibbonActivityID, role=:role';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                echo "here<div class='error'>".$e->getMessage().'</div>';
                                $partialFail = true;
                            }
                        }
                    }
                }

                //Write to database
                $type = $_POST['type'];

                try {
                    if ($dateType == 'Date') {
                        $data = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID'], 'gibbonActivityID' => $gibbonActivityID, 'name' => $name, 'provider' => $provider, 'type' => $type, 'active' => $active, 'registration' => $registration, 'listingStart' => $listingStart, 'listingEnd' => $listingEnd, 'programStart' => $programStart, 'programEnd' => $programEnd, 'gibbonYearGroupIDList' => $gibbonYearGroupIDList, 'maxParticipants' => $maxParticipants, 'payment' => $payment, 'description' => $description);
                        $sql = "UPDATE gibbonActivity SET gibbonSchoolYearID=:gibbonSchoolYearID, name=:name, provider=:provider, type=:type, active=:active, registration=:registration, gibbonSchoolYearTermIDList='', listingStart=:listingStart, listingEnd=:listingEnd, programStart=:programStart, programEnd=:programEnd, gibbonYearGroupIDList=:gibbonYearGroupIDList, maxParticipants=:maxParticipants, payment=:payment, description=:description WHERE gibbonActivityID=:gibbonActivityID";
                    } else {
                        $data = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID'], 'gibbonActivityID' => $gibbonActivityID, 'name' => $name, 'provider' => $provider, 'type' => $type, 'active' => $active, 'registration' => $registration, 'gibbonSchoolYearTermIDList' => $gibbonSchoolYearTermIDList, 'gibbonYearGroupIDList' => $gibbonYearGroupIDList, 'maxParticipants' => $maxParticipants, 'payment' => $payment, 'description' => $description);
                        $sql = 'UPDATE gibbonActivity SET gibbonSchoolYearID=:gibbonSchoolYearID, name=:name, provider=:provider, type=:type, active=:active, registration=:registration, gibbonSchoolYearTermIDList=:gibbonSchoolYearTermIDList, listingStart=NULL, listingEnd=NULL, programStart=NULL, programEnd=NULL, gibbonYearGroupIDList=:gibbonYearGroupIDList, maxParticipants=:maxParticipants, payment=:payment, description=:description WHERE gibbonActivityID=:gibbonActivityID';
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($partialFail == true) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
