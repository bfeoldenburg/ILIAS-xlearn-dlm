<?php
	// ToDo:
	//  identifier
	//  metadata
	//  extra cron-user (admin)

	// require_once "./include/inc.header.php";
	require_once './libs/composer/vendor/autoload.php';
	//include_once './Services/Cron/classes/class.ilCronStartUp.php';

	function console_log( $data ){
		echo '<script>';
		echo 'console.log('. json_encode( $data ) .')';
		echo '</script>';
	 }

	function print_r2($val){
		echo '<pre>';
		print_r($val);
		echo  '</pre>';
	}

	function isv($data) {
		if( isset($data) && strlen($data) > 0 )	{
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function _getIliasScormTree($a_packageId) {
		global $ilias, $ilDB;
		$a_out=array();
		$tquery="SELECT scorm_tree.child, scorm_tree.depth-3 depth, scorm_object.title, scorm_object.c_type
			FROM scorm_tree, scorm_object
			WHERE scorm_object.obj_id=scorm_tree.child
			AND scorm_tree.slm_id=%s
			AND scorm_object.c_type='sre'
			AND scorm_object.title <> ''
			ORDER BY scorm_tree.lft";
		$val_set = $ilDB->queryF($tquery,
			array('integer'),
			array($a_packageId)
		);
		while($val_rec = $ilDB->fetchAssoc($val_set)) {
			$a_out[]=array((int)$val_rec["child"],(int)$val_rec["depth"],$val_rec["title"],$val_rec["c_type"]);
		}
		return $a_out;
	}

	function _getIliasScormResources($a_packageId) {
		global $ilias, $ilDB;
		$a_out=array();
		$s_resourceIds="";//necessary if resources exist having different href with same identifier
		$val_set = $ilDB->queryF("
			SELECT sc_resource.obj_id
			FROM scorm_tree, sc_resource
			WHERE scorm_tree.slm_id=%s 
			AND sc_resource.obj_id=scorm_tree.child",
			array('integer'),
			array($a_packageId)	
		);
		while($val_rec = $ilDB->fetchAssoc($val_set)) {
			$s_resourceIds .= ",".$val_rec["obj_id"];
		}
		$s_resourceIds = substr($s_resourceIds,1);

		$tquery="SELECT scorm_tree.lft, scorm_tree.child, 
			CASE WHEN sc_resource.scormtype = 'asset' THEN 1 ELSE 0 END AS asset,
			sc_resource.href,
			sc_resource.import_id,
			sc_resource.obj_id
			FROM scorm_tree, sc_resource, sc_item
			WHERE scorm_tree.slm_id=%s 
			AND sc_item.obj_id=scorm_tree.child 
			AND sc_resource.import_id=sc_item.identifierref 
			AND sc_resource.obj_id in (".$s_resourceIds.") 
			ORDER BY scorm_tree.lft";
		$val_set = $ilDB->queryF($tquery,
			array('integer'),
			array($a_packageId)
		);
		while($val_rec = $ilDB->fetchAssoc($val_set)) {
			$a_out[]=array( (int)$val_rec["child"], $val_rec["href"], $val_rec["import_id"], $val_rec["obj_id"] );
		}
		return $a_out;
	}

	try {
		$ilClientId = "c-il-xlearn";
		//$cron = new ilCronStartUp($ilClientId, "root", "Veg_42ILeh!"); //ILIAS 7
		$cron = new ilCronStartUp($ilClientId, "root");
		global $ilDB, $rbacreview, $ilUser;
		
		// $cron->initIlias();
		$cron->authenticate();
		$entityBody = file_get_contents('php://input');
		if (!isv($entityBody)) {
			throw new Exception('Input leer');
		}
		$json = json_decode($entityBody, true);
		if (!isv($json['manifest']['identifier'])) {
			throw new Exception('Input ungültig');
		}
		if (strpos($entityBody, $ilClientId) == false) {
			throw new Exception('ClientId ungleich');
		}

		$actTime = time();
		$mode = $json['manifest']['upgrade'];
		$slm_id = $json['manifest']['obj_id'];

		$newScormTree = array();
		//$newScormTree[] = array($json['manifest']['organization']['title'],"");

		$isMulti = 0;
		if (count($json['manifest']['organization']['items']) > 1) {
			$isMulti = 1;
			$nst = $json['manifest']['organization']['items'];
			foreach($nst as $chaps) {
				if (isset($chaps['items'])) {
					//$newScormTree[] = array($chaps['title'],"");
					foreach($chaps['items'] as $arItem) {
						if (isset($arItem['items'])) {
							foreach($arItem['items'] as $arItem2) {
								// $newScormTree[] = array("title","href");
								if (isset($arItem2['items'])) {
									if ($mode == true AND $isMulti == 1) {
										if (isv($arItem2['href'])) {
											$newScormTree[] = array($arItem2['title'],$arItem2['href']."?".$actTime,$arItem2['oldhref'],$arItem2['oldtitle']);
										}
									} else {
										if (isv($arItem2['href'])) {
											$newScormTree[] = array($arItem2['title'],$arItem2['href']."?".$actTime);
										}
									}
								} else {
									if ($mode == true AND $isMulti == 1) {
										if (isv($arItem2['href'])) {
											$newScormTree[] = array($arItem2['title'],$arItem2['href']."?".$actTime,$arItem2['oldhref'],$arItem2['oldtitle']);
										}
									} else {
										if (isv($arItem2['href'])) {
											$newScormTree[] = array($arItem2['title'],$arItem2['href']."?".$actTime);
										}
									}
								}
							}
						} else {
							if ($mode == true AND $isMulti == 1) {
								if (isv($arItem['href'])) {
									$newScormTree[] = array($arItem['title'],$arItem['href']."?".$actTime,$arItem['oldhref'],$arItem['oldtitle']);
								}
							} else {
								if (isv($arItem['href'])) {
									$newScormTree[] = array($arItem['title'],$arItem['href']."?".$actTime);
								}
							}
						}
					}
				} else {
					if ($mode == true AND $isMulti == 1) {
						if (isv($chaps['href'])) {
							$newScormTree[] = array($chaps['title'],$chaps['href']."?".$actTime,$chaps['oldhref'],$chaps['oldtitle']);
						}
					} else {
						if (isv($chaps['href'])) {
							$newScormTree[] = array($chaps['title'],$chaps['href']."?".$actTime);
						}
					}
				}
			}
		} else {
			$arrItem = $json['manifest']['organization']['items'][0];
			if ($mode == true) {
				if (isv($arrItem['href'])) {
					$newScormTree[] = array($arrItem['title'],$arrItem['href']."?".$actTime,$arrItem['oldhref'],$arrItem['oldtitle']);
				}
			} else {
				if (isv($arrItem['href'])) {
					$newScormTree[] = array($arrItem['title'],$arrItem['href']."?".$actTime);
				}
			}
		}

		$scormTree = _getIliasScormTree($slm_id);
		$scormResrc = _getIliasScormResources($slm_id);

		// console_log($scormTree);
		// console_log( $entityBody );
		file_put_contents("./mf-log_i9.txt", "Data: " . $entityBody . "\n", FILE_APPEND);
		file_put_contents("./mf-log_i9.txt", "scormTree: " . json_encode($scormTree) . "\n", FILE_APPEND);
		file_put_contents("./mf-log_i9.txt", "scormResrc: " . json_encode($scormResrc) . "\n\n", FILE_APPEND);
		
		if ( (count($scormTree) != count($newScormTree)) || (count($scormResrc) != count($newScormTree)) ) {
			throw new Exception(count($scormTree) . " " .count($newScormTree)  . ': Anzahl Arrayelemente ungleich');
		}

// echo "<br>ILIAS-objID: ". $slm_id . "<br>\n" ;
// echo "isMulti: " . $isMulti . "<br>\n";
// echo "Upgrade: " . (($mode == true)?"TRUE":"FALSE") . "<br>\n";
// echo count($scormTree) . " - " . count($scormResrc) . " - " . count($newScormTree) . "<br>\n";
// print_r2($scormTree); print_r2($scormResrc); print_r2($newScormTree);

		$ix = 0;
		$errOut = "";
		$msgOut = "";
		if ($errOut != "") {
			echo "Error<br>\n" . "ILIAS-objID: ". $slm_id . "<br>\n" . "Upgrade: " . (($mode==true)?"TRUE":"FALSE") . "<br>\n";
			echo $errOut;
			$cron->logout();
			exit();
		} else {
			$msg1 = "";
			$ix = 0;
			foreach($scormTree as $arr) {
				foreach($scormResrc as $arr2) {
					if ($arr2[3] == $arr[0]) {
						(strpos($arr2[1],"?")>0) ? $s1=substr($arr2[1],0,strpos($arr2[1],"?")) : $s1=$arr2[1];
						if (($mode == true) && (isv($newScormTree[$ix][2]))) {
							(strpos($newScormTree[$ix][2],"?")>0) ? $s2=substr($newScormTree[$ix][2],0,strpos($newScormTree[$ix][2],"?")) : $s2=$newScormTree[$ix][2];
						} else {
							$s2 = $s1;
						}
						$msg1 .= $ix . "<br>\n";
						if ($s1 === $s2) {
							$ilDB->manipulateF('UPDATE scorm_object SET title = %s WHERE obj_id = %s', array('text', 'integer'), array($newScormTree[$ix][0], $arr2[0]));
							$msg1 .= "title, obj_id in scorm_object: " . $newScormTree[$ix][0] . ", " . $arr2[0] . "<br>\n";
							$ilDB->manipulateF('UPDATE sc_resource SET href = %s WHERE obj_id = %s', array('text', 'integer'), array($newScormTree[$ix][1], $arr2[3]));
							$msg1 .= "href, obj_id in scorm_resource: " . $newScormTree[$ix][1] . ", " . $arr2[3] . "<br>\n";
							break;
						}
					}
				}
				$ix ++;
			}
		}
		
//		echo $msg1;
		$cron->logout();
	}

	catch(Exception $e)
	{
		file_put_contents("./mf-log_i9.txt", "Debug: " . $e->getMessage() . "\n", FILE_APPEND);
		http_response_code(500);
		echo json_encode(
			array(
				"status" => 500,
				"ilObjID:" => $slm_id,
				"message" => $e->getMessage()
			)
		);
		$cron->logout();
		exit(1);
	}
?>
