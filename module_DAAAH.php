// <?php
/*
----------------------------------------------------------------
「DAAAH」(DiffAndApprovalAndHistory)
履歴と承認と差分表示のモジュール
※モジュールは基本的に全員が利用できるように設定してください。
----------------------------------------------------------------
*/

// ----------------------------------------------------------------
// テーブル名と共通変数を設定
// ----------------------------------------------------------------
global $_lang;
global $modx;
global $manager_theme;
global $modx_charset;
global $SystemAlertMsgQueque;
global $_style;
global $modx_textdir;
global $modx_manager_charset;
global $modx_lang_attribute;

$daaah_path = $modx->config['base_path'] . 'assets/plugins/daaah/';
include_once($daaah_path . 'functions.php');
include_once($daaah_path . 'config.inc.php');

if(function_exists("date_default_timezone_set"))date_default_timezone_set("Asia/Tokyo");

$tbl_history                = $modx->getFullTableName('history_of_site_content');              // 履歴テーブル
$tbl_contentvalues_history  = $modx->getFullTableName('history_of_site_tmplvar_contentvalues');// テンプレート変数履歴テーブル
$tbl_approval               = $modx->getFullTableName('approvals');                            // 多段階承認テーブル
$tbl_approval_logs          = $modx->getFullTableName('approval_logs');                        // 多段階承認履歴テーブル
$tbl_approval_content       = $modx->getFullTableName('approvaled_site_content');              // コンテンツテーブル(承認済み保管箱)
$tbl_contentvalues_approval = $modx->getFullTableName('approvaled_site_tmplvar_contentvalues');// テンプレート変数テーブル(承認済み保管箱)
$tbl_content                = $modx->getFullTableName('site_content');                         // コンテンツテーブル
$tbl_contentvalues          = $modx->getFullTableName('site_tmplvar_contentvalues');           // テンプレート変数テーブル
$tbl_user_roles             = $modx->getFullTableName('user_roles');                           // ユーザーグループテーブル
$tbl_site_modules           = $modx->getFullTableName('site_modules');                         // モジュールテーブル

$permission = $modx->hasPermission('publish_document');
$now_role   = $_SESSION['mgrRole'];


	// ----------------------------------------------------------------
	// 処理すべきレベルのON/OFFコントロール
	// ----------------------------------------------------------------
	$level_onoff = array();
	for ( $count = 0 ; $count < $approval_level ; $count ++ )
	{
		$check_role = explode( '/' , $level_and_role[$count + 1]);
		$level_onoff[$count + 1] = 0;
		for ( $chk_count = 0 ; $chk_count < count($check_role) ; $chk_count ++ )
		{
			// 当該のレベルに属するRoleの場合はON
			if($check_role[$chk_count] == $now_role ) $level_onoff[$count + 1 ] = 1;
		}
	}
	
