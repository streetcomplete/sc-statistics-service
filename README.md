# SC-Statistics-Service

Tested with PHP 7.3 and MySQL 5.6.

## Deployment

- Copy this repository to a webserver
- Perform appropriate file protection measures if included `.htaccess` file is not used
- Create `config.php` from `config.sample.php` template and fill with production settings
- Create the respective database
- `delete_data_of_deleted_users.php` should be scheduled to run daily, `update_incomplete_statistics.php` should be scheduled to run in regular intervals, for example every minute or so, `generate_ranks.php` should be scheduled to run in regular intervals, hourly is good, but can be in shorter intervals as well

## Development

- Open project in [devcontainer](https://code.visualstudio.com/docs/remote/containers-tutorial)
- Create `config.php` from `config.sample.php` template and fill with development settings
- For debugging set a breakpoint and run the `Listen for Xdebug` launch config before running any scripts
- Run scripts with `php <SCRIPT>`
- Run webserver with `php -S localhost:8080` and go to `http://localhost:8080/get_statistics.php?user_id=<USER_ID>`
