{literal}
CRM.$(function($){
  CRM.vars['campaignadv'] = {
    'inOfficeCustomFieldId': "{/literal}{$inOfficeCustomFieldId}{literal}"
  };
  console.log("smarty.now: {/literal}{$smarty.now|date_format:'%H:%M:%S'}{literal}");
  
  // Initialize CRM.url (must be done manually, not sure why).
  var crmUrlInitObject = {
    back: '{/literal}{crmURL p="*path*" q="*query*" h=0 fb=1}{literal}', 
    front: '{/literal}{crmURL p="*path*" q="*query*" h=0 fe=1}{literal}'
  };
  CRM.url(crmUrlInitObject);
  console.log('initialized crm.url with', crmUrlInitObject);
});
{/literal}