if($_REQUEST['mode'] == 'upd')
{
	// コンテンツ保存時の処理
	// From save_content.processor.php
	
	$docid = $_REQUEST['docid'];
	// 承認状態確認
	$app_result = checkApprovalStatus($docid , $approval_level);
	
	// 承認処理  -- 開始
	$approval_change_flag = 0;
	
	for ( $count = 0 ; $count < $approval_level ; $count ++ )
	{ 
		$pub_level        = $count + 1;
		$approval_value   = 0;
		$record_exit_flag = 0;
		if(($level_onoff[$pub_level] == 1) || (!$permission))
		{
			// フォームから値を取得
			$form_name  = 'approval_and_level' . $pub_level;
			$s_approval = (isset($_POST[$form_name])) ? $modx->db->escape($_POST[$form_name]) : '0';
			$form_name  = 'comment_and_level'  . $pub_level;
			$s_comment  = (isset($_POST[$form_name])) ? $modx->db->escape($_POST[$form_name]) : '';
			// 公開権限のない人がOnDocFormSaveに来たとき(ページ内容を編集したとき)は
			// 「公開しない」にリセットする
			if(!$permission) $s_approval = '0';
			
			// 承認状況更新
			// DBにレコードがあるかどうか
			if(isset($a_approval[$pub_level]))
			{
				$sql = " approval='{$s_approval}' ";
				$where  = " id='{$docid}' AND level='{$pub_level}' ";
				$modx->db->update($sql, $tbl_approval, $where);
				unset($sql);
			}
			else
			{
				$fields['id']       = $docid;
				$fields['level']    = $pub_level;
				$fields['approval'] = $s_approval;
				unset($fields);
			}
			
			if($permission)
			{
				$approval_value = 0;
				if(isset($a_approval[$pub_level])) $approval_value = $a_approval[$pub_level];
				
				if(($s_approval != $approval_value ) || ( $s_comment != ''))
				{
					// 承認ステータスに変化があったのでフラグをON
					if(isset($a_approval[$pub_level]))  $approval_change_flag = 1;
					
					// 承認履歴更新
					$user_id            = $modx->getLoginUserID();
					$fields['id']       = $docid;
					$fields['level']    = $pub_level;
					$fields['approval'] = $s_approval;
					$fields['user_id']  = $user_id;
					$fields['role_id']  = $now_role;
					$fields['editedon'] = time();
					$fields['comment']  = $s_comment;
					$modx->db->insert( $fields , $tbl_approval_logs );
				}
			}
		}
	}
	
	// すべて承認されていた場合、ドキュメントを公開設定にする
	$app_result = checkApprovalStatus($docid, $approval_level);
	
	if($app_result)
	{
		$sql['published'] = 1;
		$sql['deleted']   = 0;
		$modx->db->update($sql, $tbl_content, " id='{$docid}' ");
		unset($sql);
	}
	elseif(!$app_result)
	{
		// すべて承認していない状態、かつ新規ドキュメントのときは非公開にする
		$sql['published'] = 0;
		$sql['deletedon'] = time();
		$modx->db->update($sql, $tbl_content, " id='$docid' ");
		unset($sql);
	}
	// 承認処理  -- 終わり
	// バックアップ処理  -- はじめ
	// ドキュメントデータを取得
	$doc_data = $modx->getDocumentObject('id' , $docid );
	if($app_result)
	{ // すべての承認を受けた場合のみ処理
		$introtext       = $modx->db->escape($doc_data['introtext']);
		$content         = $modx->db->escape($doc_data['content']);
		$pagetitle       = $modx->db->escape($doc_data['pagetitle']);
		$longtitle       = $modx->db->escape($doc_data['longtitle']);
		$type            = $doc_data['type'];
		$description     = $modx->db->escape($doc_data['description']);
		$alias           = $modx->db->escape($doc_data['alias']);
		$link_attributes = $modx->db->escape($doc_data['link_attributes']);
		$isfolder        = $doc_data['isfolder'];
		$richtext        = $doc_data['richtext'];
		$published       = $doc_data['published'];
		$parent          = $doc_data['parent'];
		$template        = $doc_data['template'];
		$menuindex       = $doc_data['menuindex'];
		$searchable      = $doc_data['searchable'];
		$cacheable       = $doc_data['cacheable'];
		$createdby       = $doc_data['createdby'];
		$createdon       = $doc_data['createdon'];
		$editedby        = $doc_data['editedby'];
		$editedon        = $doc_data['editedon'];
		$publishedby     = $doc_data['publishedby'];
		$publishedon     = $doc_data['publishedon'];
		$pub_date        = $doc_data['pub_date'];
		$unpub_date      = $doc_data['unpub_date'];
		$contentType     = $modx->db->escape($doc_data['contentType']);
		$contentdispo    = $doc_data['content_dispo'];
		$donthit         = $doc_data['donthit'];
		$menutitle       = $modx->db->escape($doc_data['menutitle']);
		$hidemenu        = $doc_data['hidemenu'];
		$deleted         = $doc_data['deleted'];
		$deletedon       = $doc_data['deletedon'];
		// 履歴に登録
		$sql = "INSERT INTO $tbl_history (id,introtext,content, pagetitle, longtitle,
		                                  type, description, alias, link_attributes,
		                                  isfolder, richtext, published, parent,
		                                  template, menuindex, searchable, cacheable,
		                                  createdby, createdon, editedby, editedon,
		                                  publishedby, publishedon, pub_date, unpub_date,
		                                  contentType, content_dispo, donthit, menutitle, hidemenu
		                                  )
		                           VALUES('{$docid}','{$introtext}','{$content}', '{$pagetitle}', '{$longtitle}',
		                                  '{$type}', '{$description}', '{$alias}', '{$link_attributes}',
		                                  '{$isfolder}', '{$richtext}', '{$published}', '{$parent}',
		                                  '{$template}', '{$menuindex}', '{$searchable}', '{$cacheable}',
		                                  '{$createdby}', {$createdon}, '{$editedby}',
		                                  {$editedon}, {$publishedby}, {$publishedon}, '{$pub_date}', '{$unpub_date}',
		                                  '{$contentType}', '{$contentdispo}', '{$donthit}', '{$menutitle}', '{$hidemenu}'
		                                  )";
		$rs = $modx->db->query($sql);
	
		// 承認保管箱に登録
		$sql = "REPLACE INTO $tbl_approval_content
			   (
				id,introtext,content, pagetitle, longtitle, type, description,
				alias, link_attributes, isfolder, richtext, published, parent,
				template, menuindex, searchable, cacheable, createdby, createdon,
				editedby, editedon, publishedby, publishedon, pub_date, unpub_date,
				contentType, content_dispo, donthit, menutitle, hidemenu
				)
		VALUES
			   (
			    '{$docid}','{$introtext}','{$content}', '{$pagetitle}', '{$longtitle}', '{$type}', '{$description}',
			    '{$alias}', '{$link_attributes}', '{$isfolder}', '{$richtext}', '{$published}', '{$parent}',
			    '{$template}', '{$menuindex}', '{$searchable}', '{$cacheable}', '{$createdby}', {$createdon},
			    '{$editedby}', {$editedon}, {$publishedby}, {$publishedon}, '{$pub_date}', '{$unpub_date}',
			    '{$contentType}', '{$contentdispo}', '{$donthit}', '{$menutitle}', '{$hidemenu}'
			   )";
		$rs_app = $modx->db->query($sql);
	
		// テンプレート変数データをゲット
		$result = $modx->db->select('*', $tbl_contentvalues, " contentid ='{$docid}' ");
		
		// データ取り出し
		$a_tvs = array();
		$a_tvs_app = array();
		if( $modx->db->getRecordCount( $result ) >= 1 )
		{
			while( $row = $modx->db->getRow( $result ) )
			{
				$a_tvs[]     = "('{$row['id']}','{$row['tmplvarid']}','{$row['contentid']}', '{$modx->db->escape($row['value'])}', '{$editedon}')";
				$a_tvs_app[] = "('{$row['id']}','{$row['tmplvarid']}','{$row['contentid']}', '{$modx->db->escape($row['value'])}')";
			}
		}
	
		// テンプレート変数登録
		if(!empty($a_tvs))
		{
			// テンプレート変数履歴に登録
			$sql = "INSERT INTO {$tbl_contentvalues_history} (id,tmplvarid, contentid, value, editedon) VALUES " . implode(',', $a_tvs);
			$rs = $modx->db->query($sql);
			// テンプレート変数(承認済み保管箱)に登録
			$sql = "REPLACE INTO {$tbl_contentvalues_approval} (id,tmplvarid, contentid, value) VALUES " . implode(',', $a_tvs_app);
			$rs = $modx->db->query($sql);
		}
	}
	
	// OnDocFormSaveイベントのときに削除状態のときは承認保管箱も削除状態にする
	$published = $doc_data['published'];
	$deleted   = $doc_data['deleted'];
	$deletedon = $doc_data['deletedon'];
	
	if($deleted == 1)
	{
		// 承認保管箱に当該のデータが存在するか?
		
		$result = $modx->db->select('*', $tbl_approval_content , " id='{$docid}'");
		
		// 存在する場合、UPDATE
		if($modx->db->getRecordCount($result ) >= 1 )
		{
			unset($sql);
			$sql['published'] = $published;
			$sql['deleted']   = $deleted;
			$sql['deletedon'] = $deletedon;
			$modx->db->update( $sql , $tbl_approval_content , " id='{$docid}'");
		}
	}
	// ----------------------------------------------------------------
	// バックアップ処理  -- 終わり
	// ----------------------------------------------------------------
	header("Location: index.php?a=3&id={$docid}");
	exit;
}





