<?php

require_once 'campaignadv.civix.php';
use CRM_Campaignadv_ExtensionUtil as E;

/**
 * Implements hook_civicrm_dupeQuery().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_dupeQuery
 */
function campaignadv_civicrm_dupeQuery( $obj, $type, &$query ) {
  if ($type == 'table' && $obj->contact_type == 'Individual') {
    // For duplicate scans on Individuals, ensure we never include Public Officials
    // in the results, because those contacts should never be merged.
    $subTypeString = CRM_Utils_Array::implodePadded(['public_official']);

    foreach ($query as &$sql) {
      // We expect every existing query will select id1 and weight (and possibly id2).
      // So we must determine whether id2 is in use here. We could use some kind of
      // php-based SQL parser to do that, but that would require adding a third-party
      // library to this extension (to my knowledge there's no such parser in
      // civicrm core), and I'm not willing to take on that level of support.
      // Instead, we'll ensure the query is "limit 1" and run an actual SELECT
      // query with it; then we'll examine the row keys to determine if 'id2' is
      // an output column.
      // CONS:
      // - This an extra query execution for each dedupe rule, and in Contact Import,
      //   that will happen for each imported contact. This is a performance hit,
      //   though perhaps small. Unfortunately, there's no other way to determine
      //   the presence of `id2` without an SQL parser; and there's no information
      //   in hook parameters that would allow us to cache this (i.e. caching would
      //   help performance in the once-per-contact case of Imports, but I don't
      //   see a way to do it.)
      // But first, we must ensure this is actually a SELECT query (it should always
      // be, but I'm not taking chances on running arbitrary SQL from who knows
      // where in the codebase).
      if (strtolower(substr(trim($sql), 0, 6)) != 'select') {
        // We can't verify this is a SELECT query, so just skip with no modifications.
        continue;
      }
      $limitSql = preg_replace('/\blimit\b.*[0-9,\s]+$/i', '', $sql) . ' LIMIT 1';
      // We define a query that will always return at least one row, even if
      // $limitSql would return 0 rows. (We must have at least 1 row in order to
      // get an array of column labels.)
      // Reference: https://stackoverflow.com/a/58665805/6476602
      $columnsQuery = "
        SELECT t.*
        FROM (SELECT 1) AS ignoreMe
        LEFT JOIN (
          $limitSql
        ) AS t ON TRUE
      ";
      $columnsDao = CRM_Core_DAO::executeQuery($columnsQuery);
      $columnsDao->fetch();
      $columnKeys = array_keys($columnsDao->toArray());
      $hasId2 = (bool) in_array('id2', $columnKeys);

      // Now we'll wrap the original SQL in a subquery and use inner joins to limit by contact_sub_type.
      // By default, assume there is no 'id2' column.
      // NOTE: CiviCRM core expects the query to have 3 columns in exactly this order: id1, [optionally: id2,] weight.
      $selects = 'q.id1, q.weight';
      $joins = "inner join civicrm_contact c1 on q.id1 = c1.id and ifnull(c1.contact_sub_type, '') not like '%$subTypeString%'";
      if ($hasId2) {
        // Alter selects and joins if there is 'id2' column.
        $selects = 'q.id1, q.id2, q.weight';
        $joins .= " inner join civicrm_contact c2 on q.id2 = c2.id and ifnull(c2.contact_sub_type, '') not like '%$subTypeString%'";
      };
      $sql = "SELECT $selects FROM ($sql) q $joins";
    }
  }
}

/**
 * Implements hook_civicrm_alterAngular().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterAngular
 *
 */
