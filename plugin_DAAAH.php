//<?php
/**
 * DAAAH
 *
 * 履歴と承認と差分表示のプラグイン
 *
 * @category plugin
 * @version 0.5.3.1
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal @events OnDocFormSave,OnDocFormRender,OnLoadWebPageCache,OnLoadWebDocument,OnDocFormDelete
 * @internal @modx_category Manager and Admin

--------------------------------------------------------------------------------------------------------------------------------
「DAAAH」(DiffAndApprovalAndHistory)
履歴と承認と差分表示のプラグイン
--------------------------------------------------------------------------------------------------------------------------------

*/

// ----------------------------------------------------------------
// イベントトリガーの内容を取得
// ----------------------------------------------------------------

$daaah_path = $modx->config['base_path'] . 'assets/plugins/daaah/';
include_once($daaah_path . 'functions.php');

$e = & $modx->Event;

// Webページを表示する場合、引数に「preview_sw」があるときは処理を行わない
if (($e->name=="OnLoadWebDocument")||($e->name=="OnLoadWebPageCache")){ 
	// 処理対象のコンテンツID
	$docid = $modx->documentIdentifier;
} else {
	$docid = $e->params['id'];

}


global $_style, $style_path;

// ----------------------------------------------------------------
// 設定読み込み
// ----------------------------------------------------------------
// 多段階承認の設定ファイルを読み込み
$config_file = $daaah_path . 'config.inc.php';
include_once($config_file);

// テーブル名と共通変数を設定

$tbl_history                = $modx->getFullTableName('history_of_site_content');              // 履歴テーブル
$tbl_contentvalues_history  = $modx->getFullTableName('history_of_site_tmplvar_contentvalues');// テンプレート変数履歴テーブル
$tbl_approval_content       = $modx->getFullTableName('approvaled_site_content');              // コンテンツテーブル(承認済み保管箱)
$tbl_contentvalues_approval = $modx->getFullTableName('approvaled_site_tmplvar_contentvalues');// テンプレート変数テーブル(承認済み保管箱)
$tbl_approval               = $modx->getFullTableName('approvals');                            // 多段階承認テーブル
$tbl_approval_logs          = $modx->getFullTableName('approval_logs');                        // 多段階承認履歴テーブル
$tbl_content                = $modx->getFullTableName('site_content');                         // コンテンツテーブル
$tbl_contentvalues          = $modx->getFullTableName('site_tmplvar_contentvalues');           // テンプレート変数テーブル
$tbl_user_roles             = $modx->getFullTableName('user_roles');                           // ユーザーグループテーブル
$tbl_site_modules           = $modx->getFullTableName('site_modules');                         // モジュールテーブル

// -------------------------------------------------------------------
// モジュール「DAAAH」のモジュールID取り出し
// -------------------------------------------------------------------

// モジュール「DAAAH」のID
$result    = $modx->db->select('id', $tbl_site_modules, " name='DAAAH'");
$module_id = $modx->db->getValue($result);

// ----------------------------------------------------------------
// 権限確認
// ----------------------------------------------------------------
$permission = $modx->hasPermission('publish_document');

// 現在のログインユーザーのロールをセット
$now_role = $_SESSION['mgrRole'];


// 承認処理のための下ごしらえ -- はじめ
// 現在の承認状況をゲット
// ----------------------------------------------------------------
if ( $docid != 0 )
{ // 新規コンテンツでは表示しない

	$sql_string_where  = " id='$docid' AND ";
	$sql_string_where .= " ( ";

	$a_add_level = array();
	for ( $count = 0 ; $count < $approval_level ; $count ++ )
	{
		$a_add_level[] = " level=" . ( $count + 1 ) . " ";
	}
	$sql_string_where .= implode( " OR " , $a_add_level );

	$sql_string_where .= " ) ";

	// SQL発行
	$result = $modx->db->select('*', $tbl_approval , $sql_string_where );

	// データ取り出し
	$a_approval = array();
	if( $modx->db->getRecordCount( $result ) >= 1 )
	{
		while( $row = $modx->db->getRow( $result ) )
		{
			$a_approval[ $row['level'] ] = $row['approval'];
		}
	}
}


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