// -------------------------------------------------------------------
// モジュール「DAAAH」のモジュールID取り出し
// -------------------------------------------------------------------
$result = $modx->db->select('id', $tbl_site_modules, " name='DAAAH' ");
$module_id = $modx->db->getValue($result);

// リクエスト値受け取り
$request_err_flag = 0;
// コンテンツID
if(isset($_REQUEST['docid']))
{
	$docid = intval($_REQUEST['docid']);
}
else
{
	$docid=0;
	$request_err_flag = 1;
}

// 履歴ID
if(isset($_REQUEST['hisid']))
{
	$hisid = intval($_REQUEST['hisid']);
}
else
{
	$hisid=0;
	$request_err_flag = 1;
}

// ロールバックスイッチ
if(isset($_REQUEST['rolesw']))
{
	$rolesw = $_REQUEST['rolesw'];
	if($rolesw != 'role')
	{
		$request_err_flag = 1;
	}
}

if((!is_numeric($docid))||(!is_numeric($hisid))||($request_err_flag == 1))
{
	$modx->webAlert("処理を停止しました。本機能は編集画面より呼び出してください。");
	echo "処理を停止しました。本機能は編集画面より呼び出してください。";
	exit;
}

// ----------------------------------------------------------------
// コンテンツのみの差分を読み込みする場合のルーチン -- はじめ
// ----------------------------------------------------------------
// 現在コンテンツデータをゲット
// ----------------------------------------------------------------
// コンテンツデータをゲット
// ----------------------------------------------------------------

