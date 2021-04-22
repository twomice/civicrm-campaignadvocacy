<?php
use CRM_Campaignadv_ExtensionUtil as E;

/**
 * CampaignadvInofficeLog.create API specification (optional).
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_campaignadv_inoffice_log_create_spec(&$spec) {
  $spec['contact_id']['api.required'] = 1;
  // 'time' defaults to current timestamp
  $spec['time']['api.default'] = time();
}

/**
 * CampaignadvInofficeLog.create API.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws API_Exception
 */
function civicrm_api3_campaignadv_inoffice_log_create($params) {
  // 'create' op should always overwrite any existing value, therefore, delete such if it exists;
  // otherwise we'll get a DB error 'already exists'.
  $baoName = _civicrm_api3_get_BAO(__FUNCTION__);
  $bao = new $baoName;
  $bao->contact_id = $params['contact_id'];
  $bao->delete();
  
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params, 'CampaignadvInofficeLog');
}

/**
 * CampaignadvInofficeLog.delete API.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws API_Exception
 */
function civicrm_api3_campaignadv_inoffice_log_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * CampaignadvInofficeLog.get API.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws API_Exception
 */
function civicrm_api3_campaignadv_inoffice_log_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params, TRUE, 'CampaignadvInofficeLog');
}
