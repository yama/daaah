<?php
/*
$tbl_history                = $modx->getFullTableName( 'history_of_site_content' );               // 履歴テーブル
$tbl_contentvalues_history  = $modx->getFullTableName( 'history_of_site_tmplvar_contentvalues' ); // テンプレート変数履歴テーブル
$tbl_content                = $modx->getFullTableName( 'site_content' );                          // コンテンツテーブル
$tbl_contentvalues          = $modx->getFullTableName( 'site_tmplvar_contentvalues' );            // テンプレート変数テーブル
$tbl_approval_content       = $modx->getFullTableName( 'approvaled_site_content' );               // コンテンツテーブル(承認済み保管箱)
$tbl_contentvalues_approval = $modx->getFullTableName( 'approvaled_site_tmplvar_contentvalues' ); // テンプレート変数テーブル(承認済み保管箱)
$tbl_approval               = $modx->getFullTableName( 'approvals' );                             // 多段階承認テーブル
$tbl_user_role              = $modx->getFullTableName( 'user_roles' );                            // ユーザーグループテーブル
$tbl_approval_logs          = $modx->getFullTableName( 'approval_logs' );                         // 多段階承認履歴テーブル
$tbl_module                 = $modx->getFullTableName( 'site_modules' );                          // モジュールテーブル
*/

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

function get_a_approval($approval_level)
{
	global $modx;
	// --------------------------------------------------------------------------------------------------------------------------------
	// 承認処理のための下ごしらえ -- はじめ
	// --------------------------------------------------------------------------------------------------------------------------------
	// 現在の承認状況をゲット
	// ----------------------------------------------------------------
	$where  = " id='$contents_id' AND ";
	$where .= " ( ";
	
	$a_add_level = array();
	for ( $count = 0 ; $count < $approval_level ; $count ++ )
	{
		$a_add_level[] = " level=" . ( $count + 1 ) . " ";
	}
	$where .= implode( " OR " , $a_add_level );
	
	$where .= " ) ";
	
	// SQL発行
	$result = $modx->db->select('*', $tbl_approval , $where );
	
	// データ取り出し
	$a_approval = array();
	if( $modx->db->getRecordCount( $result ) >= 1 ) {
		while( $row = $modx->db->getRow( $result ) ) {
			$a_approval[ $row['level'] ] = $row['approval'];
		}
	}
	return $a_approval;
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
