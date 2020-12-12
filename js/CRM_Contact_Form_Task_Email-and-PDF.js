CRM.$(function($) {
  /*jshint multistr: true */
  $('div.crm-html_email-accordion div.crm-token-selector').before('\n\
    <div id="campaignadvSelector" title="Select Public Official" style="display:none">\n\
      <input name="campaignadv-official" style="margin: 2em;" />\n\
    </div>\n\
    <a id="campaignadvSelectorOpen" style="float:left; margin-bottom: 10px;" class="button"><span>' + ts("Select Public Official") + '</span></a>\n\
  ');

  var entityRefParams = {
    api: {
      params: {
        contact_type: 'Individual',        
      }
    },
    create: false
  };
  entityRefParams.api.params['custom_' + CRM.vars.campaignadv.inOfficeCustomFieldId] = 1;
  $('[name="campaignadv-official"]').crmEntityRef(entityRefParams);
  
  $('#campaignadvSelector').dialog(
    {
      width: 'auto', 
      padding: '10px',
      modal: true, 
      autoOpen: false,
      buttons: [
      {
        text: 'Cancel',
        icon: 'fa-times',
        click: function() {
          $(this).dialog('close');
        }
      },
      {
        text: 'Select',
        icon: 'fa-check',
        click: function() {
          campaignadv.insertHtmlPublicOfficial('textarea#html_message', 'input[name=campaignadv-official]');
          $(this).dialog('close');
        }
      }
    ]
  });
  $('#campaignadvSelectorOpen').click(function() {
    $('#campaignadvSelector').dialog('open');
  });
});