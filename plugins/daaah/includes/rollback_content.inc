<?php

// ----------------------------------------------------------------
// ロールバックを行う場合のルーチン -- はじめ
// ----------------------------------------------------------------

if($_REQUEST['rolesw'] != 'role') request_err();

// 本文データのロールバック
// ----------------------------------------------------------------

// SQL文構築

$f = array();
$f['content']     = $doc_data['content'];
$f['pagetitle']   = $doc_data['pagetitle'];
$f['longtitle']   = $doc_data['longtitle'];
$f['menutitle']   = $doc_data['menutitle'];
$f['description'] = $doc_data['description'];
$f['alias']       = $doc_data['alias'];
$f['editedon']    = $doc_data['editedon'];
$f['editedby']    = $doc_data['editedby'];
$f = $modx->db->escape($f);

// SQL発行
$modx->db->update( $f , '[+prefix+]site_content' , "id='{$docid}'" );


// テンプレート変数データのロールバック
// ----------------------------------------------------------------
if( $tmpl_flag == 1 )
{
    $where  = "contentid='{$docid}' AND editedon='{$hisid}'";
    $result = $modx->db->select('*', '[+prefix+]history_of_site_tmplvar_contentvalues' , $where);
    $a_tvs_app = array();
    while( $row = $modx->db->getRow( $result ))
    {
        $a_tvs_app[] = sprintf("('%s','%s','%s','%s')",$row['id'],$row['tmplvarid'],$row['contentid'],$modx->db->escape($row['value'] ));
    }

    // テンプレート変数登録
    if(!empty($a_tvs_app)) {
        $sql_app = 'REPLACE INTO '.$tbl_contentvalues.' (id,tmplvarid, contentid, value) VALUES '.implode(',', $a_tvs_app);
        $rs = $modx->db->query($sql_app);
    }
}

// ----------------------------------------------------------------
// ロールバックを行う場合のルーチン -- おわり
// ----------------------------------------------------------------
