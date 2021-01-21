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
    /*jshint multistr: true */
    CRM.$('body').append('\n\
      <div id="campaignadvSelector" title="Select Public Official" style="display:none">\n\
        <input name="campaignadv-official" style="margin: 2em;" />\n\
      </div>\n\
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
    
    CRM.$('[name=campaignadv-official]').crmEntityRef(entityRefParams);
    
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

  editor.addShortcut('ctrl+shift+p', 'Select Public Official', 'campaignadv');
  editor.addButton('campaignadv', {
    text: 'Select Public Official',
    tooltip: 'Public Official (Ctrl-Shift-P)',
    onclick: function(_) {
       editor.execCommand('campaignadv');
    }
  });

  // Append our button to toolbar1 in this editor, if it's not already there.
  // (Apparently the above editor.addButton() method only adds it to the first
  // editor in the page.
  if (!editor.settings.toolbar1.includes('campaignadv')) {
    editor.settings.toolbar1 += ' | campaignadv';
  }

});
