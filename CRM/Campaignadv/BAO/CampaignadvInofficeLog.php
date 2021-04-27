<?php
use CRM_Campaignadv_ExtensionUtil as E;

class CRM_Campaignadv_BAO_CampaignadvInofficeLog extends CRM_Campaignadv_DAO_CampaignadvInofficeLog {

  /**
   * Create a new CampaignadvInofficeLog based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Campaignadv_DAO_CampaignadvInofficeLog|NULL
   *
  public static function create($params) {
    $className = 'CRM_Campaignadv_DAO_CampaignadvInofficeLog';
    $entityName = 'CampaignadvInofficeLog';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
