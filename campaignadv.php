<?php

require_once 'campaignadv.civix.php';
use CRM_Campaignadv_ExtensionUtil as E;

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
              crm-entityref="{entity: \'Contact\', select: {allowClear: true, placeholder: ts(\'Select Contact\')}, api: {params: {custom_'. $inOfficeCustomFieldId .': 1}}}"
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
function campaignadv_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = [], $context = null) {
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
  if (preg_match('/civicrm_value_electoral_districts_[0-9]+/', $params[0]['table_name'])) {
    // We've updated the "electoral" custom fields, so  Update 'official/const'
    // relationshpis for the given contact accordingly.
    $result = civicrm_api3('contact', 'updateelectoralrelationships', array('contact_id' => $entityID));

    // Check if the contact now has "in office" = true; if so, ensure contact
    // has contact-sub-type "public official" (in addition to any exising sub-types)
    $inOfficeCustomFieldId = CRM_Core_BAO_CustomField::getCustomFieldID('electoral_in_office', 'electoral_districts');
    $contact = civicrm_api3('Contact', 'get', array(
      'sequential' => 1,
      'id' => $entityID,
      "custom_{$inOfficeCustomFieldId}" => 1,
      'return' => ["contact_sub_type"],
    ));
    // Calculate correct sub-types based on whether in-office or not.
    if ($contact['count']) {
      // Contact is in office; ensure 'public official' sub-type.
      // Use sub-types fetched via api, pass thru strtolower for easy comparison.
      $contactSubTypes = array_map('strtolower', $contact['values'][0]['contact_sub_type']);
      // Only add 'public_official' sub-type if not there already. (CiviCRM
      // will actually let you record the same sub-type multiple times for one
      // contat, and AFAIK it won't break anything, but it's nonstandard and
      // thus not idea.
      if (!in_array('public_official', $contactSubTypes)) {
        $contactSubTypes[] = 'public_official';
      }
    }
    else {
      // Contact is NOT in office; ensure NO 'public official' sub-type.
      // We have to query the api for current sub-types, because the previous
      // api call returne nothing -- that's why we're here.
      $contact = civicrm_api3('Contact', 'get', array(
        'sequential' => 1,
        'id' => $entityID,
        'return' => ["contact_sub_type"],
      ));
      // Filter out the 'public_official' sub-type with array_filter.
      $contactSubTypes = array_filter($contact['values'][0]['contact_sub_type'], function($v) {
        return (strtolower($v) != 'public_official');
      });
    }
    // Update sub-types. TODO: we could avoid doing this unnecessarily by checking
    // whether sub-types changed.
    $contact = civicrm_api3('contact', 'create', array(
      'id' => $entityID,
      'contact_sub_type' => $contactSubTypes,
    ));
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
function campaignadv_civicrm_mosaicoConfigAlter(&$config) {
  $config['tinymceConfig']['external_plugins']['campaignadv'] = CRM_Core_Resources::singleton()->getUrl('campaignadv', 'js/tinymce-plugins/campaignadv/plugin.js', 1);
  $config['tinymceConfig']['plugins'][0] .= ' campaignadv';
  $config['tinymceConfig']['toolbar1'] .= ' campaignadv';
  $config['tinymceConfig']['campaignadv'] = true;
}

function campaignadv_civicrm_mosaicoScriptUrlsAlter(&$scriptUrls) {
  $res = CRM_Core_Resources::singleton();

  $coreResourceList = $res->coreResourceList('html-header');
  $coreResourceList = array_filter($coreResourceList, 'is_string');
  foreach ($coreResourceList as $item) {
    if (
      FALSE !== strpos($item, 'js')
      && !strpos($item, 'crm.menubar.js')
      && !strpos($item, 'crm.wysiwyg.js')
      && !strpos($item, 'l10n-js')
    ) {
      if ($res->isFullyFormedUrl($item)) {
        $itemUrl = $item;
      }
      else {
        $item = CRM_Core_Resources::filterMinify('civicrm', $item);
        $itemUrl = $res->getUrl('civicrm', $item, TRUE);
      }
      $scriptUrls[] = $itemUrl;
    }
  }

  // Include our own JS.
  $url = $res->addCacheCode(CRM_Utils_System::url('civicrm/campaignadv/mosaico-js', '', TRUE, NULL, NULL, NULL, NULL));
  $scriptUrls[] = $url;
}

function campaignadv_civicrm_mosaicoStyleUrlsAlter(&$styleUrls) {
  $res = CRM_Core_Resources::singleton();

  // Load custom or core css
  $config = CRM_Core_Config::singleton();
  if (!Civi::settings()->get('disable_core_css')) {
    $styleUrls[] = $res->getUrl('civicrm', 'css/civicrm.css', TRUE);
  }
  if (!empty($config->customCSSURL)) {
    $customCSSURL = $res->addCacheCode($config->customCSSURL);
    $styleUrls[] = $customCSSURL;
  }
  // crm-i.css added ahead of other styles so it can be overridden by FA.
  array_unshift($styleUrls, $res->getUrl('civicrm', 'css/crm-i.css', TRUE));


  $coreResourceList = $res->coreResourceList('html-header');
  $coreResourceList = array_filter($coreResourceList, 'is_string');
  foreach ($coreResourceList as $item) {
    if (
      FALSE !== strpos($item, 'css')
      // Exclude jquery ui theme styles, which conflict with Mosaico styles.
      && FALSE === strpos($item, '/jquery-ui/themes/')
    ) {
      if ($res->isFullyFormedUrl($item)) {
        $itemUrl = $item;
      }
      else {
        $item = CRM_Core_Resources::filterMinify('civicrm', $item);
        $itemUrl = $res->getUrl('civicrm', $item, TRUE);
      }
      $styleUrls[] = $itemUrl;
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

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function campaignadv_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function campaignadv_civicrm_navigationMenu(&$menu) {
  _campaignadv_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _campaignadv_civix_navigationMenu($menu);
} // */


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