$result = $modx->db->select('*', $tbl_content , " id='{$docid}' ");

// データ取り出し
$a_docs = array();
if( $modx->db->getRecordCount( $result ) >= 1 )
{
	while( $row = $modx->db->getRow( $result ))
	{
		$s_now_page     = $row['content'];
		$s_now_template = $row['template'];
	}
}

// テンプレート変数データをゲット
// ----------------------------------------------------------------
$sql  = "SELECT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value ";
$sql .= "FROM " . $modx->getFullTableName('site_tmplvars') . " tv ";
$sql .= "INNER JOIN " . $modx->getFullTableName('site_tmplvar_templates')." tvtpl ON tvtpl.tmplvarid = tv.id ";
$sql .= "LEFT JOIN " . $tbl_contentvalues." tvc ON tvc.tmplvarid=tv.id AND tvc.contentid = '" . $docid. "' ";
$sql .= "WHERE tvtpl.templateid = '" . $s_now_template . "'";
$sql .= " ORDER BY tvc.tmplvarid ";
$rs = $modx->db->query($sql);
$rowCount= $modx->db->getRecordCount($rs);
$tmplvars = array();
$tmpl_flag = 0;
if($rowCount > 0)
{
	$tmpl_flag = 1;
	for ($i= 0; $i < $rowCount; $i++)
	{
		$row_tvs= $modx->fetchRow($rs);
		if( $row_tvs['value'] != '') $tmplvars []= "[" . $row_tvs['caption'] . "]" . $row_tvs['value'];
	}
	$s_now_page .= implode("\n", $tmplvars);
}


// 履歴データをゲット
// ----------------------------------------------------------------
// コンテンツデータをゲット
// ----------------------------------------------------------------

$result = $modx->db->select('*', $tbl_history , " id='{$docid}' ", ' editedon desc ');

// データ取り出し
$a_docs = array();
$s_drop_down_history = "";
if($modx->db->getRecordCount( $result ) >= 1 )
{
	while($row = $modx->db->getRow($result))
	{
		$a_docs[$row['editedon']] = $row;

		// ドロップダウン組み立て
		$s_drop_down_history .= '<option value="' . $row['editedon'] . '"';
		if( $row['editedon'] == $hisid ) $s_drop_down_history .= ' selected="selected"';
		$s_drop_down_history .= '>';
		$s_drop_down_history .= mb_strftime('%Y年%m月%d日(%a)%H時%M分%S秒'  , $row['editedon'] );
		$s_drop_down_history .= '</option>';
	}
}

