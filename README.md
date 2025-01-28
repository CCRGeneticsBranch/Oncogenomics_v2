## Installation

1. git clone

  ```git clone git@github.com:CCRGeneticsBranch/Oncogenomics_v2.git clinomics```

2. composer install
  
  ```
  cd clinomics
  composer install
  ```
  
3. change storage permission
  ```
  chmod g+w storage/logs
  chmod g+w storage/framework/views
  chmod g+w storage/framework/cache
  ```
4. set site_data link
  ```
  ln -s /mnt/projects/CCR-JK-oncogenomics/static/site_data/dev/ site_data
  cd app
  ln -s ../site_data/app/bin .
  ln -s ../site_data/app/metadata .
  ln -s ../site_data/app/ref .
  ln -s ../site_data/app/scripts .
  cd ../storage
  ln -s ../site_data/storage/bams .
  ln -s ../site_data/storage/data_integrity_report .
  ln -s ../site_data/storage/ProcessedResults .
  ln -s ../site_data/storage/project_data .
  ln -s ../site_data/storage/sync .
  ```
5. edit .env file

  ```
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=https://fsabcl-onc01d.ncifcrf.gov

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=oracle
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

IS_PUBLIC_SITE=0
AUTH_REDIRECT=https://fsabcl-onc01d.ncifcrf.gov/clinomicsd/public/login
AUTH_OATH=https://stsstg.nih.gov/auth/oauth/v2/authorize
AUTH_CLIENT_ID=
AUTH_CLIENT_SECRETE=
AUTH_SCOPE=email+profile+openid
AUTH_WEBSITE=https://stsstg.nih.gov
URL=https://fsabcl-onc01t.ncifcrf.gov/clinomicsd/public
URL_PRODUCTION=https://oncogenomics.ccr.cancer.gov/production/public
URL_PUBLIC=https://clinomics.ccr.cancer.gov/clinomics/public/
URL_DEV=https://fsabcl-onc01d.ncifcrf.gov/clinomics_dev/public
R_LIBS=/mnt/nasapps/development/R/r_libs/4.0.2/
R_PATH=/mnt/nasapps/development/R/4.0.2/bin/
LD_LIBRARY_PATH=/mnt/nasapps/development/R/shared_libs/4.0.2
MOUNT=/mnt/projects/CCR-JK-oncogenomics/static/clones/clinomics
MOUNT_PUBLIC=/mnt/projects/CCR-JK-oncogenomics/static/clones/clinomics_public
AVIA=true
TOKEN=
PUBLIC_TOKEN=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=cookie
SESSION_LIFETIME=86400
SESSION_SECURE_COOKIE=true

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_FROM_ADDRESS=oncogenomics@mail.nih.gov
MAIL_MAILER=sendmail
MAIL_HOST=mailfwd.nih.gov
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_NAME=Oncogenomics

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
  ```
