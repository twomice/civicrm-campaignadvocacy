<?php
use CRM_Campaignadv_ExtensionUtil as E;

/**
 * Contact.Updateelectoralrelationships API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contact_Updateelectoralrelationships_spec(&$spec) {
  $spec['contact_id'] = array(
    'description' => E::ts('Limit action to a specific contact'),
    'title' => E::ts('Contact ID'),
  );
}

/**
 * Contact.Updateelectoralrelationships API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contact_Updateelectoralrelationships($params) {
  $edTableName = civicrm_api3('CustomGroup', 'getvalue', ['return' => "table_name",'name' => "electoral_districts"]);
  $relationshipTypeId = (int)civicrm_api3('RelationshipType', 'getvalue', ['return' => "id",'name_a_b' => "Constituent_of_public_official"]);
  $tempTableName = 'campaigndadv_electoral_relationships';

  // Define counters for api result reporting.
  $deletedRelationshipCount = 0;
  $createdRelationshipCount = 0;

  // Define a base query to create a temporary table of all offical/constituent
  // relationships based on data in Electoral custom fields. We'll use this to
  // compare with existing relationships, so we can efficiently JUST remove the
  // relationships that exist but should not, and JUST create the relationships
  // that don't exist but should.
  //
  $tempTableQuery = "
    CREATE TEMPORARY TABLE IF NOT EXISTS $tempTableName
    SELECT DISTINCT ofc.entity_id as ofc_cid, const.entity_id as const_cid
    FROM
      {$edTableName} ofc
      INNER JOIN {$edTableName} const
        on ofc.electoral_districts_in_office = '1'
        and const.electoral_districts_in_office = '0'
        and const.electoral_districts_level = ofc.electoral_districts_level
        AND const.electoral_districts_chamber = ofc.electoral_districts_chamber
        -- districts must match, OR the ofc district must be empty (e.g. City Mayor and US Senators have no districts; constituency applies regardless of district)
        AND (ofc.electoral_districts_district = '' or const.electoral_districts_district = ofc.electoral_districts_district)
        AND const.electoral_districts_states_provinces = ofc.electoral_districts_states_provinces
        AND const.electoral_districts_county = ofc.electoral_districts_county
        AND const.electoral_districts_county != '[DistrictNotFound]'
        AND const.electoral_districts_city = ofc.electoral_districts_city
      INNER JOIN civicrm_contact ofcc
        ON ofcc.id = ofc.entity_id
        -- limit to Individuals even though orgs may have electoral data, because
        -- the 'official/constituent' relationship is for individuals only.
        and ofcc.contact_type = 'individual'
      INNER JOIN civicrm_contact constc
        ON constc.id = const.entity_id 
        -- limit to Individuals even though orgs may have electoral data, because
        -- the 'official/constituent' relationship is for individuals only.
        and constc.contact_type = 'individual'
  ";
  $tempTableQueryParams = array();

  // If we're given a contact_id in params, add a WHERE clause to limit the base
  // query only to that contact's relationships.
  $contact_id = CRM_Utils_Array::value('contact_id', $params);
  if ($contact_id) {
    $tempTableQuery .= "WHERE %1 IN (ofc.entity_id, const.entity_id)";
    $tempTableQueryParams[1] = array($contact_id, 'Int');
  }
  CRM_Core_DAO::executeQuery($tempTableQuery, $tempTableQueryParams);

  // Query for relationships that exist but should not.
  $unwantedRshipQuery = "
    SELECT r.id
    FROM
      civicrm_relationship r
      LEFT JOIN {$tempTableName} t
        ON r.contact_id_b = t.ofc_cid
          AND r.contact_id_a = t.const_cid
    WHERE
      t.ofc_cid is null
          AND r.relationship_type_id = {$relationshipTypeId}
  ";
  $unwantedRshipQueryParams = array();
  if ($contact_id) {
    $unwantedRshipQuery .= "AND %1 IN (r.contact_id_a, r.contact_id_b)";
    $unwantedRshipQueryParams[1] = array($contact_id, 'Int');
  }
  $dao = CRM_Core_DAO::executeQuery($unwantedRshipQuery, $unwantedRshipQueryParams);
  while ($dao->fetch()) {
    $result = civicrm_api3('Relationship', 'delete', [
      'id' => $dao->id,
    ]);
    $deletedRelationshipCount += $result['count'];
  }

  // Query for relationships that should exist but do not.
  $newRshipQuery = "
    SELECT t.ofc_cid, t.const_cid
    FROM
      {$tempTableName} t
      LEFT JOIN civicrm_relationship r
        ON r.contact_id_b = t.ofc_cid
          AND r.contact_id_a = t.const_cid
          AND r.relationship_type_id = {$relationshipTypeId}
    WHERE
      r.id is null
  ";
  $dao = CRM_Core_DAO::executeQuery($newRshipQuery);
  while ($dao->fetch()) {
    try {
      $result = civicrm_api3('Relationship', 'create', [
        'relationship_type_id' => $relationshipTypeId,
        'contact_id_b' => $dao->ofc_cid,
        'contact_id_a' => $dao->const_cid,
      ]);
      $createdRelationshipCount += $result['count'];
    }
    catch (CiviCRM_API3_Exception $e) {
      // If the error is because relationship already exists, we can ignore
      // it, because all we care about it that the relationship should exist.
      // Otherwise, throw it to be handled upstream.
      if ($e->getMessage() != 'Duplicate Relationship') {
        throw $e;
      }
    }
  }

  // Return value indicates how many relationships were deleted and created.
  return civicrm_api3_create_success("$deletedRelationshipCount relationships deleted; $createdRelationshipCount new relationships created.", $params, 'Contact', 'updateelectoralrelationships');

}