if(!isset($a_docs[ $hisid ]))
{
	$modx->webAlert("承認を受けたページデータが存在しません。");
	echo "承認を受けたページデータが存在しません。";
	exit;
}
else
{
	$doc_data = array();
	$doc_data = $a_docs[ $hisid ];
}

// Diffで比較対照となる過去データ取り出し
$s_old_page        = $doc_data['content'];
$s_old_pagetitle   = $doc_data['pagetitle'];
$s_old_longtitle   = $doc_data['longtitle'];
$s_old_menutitle   = $doc_data['menutitle'];
$s_old_description = $doc_data['description'];
$s_old_alias       = $doc_data['alias'];
$s_old_editedon    = $doc_data['editedon'];
$s_old_editedby    = $doc_data['editedby'];

// テンプレート変数データをゲット
// ----------------------------------------------------------------
$sql= "SELECT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value ";
$sql .= "FROM " . $modx->getFullTableName("site_tmplvars") . " tv ";
$sql .= "INNER JOIN " . $modx->getFullTableName("site_tmplvar_templates")." tvtpl ON tvtpl.tmplvarid = tv.id ";
$sql .= "LEFT JOIN " . $tbl_contentvalues_history." tvc ON tvc.tmplvarid=tv.id AND tvc.contentid = '" . $docid. "' ";
$sql .= "WHERE tvtpl.templateid = '" . $doc_data['template'] . "'";
$sql .= " AND ";
$sql .= " tvc.editedon = '" . $hisid . "'";
$sql .= " ORDER BY tvc.tmplvarid ";
$rs = $modx->db->query($sql);
$rowCount= $modx->db->getRecordCount($rs);
$tmplvars = array();
$s_old_tvs = "";
if($rowCount > 0)
{
	for ($i= 0; $i < $rowCount; $i++)
	{
		$row_tvs= $modx->fetchRow($rs);
		$tmplvars []= "[" . $row_tvs['caption'] . "]" . $row_tvs['value'];
	}
	$s_old_tvs = implode("\n", $tmplvars);
}


// コンテンツのみの差分を読み込みする場合のルーチン -- おわり
// ----------------------------------------------------------------


// ----------------------------------------------------------------
// ロールバックを行う場合のルーチン -- はじめ
// ----------------------------------------------------------------
if((isset( $rolesw)) && ( $rolesw == "role"))
{
	// 本文データのロールバック
	// ----------------------------------------------------------------
	$s_old_page = $modx->db->escape($s_old_page);

	// SQL文構築

	$sql_string_update  = "";
	$sql_string_update .= " content='$s_old_page' ";
	$sql_string_update .= ",";
	$sql_string_update .= " pagetitle='$s_old_pagetitle' ";
	$sql_string_update .= ",";
	$sql_string_update .= " longtitle='$s_old_longtitle' ";
	$sql_string_update .= ",";
	$sql_string_update .= " menutitle='$s_old_menutitle' ";
	$sql_string_update .= ",";
	$sql_string_update .= " description='$s_old_description' ";
	$sql_string_update .= ",";
	$sql_string_update .= " alias='$s_old_alias' ";
	$sql_string_update .= ",";
	$sql_string_update .= " editedon='$s_old_editedon' ";
	$sql_string_update .= ",";
	$sql_string_update .= " editedby='$s_old_editedby' ";

	// SQL発行
	$modx->db->update( $sql_string_update , $tbl_content , " id='{$docid}' " );


	// テンプレート変数データのロールバック
	// ----------------------------------------------------------------
	if( $tmpl_flag == 1 )
	{
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " contentid ='$docid' ";
		$sql_string_where .= " AND ";
		$sql_string_where .= " editedon ='$hisid' ";

		// SQL発行
		$result = $modx->db->select('*', $tbl_contentvalues_history , $sql_string_where );

		// データ取り出し
		$a_tvs_app = array();
		if( $modx->db->getRecordCount( $result ) >= 1 )
		{
			while( $row = $modx->db->getRow( $result ))
			{
				$a_tvs_app[] = "('" . $row['id'] . "','" . $row['tmplvarid'] . "','" . $row['contentid'] . "', '" . $modx->db->escape($row['value'] ) . "')";
			}
		}

		// テンプレート変数登録
		if(!empty($a_tvs_app)) {
			$sql_app = 'REPLACE INTO '.$tbl_contentvalues.' (id,tmplvarid, contentid, value) VALUES '.implode(',', $a_tvs_app);
			$rs = $modx->db->query($sql_app);
		}
	}

	// ロールバックした後は、編集画面へ移動
	header("Location: index.php?a=27&id=$docid");
	exit;
}
// ----------------------------------------------------------------
// ロールバックを行う場合のルーチン -- おわり
// ----------------------------------------------------------------


