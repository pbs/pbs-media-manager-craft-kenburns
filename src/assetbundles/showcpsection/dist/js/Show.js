/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */
(function ($) {
  /** global: Craft */
  /** global: Garnish */
  Craft.ShowAdmin = Garnish.Base.extend(
    {
      $shows: null,
      $selectedShow: null,

      init: function () {
        var self = this;
        this.$shows = $('#shows');
        this.$selectedShow = this.$shows.find('a.sel:first');

        this.addListener($('#newshowbtn'), 'activate', 'addNewShow');
        this.addListener($('#showupdatebtn'), 'activate', 'updateShow');
        this.addListener($('#addshowsite'), 'activate', 'addShowSite');

        // Add Remove Button to Select
        $('#siteId-field .select').after('&nbsp;<div class="deletesite btn delete icon"></div>');
        $('body').on('click', '.deletesite', function () {
          self.deleteShowSite($(this).parents('#siteId-field'));
        })

        // Show Settings Buttons
        var $showSettingsBtn = $('#showsettingsbtn');

        if ($showSettingsBtn.length) {
          var menuBtn = $showSettingsBtn.data('menubtn');

          menuBtn.settings.onOptionSelect = $.proxy(function (elem) {
            var $elem = $(elem);

            if ($elem.hasClass('disabled')) {
              return;
            }

            switch ($elem.data('action')) {
              case 'rename': {
                this.renameSelectedShow();
                break;
              }
              case 'delete': {
                this.deleteSelectedShow();
                break;
              }
            }
          }, this);
        }


      },

      addShowSite: function () {
        var siteField = $('#siteId-field')
        var clone = siteField.clone()
        var modifiedClone = $(clone).find('.heading').remove().end()

        $('#addshowsite').before($(modifiedClone).find('select').val(1).end())
      },

      deleteShowSite: function (target) {
        if ($('select[name="siteId[]"]').length > 1) {
          target.remove();
          return
        }

        Craft.cp.displayError(Craft.t('mediamanager', 'You need at least one associated site.'));
      },

      updateShow: function () {
        var apiKey = $('#apiKey').val()
        var showId = $('#showId').val()
        var showName = $('#showName').val()
        var siteId = []

        $('select[name="siteId[]"]').each(function () {
          var thisSiteId = $(this).val()

          if (siteId.indexOf(thisSiteId) === -1) {
            siteId.push(thisSiteId);
          }
        })

        if (apiKey && showId && siteId) {
          var data = {
            id: showId,
            name: showName,
            apiKey: apiKey,
            siteId: siteId
          };

          $('#showupdatebtn').addClass('disabled');

          Craft.sendActionRequest('POST', 'mediamanager/show/save', {data})
            .then((response) => {

              $('#showupdatebtn').removeClass('disabled');
              if (response.data.success) {
                $('#apiKey').val(response.data.show.apiKey);
                Craft.cp.displayNotice(Craft.t('mediamanager', 'Show updated'));
              }
              else if (response.data.errors) {
                var errors = this.flattenErrors(response.data.errors);
                Craft.cp.displayError(Craft.t('mediamanager', 'Could not update the show:') + "\n\n" + errors.join("\n"));
              }

            })

            .catch(({ response }) => {
              var errors = this.flattenErrors(response.data.errors);
              Craft.cp.displayError(Craft.t('mediamanager', 'Could not update the show:') + "\n\n" + errors.join("\n"));
            })
        } else {

          if (!showId) {
            Craft.cp.displayError(Craft.t('mediamanager', 'Something wrong, please reload the page'));
            return
          }

          Craft.cp.displayError(Craft.t('mediamanager', 'API Key is required'));
        }
      },

      addNewShow: function () {
        var name = this.promptForShowName('');

        if (name) {
          var data = {
            name: name
          };

          Craft.sendActionRequest('POST', 'mediamanager/show/save', { data })
            .then((response) => {
              if (response.data.success) {
                location.href = Craft.getUrl('mediamanager/shows/' + response.data.show.id);
              }
              else if (response.data.errors) {
                var errors = this.flattenErrors(response.data.errors);
                alert(Craft.t('mediamanager', 'Could not create the show:') + "\n\n" + errors.join("\n"));
              }
            })
            .catch(({ response }) => {
              Craft.cp.displayError();
            })
        }
      },

      renameSelectedShow: function () {
        var oldName = this.$selectedShow.text(),
          newName = this.promptForShowName(oldName);

        if (newName && newName !== oldName) {
          var data = {
            id: this.$selectedShow.data('id'),
            name: newName
          };

          Craft.sendActionRequest('POST', 'mediamanager/show/save', { data })
            .then((response) => {
              if (response.data.success) {
                this.$selectedShow.text(response.data.show.name);
                Craft.cp.displayNotice(Craft.t('mediamanager', 'Show renamed'));
              }
              else if (response.data.errors) {
                var errors = this.flattenErrors(response.data.errors);
                alert(Craft.t('mediamanager', 'Could not rename the show:') + "\n\n" + errors.join("\n"));
              }
            })
            .catch(({ response }) => {
              Craft.cp.displayError();
            })
        }
      },

      deleteSelectedShow: function () {
        if (confirm(Craft.t('mediamanager', 'Are you sure you want to delete this show? This will remove relationship too.'))) {
          var data = {
            id: this.$selectedShow.data('id')
          };

          Craft.sendActionRequest('POST', 'mediamanager/show/delete', { data })
            .then((response) => {
              if (response.data.success) {
                location.href = Craft.getUrl('mediamanager/shows');
              }
              else if (response.data.errors) {
                var errors = this.flattenErrors(response.data.errors);
                alert(Craft.t('mediamanager', 'Could not delete the show:') + "\n\n" + errors.join("\n"));
              }
            })
            .catch(({ response }) => {
              Craft.cp.displayError();
            })
        }
      },

      promptForShowName: function (oldName) {
        return prompt(Craft.t('mediamanager', 'What do you want to name the Show?'), oldName);
      },

      flattenErrors: function (responseErrors) {
        var errors = [];

        for (var attribute in responseErrors) {
          if (!responseErrors.hasOwnProperty(attribute)) {
            continue;
          }

          errors = errors.concat(responseErrors[attribute]);
        }

        return errors;
      }
    }
  );
})(jQuery);
