<?php

require_once 'campaignadvocacy.civix.php';
use CRM_Campaignadvocacy_ExtensionUtil as E;


/**
 * Implements of hook_civicrm_pageRun().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_pageRun
 *
 * Handler for pageRun hook.
 */
function campaignadvocacy_civicrm_pageRun(&$page) {
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
  _campaignadvocacy_periodicChecks();
}

/**
 * hook_civicrm_pageRun handler for civicrm core extensions admin page.
 * @param type $page
 */
function _campaignadvocacy_civicrm_pageRun_CRM_Admin_Page_Extensions(&$page) {
  _campaignadvocacy_prereqCheck();
}

/**
 * hook_civicrm_pageRun handler for "extensionsui" extensions admin page.
 * @param type $page
 */
function _campaignadvocacy_civicrm_pageRun_Angular_Civi_Angular_Page_Main(&$page) {
  _campaignadvocacy_prereqCheck();
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function campaignadvocacy_civicrm_config(&$config) {
  _campaignadvocacy_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function campaignadvocacy_civicrm_xmlMenu(&$files) {
  _campaignadvocacy_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function campaignadvocacy_civicrm_install() {
  _campaignadvocacy_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function campaignadvocacy_civicrm_postInstall() {
  _campaignadvocacy_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function campaignadvocacy_civicrm_uninstall() {
  _campaignadvocacy_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function campaignadvocacy_civicrm_enable() {
  _campaignadvocacy_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function campaignadvocacy_civicrm_disable() {
  _campaignadvocacy_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function campaignadvocacy_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _campaignadvocacy_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function campaignadvocacy_civicrm_managed(&$entities) {
  _campaignadvocacy_civix_civicrm_managed($entities);
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
function campaignadvocacy_civicrm_caseTypes(&$caseTypes) {
  _campaignadvocacy_civix_civicrm_caseTypes($caseTypes);
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
function campaignadvocacy_civicrm_angularModules(&$angularModules) {
  _campaignadvocacy_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function campaignadvocacy_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _campaignadvocacy_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function campaignadvocacy_civicrm_entityTypes(&$entityTypes) {
  _campaignadvocacy_civix_civicrm_entityTypes($entityTypes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function campaignadvocacy_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function campaignadvocacy_civicrm_navigationMenu(&$menu) {
  _campaignadvocacy_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _campaignadvocacy_civix_navigationMenu($menu);
} // */


function _campaignadvocacy_prereqCheck() {
  $unmet = CRM_Campaignadvocacy_Upgrader::checkExtensionDependencies();
  CRM_Campaignadvocacy_Upgrader::displayDependencyErrors($unmet);
}

function _campaignadvocacy_periodicChecks() {
  $session = CRM_Core_Session::singleton();
  if (
    !CRM_Core_Permission::check('administer CiviCRM')
    || !$session->timer('check_CRM_Campaignadvocacy_Depends', CRM_Utils_Check::CHECK_TIMER)
  ) {
    return;
  }

  _campaignadvocacy_prereqCheck();
}
