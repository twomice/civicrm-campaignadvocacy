<?php
use CRM_Campaignadv_ExtensionUtil as E;

return [
  'name' => 'CampaignadvInofficeLog',
  'table' => 'civicrm_campaignadv_inoffice_log',
  'class' => 'CRM_Campaignadv_DAO_CampaignadvInofficeLog',
  'getInfo' => fn() => [
    'title' => E::ts('Campaignadv Inoffice Log'),
    'title_plural' => E::ts('Campaignadv Inoffice Logs'),
    'description' => E::ts('Timestamps for most recent "in office" designation from Electoral API extension'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'custom_value_id' => [
      'title' => E::ts('Custom Value ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Soft FK to [electoral_districts_custom_table].id'),
      'primary_key' => TRUE,
    ],
    'time' => [
      'title' => E::ts('Time'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'description' => E::ts('Log time as unix timestamp'),
    ],
  ],
];
