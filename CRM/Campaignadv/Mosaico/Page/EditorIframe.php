<?php

/**
 * Overrides certain methods in CRM_Mosaico_Page_EditorIframe.
 *
 * Really couldn't find a way to do the needful using hooks, so we override methods.
 */
class CRM_Campaignadv_Mosaico_Page_EditorIframe extends CRM_Mosaico_Page_EditorIframe {

  /**
   * Modify return value of parent:: method.
   */
  protected function createMosaicoConfig() {
    $config = parent::createMosaicoConfig();
    $config['tinymceConfig']['external_plugins']['campaignadv'] = CRM_Core_Resources::singleton()->getUrl('campaignadv', 'js/tinymce-plugins/campaignadv/plugin.js', 1);
    $config['tinymceConfig']['plugins'][0] .= ' campaignadv';
    $config['tinymceConfig']['toolbar1'] .= ' campaignadv';
    $config['tinymceConfig']['campaignadv'] = true;
    return $config;
  }

/**
 * Copied from CRM_Core_Resources::resolveFileName, just so we can use it here
 * (why it should be private, I don't know).
 *
 */
  private function resolveFileName(&$fileName, $extName) {
    $res = CRM_Core_Resources::singleton();
    if (CRM_Core_Config::singleton()->debug && strpos($fileName, '.min.') !== FALSE) {
      $nonMiniFile = str_replace('.min.', '.', $fileName);
      if ($res->getPath($extName, $nonMiniFile)) {
        $fileName = $nonMiniFile;
      }
    }
  }

  /**
   * Modify return value of parent:: method.
   */
  protected function getScriptUrls() {
    $scriptUrls = parent::getScriptUrls();
    $res = CRM_Core_Resources::singleton();

    $coreResourceList = $res->coreResourceList('html-header');
    $coreResourceList = array_filter($coreResourceList, 'is_string');
    foreach ($coreResourceList as $item) {
      if (
        FALSE !== strpos($item, 'js')
        && !strpos($item, 'crm.menubar.js')
        && !strpos($item, 'crm.wysiwyg.js')
      ) {
        if ($res->isFullyFormedUrl($item)) {
          $itemUrl = $item;
        }
        else {
          $this->resolveFileName($item, 'civicrm');
          $itemUrl = $res->getUrl('civicrm', $item, TRUE);
        }
        $scriptUrls[] = $itemUrl;
      }
    }

    // Include our own JS.
    $scriptUrls[] = $res->addCacheCode('/civicrm/campaignadv/mosaico-js');

    return $scriptUrls;
  }

  /**
   * Modify return value of parent:: method.
   */
  protected function getStyleUrls() {
    $res = CRM_Core_Resources::singleton();
    $styleUrls = parent::getStyleUrls();

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
          $this->resolveFileName($item, 'civicrm');
          $itemUrl = $res->getUrl('civicrm', $item, TRUE);
        }
        $styleUrls[] = $itemUrl;
      }
    }

    // Include our own abridged styles from jquery-ui 'smoothness' theme, as
    // required for our jquery-ui dialog, but which don't conflict with Mosaico.
    $styleUrls[] = $res->getUrl('campaignadv', 'css/jquery-ui-smoothness-partial.css', TRUE);
    return $styleUrls;
  }
}
