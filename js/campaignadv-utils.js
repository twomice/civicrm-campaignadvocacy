/**
 * Custom JavaScript utilities for campaignadv extension..
 */

CRM.$(function($){
  campaignadv = {
    insertHtmlPublicOfficial: function insertHtmlPublicOfficial(ckeTextareaSelector, filterCidSelector) {
      // Get the ckeditor instance name based on known "crm-ui-id" value in "~/crmMailing/BodyHtml.html"
      var ckeInstanceName = CRM.$(ckeTextareaSelector).attr('id');
      // Shorthand variable for the cke instance.
      var ckeInstance = CKEDITOR.instances[ckeInstanceName];
      // Shorthand variable for the ckeditor content.
      var data = ckeInstance.getData();
      // Strip any existing "filter_cid" token.
      data = data.replace(/(<p>)?{PublicOfficial.filter_cid___[0-9]*}(<\/p>)?/, '');
      // Get the cid of the selected Public Official contact, if any.
      var filterCid = CRM.$(filterCidSelector).val();
      // If a public official cid is found, insert the filter_cid token at the end
      // of the ckeditor HTML content.
      if (filterCid) {
        data += '{PublicOfficial.filter_cid___' +  filterCid + '}';
      }
      // Apply the altered content to the on-page ckeditor instance.
      ckeInstance.setData(data);
    }
  }
  
})