# SC-Statistics-Service

Tested with PHP 7.3.

## Deployment

- Copy this repository to a webserver
- Perform appropriate file protection measures if included `.htaccess` file is not used
- Create `config.php` from `config.sample.php` template and fill with production settings
- Create the respective database
- `delete_data_of_deleted_users.php` should be scheduled to run daily, `update_incomplete_statistics.php` should be scheduled to run in regular intervals, for example every minute or so