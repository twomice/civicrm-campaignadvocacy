<?php
use CRM_Campaignadv_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Campaignadv_Upgrader extends CRM_Campaignadv_Upgrader_Base {

  /**
   * Look up extension dependency error messages and display as Core Session Status
   *
   * @param array $unmet
   */
  public static function displayDependencyErrors(array $unmet) {
    foreach ($unmet as $extProps) {
      $message = self::getUnmetDependencyErrorMessage($extProps);
      CRM_Core_Session::setStatus($message, E::ts('CampaignAdvocacy: prerequisite check failed.'), 'error');
    }
  }

  /**
   * Mapping of extensions names to localized dependency error messages
   *
   * @param string $extProps an array of extension properties
   */
  public static function getUnmetDependencyErrorMessage($extProps) {
    return E::ts('CampaignAdvocacy requires the <a href="%1">%2</a> extension to be installed and enabled for proper functionality.', array(
      1 => $extProps['url'],
      2 => $extProps['title'],
    ));
  }

  /**
   * Extension Dependency Check
   *
   * @return Array
   *   Names of unmet extension dependencies; NOTE: returns an
   *   empty array when all dependencies are met.
   */
  public static function checkExtensionDependencies() {
    $manager = CRM_Extension_System::singleton()->getManager();

    $dependencies = array(
      // @TODO move this config out of code
      'com.jlacey.electoral' => array(
        'title' => 'Electoral API',
        'url' => 'https://github.com/josephlacey/com.jlacey.electoral',
      ),
    );

    $unmet = array();
    foreach ($dependencies as $extKey => $extProps) {
      if ($manager->getStatus($extKey) != CRM_Extension_Manager::STATUS_INSTALLED) {
        $extProps['key'] = $extKey;
        $unmet[$extKey] = $extProps;
      }
    }
    return $unmet;
  }

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
  public function install() {
    $this->executeSqlFile('sql/myinstall.sql');
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   *
  public function postInstall() {
    $customFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'return' => array("id"),
      'name' => "customFieldCreatedViaManagedHook",
    ));
    civicrm_api3('Setting', 'create', array(
      'myWeirdFieldSetting' => array('id' => $customFieldId, 'weirdness' => 1),
    ));
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Add new table
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4202() {
    $this->ctx->log->info('Applying update ' . __FUNCTION__);
    // Drop-and-create our inoffice log table.
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS civicrm_campaignadv_inoffice_log");
    CRM_Core_DAO::executeQuery("
      CREATE TABLE `civicrm_campaignadv_inoffice_log` (
        `custom_value_id` int unsigned NOT NULL COMMENT 'Soft FK to [electoral_districts_custom_table].id',
        `time` int unsigned COMMENT 'Log time as unix timestamp',
        PRIMARY KEY (`custom_value_id`)
      )
    ");
    // Populate our inoffice log table with current in_office data.
    $customFieldId = CRM_Core_BAO_CustomField::getCustomFieldID('electoral_in_office', 'electoral_districts');
    list($edTableName, $inOfficeColumnName) = CRM_Core_BAO_CustomField::getTableColumnGroup($customFieldId);
    CRM_Core_DAO::executeQuery("
      INSERT INTO `civicrm_campaignadv_inoffice_log` (custom_value_id, time)
      SELECT id, %1
      FROM {$edTableName}
      WHERE {$inOfficeColumnName} = 1
    ", [1 => [time(), 'Int']]);

    return TRUE;
  }

  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = E::ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}
