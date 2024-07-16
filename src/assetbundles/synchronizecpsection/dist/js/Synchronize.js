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
  Craft.SynchronizeAdmin = Garnish.Base.extend(
    {
      init: function () {
        var self = this;

        this.addListener($('#synchronizeshowbtn'), 'activate', 'synchronizeShow');
        this.addListener($('#synchronize-single-button'), 'activate', 'synchronizeSingle');
        this.addListener($('#synchronize-all-button'), 'activate', 'synchronizeAll');
        this.addListener($('#synchronize-show-entries-button'), 'activate', 'synchronizeShowEntries');
        this.addListener($('#addshowsite'), 'activate', 'addShowSite');
        this.addListener($('#cleanallbtn'), 'activate', 'cleanGarbageEntries');

        // Add Remove Button to Select
        $('#siteId-field .select').after('&nbsp;<div class="deletesite btn delete icon"></div>');
        $('body').on('click', '.deletesite', function () {
          self.deleteShowSite($(this).parents('#siteId-field'));
        })
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

      cleanGarbageEntries: function () {

        $('#cleanallbtn').addClass('disabled');

        Craft.sendActionRequest('POST', 'mediamanager/synchronize/run-clean', {})
          .then((response) => {
            if (response.data.success) {
              Craft.cp.displayNotice(Craft.t('mediamanager', 'Start cleaning ' + response.data.total + ' entries.'));
              setTimeout(function () {
                location.href = Craft.getUrl('mediamanager/entries/');
              }, 1000);
            }
            else if (response.data.errors) {
              $('#cleanallbtn').removeClass('disabled');
              var errors = this.flattenErrors(response.data.errors);
              Craft.cp.displayError(Craft.t('mediamanager', 'Could not start cleaning:') + "\n\n" + errors.join("\n"));
            }
          })
          .catch(({ response }) => {
            $('#cleanallbtn').removeClass('disabled');
            Craft.cp.displayError();
          })
      },

      synchronizeShow: function () {
        var showId = $('#showId').val()
        var name = $('#name').val()
        var forceRegenerateThumbnail = $('#forceRegenerateThumbnail').prop('checked')
        var fieldsToSync = [];
        var fieldsToSyncInputs = $('input[name="fieldsToSync[]"]:checked').each(function (index, field) {
          fieldsToSync.push($(field).val());
        })

        if (showId) {
          var data = {
            showId: showId,
            forceRegenerateThumbnail: forceRegenerateThumbnail,
            fieldsToSync: fieldsToSync,
          };

          $('#synchronizeshowbtn').addClass('disabled');

          Craft.sendActionRequest('POST', 'mediamanager/synchronize/synchronize-show', { data })
            .then((response) => {
              if (response.data.success) {
                Craft.cp.displayNotice(Craft.t('mediamanager', 'Synchronize "' + name + '" started.'));
                setTimeout(function () {
                  location.href = Craft.getUrl('mediamanager/entries/');
                }, 1000);
              }
              else if (response.data.errors) {
                $('#synchronizeshowbtn').removeClass('disabled');
                var errors = this.flattenErrors(response.data.errors);
                Craft.cp.displayError(Craft.t('mediamanager', 'Could not start synchronize:') + "\n\n" + errors.join("\n"));
              }
            })
            .catch(({ response }) => {
              $('#synchronizeshowbtn').removeClass('disabled');
              Craft.cp.displayError();
            })

        } else {

          if (!showId) {
            Craft.cp.displayError(Craft.t('mediamanager', 'Something wrong, please reload the page'));
            return
          }

          Craft.cp.displayError(Craft.t('mediamanager', 'Site ID is required'));
        }
      },

      synchronizeSingle: function () {
        var apiKey = $('#apiKey').val()
        var siteId = []
        var forceRegenerateThumbnail = $('#forceRegenerateThumbnail').prop('checked')
        var fieldsToSync = [];
        var fieldsToSyncInputs = $('input[name="fieldsToSync[]"]:checked').each(function (index, field) {
          fieldsToSync.push($(field).val());
        })

        $('select[name="siteId[]"]').each(function () {
          var thisSiteId = $(this).val()

          if (siteId.indexOf(thisSiteId) === -1) {
            siteId.push(thisSiteId);
          }
        })

        if (apiKey && siteId) {

          var data = {
            apiKey: apiKey,
            siteId: siteId,
            forceRegenerateThumbnail: forceRegenerateThumbnail,
            fieldsToSync: fieldsToSync,
          };

          $('#synchronize-single-button').addClass('disabled');

          Craft.sendActionRequest('POST', 'mediamanager/synchronize/synchronize-single', { data: data })
            .then((response) => {
              if (response.data.success) {
                Craft.cp.displayNotice(Craft.t('mediamanager', 'Synchronize started.'));
                setTimeout(function () {
                  location.href = Craft.getUrl('mediamanager/entries/');
                }, 1000);
              }
              else if (response.data.errors) {
                $('#synchronize-single-button').removeClass('disabled');
                var errors = this.flattenErrors(response.data.errors);
                Craft.cp.displayError(Craft.t('mediamanager', 'Could not start synchronize:') + "\n\n" + errors.join("\n"));
              }
            })
            .catch(({ response }) => {
              $('#synchronize-single-button').removeClass('disabled');
              Craft.cp.displayError();
            })

        } else {
          Craft.cp.displayError(Craft.t('mediamanager', 'Media Asset\'s Site and API Key are required'));
        }
      },

      synchronizeAll: function () {
        $('#synchronize-all-button').addClass('disabled');

        var forceRegenerateThumbnail = $('#forceRegenerateThumbnail').prop('checked')
        var fieldsToSync = [];
        var fieldsToSyncInputs = $('input[name="fieldsToSync[]"]:checked').each(function (index, field) {
          fieldsToSync.push($(field).val());
        })

        var data = {
          forceRegenerateThumbnail: forceRegenerateThumbnail,
          fieldsToSync: fieldsToSync,
        };

        Craft.sendActionRequest('POST', 'mediamanager/synchronize/synchronize-all', { data: data})
          .then((response) => {
            if (response.data.success) {
              Craft.cp.displayNotice(Craft.t('mediamanager', 'Synchronize for all show started.'));
              setTimeout(function () {
                location.href = Craft.getUrl('mediamanager/entries/');
              }, 1000);
            }
            else if (response.data.errors) {
              $('#synchronize-all-button').removeClass('disabled');
              var errors = this.flattenErrors(response.data.errors);
              Craft.cp.displayError(Craft.t('mediamanager', 'Could not start synchronize:') + "\n\n" + errors.join("\n"));
            }
          })
          .catch(({ response }) => {
            $('#synchronize-all-button').removeClass('disabled');
            Craft.cp.displayError();
          })
      },

      synchronizeShowEntries: function () {
        $('#synchronize-show-entries-button').addClass('disabled');

        var fieldsToSync = [];
        var fieldsToSyncInputs = $('input[name="fieldsToSync[]"]:checked').each(function (index, field) {
          fieldsToSync.push($(field).val());
        })

        var data = {
          fieldsToSync: fieldsToSync,
        };
        Craft.sendActionRequest('POST', 'mediamanager/synchronize/synchronize-show-entries', {data: data})
          .then((response) => {
            if (response.data.success) {
              Craft.cp.displayNotice(Craft.t('mediamanager', 'Synchronize for show entries started.'));
              setTimeout(function () {
                location.href = Craft.getUrl('mediamanager/entries/');
              }, 1000);
            }
            else if (response.data.errors) {
              $('#synchronize-show-entries-button').removeClass('disabled');
              var errors = this.flattenErrors(response.data.errors);
              Craft.cp.displayError(Craft.t('mediamanager', 'Could not start synchronize:') + "\n\n" + errors.join("\n"));
            }
          })
          .catch(({ response }) => {
            $('#synchronize-show-entries-button').removeClass('disabled');
            Craft.cp.displayError();
          })
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
