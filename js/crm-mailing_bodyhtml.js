/**
 * Custom JavaScript for the Angular page "~/crmMailing/BodyHtml.html".
 */

CRM.$(function($){
  campaignadv = {
    insertHtmlText: function insertHtmlPublicOfficial() {
      // Get the ckeditor instance name based on known "crm-ui-id" value in "~/crmMailing/BodyHtml.html"
      var ckeInstanceName = CRM.$('textarea[crm-ui-id="htmlForm.body_html"]').attr('id');
      // Shorthand variable for the cke instance.
      var ckeInstance = CKEDITOR.instances[ckeInstanceName];
      // Shorthand variable for the ckeditor content.
      var data = ckeInstance.getData();
      // Strip any existing "filter_cid" token.
      data = data.replace(/(<p>)?{PublicOfficial.filter_cid___[0-9]*}(<\/p>)?/, '');
      // Get the cid of the selected Public Official contact, if any.
      var filterCid = CRM.$('input[crm-ui-id="subform.official"]').val();
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