// --------------------------------------------------------------------------------------------------------------------------------
// 承認処理のための下ごしらえ -- おわり
// --------------------------------------------------------------------------------------------------------------------------------

// --------------------------------------------------------------------------------------------------------------------------------
// メイン処理開始
// --------------------------------------------------------------------------------------------------------------------------------
switch ($e->name)
{
	// --------------------------------------------------------------------------------------------------------------------------------
	// コンテンツ保存時の処理
	// --------------------------------------------------------------------------------------------------------------------------------
	// From save_content.processor.php
	case "OnDocFormSave":

	// 承認状態確認
	// ----------------------------------------------------------------
	$app_result = checkApprovalStatus($docid , $approval_level);
	// ----------------------------------------------------------------
	// 承認処理  -- 開始
	// ----------------------------------------------------------------
	$approval_change_flag = 0;

	for ( $count = 0 ; $count < $approval_level ; $count ++ )
	{ 
		$pub_level = $count + 1;
		$approval_value = 0;
		$record_exit_flag = 0;
		if ( ( $level_onoff[$pub_level] == 1 ) || (!$permission) ) {
			// フォームから値を取得
			$form_name  = 'approval_and_level' . $pub_level;
			$s_approval = (isset($_POST[$form_name])) ? mysql_escape_string($_POST[$form_name]) : '0';
			$form_name  = 'comment_and_level'  . $pub_level;
			$s_comment  = (isset($_POST[$form_name])) ? mysql_escape_string($_POST[$form_name]) : '';

			// 公開権限のない人がOnDocFormSaveに来たとき(ページ内容を編集したとき)は
			// 「公開しない」にリセットする
			if (!$permission) $s_approval = '0';

			// 承認状況更新
			// DBにレコードがあるかどうか
			if ( isset ( $a_approval[$pub_level] ) )
			{
				$where  = " id='{$docid}' AND level='{$pub_level}' ";
				$sql    = " approval='{$s_approval}' ";
				$modx->db->update( $sql , $tbl_approval , $where );
			}
			else
			{
				unset($fields);
				$fields['id']       = $docid;
				$fields['level']    = $pub_level;
				$fields['approval'] = $s_approval;
				$modx->db->insert( $fields , $tbl_approval );
			}

			if ($permission)
			{
				$approval_value = 0;
				if(isset($a_approval[$pub_level])) $approval_value = $a_approval[$pub_level];

				if(($s_approval != $approval_value) || ($s_comment != ''))
				{
					// 承認ステータスに変化があったのでフラグをON
					if(isset($a_approval[$pub_level])) $approval_change_flag = 1;

					// 承認履歴更新
					// SQL文構築
					$user_id = $modx->getLoginUserID();
					unset($fields);
					$fields = array(
						'id'	=> $docid,
						'level'	=> $pub_level,
						'approval'	=> $s_approval,
						'user_id'	=> $user_id,
						'role_id'	=> $now_role,
						'editedon'	=> time(),
						'comment'	=> $s_comment,
					);

					// SQL発行
					$modx->db->insert( $fields , $tbl_approval_logs );
				}

			}
		}
	}

	// すべて承認されていた場合、ドキュメントを公開設定にする
	$app_result = checkApprovalStatus( $docid , $approval_level );
	
	if ( $app_result )
	{
		// SQL文構築
		$sql_string_where = " id='{$docid}' ";

		$sql_array_update = array(
				'published'	=> 1,
				'deleted'	=> 0
				);

		// SQL発行
		$modx->db->update( $sql_array_update , $tbl_content , $sql_string_where );

	} elseif ( !( $app_result ) && ( $modx->event->params['mode'] != "upd" ) ) {
		// すべて承認していない状態、かつ新規ドキュメントのときは非公開にする
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " id='$docid' ";

		$sql_array_update = array(
				'published'	=> 0,
				'deletedon'	=> time()
				);

		// SQL発行
		$modx->db->update( $sql_array_update , $tbl_content , $sql_string_where );

	}
	// ----------------------------------------------------------------
	// 承認処理  -- 終わり
	// ----------------------------------------------------------------


	// ----------------------------------------------------------------
	// バックアップ処理  -- はじめ
	// ----------------------------------------------------------------
	// ドキュメントデータを取得
	$doc_data = $modx->getDocumentObject( 'id' , $docid );

	if ($app_result)  { // すべての承認を受けた場合のみ処理

		$introtext       = mysql_real_escape_string( $doc_data['introtext'] );
		$content         = mysql_real_escape_string( $doc_data['content'] );
		$pagetitle       = mysql_real_escape_string( $doc_data['pagetitle'] );
		$longtitle       = mysql_real_escape_string( $doc_data['longtitle'] );
		$type            = $doc_data['type'];
		$description     = mysql_real_escape_string( $doc_data['description'] );
		$alias           = mysql_real_escape_string( $doc_data['alias'] );
		$link_attributes = mysql_real_escape_string( $doc_data['link_attributes'] );
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
		$contentType     = mysql_real_escape_string( $doc_data['contentType'] );
		$contentdispo    = $doc_data['content_dispo'];
		$donthit         = $doc_data['donthit'];
		$menutitle       = mysql_real_escape_string( $doc_data['menutitle'] );
		$hidemenu        = $doc_data['hidemenu'];
		$deleted         = $doc_data['deleted'];
		$deletedon       = $doc_data['deletedon'];

		// 履歴に登録
		$sql = "INSERT INTO $tbl_history (id,introtext,content, pagetitle, longtitle, type, description, alias, link_attributes, isfolder, richtext, published, parent, template, menuindex, searchable, cacheable, createdby, createdon, editedby, editedon, publishedby, publishedon, pub_date, unpub_date, contentType, content_dispo, donthit, menutitle, hidemenu)
						VALUES('" . $docid . "','" . $introtext . "','" . $content . "', '" . $pagetitle . "', '" . $longtitle . "', '" . $type . "', '" . $description . "', '" . $alias . "', '" . $link_attributes . "', '" . $isfolder . "', '" . $richtext . "', '" . $published . "', '" . $parent . "', '" . $template . "', '" . $menuindex . "', '" . $searchable . "', '" . $cacheable . "', '" . $createdby . "', " . $createdon . ", '" . $editedby . "', " . $editedon . ", " . $publishedby . ", " . $publishedon . ", '$pub_date', '$unpub_date', '$contentType', '$contentdispo', '$donthit', '$menutitle', '$hidemenu')";

		$rs = $modx->db->query($sql);

		// 承認保管箱に登録
		$sql_app = "REPLACE INTO $tbl_approval_content (id,introtext,content, pagetitle, longtitle, type, description, alias, link_attributes, isfolder, richtext, published, parent, template, menuindex, searchable, cacheable, createdby, createdon, editedby, editedon, publishedby, publishedon, pub_date, unpub_date, contentType, content_dispo, donthit, menutitle, hidemenu)
						VALUES('" . $docid . "','" . $introtext . "','" . $content . "', '" . $pagetitle . "', '" . $longtitle . "', '" . $type . "', '" . $description . "', '" . $alias . "', '" . $link_attributes . "', '" . $isfolder . "', '" . $richtext . "', '" . $published . "', '" . $parent . "', '" . $template . "', '" . $menuindex . "', '" . $searchable . "', '" . $cacheable . "', '" . $createdby . "', " . $createdon . ", '" . $editedby . "', " . $editedon . ", " . $publishedby . ", " . $publishedon . ", '$pub_date', '$unpub_date', '$contentType', '$contentdispo', '$donthit', '$menutitle', '$hidemenu')";

		$rs_app = $modx->db->query($sql_app);


		// テンプレート変数データをゲット
		// ----------------------------------------------------------------
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " contentid ='$docid' ";

		// SQL発行
		$result = $modx->db->select('*', $tbl_contentvalues , $sql_string_where );

		// データ取り出し
		$a_tvs = array();
		$a_tvs_app = array();
		if( $modx->db->getRecordCount( $result ) >= 1 ) {
			while( $row = $modx->db->getRow( $result ) ) {
				$a_tvs[] = "('" . $row['id'] . "','" . $row['tmplvarid'] . "','" . $row['contentid'] . "', '" . mysql_real_escape_string( $row['value'] ) . "', '" . $editedon . "')";
				$a_tvs_app[] = "('" . $row['id'] . "','" . $row['tmplvarid'] . "','" . $row['contentid'] . "', '" . mysql_real_escape_string( $row['value'] ) . "')";
			}
		}

		// テンプレート変数登録
		if (!empty($a_tvs)) {
			// テンプレート変数履歴に登録
			$sql = 'INSERT INTO '.$tbl_contentvalues_history.' (id,tmplvarid, contentid, value, editedon) VALUES '.implode(',', $a_tvs);
			$rs = $modx->db->query($sql);
			// テンプレート変数(承認済み保管箱)に登録
			$sql_app = 'REPLACE INTO '.$tbl_contentvalues_approval.' (id,tmplvarid, contentid, value) VALUES '.implode(',', $a_tvs_app);
			$rs = $modx->db->query($sql_app);
		}
	}

	// OnDocFormSaveイベントのときに削除状態のときは承認保管箱も削除状態にする
	$published = $doc_data['published'];
	$deleted   = $doc_data['deleted'];
	$deletedon = $doc_data['deletedon'];

	if ( $deleted == 1 ) {
		// 承認保管箱に当該のデータが存在するか?
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " id='$docid' ";

		// SQL発行
		$result = $modx->db->select('*', $tbl_approval_content , $sql_string_where );

		// 存在する場合、UPDATE
		if( $modx->db->getRecordCount( $result ) >= 1 ) {
			// SQL文構築
			$sql_string_where  = "";
			$sql_string_where .= " id='$docid' ";

			$sql_array_update = array(
					'published'	=> $published,
					'deleted'	=> $deleted,
					'deletedon'	=> $deletedon
					);

			// SQL発行
			$modx->db->update( $sql_array_update , $tbl_approval_content , $sql_string_where );
		}
	}
	// ----------------------------------------------------------------
	// バックアップ処理  -- 終わり
	// ----------------------------------------------------------------

	break;


	// --------------------------------------------------------------------------------------------------------------------------------
	// コンテンツ編集画面表示時の処理
	// --------------------------------------------------------------------------------------------------------------------------------
	// From mutate_content.dynamic.php
	case "OnDocFormRender":

	// ----------------------------------------------------------------
	// 承認処理  -- はじめ
	// ----------------------------------------------------------------
	// ロール一覧を取得
	// ----------------------------------------------------------------
	// SQL発行
	$result = $modx->db->select('*', $tbl_user_roles );

	// データ取り出し
	$a_role_list = array();
	if( $modx->db->getRecordCount( $result ) >= 1 ) {
		while( $row = $modx->db->getRow( $result ) ) {
			$a_role_list[ $row['id'] ] = $row['name'];
		}
	}


	// 承認履歴をゲット
	// ----------------------------------------------------------------
	if($docid != 0)
	{ // 新規コンテンツでは表示しない
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " id='$docid' AND ";
		$sql_string_where .= " ( ";

		$a_add_level = array();
		for ( $count = 0 ; $count < $approval_level ; $count ++ )
		{
			$a_add_level[] = " level=" . ( $count + 1 ) . " ";
		}
		$sql_string_where .= implode( " OR " , $a_add_level );

		$sql_string_where .= " ) ";

		$sql_string_orderby = " editedon desc ";



		// SQL発行
		$his_result = $modx->db->select('*', $tbl_approval_logs , $sql_string_where , $sql_string_orderby );
	}

	// データ取り出し
	$s_history = "";
	$s_history .= '<div class="split"></div>';
	$s_history .= '<span class="warning">現在の承認状況</span>';
	$s_history .= '<ul>';
	for ( $count = 0 ; $count < $approval_level ; $count ++ )
	{ 
		$pub_level = $count + 1;
		$approval_value = 0;
		if ( isset ( $a_approval[$pub_level] ) ) $approval_value = $a_approval[$pub_level];
		$s_history .= '<li>';
		$s_history .= $level_and_mes[$pub_level] . ':' . $a_approval_string[ $approval_value ] . '<br />';
		$s_history .= '</li>';
	}
	$s_history .= '</ul>';

	if ( $docid != 0 ) { // 新規コンテンツでは表示しない
		if( $modx->db->getRecordCount( $his_result ) >= 1 ) {
			$s_history .= '<div class="split"></div>';
			$s_history .= '<span class="warning">承認履歴</span>';
			$s_history .= '<ul>';
			while( $row = $modx->db->getRow( $his_result ) ) {
				$s_history .= '<li>';
				$s_history .= mb_strftime( '%Y年%m月%d日(%a)%H時%M分%S秒'  , $row['editedon'] ) . ' : ';
				$s_history .= $a_role_list [ $row['role_id'] ] . ' : ';
				$s_history .= $level_and_mes[ $row['level'] ] . ' : ';
				$s_history .= $a_approval_string[ $row['approval'] ];
				$s_history .= ' :理由　' . $row['comment'];
				$s_history .= '</li>';
			}
			$s_history .= '</ul>';
		}
	}

	// ----------------------------------------------------------------

	ob_start();
?>
<div class="sectionHeader">承認</div>
<div class="sectionBody">
	<div style="width:100%">
<?php
	if ($permission)
	{
?>
		<table width="550" border="0" cellspacing="0" cellpadding="0">
<?php
		for ( $count = 0 ; $count < $approval_level ; $count ++ )
		{
			$pub_level = $count + 1;
			$approval_value = 0;
			if ( isset ( $a_approval[$pub_level] ) ) $approval_value = $a_approval[$pub_level];
			if ( $level_onoff[$pub_level] == 1 )
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
					<input name="comment_and_level<?php echo $pub_level?>" type="text" maxlength="255" value="" class="inputBox" style="width:250px;" onchange="documentDirty=true;" spellcheck="true" />
					<img src="<?php echo $_style['icons_tooltip_over']; ?>" onmouseover="this.src='<?php echo $_style['icons_tooltip']     ; ?>';" onmouseout="this.src='<?php echo $_style['icons_tooltip_over']; ?>';" alt="承認する場合には｢承認する｣を選択してください。承認しない場合には｢承認しない｣を選択の上、理由も書き添えてください。" onclick="alert(this.alt);" style="cursor:help;margin-left:8px;" />
				</td>
			</tr>
<?php
			}
		}
?>
	</table>
<?php
	}
