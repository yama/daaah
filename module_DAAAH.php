// <?php
/*
----------------------------------------------------------------
「DAAAH」(DiffAndApprovalAndHistory)
履歴と承認と差分表示のモジュール
※モジュールは基本的に全員が利用できるように設定すること。
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

if(function_exists("date_default_timezone_set") )date_default_timezone_set("Asia/Tokyo");

// 履歴テーブル
$history_table_name = $modx->getFullTableName( 'history_of_site_content' );

// テンプレート変数履歴テーブル
$contentvalues_history_table_name = $modx->getFullTableName( 'history_of_site_tmplvar_contentvalues' );

// コンテンツテーブル
$content_table_name = $modx->getFullTableName( 'site_content' );

// テンプレート変数テーブル
$contentvalues_table_name = $modx->getFullTableName( 'site_tmplvar_contentvalues' );

// モジュールテーブル
$module_table_name = $modx->getFullTableName( 'site_modules' );


// -------------------------------------------------------------------
// モジュール「DiffForEdit」のモジュールID取り出し
// -------------------------------------------------------------------
// SQL文構築
$sql_string_where  = "";
$sql_string_where .= " name='DAAAH' ";

// SQL発行
$result = $modx->db->select('*', $module_table_name , $sql_string_where );

// データ取り出し
// モジュール「DiffForEdit」のID
if( $modx->db->getRecordCount( $result ) >= 1 ) {
	while( $row = $modx->db->getRow( $result ) ) {
		$module_id = $row['id'];
	}
}
// -------------------------------------------------------------------

// ----------------------------------------------------------------
// リクエスト値受け取り
// ----------------------------------------------------------------
$request_err_flag = 0;
// コンテンツID
if(isset($_REQUEST['contid'])) {
	$contents_id = intval($_REQUEST['contid']);
} else {
	$contents_id=0;
	$request_err_flag = 1;
}

// 履歴ID
if(isset($_REQUEST['hisid'])) {
	$hisid = intval($_REQUEST['hisid']);
} else {
	$hisid=0;
	$request_err_flag = 1;
}

// ロールバックスイッチ
if(isset($_REQUEST['rolesw'])) {
	$rolesw = $_REQUEST['rolesw'];
	if ( $rolesw != "role" ) {
		$request_err_flag = 1;
	}
}


if ( (!is_numeric($contents_id))||(!is_numeric($hisid))||($request_err_flag == 1) ) {
	$modx->webAlert( "処理を停止しました。本機能は編集画面より呼び出してください。" );
	echo "処理を停止しました。本機能は編集画面より呼び出してください。";
	exit;
}


// ベースURL取得
$base = 'http://'.$_SERVER['SERVER_NAME'].str_replace("/manager/index.php", "", $_SERVER["PHP_SELF"]);


// ----------------------------------------------------------------
// コンテンツのみの差分を読み込みする場合のルーチン -- はじめ
// ----------------------------------------------------------------
// 現在コンテンツデータをゲット
// ----------------------------------------------------------------
// コンテンツデータをゲット
// ----------------------------------------------------------------
// SQL文構築
$sql_string_where  = "";
$sql_string_where .= " id='$contents_id' ";

// SQL発行
$result = $modx->db->select('*', $content_table_name , $sql_string_where );

// データ取り出し
$a_docs = array();
if( $modx->db->getRecordCount( $result ) >= 1 ) {
	while( $row = $modx->db->getRow( $result ) ) {
		$s_now_page = $row['content'];
		$s_now_template = $row['template'];
	}
}

// テンプレート変数データをゲット
// ----------------------------------------------------------------
$sql= "SELECT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value ";
$sql .= "FROM " . $modx->getFullTableName("site_tmplvars") . " tv ";
$sql .= "INNER JOIN " . $modx->getFullTableName("site_tmplvar_templates")." tvtpl ON tvtpl.tmplvarid = tv.id ";
$sql .= "LEFT JOIN " . $contentvalues_table_name." tvc ON tvc.tmplvarid=tv.id AND tvc.contentid = '" . $contents_id. "' ";
$sql .= "WHERE tvtpl.templateid = '" . $s_now_template . "'";
$sql .= " ORDER BY tvc.tmplvarid ";
$rs= $modx->dbQuery($sql);
$rowCount= $modx->recordCount($rs);
$tmplvars = array();
$tmpl_flag = 0;
if ($rowCount > 0) {
	$tmpl_flag = 1;
	for ($i= 0; $i < $rowCount; $i++) {
		$row_tvs= $modx->fetchRow($rs);
		if ( $row_tvs['value'] != "" ) $tmplvars []= "[" . $row_tvs['caption'] . "]" . $row_tvs['value'];
	}
	$s_now_page .= implode( "\n", $tmplvars);
}


// 履歴データをゲット
// ----------------------------------------------------------------
// コンテンツデータをゲット
// ----------------------------------------------------------------
// SQL文構築
$sql_string_where  = "";
$sql_string_where .= " id='$contents_id' ";

$sql_string_orderby = " editedon desc ";

// SQL発行
$result = $modx->db->select('*', $history_table_name , $sql_string_where , $sql_string_orderby );

// データ取り出し
$a_docs = array();
$s_drop_down_history = "";
if( $modx->db->getRecordCount( $result ) >= 1 ) {
	while( $row = $modx->db->getRow( $result ) ) {
		$a_docs[ $row['editedon'] ] = $row;

		// ドロップダウン組み立て
		$s_drop_down_history .= '<option value="';
		$s_drop_down_history .= $row['editedon'];
		$s_drop_down_history .= '"';
		if ( $row['editedon'] == $hisid ) $s_drop_down_history .= ' selected="selected"';
		$s_drop_down_history .= '>';
		$s_drop_down_history .= mb_strftime( '%Y年%m月%d日(%a)%H時%M分%S秒'  , $row['editedon'] );
		$s_drop_down_history .= '</option>';

	}
}

if (!isset($a_docs[ $hisid ])) {
	$modx->webAlert( "承認を受けたページデータが存在しません。" );
	echo "承認を受けたページデータが存在しません。";
	exit;
} else {
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
$sql .= "LEFT JOIN " . $contentvalues_history_table_name." tvc ON tvc.tmplvarid=tv.id AND tvc.contentid = '" . $contents_id. "' ";
$sql .= "WHERE tvtpl.templateid = '" . $doc_data ['template'] . "'";
$sql .= " AND ";
$sql .= " tvc.editedon = '" . $hisid . "'";
$sql .= " ORDER BY tvc.tmplvarid ";
$rs= $modx->dbQuery($sql);
$rowCount= $modx->recordCount($rs);
$tmplvars = array();
$s_old_tvs = "";
if ($rowCount > 0) {
	for ($i= 0; $i < $rowCount; $i++) {
		$row_tvs= $modx->fetchRow($rs);
		$tmplvars []= "[" . $row_tvs['caption'] . "]" . $row_tvs['value'];
	}
	$s_old_tvs = implode( "\n", $tmplvars);
}


// コンテンツのみの差分を読み込みする場合のルーチン -- おわり
// ----------------------------------------------------------------


// ----------------------------------------------------------------
// ロールバックを行う場合のルーチン -- はじめ
// ----------------------------------------------------------------
if ( ( isset( $rolesw ) ) && ( $rolesw == "role" ) ) {
	// 本文データのロールバック
	// ----------------------------------------------------------------
	$s_old_page = mysql_escape_string($s_old_page);

	// SQL文構築
	$sql_string_where  = "";
	$sql_string_where .= " id='$contents_id' ";

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
	$modx->db->update( $sql_string_update , $content_table_name , $sql_string_where );


	// テンプレート変数データのロールバック
	// ----------------------------------------------------------------
	if ( $tmpl_flag == 1 ) {
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " contentid ='$contents_id' ";
		$sql_string_where .= " AND ";
		$sql_string_where .= " editedon ='$hisid' ";

		// SQL発行
		$result = $modx->db->select('*', $contentvalues_history_table_name , $sql_string_where );

		// データ取り出し
		$a_tvs_app = array();
		if( $modx->db->getRecordCount( $result ) >= 1 ) {
			while( $row = $modx->db->getRow( $result ) ) {
				$a_tvs_app[] = "('" . $row['id'] . "','" . $row['tmplvarid'] . "','" . $row['contentid'] . "', '" . mysql_escape_string( $row['value'] ) . "')";
			}
		}

		// テンプレート変数登録
		if (!empty($a_tvs_app)) {
			$sql_app = 'REPLACE INTO '.$contentvalues_table_name.' (id,tmplvarid, contentid, value) VALUES '.implode(',', $a_tvs_app);
			$rs = mysql_query($sql_app);
		}

	}


	// ロールバックした後は、編集画面へ移動
	header("Location: index.php?a=27&id=$contents_id");
	exit;

}
// ----------------------------------------------------------------
// ロールバックを行う場合のルーチン -- おわり
// ----------------------------------------------------------------


// ----------------------------------------------------------------
// 差分確認 -- はじめ
// ----------------------------------------------------------------
// Diffモジュール読み込み
$path = $modx->config['base_path'] . 'assets/plugins/daaah/';
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
while ( preg_match( '|\n\n|' , $s_now_page ) ) $s_old_page = preg_replace( '|\n\n|' , "\n" , $s_now_page );
while ( preg_match( '|\n\n|' , $s_old_page ) ) $s_old_page = preg_replace( '|\n\n|' , "\n" , $s_old_page );

$a_now_page = explode ("\n", $s_now_page);
$a_old_page = explode ("\n", $s_old_page);


// Diff開始
$diff = new Text_Diff($a_old_page ,$a_now_page);
$renderer = new Text_Diff_Renderer_inline();


// Diff結果データ加工
$publish_diff_data = $renderer->render($diff);
while ( preg_match( '|\n\n|' , $publish_diff_data ) ) $publish_diff_data = preg_replace( '|\n\n|' , "\n" , $publish_diff_data );
$publish_diff_data = str_replace("\n", "<br />\n", $publish_diff_data );
if ( $publish_diff_data == "" ) {
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
	url = "../index.php?id=<?php echo $contents_id; ?>&hisid=" + document.history.hisid.value + "&manprev=z";
	nQ = "id=<?php echo $contents_id; ?>&hisid=" + document.history.hisid.value + "&manprev=z"; // new querysting
	oQ = (win.location.href.split("?"))[1]; // old querysting
	if (nQ != oQ) {
		win.location.href = url;
		win.alreadyPreviewed = true;
	}
}

function goBySelectValue( selname ) {
	fucus_sel = document.getElementById( selname );   
	select_number = fucus_sel.selectedIndex;
	select_value  = fucus_sel.options[select_number].value;
	url = "<?php echo "index.php?a=112&id=$module_id&contid=$contents_id&hisid="; ?>" + select_value;
	location.href = url;
}

function goBySelectValueForRolback( selname ) {
	fucus_sel = document.getElementById( selname );   
	select_number = fucus_sel.selectedIndex;
	select_value  = fucus_sel.options[select_number].value;
	if ( window.confirm("編集中の内容を指定した日時の状態に戻します。\n現在の内容に再度、戻すことはできません。\nよろしいですか?") ) {
		url = "<?php echo "index.php?a=112&id=$module_id&contid=$contents_id&hisid="; ?>" + select_value + "&rolesw=role";
		location.href = url;
	}

}

</script>
<br />
<form name="history" id="contentHistory" method="post" enctype="multipart/form-data" action="index.php">
<input type="hidden" name="contid" value="<?php echo $contents_id; ?>" />
<input type="hidden" name="hisid"  value="<?php echo $hisid; ?>" />


<div class="sectionHeader">ドキュメントの更新履歴/差分表示</div>
<div class="sectionBody">
<script type="text/javascript" src="media/script/tabpane.js"></script>

<div class="tab-pane" id="daaahPane">
	<script type="text/javascript">
	tpSettings = new WebFXTabPane( document.getElementById( "daaahPane" ) );
	</script>

	<!-- General -->

	<div class="tab-page" id="tabGeneral">
		<h2 class="tab">差分</h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabGeneral" ) );</script>

		<span class="warning"><?php echo mb_strftime( '%Y年%m月%d日(%a)%H時%M分%S秒' , $hisid )?></span>に承認を受けた内容と<span class="warning">現在、編集中のページデータ</span>との差分を表示しています。<br />

		<div class="split"></div>
		<br /><span class="warning">本文のみ抽出して差分表示</span><br />　
		<table width="450" border="1" cellspacing="2" cellpadding="3">
			<tr><td><?php echo $publish_diff_data;?></td></tr>
		</table>
		<br />　
		<div class="split"></div>
		<table width="450" border="0" cellspacing="0" cellpadding="0">
			<tr style="height: 24px;"><td width="150"><span class="warning">公開中プレビュー</span></td>
			<td>
				<select id="historyList" name="historyList" class="inputBox" onchange="g過去のページoBySelectValue('historyList');" style="width:250px">
				<?php echo $s_drop_down_history; ?>
				</select>を表示
			</td></tr>
		</table>
	</div><!-- end #tabGeneral -->

	<!-- Roleback -->
	<div class="tab-page" id="tabRoleback">
		<h2 class="tab">編集内容を戻す</h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabRoleback" ) );</script>

		<table width="450" border="0" cellspacing="0" cellpadding="0">
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
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabPreviewNow" ) );</script>

		<table width="96%" border="0"><tr><td>ここには最後に保存した編集内容をプレビューしています。</td></tr>
			<tr><td><iframe name="previewnow" frameborder="0" width="100%" height="400" id="previewnowIframe" src="../index.php?id=<?php echo $contents_id; ?>&preview_sw=1&manprev=z"></iframe></td></tr>

		</table>
	</div><!-- end #tabPreview -->

	<!-- Settings -->
	<div class="tab-page" id="tabPreview">
		<h2 class="tab">公開中プレビュー</h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabPreview" ), previewOlddocument );</script>

		<table width="96%" border="0"><tr><td>ここには<?php echo mb_strftime( '%Y年%m月%d日(%a)%H時%M分%S秒' , $hisid )?>に承認を受けた内容をプレビューしています。</td></tr>
			<tr><td><iframe name="preview" frameborder="0" width="100%" height="400" id="previewIframe"></iframe></td></tr>

		</table>
	</div><!-- end #tabSettings -->


</div><!-- end #documentPane -->
</div><!-- end .sectionBody -->
</form>

<?php



// ----------------------------------------------------------------
// フッタ読み込み
// ----------------------------------------------------------------
include_once "footer.inc.php";







// ?>