// ----------------------------------------------------------------
// 差分確認 -- はじめ
// ----------------------------------------------------------------
// Diffモジュール読み込み
global $path;
$path = $modx->config['base_path'] . 'assets/plugins/daaah/';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
include_once($path . 'Text/Diff.php');
include_once($path . 'Text/Diff/Renderer/inline.php');
$php_errormsg = ""; // ignore php5 strict errors


// Diffにかける前のお膳立て
$s_old_page .= $s_old_tvs;
$s_now_page = strip_tags ( $s_now_page );
$s_old_page = strip_tags ( $s_old_page );
$s_now_page = str_replace("\t", "", $s_now_page );
$s_old_page = str_replace("\t", "", $s_old_page );
$s_now_page = str_replace("　", "", $s_now_page );
$s_old_page = str_replace("　", "", $s_old_page );
$s_now_page = str_replace(" ", "", $s_now_page );
$s_old_page = str_replace(" ", "", $s_old_page );
while ( preg_match('|\n\n|' , $s_now_page )) $s_old_page = preg_replace('|\n\n|' , "\n" , $s_now_page );
while ( preg_match('|\n\n|' , $s_old_page )) $s_old_page = preg_replace('|\n\n|' , "\n" , $s_old_page );

$a_now_page = explode ("\n", $s_now_page);
$a_old_page = explode ("\n", $s_old_page);


// Diff開始
$diff = new Text_Diff($a_old_page ,$a_now_page);
$renderer = new Text_Diff_Renderer_inline();


// Diff結果データ加工
$publish_diff_data = $renderer->render($diff);
while ( preg_match('|\n\n|' , $publish_diff_data )) $publish_diff_data = preg_replace('|\n\n|' , "\n" , $publish_diff_data );
$publish_diff_data = str_replace("\n", "<br />\n", $publish_diff_data );
if( $publish_diff_data == '')
{
	$publish_diff_data = "二つのページ内容に違いはありません。";
}

// ----------------------------------------------------------------
// 差分確認 -- おわり
// ----------------------------------------------------------------


// ----------------------------------------------------------------
// ヘッダ読み込み
// ----------------------------------------------------------------
include_once "header.inc.php";


// ----------------------------------------------------------------
// 結果画面表示
// ----------------------------------------------------------------
?>
<link rel="stylesheet" type="text/css" href="<?php echo MODX_SITE_URL;?>assets/plugins/daaah/css/style.css" />
<script type="text/javascript">
function previewOlddocument() {
	var win = window.frames['preview'];
	url = "../index.php?id=<?php echo $docid; ?>&hisid=" + document.history.hisid.value + "&manprev=z";
	nQ = "id=<?php echo $docid; ?>&hisid=" + document.history.hisid.value + "&manprev=z"; // new querysting
	oQ = (win.location.href.split("?"))[1]; // old querysting
	if(nQ != oQ) {
		win.location.href = url;
		win.alreadyPreviewed = true;
	}
}

function goBySelectValue( selname ) {
	fucus_sel = document.getElementById( selname );   
	select_number = fucus_sel.selectedIndex;
	select_value  = fucus_sel.options[select_number].value;
	url = "<?php echo "index.php?a=112&id=$module_id&docid=$docid&hisid="; ?>" + select_value;
	location.href = url;
}

