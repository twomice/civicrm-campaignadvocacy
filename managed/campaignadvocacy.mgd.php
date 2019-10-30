<?php

/**
 * This file registers Campaignadvocacy entities via hook_civicrm_managed.
 * Lifecycle events in this extension will cause these registry records to be
 * automatically inserted, updated, or deleted from the database as appropriate.
 * For more details, see "hook_civicrm_managed" (at
 * https://docs.civicrm.org/dev/en/master/hooks/hook_civicrm_managed/) as well
 * as "API and the Art of Installation" (at
 * https://civicrm.org/blogs/totten/api-and-art-installation).
 */

return array (
  array (
    'name' => 'CRM_Campaignadvocacy_RelationshipType_officalConstituent',
    'entity' => 'RelationshipType',
    'params' =>
    array (
      "name_a_b" => "Constituent of public official",
      "label_a_b" => "Constituent of public official",
      "name_b_a" => "Public official for constituent",
      "label_b_a" => "Public official for constituent",
      "description" => "Public official / constituent",
      "contact_type_a" => "Individual",
      "contact_type_b" => "Individual",
      "is_reserved" => "0",
      "is_active" => "1",
    ),
  ),
);
