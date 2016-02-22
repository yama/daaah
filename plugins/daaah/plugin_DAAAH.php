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

$daaah_path = $modx->config['base_path'] . 'assets/plugins/daaah/';
include_once($daaah_path . 'includes/plugin_code.inc');
