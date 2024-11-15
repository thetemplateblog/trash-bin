## [1.0.0-beta.1] - 2024-11-04

### Added

- Initial implementation of core features:
	- Soft Delete moves the content in to a .trash folder located in collections
	- View deleted entries in the trash bin
    - View the content of the deleted entries in the trash bin
	- Restore Entries
    - User authentication and authorization.

### Changed

- Initial release

### Fixed

### Known Issues
- I wouldn't use this in production at this time

### ToDo
- Only show menu if the user has access to it
- Hide buttons based on access
- Use Statamic style buttons & confirmations
- Implement bulk actions
- Implement scheduled removal
