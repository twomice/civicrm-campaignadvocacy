<?php
use CRM_Campaignadv_ExtensionUtil as E;

class CRM_Campaignadv_Page_MosaicoJs extends CRM_Core_Page {

  public function run() {
    // Retrieve and assign custom field ID value for "in office".
    $inOfficeCustomFieldId = CRM_Core_BAO_CustomField::getCustomFieldID('electoral_in_office', 'electoral_districts');
    $this->assign('inOfficeCustomFieldId', $inOfficeCustomFieldId);

    // Ensure content is sent as JavaScript.
    CRM_Core_Page_AJAX::setJsHeaders();

    // Parse the template and echo it directly, then exit (if we let civicrm
    // process the template normally, it will be wrapped with site chrome and
    // sent as HTML).
    $smarty = CRM_Core_Smarty::singleton();
    echo $smarty->fetch(self::getTemplateFileName());
    CRM_Utils_System::civiExit();

  }

}
