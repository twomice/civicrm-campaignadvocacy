<?php
use CRM_Campaignadv_ExtensionUtil as E;

/**
 * Campaignadv.Removeoutgoingofficials API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_campaignadv_Removeoutgoingofficials_spec(&$spec) {
}

/**
 * Campaignadv.Removeoutgoingofficials API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_campaignadv_Removeoutgoingofficials($params) {
  // Initialize counts for api return values.
  $electoralRecordRemovedCount = 0;
  $subtypeRemovedCount = 0;
  
  $edTableName = civicrm_api3('CustomGroup', 'getvalue', ['return' => "table_name",'name' => "electoral_districts"]);
  
  // Query for all contacts marked 'in_office' who have not been logged as 'in office'
  // in the last 36 hours.
  $cutoffUnixtime = strtotime('36 hours ago');
  $query = "
    SELECT
      ed.id
    FROM
      {$edTableName} ed
      INNER JOIN civicrm_campaignadv_inoffice_log cil 
        ON cil.custom_value_id = ed.id
        and ed.electoral_districts_in_office = 1
    WHERE
      cil.time < %1
  ";
  $params = [
    '1' => array($cutoffUnixtime, 'Int')
  ];
  $dao = CRM_Core_DAO::executeQuery($query, $params);
  if ($dao->N) {
    // If any such records were found, we'll delete the related custom value,
    // so  this contact will no longer have this in_office electoral record.
        
    // Now we can delete the electoral custom value record.
    $customGroup = civicrm_api3('customGroup', 'get', ['name' => 'electoral_districts']);
    $customGroupId = ($customGroup['id'] ?? NULL);
    if ($customGroupId) {
      while ($dao->fetch()) {
        // First we must get the entity_id so we  know who the contact is; we'll use this below.
        $electoralDistricts = \Civi\Api4\CustomValue::get('electoral_districts')
          ->setCheckPermissions(FALSE)
          ->addWhere('id', '=', $dao->id);
        $electoralDistrict = $electoralDistricts->execute()->first();
        $contactId = $electoralDistrict['entity_id'];
        
        // Now we can delete the electoral record.
        CRM_Core_BAO_CustomValue::deleteCustomValue($dao->id, $customGroupId);
        $electoralRecordRemovedCount++;        
      }      
    }
  }
  
  // Find all public_official contacts who have no in_office records, and rectify subtype for each.
  // Start with all contacts having in_office records.
  $inOfficeCustomFieldId = CRM_Core_BAO_CustomField::getCustomFieldID('electoral_in_office', 'electoral_districts');
  $inOfficeContacts = civicrm_api3('Contact', 'get', array(
    "custom_{$inOfficeCustomFieldId}" => 1,
    'return' => 'id',
    'options' => ['limit' => 0],
  ));
  $inOfficeContactIds = array_keys($inOfficeContacts['values']);
  // Find public_official contats who are not in that set.
  $contactsToRectify = civicrm_api3('Contact', 'get', [
    'id' => ['NOT IN' => $inOfficeContactIds],
    'contact_sub_type' => "Public_official",
    'return' => 'id',
    'options' => ['limit' => 0],
  ]);  
  $contactIdsToRectify = array_keys($contactsToRectify['values']);
  foreach ($contactIdsToRectify as $contactIdToRectify) {
    _campaignadv_rectifyContactPublicOfficialSubtype($contactIdToRectify, FALSE);
    $subtypeRemovedCount++;

    // Update constituent relationships for this contact, on the assumption
    // that their in-office status has changed.
    civicrm_api3('contact', 'updateelectoralrelationships', array('contact_id' => $contactIdToRectify));  
  }
  
  // Now, we can remove all inofficelog records that are too old to be useful,
  // just to keep the log table from growing. 72 hours seems reasonable at time of writing,
  // but maybe there's a lower value that's still safe; not going to worry about it
  // just now.
  $minimumTimeUnixtime = strtotime('72 hours ago');
  $query = "
    DELETE
    FROM
      civicrm_campaignadv_inoffice_log 
    WHERE
      time < %1
  ";
  $params = [
    '1' => array($minimumTimeUnixtime, 'Int')
  ];
  $dao = CRM_Core_DAO::executeQuery($query, $params);
  
  // Report basic info with counts.
  $returnValue = E::ts("Completed. Removed %1 'in office' electoral district records; found %2 contacts who should not have the Public Offical sub-type.", [$electoralRecordRemovedCount, $subtypeRemovedCount]);
  return civicrm_api3_create_success($returnValue, $params, 'Campaignadv', 'Removeoutgoingofficials');

}
