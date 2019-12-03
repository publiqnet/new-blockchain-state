# Step-by-step guide to deploy & run PUBLIQ Channel state
This manual assumes that you already have a server installed with NGINX, PHP-FPM(>7.1) and MySQL(>5.6).
1. Install git
```
https://www.digitalocean.com/community/tutorials/how-to-install-git-on-ubuntu-18-04
```
2. Install composer
```
https://www.digitalocean.com/community/tutorials/how-to-install-and-use-composer-on-ubuntu-18-0
```
3. Enter the directory where the application should be
```
cd /path/to/application
```
4. Clone the project
```
git clone https://github.com/publiqnet/new-blockchain-state.git projectName
```
5. Enter project directory
```
cd projectName
```
6. Install dependencies via composer
```
composer install
```
7. Create MySQL user & set permissions
```
https://www.digitalocean.com/community/tutorials/how-to-create-a-new-user-and-grant-permissions-in-mysql
```
8. Set parameters in .env file
```
DATABASE_URL=mysql://user:password@127.0.0.1:3306/database-name
CORS_ALLOW_ORIGIN=^https?://localhost(:[0-9]+)?$ (frontend URL or list of URLs)
OAUTH_ENDPOINT=https://stage-mainnet-oauth.publiq.network (do not change if you gonna use PUBLIQ oAUTH)
STATE_ENDPOINT=(State node endpoint)
BROADCAST_ENDPOINT=(Broadcast node endpoint)
CHANNEL_ENDPOINT=(Channel node endpoint)
CHANNEL_STORAGE_ENDPOINT=(Storage endpoint)
CHANNEL_STORAGE_ORDER_ENDPOINT=(Storage order generator endpoint)
CHANNEL_STORAGE_ENDPOINT_POST=(RPC endpoint)
DETECT_LANGUAGE_ENDPOINT=http://127.0.0.1:44121/5/.01
CHANNEL_ADDRESS=PUBLIC-KEY
CHANNEL_PRIVATE_KEY=PRIVATE-KEY
FRONTEND_ENDPOINT=http(s)://example.com
MAIN_DOMAIN_HOST=example.com
MAIN_DOMAIN_SCHEME=http(s)
```
9. Clear caches
```
bin/console cache:clear --no-warmup --env=(prod|dev)
```
10. Create MySQL database & schema
```
bin/console d:d:c
bin/console d:s:u --force
```
11. Create & setup virtual host to run application
```
https://tecadmin.net/setup-nginx-virtual-hosts-on-ubuntu/
https://symfony.com/doc/4.3/setup/web_server_configuration.html#nginx
```
12. Setup cronjobs
```
* * * * * /path/to/application/projectName/bin/console state:sync-new-blockchain >> /path/to/application/projectName/var/log/cron_state.txt 2>&1
*/2 * * * * /path/to/application/projectName/bin/console state:file-details >> /path/to/application/projectName/var/log/cron_file.txt 2>&1
*/10 * * * * /path/to/application/projectName/bin/console state:public-addresses >> /path/to/application/projectName/var/log/cron_addresses.txt 2>&1
```
> first sync may take a while
