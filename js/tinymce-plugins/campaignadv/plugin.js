/**
 * campaignadv - TinyMCE plugin which allows users to easily insert a "filter  cid"
 * token for public officials.

/*global tinymce:true */

tinymce.PluginManager.add('campaignadv', function(editor, pluginUrl) {
  if (!editor.settings.campaignadv) {
    throw "Failed to initialize campaignadv. TinyMCE settings should define \"campaignadv\".";
  }

  function settings() {
    return editor.settings.campaignadv;
  }

  editor.addCommand('campaignadv', function(ui, v) {
    
    CRM.$('body').append('\n\
      <div id="campaignadvSelector" title="Select Public Official" style="display:none">\n\
        <input name="campaignadv-official" style="margin: 2em;" />\n\
      </div>\n\
    '); 
        
    CRM.url({back: '/*path*?*query*', front: '/*path*?*query*'});
    CRM.$('[name=campaignadv-official]').crmEntityRef({
      api: {
        params: {
          contact_type: 'Individual',
          custom_141: 1
        }
      },
      create: false
    });
    
    CRM.$('#campaignadvSelector').dialog({
      width: 'auto', 
      padding: '10px',
      modal: true, 
      autoOpen: true,
      close: function(e, ui){
        CRM.$('div#campaignadvSelector').remove();
        CRM.$('div.select2-drop, div#select2-drop-mask').remove();
        
      },
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
            var data = tinymce.activeEditor.getContent();
            // Strip any existing "filter_cid" token.
            data = data.replace(/(<p>)?{PublicOfficial.filter_cid___[0-9]*}(<\/p>)?/, '');
            // Get the cid of the selected Public Official contact, if any.
            var filterCid = CRM.$('input[name="campaignadv-official"]').val();
            // If a public official cid is found, insert the filter_cid token at the end
            // of the ckeditor HTML content.
            if (filterCid) {
              data += '{PublicOfficial.filter_cid___' +  filterCid + '}';
            }
            // Apply the altered content to the activetinymce instance.
            tinymce.activeEditor.execCommand('mceSetContent', false, data);
            $(this).dialog('close');
          }
        }
      ]
    });
  });

  editor.addButton('campaignadv', {
    text: 'Select Elected Official',
    onclick: function(_) {
       editor.execCommand('campaignadv');
    }
  });
});
