{literal}
CRM.$(function($){
  CRM.vars['campaignadv'] = {
    'inOfficeCustomFieldId': "{/literal}{$inOfficeCustomFieldId}{literal}"
  };
  console.log("smarty.now: {/literal}{$smarty.now|date_format:'%H:%M:%S'}{literal}");
});
{/literal}