function goBySelectValueForRolback( selname ) {
	fucus_sel = document.getElementById( selname );   
	select_number = fucus_sel.selectedIndex;
	select_value  = fucus_sel.options[select_number].value;
	if( window.confirm("編集中の内容を指定した日時の状態に戻します。\n現在の内容に再度、戻すことはできません。\nよろしいですか?")) {
		url = "<?php echo "index.php?a=112&id=$module_id&docid=$docid&hisid="; ?>" + select_value + "&rolesw=role";
		location.href = url;
	}

}

</script>
<br />
<form name="history" id="contentHistory" method="post" enctype="multipart/form-data" action="index.php">
<input type="hidden" name="docid" value="<?php echo $docid; ?>" />
<input type="hidden" name="hisid"  value="<?php echo $hisid; ?>" />


<div class="sectionHeader">ドキュメントの更新履歴/差分表示</div>
<div class="sectionBody">
<script type="text/javascript" src="media/script/tabpane.js"></script>

<div class="tab-pane" id="daaahPane">
	<script type="text/javascript">
	tpSettings = new WebFXTabPane(document.getElementById("daaahPane"));
	tpSettings.selectedIndex = 2;
	</script>

	<!-- General -->

	<div class="tab-page" id="tabGeneral">
		<h2 class="tab">差分</h2>
		<script type="text/javascript">tpSettings.addTabPage(document.getElementById("tabGeneral"));</script>

		<span class="warning"><?php echo mb_strftime('%Y年%m月%d日(%a)%H時%M分%S秒', $hisid )?></span>に承認を受けた内容と<span class="warning">現在、編集中のページデータ</span>との差分を表示しています。<br />

		<div class="split"></div>
		<br /><span class="warning">本文のみ抽出して差分表示</span><br />
		<table width="550" border="1" cellspacing="2" cellpadding="3">
			<tr><td><?php echo $publish_diff_data;?></td></tr>
		</table>
		<br />
		<div class="split"></div>
		<table width="550" border="0" cellspacing="0" cellpadding="0">
			<tr style="height: 24px;"><td width="150"><span class="warning">公開中プレビュー</span></td>
			<td>
				<select id="historyList" name="historyList" class="inputBox" onchange="goBySelectValue('historyList');" style="width:250px">
				<?php echo $s_drop_down_history; ?>
				</select>を表示
			</td></tr>
		</table>
	</div><!-- end #tabGeneral -->

	<!-- Roleback -->
	<div class="tab-page" id="tabRoleback">
		<h2 class="tab">編集内容を戻す</h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById("tabRoleback"));</script>

		<table width="550" border="0" cellspacing="0" cellpadding="0">
			<tr style="height: 24px;">
			<td>
				<select id="rolebackList" name="historyList" class="inputBox" style="width:250px">
				<?php echo $s_drop_down_history; ?>
				</select>に承認を受けた内容へ<input type="button" value="編集中の内容を戻す" onclick="goBySelectValueForRolback('rolebackList');">
			</td></tr>
		</table>
	</div><!-- end #tabRoleback -->


	<!-- Preview -->
	<div class="tab-page" id="tabPreviewNow">
		<h2 class="tab">承認前プレビュー</h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById("tabPreviewNow"));</script>

		<table width="96%" border="0"><tr><td>ここには最後に保存した編集内容をプレビューしています。</td></tr>
			<tr><td><iframe name="previewnow" frameborder="0" width="100%" style="height:900px;" id="previewnowIframe" src="<?php echo $modx->config['site_url'];?>index.php?id=<?php echo $docid; ?>&preview_sw=1&manprev=z"></iframe></td></tr>

		</table>
	</div><!-- end #tabPreview -->

	<!-- Settings -->
	<div class="tab-page" id="tabPreview">
		<h2 class="tab">公開中プレビュー</h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById("tabPreview"), previewOlddocument );</script>

		<table width="96%" border="0"><tr><td>ここには<?php echo mb_strftime('%Y年%m月%d日(%a)%H時%M分%S秒' , $hisid )?>に承認を受けた内容をプレビューしています。</td></tr>
			<tr><td><iframe name="previewpub" frameborder="0" width="100%" style="height:900px;" id="previewIframe"
			src="<?php echo $modx->config['site_url'];?>index.php?id=<?php echo $_GET['docid'];?>&hisid=<?php echo $_GET['hisid'];?>&manprev=z"></iframe></td></tr>

		</table>
	</div><!-- end #tabSettings -->