?>
<?php echo $s_history ?>

	</div>
</div><!-- end .sectionBody -->
<?php
//	$output = ob_get_clean(); // 次のバックアップ処理を行わないならコメントアウトを取る

	// ----------------------------------------------------------------
	// 承認処理  -- 終わり
	// ----------------------------------------------------------------


	// ----------------------------------------------------------------
	// バックアップ処理  -- はじめ
	// ----------------------------------------------------------------
	// 履歴データをゲット
	// ----------------------------------------------------------------
	if ( $docid != 0 ) { // 新規コンテンツでは表示しない
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " id='$docid' ";

		$sql_string_orderby = " editedon desc ";

		// SQL発行
		$result = $modx->db->select('*', $tbl_history , $sql_string_where , $sql_string_orderby );

		// データ取り出し
		$a_docs = array();
		if( $modx->db->getRecordCount( $result ) >= 1 ) {
			while( $row = $modx->db->getRow( $result ) ) {
				$a_docs[] = $row;
			}
		}

		$pub_his_contents = $a_docs[0];
		$publish_history_id = intval($pub_his_contents['editedon']);

//	ob_start(); // 前段の承認処理を行わないならコメントアウトを取る
?>
<div class="subTitle" style="width:100%">
	<span class="right">ドキュメントの更新履歴/差分表示</span>

	<table cellpadding="0" cellspacing="0" class="actionButtons">
		<tr>
			<td id="Button5__"><a href="<?php echo "index.php?a=112&id=$module_id&docid=$docid&hisid=$publish_history_id"; ?>"><img src="<?php echo $style_path; ?>icons/next.gif" align="absmiddle" /> 編集中ドキュメントの更新履歴/差分表示</a></td>
		</tr>
	</table>
</div>
<?php

	}

	$output = ob_get_clean();
	$e->output($output);
	// ----------------------------------------------------------------
	// バックアップ処理  -- 終わり
	// ----------------------------------------------------------------

	break;


	// --------------------------------------------------------------------------------------------------------------------------------
	// ページ表示直前の処理
	// --------------------------------------------------------------------------------------------------------------------------------
	case "OnLoadWebPageCache":
	case "OnLoadWebDocument":
	// 当該のコンテンツIDを取得
	$docid = $modx->documentIdentifier;
	if (isset($_REQUEST['hisid']))
	{
		$search_table     = $tbl_history;
		$search_tvs_table = $tbl_contentvalues_history;
		$publish_history_id = intval($_REQUEST['hisid']);
	}
	elseif (isset($_REQUEST['preview_sw']))
	{
		$search_table     = $tbl_content;
		$search_tvs_table = $tbl_contentvalues;
		$publish_history_id = 0;
	}
	else
	{
		$search_table     = $tbl_approval_content;
		$search_tvs_table = $tbl_contentvalues_approval;
		$publish_history_id = 0;
	}

	// 履歴データをゲット
	// ----------------------------------------------------------------
	// SQL文構築
	$where = " id='$docid' ";
	if ( ( $publish_history_id != 0 ) )
	{
		$where .= " AND ";
		$where .= " editedon='$publish_history_id' ";
	}

	// SQL発行
	$result = $modx->db->select('*', $search_table , $where );

	// データ取り出し(1件だけ)
	if( $modx->db->getRecordCount( $result ) >= 1 )
	{
		$doc_data = array();
		$doc_data = $modx->db->getRow( $result );

		$modx->documentObject['introtext']       = $doc_data['introtext'];
		$modx->documentObject['content']         = $doc_data['content'];
		$modx->documentObject['pagetitle']       = $doc_data['pagetitle'];
		$modx->documentObject['longtitle']       = $doc_data['longtitle'];
		$modx->documentObject['type']            = $doc_data['type'];
		$modx->documentObject['description']     = $doc_data['description'];
		$modx->documentObject['alias']           = $doc_data['alias'];
		$modx->documentObject['link_attributes'] = $doc_data['link_attributes'];
		$modx->documentObject['isfolder']        = $doc_data['isfolder'];
		$modx->documentObject['richtext']        = $doc_data['richtext'];
		$modx->documentObject['published']       = $doc_data['published'];
		$modx->documentObject['parent']          = $doc_data['parent'];
		$modx->documentObject['template']        = $doc_data['template'];
		$modx->documentObject['menuindex']       = $doc_data['menuindex'];
		$modx->documentObject['searchable']      = $doc_data['searchable'];
		$modx->documentObject['cacheable']       = $doc_data['cacheable'];
		$modx->documentObject['createdby']       = $doc_data['createdby'];
		$modx->documentObject['createdon']       = $doc_data['createdon'];
		$modx->documentObject['editedby']        = $doc_data['editedby'];
		$modx->documentObject['editedon']        = $doc_data['editedon'];
		$modx->documentObject['publishedby']     = $doc_data['publishedby'];
		$modx->documentObject['publishedon']     = $doc_data['publishedon'];
		$modx->documentObject['pub_date']        = $doc_data['pub_date'];
		$modx->documentObject['unpub_date']      = $doc_data['unpub_date'];
		$modx->documentObject['contentType']     = $doc_data['contentType'];
		$modx->documentObject['content_dispo']   = $doc_data['content_dispo'];
		$modx->documentObject['donthit']         = $doc_data['donthit'];
		$modx->documentObject['menutitle']       = $doc_data['menutitle'];
		$modx->documentObject['hidemenu']        = $doc_data['hidemenu'];


		// テンプレート変数読み込み
		$sql= "SELECT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value ";
		$sql .= "FROM " . $modx->getFullTableName("site_tmplvars") . " tv ";
		$sql .= "INNER JOIN " . $modx->getFullTableName("site_tmplvar_templates")." tvtpl ON tvtpl.tmplvarid = tv.id ";
		$sql .= "LEFT JOIN " . $search_tvs_table." tvc ON tvc.tmplvarid=tv.id AND tvc.contentid = '" . $docid. "' ";
		$sql .= "WHERE tvtpl.templateid = '" . $doc_data['template'] . "'";
		if ( ( $publish_history_id != 0 ) ) {
			$sql .= " AND ";
			$sql .= " tvc.editedon = '" . $publish_history_id . "'";
		}
		$rs= $modx->db->query($sql);
		$rowCount= $modx->db->getRecordCount($rs);
		if ($rowCount > 0) {
			for ($i= 0; $i < $rowCount; $i++) {
				$row= $modx->fetchRow($rs);
				$tmplvars[$row['name']]= array (
					$row['name'],
					$row['value'],
					$row['display'],
					$row['display_params'],
					$row['type']
				);
			}
			$modx->documentObject= array_merge($modx->documentObject, $tmplvars);
		}
		//キャッシュは強制的にOFF
		$modx->documentObject['cacheable'] = 0;

	}
	else
	{
		//unset( $modx->documentObject );
		// トップへ移動
		header("Location: index.php");
		exit;
	}

	break;


	// --------------------------------------------------------------------------------------------------------------------------------
	// 削除直後の処理
	// --------------------------------------------------------------------------------------------------------------------------------
	// From ページ削除、削除取り消し直後
	// (削除取り消し直前のイベントがないため、現在は削除直後のみ)
	case "OnDocFormDelete":

	// ドキュメントデータを取得
	$doc_data = $modx->getDocumentObject( 'id' , $docid );

	// 削除状態のときは承認保管箱も削除状態にする。逆のときは同様で非削除に
	$published       = $doc_data['published'];
	$deleted         = $doc_data['deleted'];
	$deletedon       = $doc_data['deletedon'];

	// 承認保管箱に当該のデータが存在するか?
	// SQL文構築
	$sql_string_where  = "";
	$sql_string_where .= " id='$docid' ";

	// SQL発行
	$result = $modx->db->select('*', $tbl_approval_content , $sql_string_where );

	// 存在する場合、UPDATE
	if( $modx->db->getRecordCount( $result ) >= 1 ) {
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " id='$docid' ";

		$sql_array_update = array(
				'published'	=> $published,
				'deleted'	=> $deleted,
				'deletedon'	=> $deletedon
				);
		// SQL発行
		$modx->db->update( $sql_array_update , $tbl_approval_content , $sql_string_where );
	}


	$children_id = $e->params['children'];

	$count_max = count ( $children_id );
	reset ( $children_id );
	for ( $count = 0 ; $count < $count_max ; $count ++ ) {

		// ドキュメントデータを取得
		$process_id = $children_id [ key ( $children_id ) ];
		$doc_data = $modx->getDocumentObject( 'id' , $process_id );

		// 削除状態のときは承認保管箱も削除状態にする。逆のときは同様で非削除に
		$published       = $doc_data['published'];
		$deleted         = $doc_data['deleted'];
		$deletedon       = $doc_data['deletedon'];

		// 承認保管箱に当該のデータが存在するか?
		// SQL文構築
		$sql_string_where  = "";
		$sql_string_where .= " id='$process_id' ";

		// SQL発行
		$result = $modx->db->select('*', $tbl_approval_content , $sql_string_where );

		// 存在する場合、UPDATE
		if( $modx->db->getRecordCount( $result ) >= 1 ) {
			// SQL文構築
			$sql_string_where  = "";
			$sql_string_where .= " id='$process_id' ";

			$sql_array_update = array(
					'published'	=> $published,
					'deleted'	=> $deleted,
					'deletedon'	=> $deletedon
					);
			// SQL発行
			$modx->db->update( $sql_array_update , $tbl_approval_content , $sql_string_where );
		}
		next ( $children_id );
	}
	break;
}

// ?>