function campaignadv_civicrm_alterAngular(\Civi\Angular\Manager $angular) {

  // Alter angular content for Mailing page, by adding some buttons and a div
  // that can function as a jQuery-ui dialog.
  $changeSet = \Civi\Angular\ChangeSet::create('inect_mailing_campaignadv_tools')
    ->alterHtml('~/crmMailing/BodyHtml.html',
      function (phpQueryObject $doc) {
        // Get the custom field ID for "in office".
        $inOfficeCustomFieldId = CRM_Core_BAO_CustomField::getCustomFieldID('electoral_in_office', 'electoral_districts');
        $doc->find('input[crm-mailing-token]')->before('
          <div id="campaignadvSelector" title="Select Public Official" style="display:none">
            <input
              crm-entityref="{entity: \'Contact\', select: {allowClear: true, placeholder: ts(\'Select Contact\')}, api: {params: {custom_' . $inOfficeCustomFieldId . ': 1}}}"
              crm-ui-id="campaignadv.official"
              name="campaignadv-official"
              ng-model="mailing.official_cid"
            />
          </div>
        ' .
        // Quick-and-dirty: we're not really using AngularJS to handle this custom
        // feature, instead just using good old-fashioned jQuery. This means our
        // call to $.dialog() won't be fired on page load, so we fire it on-click
        // of the "Select Public Official" button; thus we have here all the
        // params for $.dialog(), such as dialog properties, buttons, etc.
        '
          <a id="campaignadvSelectorOpen" onclick="CRM.$( \'#campaignadvSelector\' ).dialog({width: \'auto\', modal: true,   buttons: [
            {
              text: \'Cancel\',
              icon: \'fa-times\',
              click: function() {
                CRM.$( this ).dialog( \'close\' );
              }
            },
            {
              text: \'Select\',
              icon: \'fa-check\',
              click: function() {
                campaignadv.insertHtmlPublicOfficial(\'textarea[name=body_html]\', \'input[name=campaignadv-official]\');
                CRM.$( this ).dialog( \'close\' );
              }
            }
          ]});" style="float:left; margin-left: 10px;" class="button"><span>{{ts("Select Public Official")}}</span></a>
        ');
      });
  $angular->add($changeSet);
  // Add our javascript file which will provide the campaignadv.insertHtmlPublicOfficial()
  // callback referenced above for the "Select" button.
  CRM_Core_Resources::singleton()->addScriptFile('campaignadv', 'js/campaignadv-utils.js');
}

/**
 * Implements hook_civicrm_tokens().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_tokens
 *
 */
function campaignadv_civicrm_tokens(&$tokens) {
  /*
   * NOTE: This hook implementation provides certain tokens, listed here, to be
   * added to the list of available tokens. It also provides its own tool to
   * select and insert the 'PublicOfficial.filter_cid___*' token, which is not
   * defined here; all these toekns are processed in this extension's
   * hook_civicrm_tokenValues(), whether or not they are named here.
   */
  $tokens['PublicOfficial'] = array(
    'PublicOfficial.display_name' => E::ts('Display Name'),
    'PublicOfficial.first_name' => E::ts('First Name'),
    'PublicOfficial.last_name' => E::ts('Last Name'),
    'PublicOfficial.email' => E::ts('Email Address'),
    'PublicOfficial.phone' => E::ts('Phone Number'),
    'PublicOfficial.mailing_address' => E::ts('Mailing Address'),
    'PublicOfficial.preferred_contact_method' => E::ts('Preferred Contact Method'),
    // NOTE: 'PublicOfficial.filter_cid___*' not listed. See docblock above.
  );
}

/**
 * Implements hook_civicrm_tokenValues().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_tokenValues
 *
 */
function campaignadv_civicrm_tokenValues(&$values, $cids, $job = NULL, $tokens = [], $context = NULL) {
  // Normalize tokens for CiviMail vs non-civiMail.
  $tokens = _campaignadv_normalize_token_values($tokens);
  // Define a list of used tokens that we will process here.
  $ourTokens = CRM_Utils_Array::value('PublicOfficial', $tokens, array());

  // Shorthand variable for the contact_id specified in 'PublicOfficial.filter_cid___*' token.
  $filterCid = NULL;
  // Populate filter_cid variable if possible.
  foreach ($ourTokens as $token) {
    if (preg_match('/filter_cid___([0-9]+)/', $token, $matches)) {
      $filterCid = $matches[1];
      break;
    }
  }
  if (!$filterCid) {
    // No filter_cid token was found, so any of our tokens are meaningless.
    // Just return.
    return;
  }
  // Prepare to retrieve filter_cid token values via api.
  $apiReturn = $ourTokens;
  // 'preferred_contact_method' is a special token, based on a custom field.
  // Needs special handling.
  if (in_array('preferred_contact_method', $ourTokens)) {
    $preferredContactMethodCustomFieldId = CRM_Core_BAO_CustomField::getCustomFieldID('Preferred_communication_method', 'Public_official');
    $apiReturn[] = "custom_{$preferredContactMethodCustomFieldId}";
  }

  $apiParams = array(
    'id' => $filterCid,
    'return' => $apiReturn,
    'sequential' => 1,
  );
  $result = civicrm_api3('contact', 'get', $apiParams);
  if ($result['count'] != 1) {
    // We didn't find exactly one contact, so filter_cid token is meaningless,
    // thus the rest of our tokens are too. Just return.
    return;
  }
  // Shorthand variable for api returned values for this contact.
  $tokenValues = $result['values'][0];

  // 'preferred_contact_method' gets special formatting as a mailto or normal link.
  $tokenValues["custom_{$preferredContactMethodCustomFieldId}"] = _campaignadv_format_preferred_contact_method_token_value($tokenValues["custom_{$preferredContactMethodCustomFieldId}"]);
  $tokenValues["preferred_contact_method"] = $tokenValues["custom_{$preferredContactMethodCustomFieldId}"];

  // 'mailing_address' is also a special token, requires special handling to create
  // an address block per the configured "mailing label" format".
  if (in_array('mailing_address', $ourTokens)) {
    $rows = CRM_Contact_Form_Task_LabelCommon::getRows([$filterCid], 'Work', FALSE, FALSE, FALSE);
    $tokenValues['mailing_address'] = nl2br(CRM_Utils_Address::format($rows[0][$filterCid], NULL, $microformat, TRUE, $tokens));
  }

  // Now we have token values for that one filter_cid contact. They'll be the
  // same for all content recipients, so add them to $values now.
  foreach ($cids as $cid) {
    foreach ($ourTokens as $token) {
      $values[$cid]["PublicOfficial.{$token}"] = $tokenValues[$token];
    }
  }
}

/**
 * Implements hook_civicrm_custom().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_custom
 *
 */
function campaignadv_civicrm_custom($op, $groupID, $entityID, &$params) {
  // We could do an api call to test whether $groupID is the custom group named
  // 'electoral_districts', but this regex is faster (if more brittle WRT civicrm
  // upgrades), and I'm opting for speed because this hook fires with every
  // change of any custom field value.
  if (preg_match('/civicrm_value_electoral_districts_[0-9]+/', ($params[0]['table_name'] ?? ''))) {
    // If we're here, it means we're creating or editing an Electoral Districts record
    // (on 'delete' op, $params will be only the custom value ID).
    $namedParams = CRM_Utils_Array::rekey($params, 'column_name');
    if ($namedParams['electoral_districts_in_office']['value'] ?? 0) {
      // If this is an 'in_office' electoral district, it means the contact is now
      // marked as 'in office', so we will take certain actions.
      //
      // First, log current date and time for this custom value record.
      // We'll use this log elsewhere to remove such records for out-of-office public officials.
      // Note that when creating a completely new custom value record, params will
      // not contain a custom value table id; in that case we'll have to retrive it
      // based on the param values.
      if ($customValueId = ($params[0]['id'] ?? _campaignadv_getInOfficeCustomValueId($entityID, $params))) {
        civicrm_api3('CampaignadvInofficeLog', 'create', ['custom_value_id' => $customValueId]);
      }

      // We've updated the "electoral" custom fields, so  Update 'official/const'
      // relationshpis for the given contact accordingly.
      $result = civicrm_api3('contact', 'updateelectoralrelationships', array('contact_id' => $entityID));

      // Ensure contact has contact-sub-type "public official" (in addition to any exising sub-types):
      _campaignadv_rectifyContactPublicOfficialSubtype($entityID, TRUE);
    }
  }
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
 *
 */
function campaignadv_civicrm_buildForm($formName, &$form) {
  switch ($formName) {
    case 'CRM_Contact_Form_Task_Email':
    case 'CRM_Contact_Form_Task_PDF':
      CRM_Core_Resources::singleton()->addScriptFile('campaignadv', 'js/campaignadv-utils.js');
      CRM_Core_Resources::singleton()->addScriptFile('campaignadv', 'js/CRM_Contact_Form_Task_Email-and-PDF.js');
      $vars = array(
        'inOfficeCustomFieldId' => CRM_Core_BAO_CustomField::getCustomFieldID('electoral_in_office', 'electoral_districts'),
      );
      CRM_Core_Resources::singleton()->addVars('campaignadv', $vars);

      break;
  }
}

/**
 * Implements hook_civicrm_pageRun().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_pageRun
 *
 */
function campaignadv_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  if (!empty($page->angular)) {
    $f = '_' . __FUNCTION__ . '_Angular_' . str_replace('\\', '_', $pageName);
  }
  else {
    $f = '_' . __FUNCTION__ . '_' . $pageName;
  }

  if (function_exists($f)) {
    $f($page);
  }
  _campaignadv_periodicChecks();

  if ($page->getVar('_name') == 'CRM_Admin_Page_Extensions') {
    if (!_campaignadv_civicrm_checkMosaicoHooks()) {
      CRM_Core_Session::setStatus(
        E::ts('Extensions Campaign Advocacy and Mosaico would work better together if you install the Mosaico Hooks extension.'),
        E::ts('Campaign Advocacy Extension'),
        'info'
      );
    }
  }
}

/**
 * hook_civicrm_pageRun handler for civicrm core extensions admin page.
 * @param type $page
 */
function _campaignadv_civicrm_pageRun_CRM_Admin_Page_Extensions(&$page) {
  _campaignadv_prereqCheck();
}

/**
 * mosaicohooks extension dependency
 *
 */
function campaignadv_civicrm_mosaicoConfig(&$config) {
  if (_campaignadv_civicrm_checkMosaicoHooks()) {
    $config['tinymceConfig']['external_plugins']['campaignadv'] = CRM_Core_Resources::singleton()->getUrl('campaignadv', 'js/tinymce-plugins/campaignadv/plugin.js', 1);
    $config['tinymceConfig']['plugins'][0] .= ' campaignadv';
    $config['tinymceConfig']['toolbar1'] .= ' campaignadv';
    $config['tinymceConfig']['campaignadv'] = TRUE;
  }
}

function campaignadv_civicrm_mosaicoScriptUrlsAlter(&$scriptUrls) {
  $res = CRM_Core_Resources::singleton();
  $snippets = Civi::service('bundle.coreResources')->getAll();
  foreach ($snippets as $snippet) {
    $itemUrl = NULL;
    if (
        FALSE !== strpos($snippet['name'], 'js')
        && !strpos($snippet['name'], 'crm.menubar.js')
        && !strpos($snippet['name'], 'crm.menubar.min.js')
        && !strpos($snippet['name'], 'crm.wysiwyg.js')
        && !strpos($snippet['name'], 'crm.wysiwyg.min.js')
        && !strpos($snippet['name'], 'l10n-js')
      ) {
      if ($snippet['scriptUrl'] ?? FALSE) {
        $itemUrl = $snippet['scriptUrl'];
      }
      elseif ($snippet['scriptFile'] && count($snippet['scriptFile']) == 2) {
        $itemUrl = $res->getUrl($snippet['scriptFile'][0], $snippet['scriptFile'][1], TRUE);
      }
      if ($itemUrl) {
        $scriptUrls[] = $itemUrl;
      }
    }
  }

  // Include our own JS (this url generates dynammic JS containing apprpopriate settings per session).
  $url = CRM_Utils_System::url('civicrm/campaignadv/mosaico-js', '', TRUE, NULL, NULL, NULL, NULL);
  $scriptUrls[] = $url;
}

function campaignadv_civicrm_mosaicoStyleUrlsAlter(&$styleUrls) {
  $res = CRM_Core_Resources::singleton();
  $snippets = array_merge(Civi::service('bundle.coreResources')->getAll(), Civi::service('bundle.coreStyles')->getAll());

  // crm-i.css added ahead of other styles so it can be overridden by FA.
  array_unshift($styleUrls, $res->getUrl('civicrm', 'css/crm-i.css', TRUE));

  foreach ($snippets as $snippet) {
    $itemUrl = NULL;
    if (
      FALSE !== strpos($snippet['name'], 'css')
      // Exclude jquery ui theme styles, which conflict with Mosaico styles.
      && FALSE === strpos($snippet['name'], '/jquery-ui/themes/')
    ) {
      if ($snippet['styleUrl'] ?? FALSE) {
        $itemUrl = $snippet['styleUrl'];
      }
      elseif ($snippet['styleFile'] && count($snippet['styleFile']) == 2) {
        $itemUrl = $res->getUrl($snippet['styleFile'][0], $snippet['styleFile'][1], TRUE);
      }
      if ($itemUrl) {
        $styleUrls[] = $itemUrl;
      }
    }
  }

  // Include our own abridged styles from jquery-ui 'smoothness' theme, as
  // required for our jquery-ui dialog, but which don't conflict with Mosaico.
  $styleUrls[] = $res->getUrl('campaignadv', 'css/jquery-ui-smoothness-partial.css', TRUE);
}

/**
 * hook_civicrm_pageRun handler for "extensionsui" extensions admin page.
 * @param type $page
 */
function _campaignadv_civicrm_pageRun_Angular_Civi_Angular_Page_Main(&$page) {
  _campaignadv_prereqCheck();
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function campaignadv_civicrm_config(&$config) {
  _campaignadv_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function campaignadv_civicrm_xmlMenu(&$files) {
  _campaignadv_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function campaignadv_civicrm_install() {
  _campaignadv_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function campaignadv_civicrm_postInstall() {
  _campaignadv_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function campaignadv_civicrm_uninstall() {
  _campaignadv_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function campaignadv_civicrm_enable() {
  _campaignadv_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function campaignadv_civicrm_disable() {
  _campaignadv_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function campaignadv_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _campaignadv_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function campaignadv_civicrm_managed(&$entities) {
  _campaignadv_civix_civicrm_managed($entities);
  foreach ($entities as &$e) {
    if (empty($e['params']['version'])) {
      $e['params']['version'] = '3';
    }
  }

}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function campaignadv_civicrm_caseTypes(&$caseTypes) {
  _campaignadv_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function campaignadv_civicrm_angularModules(&$angularModules) {
  _campaignadv_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function campaignadv_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _campaignadv_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function campaignadv_civicrm_entityTypes(&$entityTypes) {
  _campaignadv_civix_civicrm_entityTypes($entityTypes);
}

function _campaignadv_prereqCheck() {
  $unmet = CRM_Campaignadv_Upgrader::checkExtensionDependencies();
  CRM_Campaignadv_Upgrader::displayDependencyErrors($unmet);
}

function _campaignadv_periodicChecks() {
  $session = CRM_Core_Session::singleton();
  if (
    !CRM_Core_Permission::check('administer CiviCRM')
    || !$session->timer('check_CRM_Campaignadv_Depends', CRM_Utils_Check::CHECK_TIMER)
  ) {
    return;
  }

  _campaignadv_prereqCheck();
}

/**
 * Normalize token array structure. CiviCRM presents tokens to hook_civicrm_tokenValues
 * in varying array structures, depending on whether the context is CiviMail (in
 * which case the tokens are named in array keys) or one-off mailings / merge
 * documents (in which case tokens are named in array values). This function
 * ensures all are named in array keys.
 *
 * @param type $tokens
 * @return type
 */
function _campaignadv_normalize_token_values($tokens) {
  foreach ($tokens as $key => $values) {
    if (!array_key_exists(0, $values)) {
      $tokens[$key] = array_keys($values);
    }
  }
  return $tokens;
}

function _campaignadv_format_preferred_contact_method_token_value($value) {
  $rule = new HTML_QuickForm_Rule_Email();
  if ($rule->validate($value)) {
    $value = '<a href="mailto:' . $value . '">' . $value . '</a>';
  }
  else {
    $value = '<a href="' . $value . '">' . $value . '</a>';
  }
  return $value;
}

function _campaignadv_civicrm_checkMosaicoHooks() {
  $extensionIsInstalled = TRUE;
  $manager = CRM_Extension_System::singleton()->getManager();
  $dependencies = array(
    'com.joineryhq.mosaicohooks',
  );

  foreach ($dependencies as $ext) {
    if ($manager->getStatus($ext) != CRM_Extension_Manager::STATUS_INSTALLED) {
      $extensionIsInstalled = FALSE;
    }
  }

  return $extensionIsInstalled;
}

/**
 * Retrieve the id from the custom_value_table for the newest record matching
 * the given entityId and column values (params)
 *
 * @param type $entityId
 * @param type $params
 */
function _campaignadv_getInOfficeCustomValueId($entityId, $params) {
  // Note that there may be multiple records with identical vavles, so
  // we should get the most recent one.
  $electoralDistrictses = \Civi\Api4\CustomValue::get('electoral_districts')
    ->setCheckPermissions(FALSE)
    ->addWhere('entity_id', '=', $entityId)
    ->addOrderBy('id', 'DESC')
    ->setLimit(1);
  foreach ($params as $param) {
    $customFieldName = _campaignadv_getCustomFieldNameById($param['custom_field_id']);
    $electoralDistrictses->addWhere($customFieldName, '=', $param['value']);
  }
  $electoralDistricts = $electoralDistrictses->execute()->first();
  return $electoralDistricts['id'] ?? NULL;
}

/**
 * Get the field name for a given custom field by id. Caches to prevent multiple
 * redundant api calls.
 *
 * @staticvar array $cache
 * @param Int $customFieldId
 * @return String
 */
function _campaignadv_getCustomFieldNameById($customFieldId) {
  static $cache = [];
  if (empty($cache[$customFieldId])) {
    $cache[$customFieldId] = civicrm_api3('CustomField', 'getvalue', [
      'return' => "name",
      'id' => $customFieldId,
    ]);
  }
  return $cache[$customFieldId];
}

/**
 * Ensure contact has or does not have the public_official subtype.
 *
 * @param Int $contactId
 * @param Boolean $shouldHaveSubtype
 */
function _campaignadv_rectifyContactPublicOfficialSubtype($contactId, $shouldHaveSubtype) {
  // Initialize variables storing sub-types.
  $contactSubTypesOriginal = $contactSubTypesToSave = [];
  // Use sub-types fetched via api, pass thru strtolower for easy comparison.
  $contact = civicrm_api3('Contact', 'get', array(
    'sequential' => 1,
    'id' => $contactId,
    'return' => ["contact_sub_type"],
  ));
  $contactSubTypesOriginal = $contactSubTypesToSave = array_map('strtolower', $contact['values'][0]['contact_sub_type']);
  if ($shouldHaveSubtype) {
    // If so instructed, make sure this contact gets the subtype.
    // Only add 'public_official' sub-type if not there already. (CiviCRM
    // will actually let you record the same sub-type multiple times for one
    // contat, and AFAIK it won't break anything, but it's nonstandard and
    // thus not ideal.
    if (!in_array('public_official', $contactSubTypesToSave)) {
      $contactSubTypesToSave[] = 'public_official';
    }
  }
  else {
    // If so instructed, ensure contact does NOT get the 'public official' sub-type.
    // Filter out the 'public_official' sub-type with array_filter.
    if (!empty($contactSubTypesToSave)) {
      $contactSubTypesToSave = array_filter($contactSubTypesToSave, function($v) {
        return (strtolower($v) != 'public_official');
      });
    }
  }
  // Update sub-types if they need to be changed.
  if ($contactSubTypesToSave !== $contactSubTypesOriginal) {
    $contact = civicrm_api3('contact', 'create', array(
      'id' => $contactId,
      'contact_sub_type' => $contactSubTypesToSave,
    ));
  }
}