</div><!-- end #documentPane -->
</div><!-- end .sectionBody -->
</form>


<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php">
<div class="sectionHeader">承認</div>
<div class="sectionBody">
	<div style="width:100%">
		<?php
		$docid = $_GET['docid'];
		for ( $count = 0; $count < $approval_level; $count++ )
		{
			$a_add_level[] = " level=" . ( $count + 1 ) . " ";
		}
		$where = implode( " OR " , $a_add_level );
		$where = " id='{$docid}' AND ({$where})";
		$result = $modx->db->select('*', $tbl_approval , $where );
		
		$a_approval = array();
		if( $modx->db->getRecordCount( $result ) >= 1 )
		{
			while( $row = $modx->db->getRow( $result ) )
			{
				$a_approval[$row['level']] = $row['approval'];
			}
		}
		
		$now_role = $_SESSION['mgrRole'];
		$level_onoff = array();
		for($count = 0; $count < $approval_level; $count++)
		{
			$check_role = explode('/', $level_and_role[$count + 1]);
			$level_onoff[$count + 1] = 0;
			for ($chk_count = 0; $chk_count < count($check_role); $chk_count ++ )
			{
				// 当該のレベルに属するRoleの場合はON
				if($check_role[$chk_count] == $now_role ) $level_onoff[$count + 1 ] = 1;
			}
		}
		
		?>
		
		
		
		<table width="550" border="0" cellspacing="0" cellpadding="0">
<?php
		for ($count = 0; $count < $approval_level; $count ++ )
		{
			$pub_level = $count + 1;
			$approval_value = 0;
			if(isset($a_approval[$pub_level] ) ) $approval_value = $a_approval[$pub_level];
			if( $level_onoff[$pub_level] == 1 )
			{
?>
			<tr style="height: 24px;">
				<td><span class="warning"><?php echo $level_and_mes[$pub_level]?></span></td>
				<td>
					<select name="approval_and_level<?php echo $pub_level; ?>" onchange="documentDirty=true;">
						<option value="0"<?php echo ( $approval_value == 0 ) ? ' selected="selected" ' : ''; ?>><?php echo $a_approval_string[0] ?></option>
						<option value="1"<?php echo ( $approval_value == 1 ) ? ' selected="selected" ' : ''; ?>><?php echo $a_approval_string[1] ?></option>
					</select>
				</td>
				<td><span class="warning">理由</span></td>
				<td>
					<input type="text" name="comment_and_level<?php echo $pub_level?>" maxlength="255" value="" class="inputBox" style="width:250px;" onchange="documentDirty=true;" spellcheck="true" />
					<img src="<?php echo $_style['icons_tooltip_over']; ?>" onmouseover="this.src='<?php echo $_style['icons_tooltip']     ; ?>';" onmouseout="this.src='<?php echo $_style['icons_tooltip_over']; ?>';" alt="承認する場合には｢承認する｣を選択してください。承認しない場合には｢承認しない｣を選択の上、理由も書き添えてください。" onclick="alert(this.alt);" style="cursor:help;margin-left:8px;" />
				</td>
			</tr>
<?php
			}
		}
?>
	</table>
		
		<div class="actionButtons" style="margin-top:10px;">
		<?php
		$_SESSION['itemname'] = htmlspecialchars(stripslashes($doc_data['pagetitle']));
		?>
		<input type="hidden" name="a" value="112" />
		<input type="hidden" name="id" value="<?php echo $_REQUEST['id']; ?>" />
		<input type="hidden" name="docid" value="<?php echo $_REQUEST['docid']; ?>" />
		<input type="hidden" name="mode" value="upd" />
		<a href="#" onclick="documentDirty=false; document.mutate.save.click();"><img src="<?php echo $_style['icons_save']; ?>" />更新</a>
		<input type="submit" name="save" style="display:none" />
		</div>
	</div>
</div><!-- end .sectionBody -->
</form>




<?php

// ----------------------------------------------------------------
// フッタ読み込み
// ----------------------------------------------------------------
include_once "footer.inc.php";



// ?>