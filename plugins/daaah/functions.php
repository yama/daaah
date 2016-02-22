<?php

function level_onoff($config,$current_role)
{
	$level_onoff = array();
	for ( $count = 0 ; $count < $config['approval_level'] ; $count ++ ) {
		$check_role = explode( "/" , $level_and_role [ $count + 1 ] );
		$level_onoff [ $count + 1 ] = 0;
		for ( $chk_count = 0 ; $chk_count < count($check_role) ; $chk_count ++ ) {
			// 当該のレベルに属するRoleの場合はON
			if ( $check_role [ $chk_count ] == $current_role ) $level_onoff [ $count + 1 ] = 1;
	
			// admin権限は問答無用にすべてON
	//		if ( $current_role == "1" ) $level_onoff [ $count + 1 ] = 1;
		}
	}
	return $level_onoff;
}

// ----------------------------------------------------------------
// 現在の承認状況をゲット
// ----------------------------------------------------------------

function mb_strftime($format='%Y/%m/%d', $timestamp='')
{
	global $modx;
	$a = array('日', '月', '火', '水', '木', '金', '土');
	$A = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
	if(empty($timestamp)) $timestamp = time() + $modx->config['server_offset_time'];
	if(substr(PHP_OS,0,3) == 'WIN') $format = str_replace('%-', '%#', $format);
	$peaces    = preg_split('@(%[\-#]?[a-zA-Z%])@',$format,null,PREG_SPLIT_DELIM_CAPTURE);
	$w         = strftime('%w', $timestamp);
	
	$str = '';
	foreach($peaces as $v)
	{
		if    ($v == '%a')              $str .= $a[$w];
		elseif($v == '%A')              $str .= $A[$w];
		elseif(strpos($v, '%')!==false) $str .= strftime($v, $timestamp);
		else                            $str .= $v;
	}
	return $str;
}

function install_check($daaah_path)
{
	global $modx;
	
	$query = "SHOW TABLES LIKE '". $GLOBALS['table_prefix'] . "approvals'";
	$rs    = $modx->db->query($query);
	$a     = $modx->db->makeArray($rs);
	$count = $modx->db->getRecordCount($rs);
	if ($count==0)
	{
		$sql = file_get_contents($daaah_path . 'install/daaah.sql');
		$sql = str_replace("\r", '', $sql);
		$sql = str_replace('[+prefix+]',$GLOBALS['table_prefix'], $sql);
		$tokens = preg_split('@;[ \t]*\n@', $sql);
		foreach($tokens as $query)
		{
			$query = trim($query);
			if(!empty($query))
			{
				$rs = $modx->db->query($query);
			}
		}
	}
}

// -------------------------------------------------------------------
// モジュール「DAAAH」のモジュールID取り出し
// -------------------------------------------------------------------
function get_module_id($module_name)
{
	global $modx, $tbl_module;
	
	$result = $modx->db->select('*', $tbl_module , " name='" . $module_name . "'" );
	
	if( $modx->db->getRecordCount($result) >= 1 )
	{
		while( $row = $modx->db->getRow($result) )
		{
			$module_id = $row['id'];
		}
	}
	return $module_id;
}

// 現在の承認状況をチェック
function checkApprovalStatus($docid , $approval_level)
{
	global $modx;
	
	$result     = FALSE;
	$a_approval = getApprovalStatus($docid, $approval_level);
	
	// 1が｢承認｣のため、すべて承認されている場合は
	// $approval_levelとイコールになる
	for($count = 0; $count < $approval_level; $count ++ )
	{
		if(isset($a_approval[$count + 1]))
		{
			$approval_value += intval($a_approval[ $count + 1 ]);
		}
	}
	if($approval_level == $approval_value) $result = 'TRUE';
	return ($result);
}

// 現在の承認状況をゲット
function getApprovalStatus($docid, $approval_level)
{
	global $modx;
	
	$a_add_level = array();
	for ( $count = 0 ; $count < $approval_level ; $count ++ )
	{
		$a_add_level[] = ' level=' . ( $count + 1 ) . ' ';
	}
	$where = sprintf(" id='%s' AND  ( %s ) ", $docid, join(' OR ', $a_add_level));
	
	$result = $modx->db->select('*', '[+prefix+]approvals' , $where);

	$a_approval = array();
	if(isset($_POST))
	{
		foreach($_POST as $k=>$v)
		{
			if(strpos($k,'approval_and_level')!==false)
			{
				$k = str_replace('approval_and_level', '', $k);
				$a_approval[$k] = $v;
			}
		}
	}
	
	if(($modx->db->getRecordCount( $result ) >= 1) && count($a_approval)==0)
	{
		while($row = $modx->db->getRow($result))
		{
			$a_approval[ $row['level'] ] = $row['approval'];
		}
	}
	
	return ( $a_approval );
}

function getRoleList()
{
	global $modx;
	
	// ロール一覧を取得
	$result = $modx->db->select('*', '[+prefix+]user_roles' );

	// データ取り出し
	$a_role_list = array();
	if( $modx->db->getRecordCount( $result ) >= 1)
	{
		while($row = $modx->db->getRow($result))
		{
			$a_role_list[ $row['id'] ] = $row['name'];
		}
	}
	return $a_role_list;
}

function request_err()
{
    global $modx;
    $msg = '処理を停止しました。本機能は編集画面より呼び出してください。';
    $modx->webAlert($msg);
    exit($msg);
}
