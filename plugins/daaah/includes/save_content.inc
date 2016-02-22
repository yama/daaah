<?php
    
    // コンテンツ保存時の処理
    // From save_content.processor.php
    
    $docid = $_REQUEST['docid'];
    
    // 承認処理  -- 開始
    $approval_change_flag = 0;
    
    for ( $count = 0 ; $count < $conf['approval_level'] ; $count ++ )
    { 
        $pub_level        = $count + 1;
        $approval_value   = 0;
        $record_exit_flag = 0;
        if(($level_onoff[$pub_level] == 1) || (!$modx->hasPermission('publish_document')))
        {
            // フォームから値を取得
            $form_name  = 'approval_and_level' . $pub_level;
            $s_approval = (isset($_POST[$form_name])) ? $modx->db->escape($_POST[$form_name]) : '0';
            $form_name  = 'comment_and_level'  . $pub_level;
            $s_comment  = (isset($_POST[$form_name])) ? $modx->db->escape($_POST[$form_name]) : '';
            // 公開権限のない人がOnDocFormSaveに来たとき(ページ内容を編集したとき)は
            // 「承認しない」にリセットする
            if(!$modx->hasPermission('publish_document')) $s_approval = '0';
            
            // 承認状況更新
            // DBにレコードがあるかどうか 2011.05.08 t.k. $s_approvalの修正
            if(isset($s_approval))
            {
                $f['approval'] = $s_approval;
                $modx->db->update($f, '[+prefix+]approvals', "id='{$docid}' AND level='{$pub_level}'");
            }
            
            if($modx->hasPermission('publish_document'))
            {
                $approval_value = 0;
                if(isset($s_approval)) $approval_value = $s_approval;
                
                if(($s_approval != $approval_value ) || ( $s_comment != ''))
                {
                    // 承認ステータスに変化があったのでフラグをON
                    if(isset($s_approval))  $approval_change_flag = 1;
                    
                    // 承認履歴更新
                    $f = array();
                    $f['id']       = $docid;
                    $f['level']    = $pub_level;
                    $f['approval'] = $s_approval;
                    $f['user_id']  = $modx->getLoginUserID();
                    $f['role_id']  = $_SESSION['mgrRole'];
                    $f['editedon'] = $_SERVER['HTTP_REQUEST_TIME'];
                    $f['comment']  = $s_comment;
                    $modx->db->insert( $f , '[+prefix+]approval_logs' );
                }
            }
        }
    }
    
    // 承認処理  -- 終わり
    // バックアップ処理  -- はじめ
    // ドキュメントデータを取得
    
    // 承認状態確認
    $approvalStatus = checkApprovalStatus($docid , $conf['approval_level']); // true|false
    
    $documentObject = $modx->getDocumentObject('id' , $docid );
    
    $f = array();
    // すべて承認されていた場合、ドキュメントを公開設定にする
    if($approvalStatus && $documentObject['published']==1)
    {
        $f['published'] = 1;
        $f['deleted']   = 0;
        $modx->db->update($f, '[+prefix+]site_content', "id='{$docid}'");
    }
    elseif(!$approvalStatus)
    {
        // すべて承認していない状態、かつ新規ドキュメントのときは非公開にする
        //$sql['published'] = 0; 2011.05.08 t.k.
        $f['deletedon'] = $_SERVER['HTTP_REQUEST_TIME'];
        $modx->db->update($f, '[+prefix+]site_content', "id='{$docid}'");
    }
    
    if($approvalStatus)
    { // すべての承認を受けた場合のみ履歴に登録
        $f = $documentObject;
        unset($f['id']);
        unset($f['deletedby']);
        unset($f['privatemgr']);
        unset($f['privateweb']);
        if(isset($f['haskeywords'])) unset($f['haskeywords']);
        if(isset($f['hasmetatags'])) unset($f['hasmetatags']);
        $f = $modx->db->escape($f);
        $modx->db->insert($f,'[+prefix+]history_of_site_content');
    
        // 承認保管箱に登録
        $f = $documentObject;
        unset($f['deleted']);
        unset($f['deletedby']);
        unset($f['deletedon']);
        unset($f['privatemgr']);
        unset($f['privateweb']);
        if(isset($f['haskeywords'])) unset($f['haskeywords']);
        if(isset($f['hasmetatags'])) unset($f['hasmetatags']);
        
        $rs = $modx->db->select('*','[+prefix+]approvaled_site_content', "id='{$docid}'");
        if(!$modx->db->getRecordCount($rs))
            $modx->db->insert($f,'[+prefix+]approvaled_site_content');
        else
            $modx->db->update($f,'[+prefix+]approvaled_site_content', "id='{$docid}'");
    
        // テンプレート変数データをゲット
        $rs = $modx->db->select('id,tmplvarid,contentid,value,', '[+prefix+]site_tmplvar_contentvalues', "contentid='{$docid}'");
        while($f = $modx->db->getRow($rs))
        {
            // テンプレート変数履歴に登録
            $f = $modx->db->escape($f);
            $f['editedon'] = $documentObject['editedon'];
            $tmplvarid = $f['tmplvarid'];
            $rs = $modx->db->select('*', '[+prefix+]site_tmplvar_contentvalues', "tmplvarid='{$tmplvarid}' AND contentid='{$docid}'");
            if($modx->db->getRecordCount($rs))
                $modx->db->update($f, '[+prefix+]history_of_site_tmplvar_contentvalues', "tmplvarid='{$tmplvarid}' AND contentid='{$docid}'");
            else
                $modx->db->insert($f, '[+prefix+]history_of_site_tmplvar_contentvalues');
            
            // テンプレート変数(承認済み保管箱)に登録
            $rs = $modx->db->select('*', '[+prefix+]approvaled_site_tmplvar_contentvalues', "tmplvarid='{$tmplvarid}' AND contentid='{$docid}'");
            if($modx->db->getRecordCount($rs))
                $modx->db->update($f, '[+prefix+]approvaled_site_tmplvar_contentvalues', "tmplvarid='{$tmplvarid}' AND contentid='{$docid}'");
            else
                $modx->db->insert($f, '[+prefix+]approvaled_site_tmplvar_contentvalues');
        }
    }
    
    // OnDocFormSaveイベントのときに削除状態のときは承認保管箱も削除状態にする
    
    if($documentObject['deleted'] == 1)
    {
        // 承認保管箱に当該のデータが存在するか?
        
        $rs = $modx->db->select('*', '[+prefix+]approvaled_site_content' , " id='{$docid}'");
        
        // 存在する場合、UPDATE
        if($modx->db->getRecordCount($rs ) >= 1 )
        {
            $f = array();
            $f['published'] = $documentObject['published'];
            $f['deleted']   = $documentObject['deleted'];
            $f['deletedon'] = $documentObject['deletedon'];
            $modx->db->update( $f , '[+prefix+]approvaled_site_content' , "id='{$docid}'");
        }
    }
    // ----------------------------------------------------------------
    // バックアップ処理  -- 終わり
    // ----------------------------------------------------------------
