# Changelog

## 4.0.0-beta.1 - 2024-02-05

- adds support for Craft 4
- update control panel template button listener JS functionality
- add support for alternative rich text plugins, namely CK Editor

## 3.3.4.3 - 2024-02-01

### Changed
- ScheduledSyncController console command has been renamed to ScheduleController to align with the naming convention of the GA MM plugin.

## 3.3.4.2 - 2023-12-13
### Fixed
- Fix show availability sync that was including assets like clips and trailers when determining a show's public availability

## 3.3.4.1 - 2023-12-12
### Fixed
- Fix show sync logic that was incorrectly returning before show availability could be determined.

## 3.3.4 - 2023-12-12
### Added
- The plugin now emits its own log files for better debugging.

### Changed
- The way a show's "Episode Count" property is determined now looks at a show's asset count rather than the static "Episode Count" property on the Show record.

## 3.3.3 - 2023-11-09
### Fixed
- Fixed missing property setters in the MediaSync job that prevented the 'check for changes' feature from working properly ([e4100dc](https://github.com/pbs/pbs-media-manager-craft-kenburns/commit/e4100dc53090e2c6f20a23e6b2288d3efe9e86a9))

### Changed 
- **New** Media entries and Show entries added during a sync will always synchronize all fields, regardless of what fields were selected for sync.
- Plugin settings no longer handles field layouts for Media or Show sections. 
### Added
- Added more API attributes to the list of fields that can be synchronized to Show entries.
- Show Entry sync jobs can now sync the Passport availability and public availability flags.

## 3.3.2 - 2023-11-02
### Fixed
- Fix issue where the Thumbnail field was not updating when the sync was run if it had been included in the list of fields to sync.

### Added
- Media entry Titles can now be synced.

## 3.3.1 - 2023-11-02
### Added
- The plugin will now check whether a media entry should be 'marked for deletion' based on its presence in the API calls.
- Show synchronization jobs can now be scheduled to run at a specific time.
- You can now select what fields should be updated during a sync. 

## 3.1.1 - 2021-06-03

- Fix episode not being populated.
- Introduce show synchronize.

## 3.0.0 - 2020-06-10

- Initial release.

